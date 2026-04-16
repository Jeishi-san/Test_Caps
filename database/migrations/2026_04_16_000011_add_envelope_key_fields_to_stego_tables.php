<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add envelope key fields for cross-user stego decode sharing.
 *
 * Introduces per-document random DEK wrapping (owner + viewers) and grant activation lifecycle.
 * Preserves legacy derived-DEK records via mode marker and dual-mode decode path.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Stego Documents: Envelope Key Metadata ────────────────────────
        if (Schema::hasTable('stego_documents')) {
            Schema::table('stego_documents', function (Blueprint $table) {
                // Encryption mode: 'legacy_derived' (existing) or 'envelope_wrapped' (new)
                $table->enum('stego_mode', ['legacy_derived', 'envelope_wrapped'])
                    ->default('legacy_derived')
                    ->comment('DEK derivation mode: legacy_derived uses per-user PBKDF2, envelope_wrapped uses random DEK wrapped per user')
                    ->after('stego_dek_iter');

                // Owner-wrapped DEK metadata (for envelope mode)
                $table->text('owner_wrapped_dek')->nullable()
                    ->comment('Owner-wrapped DEK (base64), encrypted with owner master key via AES-256-GCM')
                    ->after('stego_mode');

                $table->string('owner_wrapped_dek_iv')->nullable()
                    ->comment('IV for owner DEK wrapping (base64)')
                    ->after('owner_wrapped_dek');

                $table->string('owner_wrapped_dek_auth_tag')->nullable()
                    ->comment('Auth tag for owner DEK wrapping (base64)')
                    ->after('owner_wrapped_dek_iv');

                // Wrapping algorithm and version for future rotation
                $table->string('owner_wrapped_dek_alg')->default('AES-256-GCM')
                    ->comment('Key wrapping algorithm version')
                    ->after('owner_wrapped_dek_auth_tag');

                $table->unsignedTinyInteger('owner_wrapped_dek_version')->default(1)
                    ->comment('Key wrapping scheme version for future migration')
                    ->after('owner_wrapped_dek_alg');
            });
        }

        // ── Stego Document Grants: Viewer-Wrapped DEK + Activation Lifecycle ────────
        if (Schema::hasTable('stego_document_grants')) {
            Schema::table('stego_document_grants', function (Blueprint $table) {
                // Grant activation lifecycle: 'pending' (awaiting viewer acceptance) or 'active' (viewer accepted + wrapped)
                $table->enum('grant_status', ['pending', 'active'])
                    ->default('pending')
                    ->comment('Grant activation status: pending (viewer has not accepted), active (viewer has accepted and uploaded wrapped key)')
                    ->after('granted_by');

                // When the viewer accepted the share (completed key wrapping)
                $table->timestamp('accepted_at')->nullable()
                    ->comment('Timestamp when viewer accepted share and uploaded wrapped DEK')
                    ->after('grant_status');

                // Viewer-wrapped DEK metadata (populated only when status is 'active')
                $table->text('viewer_wrapped_dek')->nullable()
                    ->comment('Viewer-wrapped DEK (base64), encrypted with viewer master key via AES-256-GCM')
                    ->after('accepted_at');

                $table->string('viewer_wrapped_dek_iv')->nullable()
                    ->comment('IV for viewer DEK wrapping (base64)')
                    ->after('viewer_wrapped_dek');

                $table->string('viewer_wrapped_dek_auth_tag')->nullable()
                    ->comment('Auth tag for viewer DEK wrapping (base64)')
                    ->after('viewer_wrapped_dek_iv');

                // Wrapping metadata
                $table->string('viewer_wrapped_dek_alg')->nullable()
                    ->comment('Key wrapping algorithm used by viewer')
                    ->after('viewer_wrapped_dek_auth_tag');

                $table->unsignedTinyInteger('viewer_wrapped_dek_version')->nullable()
                    ->comment('Key wrapping scheme version when viewer accepted')
                    ->after('viewer_wrapped_dek_alg');

                // Index for finding active grants by viewer
                $table->index(['viewer_user_id', 'grant_status']);
                $table->index(['stego_document_id', 'grant_status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stego_documents')) {
            Schema::table('stego_documents', function (Blueprint $table) {
                $table->dropColumn([
                    'stego_mode',
                    'owner_wrapped_dek',
                    'owner_wrapped_dek_iv',
                    'owner_wrapped_dek_auth_tag',
                    'owner_wrapped_dek_alg',
                    'owner_wrapped_dek_version',
                ]);
            });
        }

        if (Schema::hasTable('stego_document_grants')) {
            Schema::table('stego_document_grants', function (Blueprint $table) {
                $table->dropIndex(['viewer_user_id', 'grant_status']);
                $table->dropIndex(['stego_document_id', 'grant_status']);

                $table->dropColumn([
                    'grant_status',
                    'accepted_at',
                    'viewer_wrapped_dek',
                    'viewer_wrapped_dek_iv',
                    'viewer_wrapped_dek_auth_tag',
                    'viewer_wrapped_dek_alg',
                    'viewer_wrapped_dek_version',
                ]);
            });
        }
    }
};
