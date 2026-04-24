<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Stego\StegoService;
use Illuminate\Support\Facades\Storage;

class StegoCapacityTest extends TestCase
{
    protected StegoService $stego;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stego = new StegoService();
    }

    /** @test */
    public function it_calculates_wav_capacity_with_safety_factor(): void
    {
        $pythonPath = config('stegolock.python_path', 'python');
        if (!shell_exec("which {$pythonPath} 2>/dev/null") && !shell_exec("where {$pythonPath} 2>/dev/null")) {
            $this->markTestSkipped("Python interpreter not found");
        }

        // Create a valid PCM WAV: 1 second, mono, 16-bit => nframes=44100, channels=1
        // Capacity = floor((nframes * channels) / 8) * 0.95
        // = floor(44100 / 8) * 0.95 = 5512 * 0.95 = 5236 (approx)
        $wavData = $this->createValidPcmWav(44100, 1, 16);
        $tmpPath = sys_get_temp_dir() . '/test_capacity.wav';
        file_put_contents($tmpPath, $wavData);

        $capacity = $this->stego->capacity($tmpPath);

        $this->assertGreaterThan(0, $capacity);
        $this->assertEquals(5236, $capacity); // approximate expected

        unlink($tmpPath);
    }

    /** @test */
    public function it_calculates_txt_capacity_conservatively(): void
    {
        $content = str_repeat('A', 1000); // 1000 bytes
        $tmpPath = sys_get_temp_dir() . '/test_capacity.txt';
        file_put_contents($tmpPath, $content);

        $capacity = $this->stego->capacity($tmpPath);

        // Expected: filesize * 0.5
        $expected = (int)(strlen($content) * 0.5);

        $this->assertEquals($expected, $capacity);

        unlink($tmpPath);
    }

    /** @test */
    public function it_returns_zero_capacity_for_invalid_wav(): void
    {
        // Create an invalid WAV (wrong magic)
        $tmpPath = sys_get_temp_dir() . '/invalid.wav';
        file_put_contents($tmpPath, "RIXX"); // corrupted

        $capacity = $this->stego->capacity($tmpPath);

        $this->assertEquals(0, $capacity);

        unlink($tmpPath);
    }

    /** @test */
    public function it_returns_zero_capacity_for_empty_txt(): void
    {
        $tmpPath = sys_get_temp_dir() . '/empty.txt';
        file_put_contents($tmpPath, '');

        $capacity = $this->stego->capacity($tmpPath);

        $this->assertEquals(0, $capacity);

        unlink($tmpPath);
    }

    /** @test */
    public function image_capacity_still_works(): void
    {
        // Create a minimal 1x1 PNG image (actual PNG binary)
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmpPath = sys_get_temp_dir() . '/test.png';
        file_put_contents($tmpPath, $pngData);

        $capacity = $this->stego->capacity($tmpPath);

        $this->assertGreaterThan(0, $capacity);

        unlink($tmpPath);
    }

    /**
     * Create a minimal valid PCM WAV file in memory.
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
        $samples = str_repeat("\x00", $dataSize);

        return $header . $fmt . $dataHeader . $samples;
    }
}
