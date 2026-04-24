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
        $fileSize = 44 + $dataSize;

        $header = pack('N4', 0x46464952, $fileSize, 0x45564157, 0x20746d66); // "RIFF", size, "WAVE", "fmt "
        $fmt = pack('N2n2N2', 16, 1, $numChannels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample);
        $dataHeader = pack('N2', 0x61746164, $dataSize);
        $samples = str_repeat("\x00", $dataSize); // silence

        return $header . $fmt . $dataHeader . $samples;
    }
}
