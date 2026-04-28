<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Superadmin can list admin users (happy path)
     * Covers: listAdmins endpoint, superadmin access, paginated response structure
     */
    public function test_superadmin_can_list_admin_users(): void
    {
        // Create superadmin user
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        
        // Create admin and regular users
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'user']);

        // Act as superadmin and call listAdmins endpoint
        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/admins');

        // Assert response structure and status
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'active', 'created_at']
                ],
                'current_page',
                'last_page',
                'per_page',
                'total'
            ]);

        // Assert only admin/superadmin users are returned
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['role' => 'superadmin']);
        $response->assertJsonFragment(['role' => 'admin']);
        $response->assertJsonMissing(['role' => 'user']);
    }

    /**
     * Test 2: Admin cannot list admin users (forbidden)
     * Covers: listAdmins authorization, admin access restriction
     */
    public function test_admin_cannot_list_admin_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admins');

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden: Superadmin access required.']);
    }

    /**
     * Test 3: Unauthenticated user cannot list admin users
     * Covers: listAdmins authentication requirement
     */
    public function test_unauthenticated_user_cannot_list_admin_users(): void
    {
        $response = $this->getJson('/api/admins');

        $response->assertStatus(401);
    }

    /**
     * Test 4: Superadmin can create admin user (happy path)
     * Covers: create admin endpoint, superadmin permissions
     */
    public function test_superadmin_can_create_admin_user(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->postJson('/api/admins', [
                'name' => 'New Admin',
                'email' => 'newadmin@stegolock.local',
                'password' => 'password123',
                'role' => 'admin',
                'active' => true
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role', 'active']
            ])
            ->assertJsonFragment([
                'name' => 'New Admin',
                'role' => 'admin'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@stegolock.local',
            'role' => 'admin'
        ]);
    }

    /**
     * Test 5: Superadmin can create superadmin user
     * Covers: superadmin creating superadmin users
     */
    public function test_superadmin_can_create_superadmin_user(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->postJson('/api/admins', [
                'name' => 'New Superadmin',
                'email' => 'newsuperadmin@stegolock.local',
                'password' => 'password123',
                'role' => 'superadmin',
                'active' => true
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['role' => 'superadmin']);
    }

    /**
     * Test 6: Admin can create admin user but not superadmin
     * Covers: admin permissions for creating users
     */
    public function test_admin_can_create_admin_but_not_superadmin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Admin can create admin user
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admins', [
                'name' => 'New Admin',
                'email' => 'newadmin@stegolock.local',
                'password' => 'password123',
                'role' => 'admin'
            ]);
        $response->assertStatus(201);

        // Admin cannot create superadmin user
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admins', [
                'name' => 'New Superadmin',
                'email' => 'newsuperadmin@stegolock.local',
                'password' => 'password123',
                'role' => 'superadmin'
            ]);
        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden: Only superadmins can create superadmin users.']);
    }

    /**
     * Test 7: Superadmin can update user role (happy path)
     * Covers: updateRole endpoint, superadmin permissions
     */
    public function test_superadmin_can_update_user_role(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->putJson("/api/users/{$user->id}/role", [
                'role' => 'admin'
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'role' => 'admin',
                'message' => 'User role updated successfully.'
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin'
        ]);
    }

    /**
     * Test 8: Admin can update regular user to admin but not to superadmin
     * Covers: admin role update permissions
     */
    public function test_admin_can_update_user_to_admin_but_not_superadmin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        // Admin can update user to admin
        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$user->id}/role", ['role' => 'admin']);
        $response->assertStatus(200);

        // Admin cannot update user to superadmin
        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/users/{$user->id}/role", ['role' => 'superadmin']);
        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden: Only superadmins can create superadmin users.']);
    }

    /**
     * Test 9: Superadmin can delete admin user (happy path)
     * Covers: delete user endpoint, superadmin permissions, no self-deletion
     */
    public function test_superadmin_can_delete_admin_user(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->deleteJson("/api/users/{$admin->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'User deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
    }

    /**
     * Test 10: Superadmin cannot delete self
     * Covers: self-deletion prevention
     */
    public function test_superadmin_cannot_delete_self(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->deleteJson("/api/users/{$superadmin->id}");

        $response->assertStatus(403)
            ->assertJson(['message' => 'You cannot delete your own account.']);
    }

    /**
     * Test 11: Admin can delete regular user but not superadmin
     * Covers: admin delete permissions
     */
    public function test_admin_can_delete_user_but_not_superadmin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $superadmin = User::factory()->create(['role' => 'superadmin']);

        // Admin can delete regular user
        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$user->id}");
        $response->assertStatus(200);

        // Admin cannot delete superadmin
        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/users/{$superadmin->id}");
        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden: You do not have permission to delete this user.']);
    }

    /**
     * Test 12: Pagination works for listAdmins endpoint
     * Covers: pagination parameters, per_page limit
     */
    public function test_list_admins_pagination(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        // Create 15 admin users
        User::factory(15)->create(['role' => 'admin']);

        // Request first page with 10 items
        $response = $this->actingAs($superadmin, 'sanctum')
            ->getJson('/api/admins?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 16); // 15 admins + 1 superadmin
    }
}
