<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use App\Models\ShareDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShareDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_create_share()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create();

        $this->actingAs($user)
            ->postJson(route('shares.create'), [
                'shared_id' => $document->id,
                'slug' => 'document',
                'name' => 'Shared Document',
                'permission_level' => 'viewer',
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'share' => [
                    'id', 'token', 'slug', 'shared_id', 'permission_level'
                ]
            ]);

        $this->assertCount(1, ShareDocument::all());
        $this->assertEquals($user->id, ShareDocument::first()->user_id);
    }

    /** @test */
    public function creating_share_generates_secure_token_automatically()
    {
        $user = User::factory()->create();
        $document = Document::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('shares.create'), [
                'shared_id' => $document->id,
                'slug' => 'document',
                'name' => 'Shared Document',
            ]);

        $share = ShareDocument::first();
        $this->assertNotNull($share->token);
        $this->assertEquals(40, strlen($share->token));
    }

    /** @test */
    public function guest_cannot_create_share()
    {
        $document = Document::factory()->create();

        $this->postJson(route('shares.create'), [
            'shared_id' => $document->id,
            'slug' => 'document',
            'name' => 'Shared Document',
        ])
        ->assertStatus(401);
    }

    /** @test */
    public function owner_can_update_share_permissions()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->viewer()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson(route('shares.update', $share->id), [
                'permission_level' => 'editor'
            ])
            ->assertStatus(200);

        $this->assertEquals('editor', $share->fresh()->permission_level);
        $this->assertTrue($share->fresh()->can_edit);
    }

    /** @test */
    public function non_owner_cannot_update_share_permissions()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->putJson(route('shares.update', $share->id), [
                'permission_level' => 'editor'
            ])
            ->assertStatus(403);

        $this->assertNotEquals('editor', $share->fresh()->permission_level);
    }

    /** @test */
    public function owner_can_revoke_share()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('shares.revoke', $share->id))
            ->assertStatus(200);

        $this->assertCount(0, ShareDocument::all());
    }

    /** @test */
    public function non_owner_cannot_revoke_share()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->deleteJson(route('shares.revoke', $share->id))
            ->assertStatus(403);

        $this->assertCount(1, ShareDocument::all());
    }

    /** @test */
    public function user_can_list_their_own_shares()
    {
        $user = User::factory()->create();
        ShareDocument::factory()->count(3)->create(['user_id' => $user->id]);
        ShareDocument::factory()->count(2)->create();

        $response = $this->actingAs($user)
            ->getJson(route('shares.list'))
            ->assertStatus(200);

        $this->assertCount(3, $response->json('shared_documents'));
    }

    /** @test */
    public function public_share_is_accessible_without_authentication()
    {
        $share = ShareDocument::factory()->public()->create();

        $this->get(route('shares.view', [
            'slug' => $share->slug,
            'sharedid' => $share->shared_id,
            'token' => $share->token
        ]))
        ->assertStatus(200);
    }

    /** @test */
    public function invalid_token_returns_404()
    {
        $share = ShareDocument::factory()->create();

        $this->get(route('shares.view', [
            'slug' => $share->slug,
            'sharedid' => $share->shared_id,
            'token' => 'invalid-token'
        ]))
        ->assertStatus(404);
    }

    /** @test */
    public function expired_share_returns_not_found()
    {
        $share = ShareDocument::factory()->expired()->create();

        $this->get(route('shares.view', [
            'slug' => $share->slug,
            'sharedid' => $share->shared_id,
            'token' => $share->token
        ]))
        ->assertStatus(404);
    }

    /** @test */
    public function folder_share_loads_folder_relationships()
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->has(Document::factory()->count(2))->create();
        
        $share = ShareDocument::factory()->create([
            'shared_id' => $folder->id,
            'slug' => 'folder',
            'user_id' => $user->id
        ]);

        $response = $this->get(route('shares.view', [
            'slug' => 'folder',
            'sharedid' => $folder->id,
            'token' => $share->token
        ]))
        ->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Shares/Folder')
            ->has('folder')
            ->has('folder.documents', 2)
        );
    }

    /** @test */
    public function share_permissions_endpoint_returns_correct_data()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->editor()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson(route('shares.permissions', $share->id))
            ->assertStatus(200)
            ->assertJsonStructure([
                'permissions',
                'permission_levels'
            ])
            ->assertJsonCount(5, 'permission_levels');
    }

    /** @test */
    public function creating_share_with_email_sends_notification()
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        $document = Document::factory()->create();

        $this->actingAs($user)
            ->postJson(route('shares.create'), [
                'shared_id' => $document->id,
                'slug' => 'document',
                'name' => 'Shared Document',
                'email' => $recipient->email,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'type' => 'share'
        ]);
    }
}
