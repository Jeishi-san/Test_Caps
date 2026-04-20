<?php

namespace Tests\Feature\Phase4;

use App\Models\Document;
use App\Models\Folder;
use App\Models\Notification;
use App\Models\ShareDocument;
use App\Models\User;
use App\Services\DocumentAccessService;
use App\Services\DocumentFileService;
use App\Services\DocumentNotificationService;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ServicesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_access_service_resolves_owner_shared_public_and_admin_access(): void
    {
        /** @var User $owner */
        $owner = User::factory()->create(['role' => 'user']);
        /** @var User $sharedUser */
        $sharedUser = User::factory()->create(['role' => 'user']);
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $folder = Folder::create([
            'name' => 'Root',
            'visibility' => 'private',
        ]);

        $privateOwned = Document::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => 'private',
            'folder_id' => $folder->id,
        ]);

        $publicDoc = Document::factory()->create([
            'owner_id' => $owner->id,
            'visibility' => 'public',
            'folder_id' => $folder->id,
        ]);

        ShareDocument::create([
            'name' => 'Shared document',
            'slug' => 'document',
            'token' => 'token-doc-' . uniqid(),
            'share_id' => (string)$privateOwned->id,
            'share_type' => Document::class,
            'user_id' => (string)$sharedUser->id,
            'user_type' => User::class,
            'permission_level' => ShareDocument::PERMISSION_VIEWER,
            'visibility' => 'private',
        ]);

        $accessService = app(DocumentAccessService::class);

        $this->assertTrue($accessService->canUserAccessDocument($owner, $privateOwned));
        $this->assertTrue($accessService->canUserAccessDocument($sharedUser, $privateOwned));
        $this->assertTrue($accessService->canUserAccessDocument($sharedUser, $publicDoc));
        $this->assertTrue($accessService->canUserAccessDocument($admin, $privateOwned));
    }

    public function test_document_notification_service_creates_and_returns_notifications(): void
    {
        /** @var User $sender */
        $sender = User::factory()->create(['role' => 'owner']);
        /** @var User $recipient */
        $recipient = User::factory()->create(['role' => 'user']);

        $folder = Folder::create([
            'name' => 'Notify Folder',
            'visibility' => 'public',
        ]);

        $document = Document::factory()->create([
            'owner_id' => $sender->id,
            'folder_id' => $folder->id,
            'visibility' => 'public',
        ]);

        $this->actingAs($sender);

        $service = app(DocumentNotificationService::class);

        $notifications = $service->sendDocumentEmail(
            $document->id,
            $recipient->email,
            'email_shared',
            'Phase 4',
            'Service integration',
            'Please review this document'
        );

        $this->assertGreaterThanOrEqual(1, $notifications->count());
        $this->assertDatabaseHas('notifications', [
            'model_type' => Document::class,
            'model_id' => (string)$document->id,
            'created_by_user_id' => $sender->id,
        ]);
    }

    public function test_document_service_delegates_email_workflow_end_to_end(): void
    {
        /** @var User $sender */
        $sender = User::factory()->create(['role' => 'owner']);
        /** @var User $recipient */
        $recipient = User::factory()->create(['role' => 'user']);

        $folder = Folder::create([
            'name' => 'Delegation Folder',
            'visibility' => 'public',
        ]);

        $document = Document::factory()->create([
            'owner_id' => $sender->id,
            'folder_id' => $folder->id,
            'visibility' => 'public',
        ]);

        $this->actingAs($sender);

        $request = new Request([
            'title' => 'Delegation test',
            'body' => 'Phase 4',
            'type' => 'email_shared',
            'document_id' => $document->id,
            'content' => 'DocumentService delegation',
            'user_email' => $recipient->email,
        ]);

        $service = app(DocumentService::class);
        $result = $service->setSendDocumentEmail($request);

        $this->assertGreaterThanOrEqual(1, $result->count());

        $latest = Notification::query()->latest('id')->first();
        $this->assertNotNull($latest);
        $this->assertSame((string)$document->id, $latest->model_id);
    }

    public function test_document_file_service_folder_info_reflects_document_totals(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'owner']);
        $this->actingAs($user);

        $folder = Folder::create([
            'name' => 'Metrics Folder',
            'visibility' => 'public',
        ]);

        Document::factory()->create([
            'owner_id' => $user->id,
            'folder_id' => $folder->id,
            'visibility' => 'public',
            'size' => 1024,
        ]);

        Document::factory()->create([
            'owner_id' => $user->id,
            'folder_id' => $folder->id,
            'visibility' => 'private',
            'size' => 2048,
        ]);

        $fileService = app(DocumentFileService::class);
        $info = $fileService->getFolderInfo($folder->id);

        $this->assertNotNull($info);
        $this->assertSame(2, $info['num_documents']['total']);
        $this->assertSame(1, $info['num_documents']['public']);
        $this->assertSame(1, $info['num_documents']['private']);
    }
}
