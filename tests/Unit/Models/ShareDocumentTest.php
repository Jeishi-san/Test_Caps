<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\ShareDocument;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShareDocumentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_correct_permission_matrix_values()
    {
        $this->assertEquals([
            'download' => true,
            'upload' => false,
            'edit' => false,
            'comment' => false,
            'share' => false,
        ], ShareDocument::PERMISSION_MATRIX[ShareDocument::PERMISSION_VIEWER]);

        $this->assertEquals([
            'download' => true,
            'upload' => false,
            'edit' => false,
            'comment' => true,
            'share' => false,
        ], ShareDocument::PERMISSION_MATRIX[ShareDocument::PERMISSION_COMMENTER]);

        $this->assertEquals([
            'download' => true,
            'upload' => true,
            'edit' => true,
            'comment' => true,
            'share' => false,
        ], ShareDocument::PERMISSION_MATRIX[ShareDocument::PERMISSION_EDITOR]);

        $this->assertEquals([
            'download' => true,
            'upload' => true,
            'edit' => true,
            'comment' => true,
            'share' => true,
        ], ShareDocument::PERMISSION_MATRIX[ShareDocument::PERMISSION_CO_OWNER]);

        $this->assertEquals([
            'download' => true,
            'upload' => true,
            'edit' => true,
            'comment' => true,
            'share' => true,
        ], ShareDocument::PERMISSION_MATRIX[ShareDocument::PERMISSION_OWNER]);
    }

    /** @test */
    public function it_correctly_calculates_permission_attributes()
    {
        $share = ShareDocument::factory()->viewer()->create();
        
        $this->assertTrue($share->can_download);
        $this->assertFalse($share->can_upload);
        $this->assertFalse($share->can_edit);
        $this->assertFalse($share->can_comment);
        $this->assertFalse($share->can_share);

        $share = ShareDocument::factory()->commenter()->create();
        
        $this->assertTrue($share->can_download);
        $this->assertFalse($share->can_upload);
        $this->assertFalse($share->can_edit);
        $this->assertTrue($share->can_comment);
        $this->assertFalse($share->can_share);

        $share = ShareDocument::factory()->editor()->create();
        
        $this->assertTrue($share->can_download);
        $this->assertTrue($share->can_upload);
        $this->assertTrue($share->can_edit);
        $this->assertTrue($share->can_comment);
        $this->assertFalse($share->can_share);

        $share = ShareDocument::factory()->coOwner()->create();
        
        $this->assertTrue($share->can_download);
        $this->assertTrue($share->can_upload);
        $this->assertTrue($share->can_edit);
        $this->assertTrue($share->can_comment);
        $this->assertTrue($share->can_share);

        $share = ShareDocument::factory()->owner()->create();
        
        $this->assertTrue($share->can_download);
        $this->assertTrue($share->can_upload);
        $this->assertTrue($share->can_edit);
        $this->assertTrue($share->can_comment);
        $this->assertTrue($share->can_share);
    }

    /** @test */
    public function has_permission_method_accepts_both_formats()
    {
        $share = ShareDocument::factory()->commenter()->create();

        $this->assertTrue($share->hasPermission('can_download'));
        $this->assertTrue($share->hasPermission('download'));
        $this->assertTrue($share->hasPermission('comment'));
        $this->assertFalse($share->hasPermission('edit'));
        $this->assertFalse($share->hasPermission('share'));
    }

    /** @test */
    public function is_owner_method_returns_correct_value()
    {
        $ownerShare = ShareDocument::factory()->owner()->create();
        $viewerShare = ShareDocument::factory()->viewer()->create();

        $this->assertTrue($ownerShare->isOwner());
        $this->assertFalse($viewerShare->isOwner());
    }

    /** @test */
    public function scope_is_public_returns_correctly()
    {
        $publicShare = ShareDocument::factory()->public()->create();
        $privateShare = ShareDocument::factory()->private()->create();

        $this->assertTrue($publicShare->isPublic());
        $this->assertFalse($privateShare->isPublic());
    }

    /** @test */
    public function has_expired_correctly_detects_expired_shares()
    {
        $activeShare = ShareDocument::factory()->create([
            'valid_until' => Carbon::now()->addDay()
        ]);

        $expiredShare = ShareDocument::factory()->create([
            'valid_until' => Carbon::now()->subDay()
        ]);

        $noExpiryShare = ShareDocument::factory()->create([
            'valid_until' => null
        ]);

        $this->assertFalse($activeShare->hasExpired());
        $this->assertTrue($expiredShare->hasExpired());
        $this->assertFalse($noExpiryShare->hasExpired());
    }

    /** @test */
    public function get_permission_level_returns_viewer_as_default()
    {
        $share = ShareDocument::factory()->create([
            'permission_level' => null
        ]);

        $this->assertEquals(ShareDocument::PERMISSION_VIEWER, $share->getPermissionLevel());
    }

    /** @test */
    public function set_permission_level_correctly_updates_value()
    {
        $share = ShareDocument::factory()->viewer()->create();
        
        $this->assertEquals(ShareDocument::PERMISSION_VIEWER, $share->getPermissionLevel());
        
        $share->setPermissionLevel(ShareDocument::PERMISSION_EDITOR);
        $share->save();
        
        $this->assertEquals(ShareDocument::PERMISSION_EDITOR, $share->fresh()->getPermissionLevel());
        $this->assertTrue($share->fresh()->can_edit);
    }

    /** @test */
    public function unknown_permission_level_returns_false_for_all_permissions()
    {
        $share = ShareDocument::factory()->create([
            'permission_level' => 'invalid_level'
        ]);

        $this->assertFalse($share->can_download);
        $this->assertFalse($share->can_upload);
        $this->assertFalse($share->can_edit);
        $this->assertFalse($share->can_comment);
        $this->assertFalse($share->can_share);
    }

    /** @test */
    public function get_permission_levels_returns_correct_labels()
    {
        $levels = ShareDocument::getPermissionLevels();

        $this->assertCount(5, $levels);
        $this->assertEquals('Viewer', $levels[ShareDocument::PERMISSION_VIEWER]);
        $this->assertEquals('Commenter', $levels[ShareDocument::PERMISSION_COMMENTER]);
        $this->assertEquals('Editor', $levels[ShareDocument::PERMISSION_EDITOR]);
        $this->assertEquals('Co-owner', $levels[ShareDocument::PERMISSION_CO_OWNER]);
        $this->assertEquals('Owner', $levels[ShareDocument::PERMISSION_OWNER]);
    }
}
