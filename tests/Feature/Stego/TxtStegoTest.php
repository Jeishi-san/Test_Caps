<?php

namespace Tests\Feature\Stego;

use Tests\TestCase;
use App\Services\Stego\StegoService;

class TxtStegoTest extends TestCase
{
    protected StegoService $stego;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stego = new StegoService();
    }

    /** @test */
    public function txt_append_embed_and_extract_round_trip_succeeds(): void
    {
        $carrierContent = "This is a plain text carrier file.\nIt has multiple lines.\n";
        $tmpCarrier = sys_get_temp_dir() . '/carrier.txt';
        file_put_contents($tmpCarrier, $carrierContent);

        $payload = "Secret message hidden in text file.";
        $outputPath = sys_get_temp_dir() . '/stego.txt';

        // Embed
        $this->stego->embed($tmpCarrier, $payload, $outputPath);
        $this->assertFileExists($outputPath);

        // Verify carrier content is preserved at start
        $stegoContent = file_get_contents($outputPath);
        $this->assertStringStartsWith($carrierContent, $stegoContent);

        // Extract
        $extracted = $this->stego->extract($outputPath);
        $this->assertEquals($payload, $extracted);

        // Cleanup
        @unlink($tmpCarrier);
        @unlink($outputPath);
    }

    /** @test */
    public function txt_capacity_is_half_of_file_size(): void
    {
        $content = str_repeat('x', 1000);
        $path = sys_get_temp_dir() . '/test.txt';
        file_put_contents($path, $content);

        $capacity = $this->stego->capacity($path);
        $this->assertEquals(500, $capacity); // 1000 * 0.5

        @unlink($path);
    }
}
