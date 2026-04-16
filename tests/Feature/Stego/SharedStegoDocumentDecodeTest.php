<?php

namespace Tests\Feature\Stego;

use App\Models\StegoDocument;
use App\Models\StegoDocumentGrant;
use App\Models\User;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedStegoDocumentDecodeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function decode_page_includes_documents_shared_with_current_user()
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        // Create a document that will be shared
        $document = Document::factory()->create(['owner_id' => $owner->id]);
        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $owner->id,
            'document_id' => $document->id,
            'status' => 'ready',
        ]);

        // Grant access to viewer user
        StegoDocumentGrant::create([
            'stego_document_id' => $stegoDoc->id,
            'viewer_user_id' => $viewer->id,
            'granted_by' => $owner->id,
        ]);

        // Act as the viewing user
        $this->actingAs($viewer)
            ->get(route('stego.decode'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('stegoDocs', 1)
                ->where('stegoDocs.0.id', $stegoDoc->id)
                ->where('stegoDocs.0.is_owner', false)
                ->where('stegoDocs.0.document.id', $document->id)
            );
    }

    /** @test */
    public function decode_page_shows_both_owned_and_shared_documents()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Owned document
        $myDocument = Document::factory()->create(['owner_id' => $user->id]);
        $myStegoDoc = StegoDocument::factory()->create([
            'user_id' => $user->id,
            'document_id' => $myDocument->id,
            'status' => 'ready',
        ]);

        // Shared document from another user
        $sharedDocument = Document::factory()->create(['owner_id' => $otherUser->id]);
        $sharedStegoDoc = StegoDocument::factory()->create([
            'user_id' => $otherUser->id,
            'document_id' => $sharedDocument->id,
            'status' => 'ready',
        ]);

        StegoDocumentGrant::create([
            'stego_document_id' => $sharedStegoDoc->id,
            'viewer_user_id' => $user->id,
            'granted_by' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->get(route('stego.decode'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->has('stegoDocs', 2)
                ->where('stegoDocs.0.id', $myStegoDoc->id)
                ->where('stegoDocs.0.is_owner', true)
                ->where('stegoDocs.1.id', $sharedStegoDoc->id)
                ->where('stegoDocs.1.is_owner', false)
            );
    }

    /** @test */
    public function granted_users_are_blocked_from_web_decode_without_owner_key_context()
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $document = Document::factory()->create(['owner_id' => $owner->id]);
        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $owner->id,
            'document_id' => $document->id,
            'status' => 'ready',
        ]);

        StegoDocumentGrant::create([
            'stego_document_id' => $stegoDoc->id,
            'viewer_user_id' => $viewer->id,
            'granted_by' => $owner->id,
        ]);

        $this->actingAs($viewer)
            ->withSession(['stego_mkd' => str_repeat('a', 64)])
            ->post(route('stego.decode'), [
                'stego_document_id' => $stegoDoc->id,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('decode');

        // Verify decode state was not changed by blocked viewer attempt.
        $stegoDoc->refresh();
        $this->assertEquals('idle', $stegoDoc->decoding_status);
    }

    /** @test */
    public function users_cannot_decode_documents_they_have_not_been_granted_access_to()
    {
        $owner = User::factory()->create();
        $randomUser = User::factory()->create();

        $document = Document::factory()->create(['owner_id' => $owner->id]);
        $stegoDoc = StegoDocument::factory()->create([
            'user_id' => $owner->id,
            'document_id' => $document->id,
            'status' => 'ready',
        ]);

        $this->actingAs($randomUser)
            ->withSession(['stego_mkd' => str_repeat('b', 64)])
            ->post(route('stego.decode'), [
                'stego_document_id' => $stegoDoc->id,
            ])
            ->assertStatus(403);
    }

    /** @test */
    public function non_ready_documents_are_not_shown_in_decode_list()
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        foreach (['pending', 'failed'] as $status) {
            $document = Document::factory()->create(['owner_id' => $owner->id]);
            $stegoDoc = StegoDocument::factory()->create([
                'user_id' => $owner->id,
                'document_id' => $document->id,
                'status' => $status,
            ]);

            StegoDocumentGrant::create([
                'stego_document_id' => $stegoDoc->id,
                'viewer_user_id' => $viewer->id,
                'granted_by' => $owner->id,
            ]);
        }

        $this->actingAs($viewer)
            ->get(route('stego.decode'))
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->has('stegoDocs', 0));
    }
}
