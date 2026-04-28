<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Config;

class EncryptionService
{
    /**
     * Checks if document's encryption_mode is set to envelope_wrapped
     */
    public function isEnvelopeMode(Document $document): bool
    {
        return $document->encryption_mode === 'envelope_wrapped';
    }

    /**
     * Encrypts document data based on encryption mode.
     * Accepts encryptionMode and viewerUserIds parameters.
     * Handles envelope mode (generates random DEK, wraps for owner/viewers) vs legacy mode (derives DEK from master key)
     *
     * @param string $encryptionMode 'legacy' or 'envelope_wrapped'
     * @param array $viewerUserIds Array of user IDs to wrap DEK for (envelope mode only)
     * @param Document|null $document Document instance for legacy mode salt/iterations, and owner ID for envelope mode
     * @return array Contains DEK, IV, auth tag, wrapped DEKs (envelope mode), and encryption mode
     */
    public function encrypt(string $encryptionMode, array $viewerUserIds = [], ?Document $document = null): array
    {
        if ($encryptionMode === 'envelope_wrapped') {
            return $this->handleEnvelopeMode($viewerUserIds, $document);
        }

        return $this->handleLegacyMode($document);
    }

    /**
     * Handle envelope encryption mode: generate random DEK, wrap for owner and viewers
     */
    private function handleEnvelopeMode(array $viewerUserIds, ?Document $document): array
    {
        // Generate random 256-bit DEK
        $dek = random_bytes(32);
        // Generate 128-bit IV for AES-256-GCM
        $iv = random_bytes(16);
        // Auth tag will be populated during actual encryption
        $authTag = '';

        $wrappedDeks = [];

        if ($document) {
            // Wrap DEK for document owner
            $ownerId = $document->owner_id;
            $wrappedDeks[$ownerId] = $this->wrapDekForUser($dek, $ownerId);

            // Wrap DEK for each viewer
            foreach ($viewerUserIds as $userId) {
                $wrappedDeks[$userId] = $this->wrapDekForUser($dek, $userId);
            }
        }

        return [
            'dek' => $dek,
            'iv' => $iv,
            'auth_tag' => $authTag,
            'wrapped_deks' => $wrappedDeks,
            'encryption_mode' => 'envelope_wrapped'
        ];
    }

    /**
     * Handle legacy encryption mode: derive DEK from master key
     */
    private function handleLegacyMode(?Document $document): array
    {
        // Get master key from config (should be stored securely, e.g., in .env)
        $masterKey = Config::get('stegolock.master_encryption_key');
        if (!$masterKey) {
            throw new \RuntimeException('Master encryption key not configured');
        }

        // Derive DEK using PBKDF2 with document-specific salt and iterations
        $salt = $document->enc_dek_salt ?? '';
        $iterations = $document->enc_dek_iterations ?? 10000;
        $dek = hash_pbkdf2('sha256', $masterKey, $salt, $iterations, 32, true);

        // Generate IV
        $iv = random_bytes(16);
        $authTag = '';

        return [
            'dek' => $dek,
            'iv' => $iv,
            'auth_tag' => $authTag,
            'encryption_mode' => 'legacy'
        ];
    }

    /**
     * Wrap DEK for a specific user using their public key (simplified for integration)
     * In production, this would use asymmetric encryption with user's public key
     */
    private function wrapDekForUser(string $dek, int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new \RuntimeException("User {$userId} not found");
        }

        // Placeholder: In real implementation, encrypt DEK with user's public key
        return [
            'wrapped_dek' => base64_encode($dek),
            'wrapped_dek_iv' => base64_encode(random_bytes(16)),
            'wrapped_dek_auth_tag' => base64_encode(random_bytes(16)),
            'user_id' => $userId
        ];
    }
}
