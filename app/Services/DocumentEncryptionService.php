<?php

namespace App\Services;

use App\Models\Document;
use App\Services\Stego\CryptoService;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling AES-256-GCM document encryption and decryption.
 * 
 * Handles all cryptographic operations including:
 * - Encrypting documents at rest
 * - Decrypting documents for serving
 * - Managing encryption metadata (IVs, auth tags, DEK salts)
 * - Verifying document integrity
 */
class DocumentEncryptionService
{
    public function __construct(private readonly CryptoService $crypto)
    {
    }

    /**
     * Check whether document-at-rest encryption is enabled.
     * Encryption requires DOCUMENT_MASTER_KEY to be set in .env.
     */
    public function isEncryptionEnabled(): bool
    {
        return !empty(env('DOCUMENT_MASTER_KEY'));
    }

    /**
     * Get the application-wide 256-bit master key from .env.
     * This key is used as the base for per-document DEK derivation.
     *
     * @return string The master key (hex-encoded)
     * @throws \RuntimeException If the key is not configured
     */
    public function getMasterKey(): string
    {
        $key = env('DOCUMENT_MASTER_KEY');

        if (empty($key)) {
            throw new \RuntimeException(
                'DOCUMENT_MASTER_KEY is not set in .env. ' .
                'Generate one with: php -r "echo bin2hex(openssl_random_pseudo_bytes(32));"'
            );
        }

        return $key;
    }

    /**
     * Encrypt a document's file on disk in-place using AES-256-GCM and
     * store encryption metadata (IV, auth tag, DEK salt, hash) in the DB.
     *
     * Safe to call multiple times — skips already-encrypted documents.
     * Skips URL-type documents that have no physical file.
     *
     * @param Document $document A persisted document with a valid ID
     * @throws \RuntimeException On encryption failure or missing master key
     */
    public function encryptDocumentFile(Document $document): void
    {
        if ($document->is_encrypted) {
            return; // Already encrypted — nothing to do
        }

        if (!$document->hasPhysicalFile()) {
            return; // URL document — no file to encrypt
        }

        $absolutePath = public_path($document->file_path);

        if (!file_exists($absolutePath)) {
            Log::warning("DocumentEncryptionService: cannot encrypt — file not found: {$absolutePath}");
            return;
        }

        $plaintext = file_get_contents($absolutePath);
        $masterKey = $this->getMasterKey();

        // Derive a unique DEK for this document from the master key + document ID.
        $dekResult = $this->crypto->deriveDEK($masterKey, (string)$document->id);

        // Encrypt with AES-256-GCM and compute plaintext integrity hash.
        $encrypted = $this->crypto->encrypt($plaintext, $dekResult['dek']);
        $hash = $this->crypto->hashDocument($plaintext);

        // Overwrite the file on disk with raw ciphertext bytes.
        $rawCiphertext = base64_decode($encrypted['ciphertext'], true);
        if ($rawCiphertext === false) {
            throw new \RuntimeException('Failed to decode encrypted document ciphertext.');
        }
        file_put_contents($absolutePath, $rawCiphertext);

        // Persist encryption metadata so the file can be decrypted later.
        $document->update([
            'is_encrypted'       => true,
            'enc_iv'             => $encrypted['iv'],
            'enc_auth_tag'       => $encrypted['auth_tag'],
            'enc_dek_salt'       => $dekResult['salt'],
            'enc_dek_iterations' => $dekResult['iterations'],
            'enc_hash_sha256'    => $hash,
        ]);

        Log::info("DocumentEncryptionService: document #{$document->id} encrypted with AES-256-GCM.");
    }

    /**
     * Read and decrypt a document's file, returning plaintext bytes.
     *
     * For unencrypted (legacy) documents the file is returned as-is.
     * For URL-type documents, returns an empty string.
     *
     * @param Document $document
     * @return string Raw plaintext bytes
     * @throws \RuntimeException On decryption failure or integrity mismatch
     */
    public function decryptDocumentContent(Document $document): string
    {
        if (!$document->hasPhysicalFile()) {
            return ''; // URL document — nothing to decrypt
        }

        $absolutePath = public_path($document->file_path);

        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("File not found on disk: {$absolutePath}");
        }

        if (!$document->is_encrypted) {
            // Legacy plaintext document — serve directly.
            return file_get_contents($absolutePath);
        }

        // Read raw ciphertext bytes from disk for CryptoService.
        $ciphertext = file_get_contents($absolutePath);

        $masterKey = $this->getMasterKey();

        // Re-derive the exact same DEK using stored salt + iterations.
        $dekResult = $this->crypto->deriveDEK(
            $masterKey,
            (string)$document->id,
            $document->enc_dek_salt,
            $document->enc_dek_iterations
        );

        // Decrypt and authenticate.
        $plaintext = $this->crypto->decrypt(
            $ciphertext,
            $dekResult['dek'],
            $document->enc_iv,
            $document->enc_auth_tag
        );

        // Verify SHA-256 integrity hash to detect tampering.
        if (!$this->crypto->verifyHash($plaintext, $document->enc_hash_sha256)) {
            throw new \RuntimeException(
                "Document #{$document->id} integrity check failed. " .
                'File may be corrupt or tampered with.'
            );
        }

        return $plaintext;
    }
}
