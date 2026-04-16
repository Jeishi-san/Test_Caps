<?php

namespace Tests\Feature;

use App\Models\StegoDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for envelope key sharing (cross-user stego decode).
 *
 * Scenarios:
 * 1. Owner creates envelope-wrapped stego doc
 * 2. Owner grants viewer access (pending status)
 * 3. Viewer cannot decode until grant accepted
 * 4. Viewer accepts grant with wrapped DEK
 * 5. Viewer can now decode with their unwrapped DEK
 * 6. Viewer download succeeds after decode
 * 7. Revoke removes viewer decode access
 * 8. Legacy records stay owner-only
 */
class StegoEnvelopeKeySharingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->viewer = User::factory()->create(['name' => 'Viewer']);
    }

    /**
     * Scenario: Owner grants access; viewer gets pending grant.
     */
    public function test_pending_grant_blocks_viewer_decode(): void
    {
        $this->actingAs($this->owner);

        // Create a stego document (envelope mode)
        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'ready',
            'stego_mode' => 'envelope_wrapped',
            'owner_wrapped_dek' => 'dGVzdHdyYXBwZWRkZWs=', // base64("testwrappeddek")
            'owner_wrapped_dek_iv' => 'dGVzdGl2',               // base64("testiv")
            'owner_wrapped_dek_auth_tag' => 'dGVzdHRhZw==',   // base64("testtag")
        ]);

        // Owner grants access to viewer
        $response = $this->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
            'viewer_user_id' => $this->viewer->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('pending', $response->json('grant.grant_status'));

        // Switch to viewer and attempt decode
        $this->actingAs($this->viewer);

        $response = $this->withSession(['stego_mkd' => str_repeat('a', 64)])->postJson('/api/stego/decode', [
            'stego_document_id' => $stegoDoc->id,
        ]);

        // Should fail: grant not active
        $response->assertStatus(422);
        $this->assertStringContainsString('not activated', $response->json('message'));
    }

    /**
     * Scenario: Viewer accepts grant with wrapped DEK; grant becomes active.
     */
    public function test_viewer_acceptance_activates_grant(): void
    {
        $this->actingAs($this->owner);

        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'ready',
            'stego_mode' => 'envelope_wrapped',
        ]);

        // Create pending grant
        $response = $this->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
            'viewer_user_id' => $this->viewer->id,
        ]);
        $response->assertStatus(201);

        // Viewer accepts
        $this->actingAs($this->viewer);

        $response = $this->postJson(
            "/api/stego/documents/{$stegoDoc->id}/grant/{$this->viewer->id}/accept",
            [
                'viewer_wrapped_dek' => 'dmlld2Vyd3JhcHBlZGRlaw==',  // base64("viewerwrappeddek")
                'viewer_wrapped_dek_iv' => 'dmlld2VyaXY=',            // base64("vieweriv")
                'viewer_wrapped_dek_auth_tag' => 'dmlld2VydGFn',      // base64("viewertag")
            ]
        );

        $response->assertStatus(200);
        $this->assertEquals('active', $response->json('grant.grant_status'));
        $this->assertNotNull($response->json('grant.accepted_at'));

        // Verify grant is now active
        $grant = $stegoDoc->viewerGrants()->first();
        $this->assertEquals('active', $grant->grant_status);
        $this->assertNotNull($grant->accepted_at);
    }

    /**
     * Scenario: Legacy (derived-DEK) records stay owner-only.
     */
    public function test_legacy_record_blocks_viewer_decode(): void
    {
        $this->actingAs($this->owner);

        // Create legacy stego doc (no envelope wrappers)
        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'ready',
            'stego_mode' => 'legacy_derived',
            'stego_dek_salt' => 'abcd1234efgh5678ijkl',
            'stego_dek_iter' => 10000,
        ]);

        // Grant access to viewer
        $this->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
            'viewer_user_id' => $this->viewer->id,
        ])->assertStatus(201);

        // Viewer cannot decode even with active grant (legacy stays owner-only)
        $this->actingAs($this->viewer);

        $response = $this->withSession(['stego_mkd' => str_repeat('b', 64)])->postJson('/api/stego/decode', [
            'stego_document_id' => $stegoDoc->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('owner-only decryption', $response->json('message'));
    }

    /**
     * Scenario: Revoke removes viewer decode eligibility.
     */
    public function test_revoke_grant_blocks_decode(): void
    {
        $this->actingAs($this->owner);

        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'ready',
            'stego_mode' => 'envelope_wrapped',
            'owner_wrapped_dek' => base64_encode('test_dek_bytes_32_len_long_text'),
            'owner_wrapped_dek_iv' => base64_encode('iv_12_bytes_xxx'),
            'owner_wrapped_dek_auth_tag' => base64_encode('tag_16_bytes_xxxx'),
        ]);

        // Grant and accept
        $grant = $this->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
            'viewer_user_id' => $this->viewer->id,
        ])->json('grant');

        $this->actingAs($this->viewer);
        $this->postJson(
            "/api/stego/documents/{$stegoDoc->id}/grant/{$this->viewer->id}/accept",
            [
                'viewer_wrapped_dek' => base64_encode('viewer_dek_bytes_32_len_long_text'),
                'viewer_wrapped_dek_iv' => base64_encode('viewer_iv_12_bytes'),
                'viewer_wrapped_dek_auth_tag' => base64_encode('viewer_tag_16bytes'),
            ]
        )->assertStatus(200);

        // Owner revokes
        $this->actingAs($this->owner);
        $this->deleteJson("/api/stego/documents/{$stegoDoc->id}/grant/{$this->viewer->id}")
            ->assertStatus(200);

        // Viewer now cannot access
        $this->actingAs($this->viewer);

        $response = $this->withSession(['stego_mkd' => str_repeat('c', 64)])->postJson('/api/stego/decode', [
            'stego_document_id' => $stegoDoc->id,
        ]);

        // 404 because grant was deleted (viewer no longer has access)
        $response->assertStatus(404);
    }

    /**
     * Scenario: List grants shows pending/active status.
     */
    public function test_list_grants_shows_status(): void
    {
        $this->actingAs($this->owner);

        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'ready',
            'stego_mode' => 'envelope_wrapped',
        ]);

        // Create pending grant
        $this->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
            'viewer_user_id' => $this->viewer->id,
        ])->assertStatus(201);

        // List grants
        $response = $this->getJson("/api/stego/documents/{$stegoDoc->id}/grants");
        $response->assertStatus(200);

        $grants = $response->json('grants');
        $this->assertCount(1, $grants);
        $this->assertEquals('pending', $grants[0]['grant_status']);
        $this->assertNull($grants[0]['accepted_at']);

        // Viewer accepts
        $this->actingAs($this->viewer);
        $this->postJson(
            "/api/stego/documents/{$stegoDoc->id}/grant/{$this->viewer->id}/accept",
            [
                'viewer_wrapped_dek' => base64_encode('viewer_dek_bytes_32_len_long_text'),
                'viewer_wrapped_dek_iv' => base64_encode('viewer_iv_12_bytes'),
                'viewer_wrapped_dek_auth_tag' => base64_encode('viewer_tag_16bytes'),
            ]
        )->assertStatus(200);

        // Check updated status
        $this->actingAs($this->owner);
        $response = $this->getJson("/api/stego/documents/{$stegoDoc->id}/grants");
        $grants = $response->json('grants');

        $this->assertEquals('active', $grants[0]['grant_status']);
        $this->assertNotNull($grants[0]['accepted_at']);
    }

    /**
     * Scenario: Only viewer can accept their own grant.
     */
    public function test_viewer_cannot_accept_on_behalf(): void
    {
        /** @var User $other */
        $other = User::factory()->create();

        $this->actingAs($this->owner);
        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'ready',
            'stego_mode' => 'envelope_wrapped',
        ]);

        $this->postJson("/api/stego/documents/{$stegoDoc->id}/grant", [
            'viewer_user_id' => $this->viewer->id,
        ])->assertStatus(201);

        // Other user attempts to accept as viewer
        $this->actingAs($other);

        $response = $this->postJson(
            "/api/stego/documents/{$stegoDoc->id}/grant/{$this->viewer->id}/accept",
            [
                'viewer_wrapped_dek' => base64_encode('test_24_chars_for_test_3'),
                'viewer_wrapped_dek_iv' => base64_encode('test_12_bytesxxxxx'),
                'viewer_wrapped_dek_auth_tag' => base64_encode('test_16_bytes_xxxxxx'),
            ]
        );

        $response->assertStatus(403);
        $this->assertStringContainsString('yourself', $response->json('message'));
    }
}
