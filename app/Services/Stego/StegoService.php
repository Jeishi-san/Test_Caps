<?php

namespace App\Services\Stego;

use Exception;
use Illuminate\Support\Facades\Log;
use GdImage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * StegoService
 *
 * Handles steganographic embedding and extraction of data within carrier files.
 *
 * Supported carrier types:
 *  - Images (PNG, BMP) : LSB via Python stegano library (driver = 'python', default)
 *                        or via PHP GD extension          (driver = 'php')
 *  - Other files       : Append-with-marker approach (safe for binary carrier types
 *                        that tolerate appended data, e.g. text, generic binary)
 *
 * Driver selection is controlled by the STEGO_DRIVER .env variable:
 *   STEGO_DRIVER=python  — Uses Python stegano library (requires pip install stegano Pillow)
 *   STEGO_DRIVER=php     — Uses built-in PHP GD extension (no extra dependencies)
 *
 * Replaces the gRPC stego-service microservice described in the .md guide.
 */
class StegoService
{
    // Unique binary marker that separates carrier content from hidden payload.
    // Chosen to be unlikely to appear naturally in image/binary data.
    private const MARKER = "\x53\x54\x45\x47\x4F\x4C\x4F\x43\x4B"; // "STEGOLOCK"

    // Maximum bytes that can be hidden per pixel channel using LSB.
    // 1 bit per channel × 3 channels (R, G, B) = 3 bits per pixel = ~0.375 bytes/pixel.
    private const BITS_PER_CHANNEL = 1;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Embed binary data into a carrier file.
     *
     * @param  string $carrierPath  Absolute path to the carrier file
     * @param  string $data         Raw binary data to hide (already encrypted)
     * @param  string $outputPath   Path to write the modified carrier file
     * @return string               The output path
     * @throws Exception
     */
    public function embed(string $carrierPath, string $data, string $outputPath): string
    {
        $this->assertFileExists($carrierPath);

        $mime = mime_content_type($carrierPath);
        $ext  = strtolower(pathinfo($carrierPath, PATHINFO_EXTENSION));

        if ($this->isLsbCapable($mime)) {
            return $this->isPythonDriver()
                ? $this->embedLSBPython($carrierPath, $data, $outputPath)
                : $this->embedLSB($carrierPath, $data, $outputPath, $mime);
        }

        // Audio WAV: accept by mime or by .wav extension
        if ($this->isAudio($mime) || $ext === 'wav') {
            return $this->embedAudio($carrierPath, $data, $outputPath);
        }

        // Text TXT: accept by mime or by .txt extension
        if ($this->isText($mime) || $ext === 'txt') {
            return $this->embedAppend($carrierPath, $data, $outputPath);
        }

        // Anything not explicitly allowed is rejected
        throw new \RuntimeException(
            "Unsupported carrier type: {$mime}. Allowed: " .
            implode(', ', $this->getSupportedMimeTypes())
        );
    }

    /**
     * Extract hidden binary data from a carrier file.
     *
     * @param  string $carrierPath Absolute path to the (modified) carrier file
     * @return string              Raw binary data that was hidden
     * @throws Exception
     */
    public function extract(string $carrierPath): string
    {
        $this->assertFileExists($carrierPath);

        $mime = mime_content_type($carrierPath);
        $ext  = strtolower(pathinfo($carrierPath, PATHINFO_EXTENSION));

        if ($this->isLsbCapable($mime)) {
            return $this->isPythonDriver()
                ? $this->extractLSBPython($carrierPath)
                : $this->extractLSB($carrierPath);
        }

        // Audio WAV: accept by mime or by .wav extension
        if ($this->isAudio($mime) || $ext === 'wav') {
            return $this->extractAudio($carrierPath);
        }

        // Text TXT: accept by mime or by .txt extension
        if ($this->isText($mime) || $ext === 'txt') {
            return $this->extractAppend($carrierPath);
        }

        throw new \RuntimeException(
            "Unsupported carrier type: {$mime}. Allowed: " .
            implode(', ', $this->getSupportedMimeTypes())
        );
    }

