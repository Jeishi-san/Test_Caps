<?php

namespace Tests\Feature\Stego;

use Tests\TestCase;
use App\Services\Stego\StegoService;
use Illuminate\Support\Facades\Storage;

class WavStegoTest extends TestCase
{
    protected StegoService $stego;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stego = new StegoService();
    }

    /** @test */
    public function wav_embed_and_extract_round_trip_succeeds(): void
    {
        // Skip if Python not available
        $pythonPath = config('stegolock.python_path', 'python');
        if (!shell_exec("which {$pythonPath} 2>/dev/null") && !shell_exec("where {$pythonPath} 2>/dev/null")) {
            $this->markTestSkipped("Python interpreter not found at {$pythonPath}");
        }

        // Create a valid PCM WAV file (mono, 16-bit, 44100 Hz, 1 second)
        $wavData = $this->createValidPcmWav(44100, 1, 16);
        $carrierPath = sys_get_temp_dir() . '/carrier.wav';
        file_put_contents($carrierPath, $wavData);

        $payload = 'Hello WAV stego! This is a test payload.';
        $outputPath = sys_get_temp_dir() . '/stego.wav';
        $extractedPath = sys_get_temp_dir() . '/extracted.bin';

        try {
            // Embed
            $this->stego->embed($carrierPath, $payload, $outputPath);
            $this->assertFileExists($outputPath, 'Stego WAV file was created');

            // Extract
            $extracted = $this->stego->extract($outputPath);
            $this->assertEquals($payload, $extracted, 'Extracted payload matches original');

            // Cleanup
            @unlink($outputPath);
        } finally {
            @unlink($carrierPath);
            if (file_exists($extractedPath)) @unlink($extractedPath);
        }
    }

    /** @test */
    public function wav_capacity_is_positive_for_valid_file(): void
    {
        $wavData = $this->createValidPcmWav(44100, 1, 16);
        $path = sys_get_temp_dir() . '/cap_test.wav';
        file_put_contents($path, $wavData);

        $capacity = $this->stego->capacity($path);
        $this->assertGreaterThan(0, $capacity, 'WAV capacity should be positive');

        @unlink($path);
    }

    /**
     * Create a minimal valid PCM WAV file.
     *
     * @param int $sampleRate
     * @param int $channels
     * @param int $bitsPerSample (8 or 16)
     * @return string Binary WAV data
     */
    private function createValidPcmWav(int $sampleRate, int $channels, int $bitsPerSample): string
    {
        $numChannels = $channels;
        $sampleRate = $sampleRate;
        $bitsPerSample = $bitsPerSample;
        $byteRate = $sampleRate * $numChannels * $bitsPerSample / 8;
        $blockAlign = $numChannels * $bitsPerSample / 8;
        $dataSize = $sampleRate * $blockAlign; // 1 second of audio
        $fileSize = 36 + $dataSize;

        // Build WAV header (44 bytes total)
        // RIFF chunk
        $header  = 'RIFF';                           // Chunk ID (4 bytes)
        $header .= pack('V', 36 + $dataSize);         // Chunk Size (4 bytes): 4 + (8 + 16) + (8 + dataSize) - 8 = 36 + dataSize
        $header .= 'WAVE';                           // Format (4 bytes)

        // fmt subchunk
        $header .= 'fmt ';                           // Subchunk1 ID (4 bytes)
        $header .= pack('V', 16);                    // Subchunk1 Size (4 bytes): 16 for PCM
        $header .= pack('v', 1);                     // Audio Format (2 bytes): 1 = PCM
        $header .= pack('v', $numChannels);           // Num Channels (2 bytes)
        $header .= pack('V', $sampleRate);            // Sample Rate (4 bytes)
        $header .= pack('V', $byteRate);              // Byte Rate (4 bytes)
        $header .= pack('v', $blockAlign);            // Block Align (2 bytes)
        $header .= pack('v', $bitsPerSample);         // Bits Per Sample (2 bytes)

        // data subchunk
        $header .= 'data';                           // Subchunk2 ID (4 bytes)
        $header .= pack('V', $dataSize);              // Subchunk2 Size (4 bytes)

        // Audio data (silence)
        $samples = str_repeat("\x00", $dataSize);

        return $header . $samples;
    }
}
