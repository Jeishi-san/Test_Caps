<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table to update the CHECK constraint
        if (DB::getDriverName() === 'sqlite') {
            // Create new users table with updated role enum
            Schema::create('users_new', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('username')->unique()->nullable();
                $table->enum('role', ['user', 'superadmin', 'admin'])->default('user');
                $table->string('mkd_salt', 32)->nullable()
                    ->comment('PBKDF2-SHA256 salt for Master Key Derivation (hex, 32 chars = 16 bytes)');
                $table->string('email')->unique();
                $table->string('avatar')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });

            // Copy data from old table, mapping 'owner' to 'superadmin'
            DB::statement("
                INSERT INTO users_new (id, name, username, role, mkd_salt, email, avatar, email_verified_at, password, remember_token, created_at, updated_at)
                SELECT id, name, username, 
                       CASE WHEN role = 'owner' THEN 'superadmin' ELSE role END as role,
                       mkd_salt, email, avatar, email_verified_at, password, remember_token, created_at, updated_at
                FROM users
            ");

            // Drop old table and rename new one
            Schema::drop('users');
            Schema::rename('users_new', 'users');
        } else {
            // For MySQL/other databases:
            // 1. First update existing 'owner' records to a value that will be in the new enum
            // 2. Then modify the enum
            // Since we can't update to 'superadmin' before it's in the enum,
            // we need to modify the enum FIRST to include both values, then update data, then optionally remove 'owner'
            
            // Step 1: Modify enum to include 'superadmin' (keeping 'owner' for now)
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'owner', 'superadmin', 'admin') DEFAULT 'user'");
            
            // Step 2: Update 'owner' to 'superadmin'
            DB::statement("UPDATE users SET role = 'superadmin' WHERE role = 'owner'");
            
            // Step 3: Modify enum to remove 'owner' (optional - keeps DB clean)
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'superadmin', 'admin') DEFAULT 'user'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Create old users table with original role enum
            Schema::create('users_old', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('username')->unique()->nullable();
                $table->enum('role', ['user', 'owner', 'admin'])->default('user');
                $table->string('mkd_salt', 32)->nullable()
                    ->comment('PBKDF2-SHA256 salt for Master Key Derivation (hex, 32 chars = 16 bytes)');
                $table->string('email')->unique();
                $table->string('avatar')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });

            // Copy data back, mapping 'superadmin' to 'owner'
            DB::statement("
                INSERT INTO users_old (id, name, username, role, mkd_salt, email, avatar, email_verified_at, password, remember_token, created_at, updated_at)
                SELECT id, name, username, 
                       CASE WHEN role = 'superadmin' THEN 'owner' ELSE role END as role,
                       mkd_salt, email, avatar, email_verified_at, password, remember_token, created_at, updated_at
                FROM users
            ");

            Schema::drop('users');
            Schema::rename('users_old', 'users');
        } else {
            DB::statement("UPDATE users SET role = 'owner' WHERE role = 'superadmin'");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'owner', 'admin') DEFAULT 'user'");
        }
    }
};
