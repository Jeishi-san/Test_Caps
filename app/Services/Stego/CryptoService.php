<?php

namespace App\Services\Stego;

use Exception;

/**
 * CryptoService
 *
 * Handles all cryptographic operations for StegoLock:
 *  - Master Key Derivation (MKD) via PBKDF2-SHA256
 *  - Document Encryption Key (DEK) derivation
 *  - AES-256-GCM symmetric encryption / decryption
 *  - SHA-256 document integrity hashing
 *
 * Replaces the gRPC crypto-service microservice described in the .md guide.
 * All operations use PHP's built-in OpenSSL extension — no extra packages needed.
 */
class CryptoService
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    private const CIPHER         = 'AES-256-GCM';
    private const KEY_LENGTH     = 32;   // 256 bits
    private const IV_LENGTH      = 12;   // 96 bits (recommended for GCM)
    private const AUTH_TAG_LEN   = 16;   // 128 bits

    // MKD defaults (can be overridden via .env)
    private const MKD_ITERATIONS  = 100_000;
    private const MKD_SALT_LENGTH = 16;

    // DEK defaults
    private const DEK_ITERATIONS  = 10_000;
    private const DEK_SALT_LENGTH = 16;

    // -------------------------------------------------------------------------
    // Master Key Derivation (MKD)
    // -------------------------------------------------------------------------

    /**
     * Derive a Master Key from a user password using PBKDF2-SHA256.
     *
     * @param  string      $password   The user's plaintext password
     * @param  string|null $salt       Hex-encoded salt; generated if null
     * @param  int|null    $iterations PBKDF2 iteration count
     * @return array{ masterKey: string, salt: string, iterations: int }
     *              masterKey and salt are hex-encoded
     */
    public function deriveMasterKey(
        string $password,
        ?string $salt = null,
        ?int $iterations = null
    ): array {
        $iterations = $iterations ?? (int) config('stegolock.mkd_iterations', self::MKD_ITERATIONS);

        if ($salt === null) {
            $rawSalt = $this->secureRandom(self::MKD_SALT_LENGTH);
            $salt    = bin2hex($rawSalt);
        } else {
            $rawSalt = hex2bin($salt);
        }

        $masterKey = $this->pbkdf2Derive($password, $rawSalt, $iterations);

        return [
            'masterKey'  => $masterKey,
            'salt'       => $salt,
            'iterations' => $iterations,
        ];
    }

    // -------------------------------------------------------------------------
    // Document Encryption Key (DEK) Derivation
    // -------------------------------------------------------------------------

    /**
     * Derive a per-document DEK from the master key and a document identifier.
     *
     * Using a unique document ID ensures that each document has its own DEK,
     * so compromising one DEK does not expose others.
     *
     * @param  string      $masterKey  Hex-encoded master key
     * @param  string      $documentId Unique document identifier (UUID or int cast to string)
     * @param  string|null $salt       Hex-encoded salt; generated if null
     * @param  int|null    $iterations PBKDF2 iteration count
     * @return array{ dek: string, salt: string, iterations: int }
     *              dek and salt are hex-encoded
     */
    public function deriveDEK(
        string $masterKey,
        string $documentId,
        ?string $salt = null,
        ?int $iterations = null
    ): array {
        $iterations = $iterations ?? (int) config('stegolock.dek_iterations', self::DEK_ITERATIONS);

        if ($salt === null) {
            // Deterministic salt derived from documentId so the same DEK can be
            // re-derived without storing extra randomness.
            $rawSalt = substr(hash('sha256', $documentId . 'dek-salt', true), 0, self::DEK_SALT_LENGTH);
            $salt    = bin2hex($rawSalt);
        } else {
            $rawSalt = hex2bin($salt);
        }

        $dek = $this->pbkdf2Derive(hex2bin($masterKey), $rawSalt, $iterations);

        return [
            'dek'        => $dek,
            'salt'       => $salt,
            'iterations' => $iterations,
        ];
    }

    // -------------------------------------------------------------------------
    // Encryption
    // -------------------------------------------------------------------------

    /**
     * Encrypt plaintext using AES-256-GCM.
     *
     * @param  string $plaintext Plaintext bytes to encrypt
     * @param  string $dek       Hex-encoded Document Encryption Key
     * @return array{ ciphertext: string, iv: string, auth_tag: string }
     *              All values are hex-encoded
     * @throws Exception
     */
    public function encrypt(string $plaintext, string $dek): array
    {
        // Validate inputs
        if (empty($plaintext)) {
            throw new Exception('Plaintext cannot be empty');
        }

        if (empty($dek)) {
            throw new Exception('DEK cannot be empty');
        }

        try {
            $dekBinary = hex2bin($dek);
            if (strlen($dekBinary) !== self::KEY_LENGTH) {
                throw new Exception('DEK must be a hex-encoded string of ' . (self::KEY_LENGTH * 2) . ' characters');
            }
        } catch (\Exception $e) {
            throw new Exception('DEK must be a hex-encoded string of ' . (self::KEY_LENGTH * 2) . ' characters');
        }

        // Compress BEFORE encrypting: encrypted output is random noise and
        // incompressible, so compression must come first to be effective.
        $compressed = gzcompress($plaintext, 6);

        $iv  = $this->secureRandom(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $compressed,
            self::CIPHER,
            $dekBinary,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::AUTH_TAG_LEN
        );

        if ($ciphertext === false) {
            throw new Exception('AES-256-GCM encryption failed: ' . openssl_error_string());
        }

        // Base64-encode all output: ~33% overhead vs 100% overhead for bin2hex.
        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv'         => base64_encode($iv),
            'auth_tag'   => base64_encode($tag),
        ];
    }

    // -------------------------------------------------------------------------
    // Decryption
    // -------------------------------------------------------------------------

    /**
     * Decrypt ciphertext using AES-256-GCM.
     *
     * @param  string $ciphertext Hex-encoded ciphertext
     * @param  string $dek        Hex-encoded Document Encryption Key
     * @param  string $iv         Hex-encoded initialisation vector
     * @param  string $authTag    Hex-encoded GCM authentication tag
     * @return string Decrypted plaintext
     * @throws Exception If decryption or authentication fails
     */
    public function decrypt(string $ciphertext, string $dek, string $iv, string $authTag): string
    {
        // Validate inputs
        if (empty($ciphertext)) {
            throw new Exception('Ciphertext cannot be empty');
        }

        if (empty($dek)) {
            throw new Exception('DEK cannot be empty');
        }

        try {
            $dekBinary = hex2bin($dek);
            if (strlen($dekBinary) !== self::KEY_LENGTH) {
                throw new Exception('DEK must be a hex-encoded string of ' . (self::KEY_LENGTH * 2) . ' characters');
            }
        } catch (\Exception $e) {
            throw new Exception('DEK must be a hex-encoded string of ' . (self::KEY_LENGTH * 2) . ' characters');
        }

        if (empty($iv)) {
            throw new Exception('IV cannot be empty');
        }

        try {
            $ivBinary = base64_decode($iv);
            if (strlen($ivBinary) !== self::IV_LENGTH) {
                throw new Exception('IV must be a base64-encoded string of ' . self::IV_LENGTH . ' bytes');
            }
        } catch (\Exception $e) {
            throw new Exception('IV must be a base64-encoded string of ' . self::IV_LENGTH . ' bytes');
        }

        if (empty($authTag)) {
            throw new Exception('Auth tag cannot be empty');
        }

        try {
            $authTagBinary = base64_decode($authTag);
            if (strlen($authTagBinary) !== self::AUTH_TAG_LEN) {
                throw new Exception('Auth tag must be a base64-encoded string of ' . self::AUTH_TAG_LEN . ' bytes');
            }
        } catch (\Exception $e) {
            throw new Exception('Auth tag must be a base64-encoded string of ' . self::AUTH_TAG_LEN . ' bytes');
        }

        // Check if ciphertext is base64-encoded (legacy DB/local file) or raw binary (segments)
        $ciphertextBinary = base64_decode($ciphertext, true);
        if ($ciphertextBinary === false) {
            // If base64 decode fails, assume it's raw binary from segments
            $ciphertextBinary = $ciphertext;
        }

        if (empty($ciphertextBinary)) {
            throw new Exception('Ciphertext cannot be empty');
        }

        $compressed = openssl_decrypt(
            $ciphertextBinary,
            self::CIPHER,
            $dekBinary,
            OPENSSL_RAW_DATA,
            $ivBinary,
            $authTagBinary
        );

        if ($compressed === false) {
            throw new Exception('AES-256-GCM decryption failed: authentication tag mismatch or corrupt data.');
        }

        // Decompress AFTER decrypting to recover the original plaintext.
        $plaintext = gzuncompress($compressed);
        if ($plaintext === false) {
            throw new Exception('Failed to decompress decrypted data');
        }

        return $plaintext;
    }

    // -------------------------------------------------------------------------
    // Hashing
    // -------------------------------------------------------------------------

    /**
     * Compute a SHA-256 integrity hash of the document plaintext.
     *
     * @param  string $plaintext
     * @return string 64-character hex string
     */
    public function hashDocument(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Verify a SHA-256 integrity hash.
     *
     * @param  string $plaintext
     * @param  string $expectedHash Hex-encoded hash
     * @return bool
     */
    public function verifyHash(string $plaintext, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->hashDocument($plaintext));
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Shared PBKDF2-SHA256 key derivation — used by both deriveMasterKey() and deriveDEK().
     *
     * Eliminates the duplicated hash_pbkdf2() call that previously existed in both methods.
     *
     * @param  string $input      Password string or raw master-key bytes
     * @param  string $rawSalt    Raw binary salt
     * @param  int    $iterations PBKDF2 iteration count
     * @return string             Hex-encoded derived key (KEY_LENGTH bytes = 64 hex chars)
     */
    private function pbkdf2Derive(string $input, string $rawSalt, int $iterations): string
    {
        return hash_pbkdf2(
            'sha256',
            $input,
            $rawSalt,
            $iterations,
            self::KEY_LENGTH * 2, // KEY_LENGTH bytes × 2 hex chars/byte
            false                 // return hex string, not raw binary
        );
    }

    /**
     * Generate cryptographically secure random bytes.
     *
     * @param  int $length Number of bytes
     * @return string Raw binary string
     * @throws Exception If the system RNG is not available
     */
    private function secureRandom(int $length): string
    {
        $bytes = openssl_random_pseudo_bytes($length, $strong);

        if (!$strong) {
            throw new Exception('Failed to generate cryptographically strong random bytes.');
        }

        return $bytes;
    }

    // -------------------------------------------------------------------------
    // Envelope Key Management (DEK wrapping for multi-user sharing)
    // -------------------------------------------------------------------------

    /**
     * Generate a random Document Encryption Key (DEK) for envelope-mode stego records.
     * 
     * Unlike derived-DEK (bound to document ID + master key), random DEK can be
     * wrapped per user, enabling cross-user shared decode.
     *
     * @return string Hex-encoded random DEK (KEY_LENGTH bytes = 64 hex chars)
     * @throws Exception If random generation fails
     */
    public function generateDEK(): string
    {
        $rawDek = $this->secureRandom(self::KEY_LENGTH);
        return bin2hex($rawDek);
    }

    /**
     * Wrap a DEK using a user's master key via AES-256-GCM.
     *
     * Enables per-user wrapping of a shared document DEK so each user (owner + viewers)
     * can decrypt with their own master key without exposing the master key to the server.
     *
     * @param  string $dekHex      Hex-encoded DEK to wrap
     * @param  string $masterKeyHex Hex-encoded user's master key
     * @param  int    $wrapVersion Wrapping scheme version (for future rotation)
     * @return array{ wrapped_dek: string, iv: string, auth_tag: string, algorithm: string, version: int }
     *              All strings are base64-encoded
     * @throws Exception If wrapping fails
     */
    public function wrapDekForUser(string $dekHex, string $masterKeyHex, int $wrapVersion = 1): array
    {
        if (empty($dekHex) || strlen($dekHex) !== 64) {
            throw new Exception('DEK must be a 64-character hex string (32 bytes)');
        }

        if (empty($masterKeyHex) || strlen($masterKeyHex) !== 64) {
            throw new Exception('Master key must be a 64-character hex string (32 bytes)');
        }

        try {
            $dek = hex2bin($dekHex);
            $masterKey = hex2bin($masterKeyHex);

            if (strlen($dek) !== self::KEY_LENGTH) {
                throw new Exception('DEK must be 32 bytes');
            }

            if (strlen($masterKey) !== self::KEY_LENGTH) {
                throw new Exception('Master key must be 32 bytes');
            }
        } catch (\Exception $e) {
            throw new Exception('Invalid hex string format: ' . $e->getMessage());
        }

        $iv = $this->secureRandom(self::IV_LENGTH);
        $tag = '';

        $wrappedDek = openssl_encrypt(
            $dek,
            self::CIPHER,
            $masterKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::AUTH_TAG_LEN
        );

        if ($wrappedDek === false) {
            throw new Exception('DEK wrapping failed: ' . openssl_error_string());
        }

        return [
            'wrapped_dek' => base64_encode($wrappedDek),
            'iv'          => base64_encode($iv),
            'auth_tag'    => base64_encode($tag),
            'algorithm'   => self::CIPHER,
            'version'     => $wrapVersion,
        ];
    }

    /**
     * Unwrap a DEK using a user's master key via AES-256-GCM.
     *
     * Recovers the original DEK from a wrapped copy so the caller can decrypt the document.
     *
     * @param  string $wrappedDek   Base64-encoded wrapped DEK
     * @param  string $iv           Base64-encoded IV
     * @param  string $authTag      Base64-encoded authentication tag
     * @param  string $masterKeyHex Hex-encoded user's master key
     * @return string               Hex-encoded unwrapped DEK (64 hex chars)
     * @throws Exception            If unwrapping fails or auth tag mismatch
     */
    public function unwrapDekForUser(
        string $wrappedDek,
        string $iv,
        string $authTag,
        string $masterKeyHex
    ): string {
        if (empty($wrappedDek)) {
            throw new Exception('Wrapped DEK cannot be empty');
        }

        if (empty($masterKeyHex) || strlen($masterKeyHex) !== 64) {
            throw new Exception('Master key must be a 64-character hex string (32 bytes)');
        }

        try {
            $masterKey = hex2bin($masterKeyHex);
            $wrappedBinary = base64_decode($wrappedDek, true);
            $ivBinary = base64_decode($iv, true);
            $tagBinary = base64_decode($authTag, true);

            if (strlen($masterKey) !== self::KEY_LENGTH) {
                throw new Exception('Master key must be 32 bytes');
            }

            if ($wrappedBinary === false || $ivBinary === false || $tagBinary === false) {
                throw new Exception('Invalid base64 encoding in wrapped key metadata');
            }

            if (strlen($ivBinary) !== self::IV_LENGTH) {
                throw new Exception('IV must be 12 bytes');
            }

            if (strlen($tagBinary) !== self::AUTH_TAG_LEN) {
                throw new Exception('Auth tag must be 16 bytes');
            }
        } catch (\Exception $e) {
            throw new Exception('Invalid wrapped key format: ' . $e->getMessage());
        }

        $dek = openssl_decrypt(
            $wrappedBinary,
            self::CIPHER,
            $masterKey,
            OPENSSL_RAW_DATA,
            $ivBinary,
            $tagBinary
        );

        if ($dek === false) {
            throw new Exception('DEK unwrapping failed: authentication tag mismatch or corrupt data.');
        }

        if (strlen($dek) !== self::KEY_LENGTH) {
            throw new Exception('Unwrapped DEK has unexpected length');
        }

        return bin2hex($dek);
    }

    /**
     * Wrap a DEK with a server-managed key derived from APP_KEY.
     *
     * This supports owner-driven automatic share activation where the viewer
     * does not perform a separate acceptance/wrapping step.
     *
     * @param  string $dekHex      Hex-encoded DEK (64 chars)
     * @param  int    $wrapVersion Key-wrap version tag
     * @return array{ wrapped_dek: string, iv: string, auth_tag: string, algorithm: string, version: int }
     * @throws Exception
     */
    public function wrapDekForServer(string $dekHex, int $wrapVersion = 1): array
    {
        if (empty($dekHex) || strlen($dekHex) !== 64) {
            throw new Exception('DEK must be a 64-character hex string (32 bytes)');
        }

        $dek = hex2bin($dekHex);
        if ($dek === false || strlen($dek) !== self::KEY_LENGTH) {
            throw new Exception('Invalid DEK hex format for server wrapping');
        }

        $serverKey = $this->serverWrapKey();
        $iv = $this->secureRandom(self::IV_LENGTH);
        $tag = '';

        $wrappedDek = openssl_encrypt(
            $dek,
            self::CIPHER,
            $serverKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::AUTH_TAG_LEN
        );

        if ($wrappedDek === false) {
            throw new Exception('Server DEK wrapping failed: ' . openssl_error_string());
        }

        return [
            'wrapped_dek' => base64_encode($wrappedDek),
            'iv'          => base64_encode($iv),
            'auth_tag'    => base64_encode($tag),
            'algorithm'   => 'AES-256-GCM-SERVER',
            'version'     => $wrapVersion,
        ];
    }

    /**
     * Unwrap a DEK that was wrapped with wrapDekForServer().
     *
     * @param  string $wrappedDek Base64 wrapped DEK
     * @param  string $iv         Base64 IV
     * @param  string $authTag    Base64 auth tag
     * @return string             Hex DEK
     * @throws Exception
     */
    public function unwrapDekForServer(string $wrappedDek, string $iv, string $authTag): string
    {
        if (empty($wrappedDek) || empty($iv) || empty($authTag)) {
            throw new Exception('Server-wrapped DEK payload is incomplete');
        }

        $wrappedBinary = base64_decode($wrappedDek, true);
        $ivBinary = base64_decode($iv, true);
        $tagBinary = base64_decode($authTag, true);

        if ($wrappedBinary === false || $ivBinary === false || $tagBinary === false) {
            throw new Exception('Invalid base64 encoding in server-wrapped DEK payload');
        }

        if (strlen($ivBinary) !== self::IV_LENGTH) {
            throw new Exception('Server-wrapped DEK IV must be 12 bytes');
        }

        if (strlen($tagBinary) !== self::AUTH_TAG_LEN) {
            throw new Exception('Server-wrapped DEK auth tag must be 16 bytes');
        }

        $serverKey = $this->serverWrapKey();

        $dek = openssl_decrypt(
            $wrappedBinary,
            self::CIPHER,
            $serverKey,
            OPENSSL_RAW_DATA,
            $ivBinary,
            $tagBinary
        );

        if ($dek === false || strlen($dek) !== self::KEY_LENGTH) {
            throw new Exception('Server DEK unwrapping failed: authentication tag mismatch or corrupt data.');
        }

        return bin2hex($dek);
    }

    /**
     * Build a stable 32-byte server wrapping key from APP_KEY.
     */
    private function serverWrapKey(): string
    {
        $appKey = (string) config('app.key', '');

        if ($appKey === '') {
            throw new Exception('APP_KEY is not configured');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false || $decoded === '') {
                throw new Exception('APP_KEY base64 payload is invalid');
            }

            return hash('sha256', $decoded, true);
        }

        return hash('sha256', $appKey, true);
    }
}
