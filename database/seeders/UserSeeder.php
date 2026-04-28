<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin account
        User::updateOrCreate(
            ['email' => 'admin@stegolock.local'],
            [
                'name' => 'System Administrator',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'active' => true,
            ]
        );
        
        // Superadmin account (was previously 'owner')
        User::updateOrCreate(
            ['email' => 'superadmin@stegolock.local'],
            [
                'name' => 'Company Owner',
                'username' => 'superadmin',
                'password' => Hash::make('superadmin123'),
                'role' => 'superadmin',
                'active' => true,
            ]
        );
        
        // Regular user accounts
        User::updateOrCreate(
            ['email' => 'john.doe@stegolock.local'],
            [
                'name' => 'John Doe',
                'username' => 'john.doe',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'active' => true,
            ]
        );
        
        User::updateOrCreate(
            ['email' => 'jane.smith@stegolock.local'],
            [
                'name' => 'Jane Smith',
                'username' => 'jane.smith',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'active' => true,
            ]
        );
        
        User::updateOrCreate(
            ['email' => 'mike.johnson@stegolock.local'],
            [
                'name' => 'Mike Johnson',
                'username' => 'mike.johnson',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'active' => true,
            ]
        );
        
        // Additional random users (only create if we don't have enough)
        $userCount = User::where('role', 'user')->count();
        $targetCount = 10; // 3 named users + 7 random
        
        if ($userCount < $targetCount) {
            User::factory()->count($targetCount - $userCount)->create([
                'role' => 'user',
                'active' => true,
            ]);
        }
    }
}