    /**
     * Calculate the PSNR (Peak Signal-to-Noise Ratio) between the original carrier
     * and the stego image to quantify the visual quality impact of LSB embedding.
     *
     * PSNR >= 40 dB is the accepted threshold for imperceptible modifications.
     * Requires the Python driver (uses opencv-python cv2.PSNR()).
     *
     * @param  string $originalPath Absolute path to the original (unmodified) carrier image
     * @param  string $stegoPath    Absolute path to the stego image
     * @return array{ psnr: float, threshold_40db: bool, quality: string }
     * @throws Exception
     */
    public function psnr(string $originalPath, string $stegoPath): array
    {
        $this->assertFileExists($originalPath);
        $this->assertFileExists($stegoPath);

        $result = $this->runPythonScript('psnr', [$originalPath, $stegoPath]);

        return [
            'psnr'           => (float)  $result['data']['psnr'],
            'threshold_40db' => (bool)   $result['data']['threshold_40db'],
            'quality'        => (string) $result['data']['quality'],
        ];
    }

    /**
     * Calculate the maximum payload capacity (in bytes) of a carrier file.
     *
     * @param  string $carrierPath
     * @return int Bytes
     * @throws Exception
     */
    public function capacity(string $carrierPath): int
    {
        $this->assertFileExists($carrierPath);

        $mime = mime_content_type($carrierPath);
        $ext  = strtolower(pathinfo($carrierPath, PATHINFO_EXTENSION));

        if ($this->isLsbCapable($mime)) {
            return $this->isPythonDriver()
                ? $this->capacityPython($carrierPath)
                : $this->capacityPhp($carrierPath, $mime);
        }

        // Audio WAV: 1 bit per sample, header excluded, safety factor applied
        // Accept by mime type OR by .wav extension (defensive fallback for environments with poor mime detection)
        if ($this->isAudio($mime) || $ext === 'wav') {
            return $this->capacityWav($carrierPath);
        }

        // Text TXT: append-mode conservative capacity
        // Accept by mime type OR by .txt extension
        if ($this->isText($mime) || $ext === 'txt') {
            return $this->capacityText($carrierPath);
        }

        // Unknown type: no capacity
        return 0;
    }

    // -------------------------------------------------------------------------
    // Python Stegano Driver
    // -------------------------------------------------------------------------

    /**
     * Returns true when the Python stegano library should be used for LSB operations.
     */
    private function isPythonDriver(): bool
    {
        return config('stegolock.driver', 'python') === 'python';
    }

    /**
     * Embed binary data into a PNG/BMP carrier using Python stegano (LSB).
     *
     * Binary data is base64-encoded before passing to Python because
     * stegano.lsb.hide() only accepts string payloads.
     *
     * @throws Exception
     */
    private function embedLSBPython(string $carrierPath, string $data, string $outputPath): string
    {
        $b64Payload = base64_encode($data);
        
        // Write payload to temporary file to avoid command line length limits
        $payloadFile = tempnam(sys_get_temp_dir(), 'stego_payload_');
        file_put_contents($payloadFile, $b64Payload);
        
        try {
            $result = $this->runPythonScript('embed', [$carrierPath, $payloadFile, $outputPath]);
            return $result['data']; // returns the output path
        } finally {
            // Clean up temporary file
            if (file_exists($payloadFile)) {
                @unlink($payloadFile);
            }
        }
    }

    /**
     * Extract LSB-hidden data from a carrier image using Python stegano.
     *
     * The Python script returns the base64-encoded payload; we decode it
     * back to raw binary before returning.
     *
     * @throws Exception
     */
    private function extractLSBPython(string $carrierPath): string
    {
        $result = $this->runPythonScript('extract', [$carrierPath]);

        $decoded = base64_decode($result['data'], strict: true);

        if ($decoded === false) {
            throw new Exception('Python stegano returned an invalid base64 payload. Image may be corrupt.');
        }

        return $decoded;
    }

    /**
     * Calculate carrier capacity (bytes) via Python / Pillow.
     *
     * The Python script already accounts for base64 overhead.
     *
     * @throws Exception
     */
    private function capacityPython(string $carrierPath): int
    {
        $result = $this->runPythonScript('capacity', [$carrierPath]);

        // Apply 10% safety buffer to match PHP driver and prevent edge case failures
        return (int) ($result['data'] * 0.9);
    }

