<?php

namespace Tests\Feature\Api;

use App\Models\StegoCarrier;
use App\Models\Document;
use App\Models\User;
use App\Jobs\ValidateCarrierJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CarrierPoolTest
 *
 * Feature tests for the Carrier Pool API endpoints.
 *
 * Tests verify:
 *  - Carrier upload to pool
 *  - Carrier listing with filters
 *  - Carrier deletion
 *  - Preflight check for encoding capacity
 *  - Background validation job dispatch
 *  - Quota enforcement
 */
class CarrierPoolTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'username' => 'pooltester',
            'role'     => 'user',
            'mkd_salt' => str_repeat('ab', 16),
        ]);
    }

    private function createDocumentWithBytes(int $bytes): Document
    {
        $relativePath = 'documents/preflight-' . uniqid('', true) . '.txt';
        $absolutePath = public_path($relativePath);

        $directory = dirname($absolutePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($absolutePath, str_repeat('A', $bytes));

        return Document::factory()->create([
            'owner_id' => $this->user->id,
            'name' => 'preflight-' . $bytes . '.txt',
            'original_name' => 'preflight-' . $bytes . '.txt',
            'file_path' => $relativePath,
            'size' => $bytes,
            'extension' => 'txt',
            'is_encrypted' => false,
        ]);
    }

    private function expectedDecodedCiphertextBytes(int $bytes): int
    {
        $compressed = gzcompress(str_repeat('A', $bytes), 6);
        $this->assertIsString($compressed);

        return strlen($compressed);
    }

    // -------------------------------------------------------------------------
    // Authentication guard
    // -------------------------------------------------------------------------

    #[Test]
    public function carriers_index_requires_authentication(): void
    {
        $this->getJson('/api/stego/carriers')->assertStatus(401);
    }

    #[Test]
    public function carriers_store_requires_authentication(): void
    {
        $this->postJson('/api/stego/carriers')->assertStatus(401);
    }

    #[Test]
    public function carriers_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/stego/carriers/1')->assertStatus(401);
    }

    #[Test]
    public function preflight_requires_authentication(): void
    {
        $this->postJson('/api/stego/preflight')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Carrier upload
    // -------------------------------------------------------------------------

    #[Test]
    public function can_upload_carrier_to_pool(): void
    {
        Queue::fake();

        Storage::fake('local');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->image('test-carrier.png', 800, 600),
                'name' => 'Test Carrier',
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'carrier_id',
                'validation_status',
            ])
            ->assertJsonPath('validation_status', 'pending');

        // Verify carrier was created in database
        $this->assertDatabaseHas('stego_carriers', [
            'name' => 'Test Carrier',
            'uploaded_by' => $this->user->id,
            'validation_status' => 'pending',
        ]);

        // Verify validation job was dispatched
        Queue::assertPushed(ValidateCarrierJob::class, function ($job) {
            return $job->carrierId === StegoCarrier::first()->id;
        });
    }

    #[Test]
    public function carrier_upload_validates_file_type(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->create('document.pdf', 100),
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function carrier_upload_validates_file_size(): void
    {
        // Create a file larger than 100MB
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->create('large-image.png', 102401), // 100MB + 1KB
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Carrier listing
    // -------------------------------------------------------------------------

    #[Test]
    public function can_list_user_carriers(): void
    {
        // Create carriers for this user
        StegoCarrier::factory()->count(3)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
        ]);

        // Create carriers for another user (should not appear)
        $otherUser = User::factory()->create(['username' => 'other', 'role' => 'user']);
        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $otherUser->id,
            'validation_status' => 'valid',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stego/carriers');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function can_filter_carriers_by_validation_status(): void
    {
        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
        ]);

        StegoCarrier::factory()->count(1)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'pending',
        ]);

        StegoCarrier::factory()->count(1)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'invalid',
        ]);

        // Filter for valid carriers
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stego/carriers?status=valid');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Filter for pending carriers
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/stego/carriers?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // -------------------------------------------------------------------------
    // Carrier deletion
    // -------------------------------------------------------------------------

    #[Test]
    public function can_delete_carrier_from_pool(): void
    {
        Storage::fake('local');

        $carrier = StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'file_path' => 'stego/carriers/test.png',
            'is_in_use' => false,
        ]);

        // Create the file in storage
        Storage::disk('local')->put('stego/carriers/test.png', 'fake content');

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/stego/carriers/{$carrier->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Carrier removed from pool.');

        // Verify carrier was deleted from database
        $this->assertDatabaseMissing('stego_carriers', [
            'id' => $carrier->id,
        ]);

        // Verify file was deleted from storage
        $this->assertFalse(Storage::disk('local')->exists('stego/carriers/test.png'));
    }

    #[Test]
    public function cannot_delete_carrier_in_use(): void
    {
        $carrier = StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'is_in_use' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/stego/carriers/{$carrier->id}");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Carrier is currently in use by an active stego document.');

        // Verify carrier still exists
        $this->assertDatabaseHas('stego_carriers', [
            'id' => $carrier->id,
        ]);
    }

    #[Test]
    public function cannot_delete_another_users_carrier(): void
    {
        $otherUser = User::factory()->create(['username' => 'other', 'role' => 'user']);

        $carrier = StegoCarrier::factory()->create([
            'uploaded_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/stego/carriers/{$carrier->id}");

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Preflight check
    // -------------------------------------------------------------------------

    #[Test]
    public function preflight_returns_sufficient_capacity(): void
    {
        $documentBytes = 2000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);

        // Create valid carriers with known capacities
        StegoCarrier::factory()->count(3)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => 1000000, // 1MB each
            'is_in_use' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', true)
            ->assertJsonPath('available_bytes', 3000000)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 3);
    }

    #[Test]
    public function preflight_returns_insufficient_capacity(): void
    {
        $documentBytes = 2000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);
        $availableBytes = max(1, $requiredBytes - 1);
        $firstCarrierBytes = max(1, $availableBytes - 1);
        $secondCarrierBytes = 1;

        // Create valid carriers with limited capacity
        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $firstCarrierBytes,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $secondCarrierBytes,
            'is_in_use' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', false)
            ->assertJsonPath('available_bytes', $availableBytes)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 2);
    }

    #[Test]
    public function preflight_ignores_invalid_carriers(): void
    {
        $documentBytes = 2000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);

        // Create mix of valid and invalid carriers
        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => 1000000,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'invalid',
            'capacity_bytes' => 1000000,
            'is_in_use' => false,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', true)
            ->assertJsonPath('available_bytes', 2000000)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 2);
    }

    #[Test]
    public function preflight_ignores_carriers_in_use(): void
    {
        $documentBytes = 3000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);
        $availableBytes = max(1, $requiredBytes - 1);
        $firstCarrierBytes = max(1, $availableBytes - 1);
        $secondCarrierBytes = 1;

        // Create carriers, some in use
        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $firstCarrierBytes,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $secondCarrierBytes,
            'is_in_use' => false,
        ]);

        StegoCarrier::factory()->count(2)->create([
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => max($requiredBytes, 1),
            'is_in_use' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', false)
            ->assertJsonPath('available_bytes', $availableBytes)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('required_bytes_basis', 'decoded_ciphertext')
            ->assertJsonPath('valid_carriers', 2);
    }

    #[Test]
    public function preflight_works_with_wav_carriers(): void
    {
        // Skip if Python not available for capacity calculation
        $pythonPath = config('stegolock.python_path', 'python');
        if (!shell_exec("which {$pythonPath} 2>/dev/null") && !shell_exec("where {$pythonPath} 2>/dev/null")) {
            $this->markTestSkipped("Python interpreter not found");
        }

        $documentBytes = 2000000;
        $document = $this->createDocumentWithBytes($documentBytes);
        $requiredBytes = $this->expectedDecodedCiphertextBytes($documentBytes);

        // Create a valid WAV carrier with known capacity
        // We'll create a real WAV file and use the service to compute capacity
        $wavData = $this->createValidPcmWav(44100, 1, 16); // ~5236 bytes capacity
        $wavPath = sys_get_temp_dir() . '/preflight.wav';
        file_put_contents($wavPath, $wavData);

        // Use StegoService to compute actual capacity (with safety factor)
        $stego = new \App\Services\Stego\StegoService();
        $capacity = $stego->capacity($wavPath);
        $this->assertGreaterThan(0, $capacity, 'WAV capacity must be positive for preflight test');

        // Create carrier record with that capacity
        $carrier = StegoCarrier::create([
            'name' => 'preflight-wav',
            'file_path' => $wavPath,
            'file_type' => 'wav',
            'mime_type' => 'audio/wav',
            'size' => filesize($wavPath),
            'uploaded_by' => $this->user->id,
            'validation_status' => 'valid',
            'capacity_bytes' => $capacity,
            'is_in_use' => false,
            'validated_at' => now(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/preflight', [
                'document_id' => $document->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('can_encode', $capacity >= $requiredBytes)
            ->assertJsonPath('available_bytes', $capacity)
            ->assertJsonPath('required_bytes', $requiredBytes)
            ->assertJsonPath('valid_carriers', 1);

        // Cleanup
        @unlink($wavPath);
    }

    // -------------------------------------------------------------------------
    // Quota enforcement
    // -------------------------------------------------------------------------

    #[Test]
    public function cannot_exceed_max_carriers_per_user(): void
    {
        // Create max carriers (50)
        StegoCarrier::factory()->count(50)->create([
            'uploaded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->image('test-carrier.png', 800, 600),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Pool limit reached (50 carriers). Remove unused carriers first.');
    }

    #[Test]
    public function cannot_exceed_max_total_size(): void
    {
        // Create carriers totaling 500MB
        StegoCarrier::factory()->count(5)->create([
            'uploaded_by' => $this->user->id,
            'size' => 100 * 1024 * 1024, // 100MB each
        ]);

        // Try to upload another 100MB carrier
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fake()->create('large.png', 102400), // 100MB
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Pool storage limit of 500MB would be exceeded.');
    }

    // -------------------------------------------------------------------------
    // Audio (WAV) carrier upload
    // -------------------------------------------------------------------------

    #[Test]
    public function can_upload_wav_carrier_to_pool(): void
    {
        Queue::fake();
        Storage::fake('local');

        // Create a minimal valid PCM WAV file (mono, 16-bit, 1 second)
        $wavData = $this->createValidPcmWav(44100, 1, 16);
        $wavPath = sys_get_temp_dir() . '/test-upload.wav';
        file_put_contents($wavPath, $wavData);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fromPath($wavPath, 'test.wav', 'audio/wav'),
                'name' => 'Test WAV Carrier',
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'message',
                'carrier_id',
                'validation_status',
            ])
            ->assertJsonPath('validation_status', 'pending');

        // Verify carrier record
        $this->assertDatabaseHas('stego_carriers', [
            'name' => 'Test WAV Carrier',
            'uploaded_by' => $this->user->id,
            'mime_type' => 'audio/wav',
            'file_type' => 'wav',
            'validation_status' => 'pending',
        ]);

        // Verify validation job dispatched
        Queue::assertPushed(ValidateCarrierJob::class, function ($job) {
            return $job->carrierId === StegoCarrier::first()->id;
        });

        unlink($wavPath);
    }

    #[Test]
    public function wav_upload_rejects_non_pcm(): void
    {
        // Skip if Python not available for WAV validation
        $pythonPath = config('stegolock.python_path', 'python');
        if (!shell_exec("which {$pythonPath} 2>/dev/null") && !shell_exec("where {$pythonPath} 2>/dev/null")) {
            $this->markTestSkipped("Python interpreter not found");
        }

        Storage::fake('local');

        // Create a WAV with non-PCM compression (comptype != 'NONE')
        $wavData = $this->createCompressedWavHeader();
        $wavPath = sys_get_temp_dir() . '/compressed.wav';
        file_put_contents($wavPath, $wavData);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fromPath($wavPath, 'compressed.wav', 'audio/wav'),
            ]);

        // Upload accepted (MIME ok), but validation should fail
        $response->assertStatus(202);

        $carrier = StegoCarrier::first();
        $this->assertNotNull($carrier);

        $job = new ValidateCarrierJob($carrier->id);
        $job->handle(app(\App\Services\Stego\StegoService::class));

        $carrier->refresh();
        $this->assertEquals('invalid', $carrier->validation_status);
        $this->assertStringContainsString('Compressed WAV not supported', $carrier->validation_error);

        unlink($wavPath);
    }

    #[Test]
    public function wav_upload_validates_size_limit(): void
    {
        Storage::fake('local');

        // Create a WAV file larger than 200MB limit
        $largeSize = 200 * 1024 * 1024 + 1; // 200MB + 1 byte
        $wavPath = sys_get_temp_dir() . '/large.wav';
        // Write minimal header + padding
        file_put_contents($wavPath, str_repeat("\x00", $largeSize));

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fromPath($wavPath, 'large.wav', 'audio/wav'),
            ]);

        $response->assertStatus(422); // validation fails at upload time

        unlink($wavPath);
    }

    // -------------------------------------------------------------------------
    // Text (TXT) carrier upload
    // -------------------------------------------------------------------------

    #[Test]
    public function can_upload_txt_carrier_to_pool(): void
    {
        Queue::fake();
        Storage::fake('local');

        $txtPath = sys_get_temp_dir() . '/test-upload.txt';
        file_put_contents($txtPath, "This is a plain text carrier file for steganography.\n");

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fromPath($txtPath, 'test.txt', 'text/plain'),
                'name' => 'Test TXT Carrier',
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('validation_status', 'pending');

        $this->assertDatabaseHas('stego_carriers', [
            'name' => 'Test TXT Carrier',
            'uploaded_by' => $this->user->id,
            'mime_type' => 'text/plain',
            'file_type' => 'txt',
            'validation_status' => 'pending',
        ]);

        Queue::assertPushed(ValidateCarrierJob::class);
        unlink($txtPath);
    }

    #[Test]
    public function txt_upload_rejects_non_plain_text(): void
    {
        Storage::fake('local');

        // Upload a file with .txt extension but non-plain-text MIME (simulate misdetection)
        // In Laravel's UploadedFile, we can't easily spoof MIME; instead we test the validation rule
        // by uploading a binary file with .txt extension - the MIME will be detected as application/octet-stream
        $binaryPath = sys_get_temp_dir() . '/binary.txt';
        file_put_contents($binaryPath, "\x00\x01\x02\x03\x04"); // binary content

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fromPath($binaryPath, 'binary.txt', 'application/octet-stream'),
            ]);

        // MIME not in allowed list → upload rejected
        $response->assertStatus(422);

        unlink($binaryPath);
    }

    #[Test]
    public function txt_upload_validates_size_limit(): void
    {
        Storage::fake('local');

        // Create a TXT file larger than 10MB limit
        $largeSize = 10 * 1024 * 1024 + 1; // 10MB + 1 byte
        $txtPath = sys_get_temp_dir() . '/large.txt';
        file_put_contents($txtPath, str_repeat('A', $largeSize));

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/stego/carriers', [
                'carrier' => UploadedFile::fromPath($txtPath, 'large.txt', 'text/plain'),
            ]);

        $response->assertStatus(422);
        unlink($txtPath);
    }

    // -------------------------------------------------------------------------
    // Helper: create valid PCM WAV file in memory
    // -------------------------------------------------------------------------

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

    private function createCompressedWavHeader(): string
    {
        // Build a WAV header with compression type = 1 (not PCM)
        $sampleRate = 44100;
        $channels = 1;
        $bitsPerSample = 16;
        $byteRate = $sampleRate * $channels * $bitsPerSample / 8;
        $blockAlign = $channels * $bitsPerSample / 8;
        $dataSize = $sampleRate * $blockAlign;
        $fileSize = 44 + $dataSize;

        $header = pack('N4', 0x46464952, $fileSize, 0x45564157, 0x20746d66);
        // Format: wFormatTag = 1 (PCM) normally; we'll keep PCM but this test is about non-PCM detection
        // Actually to test non-PCM we need a different wFormatTag (e.g., 6 for ALAW, 7 for MULAW)
        // Let's use format tag 7 (μ-law) which is compressed
        $fmt = pack('N2n2N2', 16, 7, $channels, $sampleRate, $byteRate, $blockAlign, $bitsPerSample);
        $dataHeader = pack('N2', 0x61746164, $dataSize);
        $samples = str_repeat("\x00", $dataSize);

        return $header . $fmt . $dataHeader . $samples;
    }
}
