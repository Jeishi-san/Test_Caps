<?php

namespace Tests\Feature\Phase4;

use App\Models\Document;
use App\Models\Folder;
use App\Models\ShareDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_benchmark_accessible_by_scope_reports_queries_and_runtime(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['role' => 'user']);
        /** @var User $other */
        $other = User::factory()->create(['role' => 'user']);

        $sharedFolder = Folder::create([
            'name' => 'Bench Shared',
            'visibility' => 'private',
        ]);

        $privateFolder = Folder::create([
            'name' => 'Bench Private',
            'visibility' => 'private',
        ]);

        Document::factory()->count(150)->create([
            'owner_id' => $user->id,
            'folder_id' => $privateFolder->id,
            'visibility' => 'private',
        ]);

        $sharedDocs = Document::factory()->count(150)->create([
            'owner_id' => $other->id,
            'folder_id' => $privateFolder->id,
            'visibility' => 'private',
        ]);

        Document::factory()->count(150)->create([
            'owner_id' => $other->id,
            'folder_id' => $sharedFolder->id,
            'visibility' => 'private',
        ]);

        foreach ($sharedDocs as $doc) {
            ShareDocument::create([
                'name' => 'Bench Share Doc ' . $doc->id,
                'slug' => 'document',
                'token' => 'bench-doc-' . $doc->id,
                'share_id' => (string)$doc->id,
                'share_type' => Document::class,
                'user_id' => (string)$user->id,
                'user_type' => User::class,
                'permission_level' => ShareDocument::PERMISSION_VIEWER,
                'visibility' => 'private',
            ]);
        }

        ShareDocument::create([
            'name' => 'Bench Folder Share',
            'slug' => 'folder',
            'token' => 'bench-folder-' . uniqid(),
            'share_id' => (string)$sharedFolder->id,
            'share_type' => Folder::class,
            'user_id' => (string)$user->id,
            'user_type' => User::class,
            'permission_level' => ShareDocument::PERMISSION_VIEWER,
            'visibility' => 'private',
        ]);

        $this->actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $legacyStart = hrtime(true);
        $ownedIds = Document::query()->where('owner_id', $user->id)->pluck('id');
        $directSharedIds = DB::table('share_documents')->where('user_id', (string)$user->id)->pluck('share_id');
        $sharedFolderIds = DB::table('share_documents')->where('user_id', (string)$user->id)->where('slug', 'folder')->pluck('share_id');
        $legacyResult = Document::query()
            ->whereIn('id', $ownedIds->merge($directSharedIds)->unique()->values())
            ->orWhereIn('folder_id', $sharedFolderIds)
            ->get();
        $legacyDurationMs = (hrtime(true) - $legacyStart) / 1_000_000;
        $legacyQueries = count(DB::getQueryLog());

        DB::flushQueryLog();

        $optimizedStart = hrtime(true);
        $optimizedResult = Document::query()->accessibleBy($user)->get();
        $optimizedDurationMs = (hrtime(true) - $optimizedStart) / 1_000_000;
        $optimizedQueries = count(DB::getQueryLog());

        fwrite(STDOUT, sprintf(
            "\n[Phase4 Benchmark] Legacy: %.2fms, %d queries | Optimized: %.2fms, %d queries\n",
            $legacyDurationMs,
            $legacyQueries,
            $optimizedDurationMs,
            $optimizedQueries
        ));

        $this->assertGreaterThan(0, $legacyResult->count());
        $this->assertSame($legacyResult->count(), $optimizedResult->count());
        $this->assertLessThan($legacyQueries, $optimizedQueries);
    }
}