    /**
     * Run the stego_lsb.py script and return the decoded JSON response.
     *
     * @param  string   $command  embed | extract | capacity
     * @param  string[] $args     Additional arguments
     * @return array              The decoded JSON response
     * @throws Exception          If the process fails or returns an error
     */
    private function runPythonScript(string $command, array $args = []): array
    {
        $pythonPath  = config('stegolock.python_path', 'python');
        $scriptPath  = config('stegolock.python_script', base_path('python/stego_lsb.py'));
        $baseTimeout = (int) config('stegolock.python_timeout', 540);
        $timeout     = $this->resolvePythonTimeout($command, $args, $baseTimeout);
        $pythonInnerTimeout = max(30, $timeout - 60);

        if (!file_exists($scriptPath)) {
            throw new Exception("Python stego script not found at: {$scriptPath}");
        }

        $process = new Process(
            array_merge([$pythonPath, $scriptPath, $command], $args),
            env: ['STEGO_TIMEOUT_SECONDS' => (string) $pythonInnerTimeout],
            timeout: $timeout
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new \RuntimeException(sprintf(
                'Steganography processing timed out after %d seconds. Try using a smaller payload, a smaller image, or increase PYTHON_TIMEOUT in your .env file.',
                $timeout
            ), previous: $e);
        }

        $output = trim($process->getOutput());

        if (empty($output)) {
            $stderr = trim($process->getErrorOutput());
            
            // Hide raw Python stack traces from users and provide friendly errors
            if (!empty($stderr)) {
                // Detect common error types for friendly messages
                if (str_contains($stderr, 'ModuleNotFoundError: No module named')) {
                    $module = trim(preg_replace('/^.*ModuleNotFoundError: No module named \'([^\']+)\'.*$/s', '$1', $stderr));
                    throw new \RuntimeException("Missing required dependency: Python module '{$module}' not installed. Install using: pip install {$module}");
                }
                
                if (str_contains($stderr, 'ImportError') || str_contains($stderr, 'SyntaxError') || str_contains($stderr, 'Traceback')) {
                    throw new \RuntimeException("Steganography engine encountered an internal error. Please check your Python environment and dependencies.");
                }
                
                // Pass through other clear error messages
                if (preg_match('/^[A-Za-z0-9\s,.!?]+$/', $stderr) && strlen($stderr) < 200) {
                    throw new \RuntimeException($stderr);
                }
            }
            
            throw new \RuntimeException("Steganography process failed. Please try again with a different carrier image.");
        }

        $decoded = json_decode($output, associative: true);

        // Some third-party Python libs may print informational prompts before
        // JSON output. If full output is not valid JSON, parse the last JSON-like line.
        if (!is_array($decoded)) {
            $lines = preg_split('/\R+/', $output) ?: [];

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $candidate = trim($lines[$i]);

                if ($candidate === '') {
                    continue;
                }

                $decoded = json_decode($candidate, associative: true);

                if (is_array($decoded)) {
                    break;
                }
            }
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException("Failed to process carrier image. Please try a different image format (PNG or BMP recommended).");
        }

        if (empty($decoded['success'])) {
            // Log technical error for debugging while hiding from users
            Log::error("Steganography operation failed", [
                'error' => $decoded['error'] ?? 'unknown error',
                'code' => $decoded['code'] ?? null,
                'command' => $command,
                'args' => $args,
                'exit_code' => $process->getExitCode()
            ]);
            
            $userError = $decoded['user_message'] ?? $decoded['error'] ?? null;
            
            // Hide internal errors, show user-friendly messages
            if ($userError && preg_match('/^[A-Za-z0-9\s,.!?]+$/', $userError)) {
                throw new \RuntimeException($userError);
            }
            
            // Catch known error codes
            if (isset($decoded['code'])) {
                match ($decoded['code']) {
                    'PAYLOAD_TOO_LARGE' => throw new \RuntimeException("File is too large for this carrier image. Use a larger image or reduce file size."),
                    'INVALID_IMAGE_FORMAT' => throw new \RuntimeException("Unsupported image format. Please use PNG or BMP images. JPEG is not supported for LSB encoding."),
                    'CORRUPTED_IMAGE' => throw new \RuntimeException("Carrier image appears corrupted or is not a valid bitmap file. Try a different image."),
                    'PASSWORD_INCORRECT' => throw new \RuntimeException("Incorrect password for this document."),
                    'PSNR_THRESHOLD_FAILED' => throw new \RuntimeException("Image quality dropped too low after encoding. Use a larger carrier image."),
                    default => throw new \RuntimeException("Encoding failed. Please try again with a different carrier image.")
                };
            }
            
            // Generic fallback error with helpful instructions
            throw new \RuntimeException("Encoding failed. Please try using a clean unedited PNG image that has not been compressed or re-saved.");
        }

