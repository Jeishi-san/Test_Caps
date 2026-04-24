<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Stego\CarrierPoolSelector;
use App\Models\StegoCarrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CarrierPoolSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected CarrierPoolSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new CarrierPoolSelector();
    }

    /** @test */
    public function it_selects_carriers_greedily_when_mandates_disabled(): void
    {
        config(['stegolock.carrier_mandates.enabled' => false]);

        $user = User::factory()->create();
        $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);
        $this->createCarrierForUser($user->id, 'image', 'image/png', 200000);

        $selected = $this->selector->select($user->id, 150000, true);

        $this->assertCount(1, $selected); // greedy picks largest that fits
        $this->assertEquals(200000, $selected->first()->capacity_bytes);
    }

    /** @test */
    public function it_fulfills_image_mandate_from_user_pool(): void
    {
        config(['stegolock.carrier_mandates.enabled' => true, 'require_image' => true, 'require_audio' => false, 'require_text' => false]);

        $user = User::factory()->create();
        // User has one image carrier
        $this->createCarrierForUser($user->id, 'image', 'image/png', 150000);
        // Also has an audio carrier but not required
        $this->createCarrierForUser($user->id, 'audio', 'audio/wav', 100000);

        $selected = $this->selector->select($user->id, 100000, true);

        $this->assertTrue($selected->contains('mime_type', 'image/png'));
        // Should not need system carriers
        $this->assertTrue($selected->all(fn($c) => $c->uploaded_by == $user->id));
    }

    /** @test */
    public function it_falls_back_to_system_pool_when_user_lacks_audio_mandate(): void
    {
        config(['stegolock.carrier_mandates.enabled' => true, 'require_image' => false, 'require_audio' => true, 'require_text' => false]);

        $user = User::factory()->create();
        // User has only image carriers
        $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);

        // System user has an audio carrier
        $systemUser = User::factory()->create(['role' => 'admin']);
        $this->createCarrierForUser($systemUser->id, 'audio', 'audio/wav', 200000, 'system.wav');

        $selected = $this->selector->select($user->id, 50000, true);

        $this->assertTrue($selected->contains('mime_type', 'audio/wav'));
        $this->assertTrue($selected->contains('uploaded_by', $systemUser->id));
    }

    /** @test */
    public function it_fails_hard_when_mandate_cannot_be_satisfied(): void
    {
        config(['stegolock.carrier_mandates.enabled' => true, 'require_image' => false, 'require_audio' => true, 'require_text' => false]);

        $user = User::factory()->create();
        // User has no audio
        $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);

        // No system audio either
        $systemUser = User::factory()->create(['role' => 'admin']);
        $this->createCarrierForUser($systemUser->id, 'image', 'image/png', 100000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot encode: no audio carrier available');

        $this->selector->select($user->id, 50000, true);
    }

    /** @test */
    public function it_uses_deterministic_tie_breaking(): void
    {
        config(['stegolock.carrier_mandates.enabled' => false]);

        $user = User::factory()->create();
        // Create two carriers with same capacity but different IDs
        $c1 = $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);
        $c2 = $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);

        // Ensure c1 has lower id than c2 (usually true due to auto-increment)
        $this->assertLessThan($c2->id, $c1->id);

        $selected = $this->selector->select($user->id, 50000, true);

        // With same capacity, lower ID should be chosen first
        $this->assertEquals($c1->id, $selected->first()->id);
    }

    /** @test */
    public function it_satisfies_multiple_mandates_and_greedy_fills(): void
    {
        config(['stegolock.carrier_mandates.enabled' => true, 'require_image' => true, 'require_audio' => true, 'require_text' => false]);

        $user = User::factory()->create();
        // User has one image and one audio
        $img = $this->createCarrierForUser($user->id, 'image', 'image/png', 80000);
        $aud = $this->createCarrierForUser($user->id, 'audio', 'audio/wav', 80000);

        // Need total 200000, mandates consume 160000, need 40000 more from greedy
        $selected = $this->selector->select($user->id, 200000, true);

        $this->assertCount(3, $selected); // image + audio + at least one more
        $this->assertTrue($selected->contains('id', $img->id));
        $this->assertTrue($selected->contains('id', $aud->id));
    }

    /**
     * Helper: create a StegoCarrier record for a user.
     *
     * @param int $userId
     * @param string $type 'image', 'audio', 'text'
     * @param string $mime
     * @param int $capacity
     * @param string|null $name
     * @return StegoCarrier
     */
    private function createCarrierForUser(int $userId, string $type, string $mime, int $capacity, ?string $name = null): StegoCarrier
    {
        $ext = match ($type) {
            'image' => 'png',
            'audio' => 'wav',
            'text'  => 'txt',
        };

        return StegoCarrier::create([
            'name' => $name ?? "test-{$type}-" . uniqid() . ".{$ext}",
            'file_path' => "stego/carriers/{$userId}/test.{$ext}",
            'file_type' => $ext,
            'mime_type' => $mime,
            'size' => $capacity + 1000, // size slightly larger than capacity
            'uploaded_by' => $userId,
            'validation_status' => 'valid',
            'capacity_bytes' => $capacity,
            'is_in_use' => false,
            'validated_at' => now(),
        ]);
    }
}
