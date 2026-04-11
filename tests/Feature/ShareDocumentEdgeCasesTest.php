<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Document;
use App\Models\ShareDocument;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShareDocumentEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function share_with_null_permission_level_defaults_to_viewer()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create();

        $this->actingAs($user)
            ->postJson(route('shares.create'), [
                'shared_id' => $document->id,
                'slug' => 'document',
                'name' => 'Test Share',
                'permission_level' => null
            ])
            ->assertStatus(200);

        $share = ShareDocument::first();
        $this->assertEquals(ShareDocument::PERMISSION_VIEWER, $share->getPermissionLevel());
        $this->assertTrue($share->can_download);
        $this->assertFalse($share->can_edit);
    }

    /** @test */
    public function share_without_expiry_never_expires()
    {
        $share = ShareDocument::factory()->create([
            'valid_until' => null
        ]);

        $this->assertFalse($share->hasExpired());

        Carbon::setTestNow(now()->addYears(10));
        $this->assertFalse($share->hasExpired());
        Carbon::setTestNow();
    }

    /** @test */
    public function expiring_share_stops_working_at_exact_expiry_time()
    {
        $expiryTime = now()->addMinutes(60);

        $share = ShareDocument::factory()->create([
            'valid_until' => $expiryTime
        ]);

        Carbon::setTestNow($expiryTime->subSecond());
        $this->assertFalse($share->hasExpired());

        Carbon::setTestNow($expiryTime);
        $this->assertTrue($share->hasExpired());

        Carbon::setTestNow($expiryTime->addSecond());
        $this->assertTrue($share->hasExpired());

        Carbon::setTestNow();
    }

    /** @test */
    public function multiple_shares_can_exist_for_same_resource()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create();

        // Create 5 different shares for the same document
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->postJson(route('shares.create'), [
                    'shared_id' => $document->id,
                    'slug' => 'document',
                    'name' => "Share #{$i}",
                ])
                ->assertStatus(200);
        }

        $this->assertCount(5, ShareDocument::all());
        $this->assertCount(5, ShareDocument::where('share_id', $document->id)->get());
        
        // All tokens should be unique
        $tokens = ShareDocument::pluck('token');
        $this->assertEquals(5, $tokens->unique()->count());
    }

    /** @test */
    public function revoked_share_cannot_be_accessed()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $user->id]);

        $this->get(route('shares.view', [
            'slug' => $share->slug,
            'sharedid' => $share->shared_id,
            'token' => $share->token
        ]))->assertStatus(200);

        $this->actingAs($user)->deleteJson(route('shares.revoke', $share->id));

        $this->get(route('shares.view', [
            'slug' => $share->slug,
            'sharedid' => $share->shared_id,
            'token' => $share->token
        ]))->assertStatus(404);
    }

    /** @test */
    public function updated_permissions_take_effect_immediately()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->viewer()->create(['user_id' => $user->id]);

        $this->assertFalse($share->can_edit);

        $this->actingAs($user)
            ->putJson(route('shares.update', $share->id), [
                'permission_level' => 'editor'
            ]);

        $freshShare = $share->fresh();
        $this->assertTrue($freshShare->can_edit);
        $this->assertTrue($freshShare->can_upload);
        $this->assertTrue($freshShare->can_comment);
        $this->assertFalse($freshShare->can_share);
    }

    /** @test */
    public function share_token_cannot_be_bruteforced()
    {
        $share = ShareDocument::factory()->create();

        // Attempt 100 random invalid tokens
        for ($i = 0; $i < 100; $i++) {
            $this->get(route('shares.view', [
                'slug' => $share->slug,
                'sharedid' => $share->shared_id,
                'token' => \Illuminate\Support\Str::random(40)
            ]))->assertStatus(404);
        }

        // Valid token should still work
        $this->get(route('shares.view', [
            'slug' => $share->slug,
            'sharedid' => $share->shared_id,
            'token' => $share->token
        ]))->assertStatus(200);
    }

    /** @test */
    public function non_existent_resource_id_returns_404()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user)
            ->postJson(route('shares.create'), [
                'shared_id' => 9999999, // Non-existent ID
                'slug' => 'document',
                'name' => 'Non-existent Share',
            ]);

        // Attempt to access invalid share
        $this->get(route('shares.view', [
            'slug' => 'document',
            'sharedid' => 9999999,
            'token' => 'any-token'
        ]))->assertStatus(404);
    }

    /** @test */
    public function admin_can_modify_any_users_share()
    {
        $admin = User::factory()->admin()->create();
        $regularUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $regularUser->id]);

        $this->actingAs($admin)
            ->putJson(route('shares.update', $share->id), [
                'permission_level' => 'owner'
            ])
            ->assertStatus(200);

        $this->assertEquals('owner', $share->fresh()->permission_level);

        $this->actingAs($admin)
            ->deleteJson(route('shares.revoke', $share->id))
            ->assertStatus(200);

        $this->assertCount(0, ShareDocument::all());
    }

    /** @test */
    public function share_slug_mismatch_returns_404()
    {
        $share = ShareDocument::factory()->create(['slug' => 'document']);

        // Wrong slug but correct token and id
        $this->get(route('shares.view', [
            'slug' => 'folder',
            'sharedid' => $share->shared_id,
            'token' => $share->token
        ]))->assertStatus(404);

        // Correct slug works
        $this->get(route('shares.view', [
            'slug' => 'document',
            'sharedid' => $share->shared_id,
            'token' => $share->token
        ]))->assertStatus(200);
    }
}