        return $decoded;
    }

    /**
     * Resolve a command-specific timeout in seconds.
     *
     * Embed operations can take significantly longer for large carriers and
     * payloads, especially on Windows. Use a dynamic timeout floor to avoid
     * premature subprocess termination.
     */
    private function resolvePythonTimeout(string $command, array $args, int $baseTimeout): int
    {
        $baseTimeout = max(90, $baseTimeout);

        if ($command !== 'embed') {
            return max($baseTimeout, 90);
        }

        $carrierPath = $args[0] ?? null;
        $payloadPath = $args[1] ?? null;

        $carrierBytes = (is_string($carrierPath) && is_file($carrierPath)) ? (int) filesize($carrierPath) : 0;
        $payloadBytes = (is_string($payloadPath) && is_file($payloadPath)) ? (int) filesize($payloadPath) : 0;

        $carrierMb = $carrierBytes > 0 ? ($carrierBytes / (1024 * 1024)) : 0.0;
        $payloadMb = $payloadBytes > 0 ? ($payloadBytes / (1024 * 1024)) : 0.0;

        // Baseline + weighted size factor tuned for large image embedding on Windows.
        $sizeBasedTimeout = (int) ceil(300 + ($carrierMb * 8) + ($payloadMb * 40));

        return max($baseTimeout, $sizeBasedTimeout);
    }

    // -------------------------------------------------------------------------
    // LSB Steganography (PNG / BMP images via PHP GD — fallback driver)
    // -------------------------------------------------------------------------

    /**
     * Detect number of color channels in an image (3 for RGB, 4 for RGBA).
     */
    private function detectChannels(GdImage $image): int
    {
        // Check if image supports alpha channel
        return (imageistruecolor($image) && imagecolortransparent($image) === -1) ? 4 : 3;
    }

    /**
     * Embed data into image pixels using LSB substitution (PHP GD).
     * The payload length (4 bytes, big-endian) is stored first, followed by data bits.
     */
    private function embedLSB(string $carrierPath, string $data, string $outputPath, string $mime): string
    {
        $image = $this->loadImage($carrierPath, $mime);

        $width  = imagesx($image);
        $height = imagesy($image);
        $pixels = $width * $height;
        // Always use 3 channels (RGB). Alpha channel is not used for embedding to maintain
        // compatibility with extractLSB which reads only RGB. This also avoids flawed channel detection.
        $channels = 3;

        // Prepend 4-byte big-endian length header to the payload.
        // Add ###END### delimiter (9 bytes) to mark end of data for reliable extraction.
        $delimiter = '###END###';
        $payload = pack('N', strlen($data) + strlen($delimiter)) . $data . $delimiter;
        $bits    = $this->bytesToBits($payload);
        $bitCount = count($bits);

        // Apply safety buffer: 90% of actual capacity to prevent edge case failures
        $maxBits = (int) ($pixels * $channels * 0.9);

        if ($bitCount > $maxBits) {
            imagedestroy($image);
            throw new Exception(
                "Payload too large for carrier. Need {$bitCount} bits, capacity is " . ($pixels * $channels) . " bits."
            );
        }

        $bitIndex = 0;

        outer:
        for ($y = 0; $y < $height && $bitIndex < $bitCount; $y++) {
            for ($x = 0; $x < $width && $bitIndex < $bitCount; $x++) {
                $pixel = imagecolorat($image, $x, $y);

                $r = ($pixel >> 16) & 0xFF;
                $g = ($pixel >> 8)  & 0xFF;
                $b = $pixel         & 0xFF;
                // Preserve original alpha if present (do not modify)
                $a = ($pixel >> 24) & 0xFF;

                if ($bitIndex < $bitCount) {
                    $r = ($r & 0xFE) | $bits[$bitIndex++];
                }
                if ($bitIndex < $bitCount) {
                    $g = ($g & 0xFE) | $bits[$bitIndex++];
                }
                if ($bitIndex < $bitCount) {
                    $b = ($b & 0xFE) | $bits[$bitIndex++];
                }

                // Use imagecolorallocatealpha to preserve alpha channel if present
                imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $a));
            }
        }

        $this->saveImage($image, $outputPath, $mime);
        imagedestroy($image);

        return $outputPath;
    }

    // -------------------------------------------------------------------------
    // Audio (WAV) Embed/Extract via Python
    // -------------------------------------------------------------------------

    /**
     * Embed data into a WAV carrier using Python LSB (1 bit per sample).
     */
    private function embedAudio(string $carrierPath, string $data, string $outputPath): string
    {
        $b64Payload = base64_encode($data);

        $payloadFile = tempnam(sys_get_temp_dir(), 'stego_wav_payload_');
        file_put_contents($payloadFile, $b64Payload);

        try {
            $result = $this->runWavScript('embed', [$carrierPath, $payloadFile, $outputPath]);
            return $result['data'] ?? $outputPath;
        } finally {
            if (file_exists($payloadFile)) {
                @unlink($payloadFile);
            }
        }
    }

    /**
     * Extract data from a WAV carrier using Python LSB.
     */
    private function extractAudio(string $carrierPath): string
    {
        $result = $this->runWavScript('extract', [$carrierPath]);

        $decoded = base64_decode($result['data'] ?? '', strict: true);
        if ($decoded === false) {
            throw new Exception('Invalid base64 payload from WAV extraction. Carrier may be corrupt.');
        }

        return $decoded;
    }

    /**
     * Run the wav_embed.py script and return decoded JSON.
     */
    private function runWavScript(string $command, array $args = []): array
    {
        $pythonPath = config('stegolock.python_path', 'python');
        $scriptPath = base_path('python' . DIRECTORY_SEPARATOR . 'wav_embed.py');

        if (!file_exists($scriptPath)) {
            throw new Exception("WAV stego script not found: {$scriptPath}");
        }

        $process = new Process(
            array_merge([$pythonPath, $scriptPath, $command], $args),
            timeout: (int) config('stegolock.python_timeout', 540)
        );

        $process->run();

        $output = trim($process->getOutput());
        $decoded = json_decode($output, associative: true);

        if (!is_array($decoded)) {
            $lines = preg_split('/\R+/', $output) ?: [];
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $candidate = trim($lines[$i]);
                if ($candidate === '') continue;
                $decoded = json_decode($candidate, associative: true);
                if (is_array($decoded)) break;
            }
        }

        if (!is_array($decoded)) {
            throw new Exception('WAV processing failed: invalid response from Python script');
        }

        if (empty($decoded['success'])) {
            $error = $decoded['error'] ?? 'Unknown WAV processing error';
            $userMessage = $decoded['user_message'] ?? $error;
            throw new Exception($userMessage);
        }

        return $decoded;
    }

    /**
     * Extract LSB-embedded data from an image.
     */
    private function extractLSB(string $carrierPath): string
    {
        $mime  = mime_content_type($carrierPath);
        $image = $this->loadImage($carrierPath, $mime);

        $width  = imagesx($image);
        $height = imagesy($image);

        $bits = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);

                // Read in R→G→B order to match embedLSB write order
                $bits[] = ($pixel >> 16) & 1;        // R LSB
                $bits[] = ($pixel >> 8)  & 1;        // G LSB
                $bits[] = $pixel         & 1;        // B LSB
            }
        }

        imagedestroy($image);

        // Read the 4-byte length header (32 bits).
        if (count($bits) < 32) {
            throw new Exception('Carrier image too small to contain a valid payload.');
        }

        $lengthBits  = array_slice($bits, 0, 32);
        $payloadLength = $this->bitsToInt($lengthBits);

        $payloadBits = array_slice($bits, 32, $payloadLength * 8);

        if (count($payloadBits) < $payloadLength * 8) {
            throw new Exception('Carrier image does not contain enough data for the declared payload length.');
        }

        $raw = $this->bitsToBytes($payloadBits);

        // The embedLSB method appends a '###END###' delimiter (9 bytes) to the payload.
        // Strip it if present to return only the original data.
        $delimiter = '###END###';
        $delimiterLength = strlen($delimiter);
        if (strlen($raw) >= $delimiterLength && substr($raw, -$delimiterLength) === $delimiter) {
            $raw = substr($raw, 0, -$delimiterLength);
        }

        return $raw;
    }

    // -------------------------------------------------------------------------
    // Append-with-Marker Approach (text, binary files)
    // -------------------------------------------------------------------------

    /**
     * Append data to the end of a carrier file with a recognisable delimiter.
     * The appended section is: MARKER + pack('N', length) + data
     */
    private function embedAppend(string $carrierPath, string $data, string $outputPath): string
    {
        $original = file_get_contents($carrierPath);

        if ($original === false) {
            throw new Exception("Could not read carrier file: {$carrierPath}");
        }

        $payload  = self::MARKER . pack('N', strlen($data)) . $data;
        $result   = file_put_contents($outputPath, $original . $payload);

        if ($result === false) {
            throw new Exception("Could not write output file: {$outputPath}");
        }

        return $outputPath;
    }

    /**
     * Extract append-mode payload from a carrier file.
     */
    private function extractAppend(string $carrierPath): string
    {
        $content    = file_get_contents($carrierPath);
        $markerLen  = strlen(self::MARKER);
        $pos        = strrpos($content, self::MARKER);

        if ($pos === false) {
            throw new Exception('No StegoLock marker found in carrier file. File may not contain embedded data.');
        }

        $lengthBytes = substr($content, $pos + $markerLen, 4);
        $length      = unpack('N', $lengthBytes)[1];
        $data        = substr($content, $pos + $markerLen + 4, $length);

        if (strlen($data) !== $length) {
            throw new Exception('Payload length mismatch. Carrier file may be corrupt.');
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // GD Helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate LSB capacity of an image using PHP GD.
     * Formula: 3 colour channels × 1 bit/channel per pixel → bytes, minus 4-byte length prefix.
     *
     * Extracted from an inline closure that previously lived inside capacity().
     */
    private function capacityPhp(string $carrierPath, string $mime): int
    {
        $image  = $this->loadImage($carrierPath, $mime);
        $pixels = imagesx($image) * imagesy($image);
        imagedestroy($image);

        return (int) (($pixels * 3 * self::BITS_PER_CHANNEL) / 8) - 4;
    }

    private function isLsbCapable(string $mime): bool
    {
        // JPEG is accepted for embedding only — the PHP GD driver always saves
        // the stego output as PNG (JPEG compression is lossy and destroys LSBs).
        return in_array($mime, ['image/png', 'image/bmp', 'image/x-bmp', 'image/jpeg', 'image/jpg'], true);
    }

    private function isAudio(string $mime): bool
    {
        return in_array($mime, config('stegolock.carriers.allowed.audio.mime_types', ['audio/wav', 'audio/x-wav', 'audio/wave']), true);
    }

    private function isText(string $mime): bool
    {
        return in_array($mime, config('stegolock.carriers.allowed.text.mime_types', ['text/plain']), true);
    }

    /**
     * Get list of all supported MIME types from config.
     * Used for error messages.
     */
    private function getSupportedMimeTypes(): array
    {
        $allowed = config('stegolock.carriers.allowed', []);
        $mimes = [];
        foreach ($allowed as $type => $cfg) {
            if (isset($cfg['mime_types']) && is_array($cfg['mime_types'])) {
                $mimes = array_merge($mimes, $cfg['mime_types']);
            }
        }
        return array_unique($mimes);
    }

    private function loadImage(string $path, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/png'                     => imagecreatefrompng($path),
            'image/bmp',
            'image/x-bmp'                   => imagecreatefrombmp($path),
            'image/jpeg',
            'image/jpg'                     => function_exists('imagecreatefromjpeg')
                ? imagecreatefromjpeg($path)
                : throw new Exception(
                    'PHP GD was built without JPEG support. ' .
                    'Set STEGO_DRIVER=python in .env to use JPEG carriers.'
                ),
            default                         => throw new Exception("Unsupported image MIME type for LSB: {$mime}"),
        };

        if ($image === false) {
            throw new Exception("GD could not load image: {$path}");
        }

        return $image;
    }

    private function saveImage(GdImage $image, string $outputPath, string $mime): void
    {
        // JPEG is lossy — re-encoding as JPEG destroys LSB data.
        // Stego output is always written as lossless PNG when the carrier was JPEG.
        $saveMime = in_array($mime, ['image/jpeg', 'image/jpg'], true) ? 'image/png' : $mime;

        $ok = match ($saveMime) {
            'image/png'           => imagepng($image, $outputPath, 9),
            'image/bmp',
            'image/x-bmp'         => imagebmp($image, $outputPath),
            default               => throw new Exception("Unsupported image MIME type for save: {$saveMime}"),
        };

        if (!$ok) {
            throw new Exception("GD could not save image to: {$outputPath}");
        }
    }

    // -------------------------------------------------------------------------
    // Audio (WAV) Capacity
    // -------------------------------------------------------------------------

    /**
     * Calculate WAV capacity: (filesize - 44 bytes header) / 8 bits per byte × 0.95 safety
     * Supports PCM only. Returns 0 if file too small or invalid.
     *
     * Hardened to validate WAV header before calculating capacity.
     */
    private function capacityWav(string $carrierPath): int
    {
        // First, validate WAV header in PHP (works without Python)
        $headerValidation = $this->validateWavHeader($carrierPath);
        if (!$headerValidation['valid']) {
            return 0;
        }

        // Try Python validator for more accurate capacity calculation if available
        $result = $this->runWavValidator($carrierPath);
        if ($result['valid'] && isset($result['capacity_bytes'])) {
            return (int) $result['capacity_bytes'];
        }

        // Fallback: calculate capacity from header info
        $fileSize = filesize($carrierPath);
        if ($fileSize === false || $fileSize < 44) {
            return 0;
        }

        // Capacity = (fileSize - 44 header) * 0.95 safety factor
        // Each byte of audio data can hold 1 bit
        $audioBytes = $fileSize - 44;
        $capacityBytes = (int) ($audioBytes * 0.95);

        return max(0, $capacityBytes);
    }

    /**
     * Validate WAV file header in pure PHP.
     * Returns ['valid' => true] or ['valid' => false, 'reason' => '...']
     */
    private function validateWavHeader(string $filePath): array
    {
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize < 44) {
            return ['valid' => false, 'reason' => 'File too small to contain a valid WAV header (minimum 44 bytes)'];
        }

        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return ['valid' => false, 'reason' => 'Cannot open file for header validation'];
        }

        try {
            // Read RIFF header (12 bytes)
            $riffHeader = fread($handle, 12);
            if (strlen($riffHeader) < 12) {
                return ['valid' => false, 'reason' => 'Incomplete RIFF header'];
            }

            // Check "RIFF" magic
            if (substr($riffHeader, 0, 4) !== 'RIFF') {
                return ['valid' => false, 'reason' => 'Missing RIFF signature'];
            }

            // Check "WAVE" format
            if (substr($riffHeader, 8, 4) !== 'WAVE') {
                return ['valid' => false, 'reason' => 'Not a WAVE file (missing WAVE signature)'];
            }

            // Read fmt chunk header (8 bytes)
            $fmtHeader = fread($handle, 8);
            if (strlen($fmtHeader) < 8) {
                error_log("WAV Debug: Incomplete fmt chunk header, got " . strlen($fmtHeader) . " bytes");
                return ['valid' => false, 'reason' => 'Incomplete fmt chunk header'];
            }

            // Check "fmt " chunk
            if (substr($fmtHeader, 0, 4) !== 'fmt ') {
                return ['valid' => false, 'reason' => 'Missing fmt chunk'];
            }

            $fmtSize = unpack('V', substr($fmtHeader, 4, 4))[1];

            // Read fmt chunk data
            $fmtData = fread($handle, $fmtSize);
            if (strlen($fmtData) < $fmtSize) {
                return ['valid' => false, 'reason' => 'Incomplete fmt chunk data'];
            }

            // Parse audio format (PCM = 1)
            $audioFormat = unpack('v', substr($fmtData, 0, 2))[1];
            if ($audioFormat !== 1) {
                return ['valid' => false, 'reason' => "Non-PCM compression not supported (format: {$audioFormat})"];
            }

            // Check sample width (8-bit or 16-bit)
            $sampleWidth = unpack('v', substr($fmtData, 14, 2))[1];

            // Check sample width (8-bit or 16-bit)
            // Note: WAV "bits per sample" field is in BITS, not bytes
            if (!in_array($sampleWidth, [8, 16], true)) {
                return ['valid' => false, 'reason' => "Unsupported sample width: {$sampleWidth} bits (only 8/16-bit PCM supported)"];
            }

            // Check channels
            $channels = unpack('v', substr($fmtData, 2, 2))[1];

            if ($channels === 0) {
                return ['valid' => false, 'reason' => 'Zero audio channels'];
            }
            if ($channels > 2) {
                return ['valid' => false, 'reason' => "Too many channels: {$channels} (max 2 supported)"];
            }

            // Verify data chunk exists
            while (!feof($handle)) {
                $chunkHeader = fread($handle, 8);

                if (strlen($chunkHeader) < 8) {
                    break;
                }

                $chunkId = substr($chunkHeader, 0, 4);
                $chunkSize = unpack('V', substr($chunkHeader, 4, 4))[1];

                if ($chunkId === 'data') {
                    if ($chunkSize === 0) {
                        return ['valid' => false, 'reason' => 'WAV file contains no audio data'];
                    }
                    return ['valid' => true];
                }

                // Skip to next chunk
                fseek($handle, $chunkSize, SEEK_CUR);
            }

            return ['valid' => false, 'reason' => 'No data chunk found in WAV file'];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Run the wav_validator.py script and return decoded JSON.
     */
    private function runWavValidator(string $filePath): array
    {
        $pythonPath = config('stegolock.python_path', 'python');
        $scriptPath = base_path('python' . DIRECTORY_SEPARATOR . 'wav_validator.py');

        if (!file_exists($scriptPath)) {
            return ['valid' => false, 'reason' => 'WAV validator script not found'];
        }

        $process = new Process(
            [$pythonPath, $scriptPath, $filePath],
            timeout: (int) config('stegolock.python_timeout', 60)
        );

        try {
            $process->run();
            $output = trim($process->getOutput());
            $decoded = json_decode($output, associative: true);
            if (is_array($decoded)) {
                return $decoded;
            }
            return ['valid' => false, 'reason' => 'Invalid validator response'];
        } catch (\Throwable $e) {
            return ['valid' => false, 'reason' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Text (TXT) Capacity
    // -------------------------------------------------------------------------

    /**
     * Calculate TXT capacity: conservative 50% of file size for append-mode.
     */
    private function capacityText(string $carrierPath): int
    {
        $size = filesize($carrierPath);
        if ($size === false || $size === 0) {
            return 0;
        }

        $capacity = (int) ($size * config('stegolock.capacity_safety_factor.text', 0.50));
        return max(0, $capacity);
    }

    // -------------------------------------------------------------------------
    // Bit Manipulation Helpers
    // -------------------------------------------------------------------------

    /** Convert a byte string to an array of individual bits (MSB first). */
    private function bytesToBits(string $bytes): array
    {
        $bits = [];
        for ($i = 0; $i < strlen($bytes); $i++) {
            $byte = ord($bytes[$i]);
            for ($b = 7; $b >= 0; $b--) {
                $bits[] = ($byte >> $b) & 1;
            }
        }
        return $bits;
    }

    /** Convert an array of 8 × N bits (MSB first) back to a byte string. */
    private function bitsToBytes(array $bits): string
    {
        $out = '';
        $chunks = array_chunk($bits, 8);
        foreach ($chunks as $chunk) {
            if (count($chunk) < 8) {
                break;
            }
            $byte = 0;
            foreach ($chunk as $i => $bit) {
                $byte |= ($bit << (7 - $i));
            }
            $out .= chr($byte);
        }
        return $out;
    }

    /** Convert 32 bits (MSB first) to an unsigned 32-bit integer. */
    private function bitsToInt(array $bits): int
    {
        $value = 0;
        foreach ($bits as $i => $bit) {
            $value |= ($bit << (31 - $i));
        }
        return $value;
    }

    private function assertFileExists(string $path): void
    {
        if (!file_exists($path)) {
            throw new Exception("File not found: {$path}");
        }
    }
}
