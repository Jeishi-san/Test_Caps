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
    public function it_satisfies_min_diverse_types_1_with_largest_carrier(): void
    {
        // Default: min_diverse_types=1, min_carriers=1
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 1,
            'minimum_carriers' => 1,
        ]]);

        $user = User::factory()->create();
        // User has image (150k) and audio (100k)
        $this->createCarrierForUser($user->id, 'image', 'image/png', 150000);
        $this->createCarrierForUser($user->id, 'audio', 'audio/wav', 100000);

        $selected = $this->selector->select($user->id, 100000, true);

        // Should pick the single largest carrier that satisfies capacity
        $this->assertCount(1, $selected);
        $this->assertEquals('image/png', $selected->first()->mime_type);
        $this->assertEquals(150000, $selected->first()->capacity_bytes);
    }

    /** @test */
    public function it_requires_two_different_types_when_min_diverse_types_is_two(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 2,
            'minimum_carriers' => 2,
        ]]);

        $user = User::factory()->create();
        // User has two images and one audio
        $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);
        $this->createCarrierForUser($user->id, 'image', 'image/jpeg', 80000);
        $audio = $this->createCarrierForUser($user->id, 'audio', 'audio/wav', 120000);

        $selected = $this->selector->select($user->id, 150000, true);

        // Must include at least 2 types: image + audio
        $this->assertTrue($selected->contains('mime_type', 'audio/wav'));
        $this->assertTrue($selected->contains('mime_type', 'image/png') || $selected->contains('mime_type', 'image/jpeg'));
        // At least 2 carriers
        $this->assertGreaterThanOrEqual(2, $selected->count());
        // Audio carrier should be included
        $this->assertTrue($selected->contains('id', $audio->id));
    }

    /** @test */
    public function it_fails_when_diverse_types_requirement_cannot_be_met(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 2,
            'minimum_carriers' => 1,
        ]]);

        $user = User::factory()->create();
        // User has only images
        $this->createCarrierForUser($user->id, 'image', 'image/png', 200000);
        $this->createCarrierForUser($user->id, 'image', 'image/jpeg', 150000);

        // System also only has images
        $systemUser = User::factory()->create(['role' => 'admin']);
        $this->createCarrierForUser($systemUser->id, 'image', 'image/png', 300000);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mandate requires at least 2 different carrier types');

        $this->selector->select($user->id, 100000, true);
    }

    /** @test */
    public function it_uses_system_carrier_to_satisfy_diverse_types_requirement(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 2,
            'minimum_carriers' => 2,
        ]]);

        $user = User::factory()->create();
        // User has only images
        $this->createCarrierForUser($user->id, 'image', 'image/png', 200000);

        // System has audio
        $systemUser = User::factory()->create(['role' => 'admin']);
        $systemAudio = $this->createCarrierForUser($systemUser->id, 'audio', 'audio/wav', 150000, 'system.wav');

        $selected = $this->selector->select($user->id, 100000, true);

        // Should include user's image and system's audio
        $this->assertTrue($selected->contains('uploaded_by', $user->id));
        $this->assertTrue($selected->contains('id', $systemAudio->id));
        $this->assertTrue($selected->contains('mime_type', 'audio/wav'));
    }

    /** @test */
    public function it_requires_two_carriers_even_when_one_large_enough(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 1,
            'minimum_carriers' => 2,
        ]]);

        $user = User::factory()->create();
        // User has one huge image and one smaller image
        $this->createCarrierForUser($user->id, 'image', 'image/png', 500000);
        $this->createCarrierForUser($user->id, 'image', 'image/jpeg', 100000);

        $selected = $this->selector->select($user->id, 100000, true);

        // Despite one carrier being enough, must have at least 2 carriers
        $this->assertCount(2, $selected);
    }

    /** @test */
    public function it_requires_three_carriers_when_min_carriers_is_three(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 1,
            'minimum_carriers' => 3,
        ]]);

        $user = User::factory()->create();
        // Create three images
        $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);
        $this->createCarrierForUser($user->id, 'image', 'image/jpeg', 80000);
        $this->createCarrierForUser($user->id, 'image', 'image/bmp', 120000);

        $selected = $this->selector->select($user->id, 50000, true);

        // Must use at least 3 carriers even though 1 would suffice
        $this->assertCount(3, $selected);
    }

    /** @test */
    public function it_fails_when_not_enough_carriers_available_for_min_carriers_requirement(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 1,
            'minimum_carriers' => 3,
        ]]);

        $user = User::factory()->create();
        // User has only 2 carriers
        $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);
        $this->createCarrierForUser($user->id, 'audio', 'audio/wav', 80000);

        // Create an admin user (system) but with no carriers
        User::factory()->create(['role' => 'admin']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mandate requires at least 3 total carriers');

        $this->selector->select($user->id, 50000, true);
    }

    /** @test */
    public function it_combines_diverse_types_and_min_carriers_constraints(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 2,
            'minimum_carriers' => 3,
        ]]);

        $user = User::factory()->create();
        // User has 2 images and 1 audio
        $img1 = $this->createCarrierForUser($user->id, 'image', 'image/png', 100000);
        $img2 = $this->createCarrierForUser($user->id, 'image', 'image/jpeg', 80000);
        $aud = $this->createCarrierForUser($user->id, 'audio', 'audio/wav', 120000);

        $selected = $this->selector->select($user->id, 50000, true);

        // Must have at least 2 types (image + audio) AND at least 3 total carriers
        $this->assertGreaterThanOrEqual(3, $selected->count());
        $this->assertTrue($selected->contains('mime_type', 'audio/wav'));
        $this->assertTrue($selected->contains('mime_type', 'image/png') || $selected->contains('mime_type', 'image/jpeg'));
        // Should include both images and audio (since need 3 carriers)
        $this->assertTrue($selected->contains('id', $img1->id) || $selected->contains('id', $img2->id));
        $this->assertTrue($selected->contains('id', $aud->id));
    }

    /** @test */
    public function it_selects_largest_carriers_first_for_diverse_types(): void
    {
        config(['stegolock.carrier_mandates' => [
            'enabled' => true,
            'minimum_diverse_types' => 2,
            'minimum_carriers' => 2,
        ]]);

        $user = User::factory()->create();
        // User has multiple types with different capacities
        // Small image, large audio, medium text
        $smallImg = $this->createCarrierForUser($user->id, 'image', 'image/png', 50000);
        $largeAud = $this->createCarrierForUser($user->id, 'audio', 'audio/wav', 200000);
        $mediumTxt = $this->createCarrierForUser($user->id, 'text', 'text/plain', 100000);

        $selected = $this->selector->select($user->id, 80000, true);

        // Should pick the 2 largest carriers among different types to satisfy min_diverse_types=2
        // Largest: audio (200k), then text (100k) — both > needed
        $this->assertCount(2, $selected);
        $this->assertTrue($selected->contains('id', $largeAud->id));
        $this->assertTrue($selected->contains('id', $mediumTxt->id));
        // The small image should not be selected
        $this->assertFalse($selected->contains('id', $smallImg->id));
    }

    /** @test */
    public function it_preserves_backward_compatibility_with_defaults(): void
    {
        // Default config: min_diverse_types=1, min_carriers=1, enabled=true
        config(['stegolock.carrier_mandates.enabled' => true]);

        $user = User::factory()->create();
        $this->createCarrierForUser($user->id, 'image', 'image/png', 200000);
        $this->createCarrierForUser($user->id, 'image', 'image/jpeg', 150000);

        $selected = $this->selector->select($user->id, 120000, true);

        // Should behave like greedy: pick largest single carrier that fits
        $this->assertCount(1, $selected);
        $this->assertEquals(200000, $selected->first()->capacity_bytes);
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
