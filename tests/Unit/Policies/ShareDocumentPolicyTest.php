<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\ShareDocument;
use App\Policies\ShareDocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShareDocumentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected ShareDocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new \App\Policies\ShareDocumentPolicy();
    }

    /** @test */
    public function view_allows_owner()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $share));
    }

    /** @test */
    public function view_denies_non_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->view($user, $share));
    }

    /** @test */
    public function view_allows_admin()
    {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($this->policy->view($admin, $share));
    }

    /** @test */
    public function create_allows_authenticated_users()
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->create($user));
    }

    /** @test */
    public function update_allows_owner()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $share));
    }

    /** @test */
    public function update_denies_non_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->update($user, $share));
    }

    /** @test */
    public function update_allows_admin()
    {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($this->policy->update($admin, $share));
    }

    /** @test */
    public function delete_allows_owner()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $share));
    }

    /** @test */
    public function delete_denies_non_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->delete($user, $share));
    }

    /** @test */
    public function delete_allows_admin()
    {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($this->policy->delete($admin, $share));
    }

    /** @test */
    public function restore_allows_owner()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->restore($user, $share));
    }

    /** @test */
    public function restore_denies_non_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->restore($user, $share));
    }

    /** @test */
    public function restore_allows_admin()
    {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($this->policy->restore($admin, $share));
    }

    /** @test */
    public function force_delete_allows_owner()
    {
        $user = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->forceDelete($user, $share));
    }

    /** @test */
    public function force_delete_denies_non_owner()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse($this->policy->forceDelete($user, $share));
    }

    /** @test */
    public function force_delete_allows_admin()
    {
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();
        $share = ShareDocument::factory()->create(['user_id' => $otherUser->id]);

        $this->assertTrue($this->policy->forceDelete($admin, $share));
    }

    /** @test */
    public function view_any_allows_all_authenticated_users()
    {
        $user = User::factory()->create();
        
        $this->assertTrue($this->policy->viewAny($user));
    }
}
