<?php

namespace Tests\Feature\Phase4;

use App\Models\Document;
use App\Models\Folder;
use App\Models\ShareDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueryOptimizationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accessible_by_scope_reduces_query_count_vs_legacy_pattern(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);

        $sharedFolder = Folder::create([
            'name' => 'Shared Folder',
            'visibility' => 'private',
        ]);

        $otherFolder = Folder::create([
            'name' => 'Other Folder',
            'visibility' => 'private',
        ]);

        $ownedDocs = Document::factory()->count(20)->create([
            'owner_id' => $user->id,
            'folder_id' => $otherFolder->id,
            'visibility' => 'private',
        ]);

        $sharedDocs = Document::factory()->count(20)->create([
            'owner_id' => User::factory()->create()->id,
            'folder_id' => $otherFolder->id,
            'visibility' => 'private',
        ]);

        $folderSharedDocs = Document::factory()->count(20)->create([
            'owner_id' => User::factory()->create()->id,
            'folder_id' => $sharedFolder->id,
            'visibility' => 'private',
        ]);

        foreach ($sharedDocs as $doc) {
            ShareDocument::create([
                'name' => 'Doc Share ' . $doc->id,
                'slug' => 'document',
                'token' => 'token-sd-' . $doc->id,
                'share_id' => (string)$doc->id,
                'share_type' => Document::class,
                'user_id' => (string)$user->id,
                'user_type' => User::class,
                'permission_level' => ShareDocument::PERMISSION_VIEWER,
                'visibility' => 'private',
            ]);
        }

        ShareDocument::create([
            'name' => 'Folder Share',
            'slug' => 'folder',
            'token' => 'token-folder-' . uniqid(),
            'share_id' => (string)$sharedFolder->id,
            'share_type' => Folder::class,
            'user_id' => (string)$user->id,
            'user_type' => User::class,
            'permission_level' => ShareDocument::PERMISSION_VIEWER,
            'visibility' => 'private',
        ]);

        $this->actingAs($user);

        // Legacy pattern: separate queries + merge IDs in app layer.
        DB::flushQueryLog();
        DB::enableQueryLog();

        $ownedIds = Document::query()->where('owner_id', $user->id)->pluck('id');
        $directSharedIds = DB::table('share_documents')
            ->where('user_id', (string)$user->id)
            ->pluck('share_id');
        $sharedFolderIds = DB::table('share_documents')
            ->where('user_id', (string)$user->id)
            ->where('slug', 'folder')
            ->pluck('share_id');

        $legacyResult = Document::query()
            ->whereIn('id', $ownedIds->merge($directSharedIds)->unique()->values())
            ->orWhereIn('folder_id', $sharedFolderIds)
            ->get();

        $legacyQueryCount = count(DB::getQueryLog());

        DB::flushQueryLog();

        // Optimized pattern: single composable query scope.
        $optimizedResult = Document::query()->accessibleBy($user)->get();
        $optimizedQueryCount = count(DB::getQueryLog());

        $this->assertGreaterThan($optimizedQueryCount, $legacyQueryCount, 'Legacy query count should exceed optimized query count.');
        $this->assertGreaterThan(0, $optimizedResult->count());
        $this->assertSame(
            $legacyResult->pluck('id')->sort()->values()->all(),
            $optimizedResult->pluck('id')->sort()->values()->all(),
            'Optimized scope should return the same document set as legacy logic.'
        );
    }

    public function test_folder_accessible_by_scope_supports_shared_and_public_visibility(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);

        $publicFolder = Folder::create(['name' => 'Public', 'visibility' => 'public']);
        $privateOwnedFolder = Folder::create(['name' => 'Owned Private', 'visibility' => 'private']);
        $privateSharedFolder = Folder::create(['name' => 'Shared Private', 'visibility' => 'private']);

        Document::factory()->create([
            'owner_id' => $user->id,
            'folder_id' => $privateOwnedFolder->id,
            'visibility' => 'private',
        ]);

        ShareDocument::create([
            'name' => 'Folder shared with user',
            'slug' => 'folder',
            'token' => 'token-folder-2-' . uniqid(),
            'share_id' => (string)$privateSharedFolder->id,
            'share_type' => Folder::class,
            'user_id' => (string)$user->id,
            'user_type' => User::class,
            'permission_level' => ShareDocument::PERMISSION_VIEWER,
            'visibility' => 'private',
        ]);

        $this->actingAs($user);

        $folderIds = Folder::query()->accessibleBy($user)->pluck('id')->all();

        $this->assertContains($publicFolder->id, $folderIds);
        $this->assertContains($privateOwnedFolder->id, $folderIds);
        $this->assertContains($privateSharedFolder->id, $folderIds);
    }
}
