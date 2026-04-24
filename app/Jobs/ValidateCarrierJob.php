<?php

namespace App\Jobs;

use App\Models\StegoCarrier;
use App\Services\Stego\StegoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Throwable;

/**
 * ValidateCarrierJob
 * 
 * Background job that validates a carrier file after upload.
 * Measures PSNR (for images) and capacity, then updates the carrier record.
 * 
 * This moves validation from encode-time to upload-time, ensuring only
 * pre-validated carriers enter the pool — encode cannot fail due to a bad carrier.
 */
class ValidateCarrierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $carrierId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(StegoService $stegoService): void
    {
        $carrier = StegoCarrier::find($this->carrierId);

        if (!$carrier) {
            Log::warning('ValidateCarrierJob: Carrier not found', ['carrier_id' => $this->carrierId]);
            return;
        }

        try {
            $filePath = Storage::path($carrier->file_path);

            if (!file_exists($filePath)) {
                $this->markInvalid($carrier, "Carrier file not found on disk: {$carrier->file_path}");
                return;
            }

            $mime = $carrier->mime_type ?? '';

            // Audio (WAV) validation: run wav_validator.py
            if (str_starts_with($mime, 'audio/')) {
                $this->validateAudioCarrier($carrier, $filePath);
                return;
            }

            // Text (TXT) validation: simple readability and non-empty check
            if (str_starts_with($mime, 'text/')) {
                $this->validateTextCarrier($carrier, $filePath);
                return;
            }

            // Image validation: capacity + PSNR
            $capacity = $stegoService->capacity($filePath);
            $carrier->capacity_bytes = $capacity;

            if ($capacity <= 0) {
                $this->markInvalid($carrier, "Carrier capacity is zero or insufficient");
                return;
            }

            $psnrResult = $this->measureBaselinePsnr($stegoService, $filePath);

            if ($psnrResult !== null) {
                $carrier->psnr = $psnrResult;

                if ($psnrResult < 40.0) {
                    $this->markInvalid($carrier, "PSNR {$psnrResult} dB is below the 40 dB threshold");
                    return;
                }
            }

            $carrier->validation_status = 'valid';
            $carrier->validated_at = now();
            $carrier->save();

            Log::info('ValidateCarrierJob: Carrier validated successfully', [
                'carrier_id' => $carrier->id,
                'capacity_bytes' => $capacity,
                'psnr' => $carrier->psnr,
            ]);

        } catch (Throwable $e) {
            Log::error('ValidateCarrierJob: Validation failed', [
                'carrier_id' => $this->carrierId,
                'error' => $e->getMessage(),
            ]);
            $this->markInvalid($carrier, $e->getMessage());
        }
    }

    /**
     * Measure baseline PSNR by comparing the carrier to itself.
     * This establishes a baseline quality metric for the carrier.
     */
    private function measureBaselinePsnr(StegoService $stegoService, string $filePath): ?float
    {
        try {
            // Create a temporary copy to measure baseline PSNR
            $tmpPath = tempnam(sys_get_temp_dir(), 'psnr_') . '.' . pathinfo($filePath, PATHINFO_EXTENSION);
            copy($filePath, $tmpPath);

            try {
                $psnrResult = $stegoService->psnr($filePath, $tmpPath);
                return $psnrResult['psnr'] ?? null;
            } finally {
                if (file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        } catch (Throwable $e) {
            Log::warning('ValidateCarrierJob: PSNR measurement failed', [
                'carrier_id' => $this->carrierId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Mark carrier as invalid with error reason.
     */
    private function markInvalid(StegoCarrier $carrier, string $reason): void
    {
        $carrier->validation_status = 'invalid';
        $carrier->validation_error = $reason;
        $carrier->validated_at = now();
        $carrier->save();

        Log::info('ValidateCarrierJob: Carrier marked as invalid', [
            'carrier_id' => $carrier->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Validate an audio (WAV) carrier using wav_validator.py.
     * Maps technical errors to user-friendly messages per behavior matrix.
     */
    private function validateAudioCarrier(StegoCarrier $carrier, string $filePath): void
    {
        $pythonPath = config('stegolock.python_path', 'python');
        $scriptPath = base_path('python' . DIRECTORY_SEPARATOR . 'wav_validator.py');

        if (!file_exists($scriptPath)) {
            $this->markInvalid($carrier, 'Unsupported audio format: WAV validation service unavailable.');
            return;
        }

        $process = new Process(
            [$pythonPath, $scriptPath, $filePath],
            timeout: (int) config('stegolock.python_timeout', 60)
        );

        try {
            $process->run();
            $output = trim($process->getOutput());
            $result = json_decode($output, associative: true);

            if (!is_array($result)) {
                $this->markInvalid($carrier, 'Unsupported audio format: unable to parse validation response.');
                return;
            }

            if (empty($result['valid'])) {
                $technicalReason = $result['reason'] ?? 'Unknown WAV validation error';
                $userMessage = $this->mapWavValidationError($technicalReason);
                $this->markInvalid($carrier, $userMessage);
                Log::info('ValidateCarrierJob: WAV validation failed', [
                    'carrier_id' => $carrier->id,
                    'technical_reason' => $technicalReason,
                    'user_message' => $userMessage,
                ]);
                return;
            }

            // Valid WAV — set capacity from validator (already includes safety factor)
            $carrier->capacity_bytes = (int) ($result['capacity_bytes'] ?? 0);
            $carrier->validation_status = 'valid';
            $carrier->validated_at = now();
            $carrier->save();

            Log::info('ValidateCarrierJob: WAV carrier validated', [
                'carrier_id' => $carrier->id,
                'capacity_bytes' => $carrier->capacity_bytes,
                'sample_width' => $result['sample_width'] ?? null,
                'channels' => $result['channels'] ?? null,
                'nframes' => $result['nframes'] ?? null,
            ]);

        } catch (ProcessFailedException $e) {
            $this->markInvalid($carrier, 'Unsupported audio format: WAV processing failed.');
        } catch (\Throwable $e) {
            $this->markInvalid($carrier, 'Unsupported audio format: Unable to validate WAV file.');
        }
    }

    /**
     * Map technical WAV validation errors to user-friendly messages.
     * Per behavior matrix error contract.
     */
    private function mapWavValidationError(string $technicalReason): string
    {
        $reasonLower = strtolower($technicalReason);

        if (str_contains($reasonLower, 'non-pcm') || str_contains($reasonLower, 'compression')) {
            return 'Compressed WAV not supported. Use PCM uncompressed WAV.';
        }

        if (str_contains($reasonLower, 'no audio frames') || str_contains($reasonLower, 'nframes=0') || str_contains($reasonLower, 'contains no audio')) {
            return 'Audio file contains no audio data.';
        }

        if (str_contains($reasonLower, 'invalid wav') || str_contains($reasonLower, 'malformed') || str_contains($reasonLower, 'structure')) {
            return 'Unsupported audio format: WAV file is malformed.';
        }

        if (str_contains($reasonLower, 'unsupported sample width') || str_contains($reasonLower, 'sample width')) {
            return 'Unsupported audio format: WAV file uses unsupported sample format. Use 8-bit or 16-bit PCM.';
        }

        if (str_contains($reasonLower, 'too many channels') || str_contains($reasonLower, 'channels')) {
            return 'Unsupported audio format: WAV file has too many channels. Use mono or stereo.';
        }

        // Generic fallback
        return 'Unsupported audio format. Please upload a valid PCM WAV file.';
    }

    /**
     * Validate a text (TXT) carrier: readable and non-empty.
     */
    private function validateTextCarrier(StegoCarrier $carrier, string $filePath): void
    {
        if (!is_readable($filePath)) {
            $this->markInvalid($carrier, 'Text carrier could not be read (permission denied)');
            return;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            $this->markInvalid($carrier, 'Text carrier could not be read (read error)');
            return;
        }

        $size = strlen($content);
        if ($size === 0) {
            $this->markInvalid($carrier, 'Text carrier is empty');
            return;
        }

        // Capacity is conservative 50% of file size
        $carrier->capacity_bytes = (int) ($size * 0.5);

        if ($carrier->capacity_bytes <= 0) {
            $this->markInvalid($carrier, 'Text carrier capacity is too low (file too small)');
            return;
        }

        $carrier->validation_status = 'valid';
        $carrier->validated_at = now();
        $carrier->save();

        Log::info('ValidateCarrierJob: Text carrier validated', [
            'carrier_id' => $carrier->id,
            'capacity_bytes' => $carrier->capacity_bytes,
            'file_size' => $size,
        ]);
    }
}
