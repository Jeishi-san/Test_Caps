<?php

namespace Tests\Unit\Services\Stego;

use App\Services\Stego\CryptoService;
use Tests\TestCase;

/**
 * Unit tests for DEK envelope key wrapping/unwrapping.
 */
class CryptoEnvelopeTest extends TestCase
{
    private CryptoService $crypto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crypto = new CryptoService();
    }

    /**
     * Test DEK generation produces 64-char hex string.
     */
    public function test_generate_dek_produces_64_char_hex(): void
    {
        $dek = $this->crypto->generateDEK();

        $this->assertIsString($dek);
        $this->assertEquals(64, strlen($dek));
        $this->assertTrue(ctype_xdigit($dek), 'DEK should be valid hex');
    }

    /**
     * Test wrap/unwrap round-trip preserves DEK.
     */
    public function test_wrap_unwrap_roundtrip(): void
    {
        $dek = $this->crypto->generateDEK();
        $masterKey = $this->crypto->deriveMasterKey('test_password')['masterKey'];

        // Wrap
        $wrapped = $this->crypto->wrapDekForUser($dek, $masterKey);

        $this->assertArrayHasKey('wrapped_dek', $wrapped);
        $this->assertArrayHasKey('iv', $wrapped);
        $this->assertArrayHasKey('auth_tag', $wrapped);
        $this->assertEquals('AES-256-GCM', $wrapped['algorithm']);
        $this->assertEquals(1, $wrapped['version']);

        // Unwrap
        $unwrapped = $this->crypto->unwrapDekForUser(
            $wrapped['wrapped_dek'],
            $wrapped['iv'],
            $wrapped['auth_tag'],
            $masterKey
        );

        $this->assertEquals($dek, $unwrapped);
    }

    /**
     * Test unwrap fails with wrong master key (auth tag mismatch).
     */
    public function test_unwrap_fails_with_wrong_master_key(): void
    {
        $dek = $this->crypto->generateDEK();
        $masterKey1 = $this->crypto->deriveMasterKey('password1')['masterKey'];
        $masterKey2 = $this->crypto->deriveMasterKey('password2')['masterKey'];

        $wrapped = $this->crypto->wrapDekForUser($dek, $masterKey1);

        // Attempt unwrap with wrong key
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('authentication tag mismatch');

        $this->crypto->unwrapDekForUser(
            $wrapped['wrapped_dek'],
            $wrapped['iv'],
            $wrapped['auth_tag'],
            $masterKey2
        );
    }

    /**
     * Test unwrap fails with corrupted wrapped DEK.
     */
    public function test_unwrap_fails_with_corrupted_data(): void
    {
        $masterKey = $this->crypto->deriveMasterKey('test_password')['masterKey'];

        // Invalid base64
        $this->expectException(\Exception::class);

        $this->crypto->unwrapDekForUser(
            'not_valid_base64!!!',
            base64_encode('iv_12_bytes_xxx'),
            base64_encode('tag_16_bytes_xxxx'),
            $masterKey
        );
    }

    /**
     * Test different master keys produce different wrapped DEKs for same DEK.
     */
    public function test_different_master_keys_produce_different_wraps(): void
    {
        $dek = $this->crypto->generateDEK();
        $key1 = $this->crypto->deriveMasterKey('password1')['masterKey'];
        $key2 = $this->crypto->deriveMasterKey('password2')['masterKey'];

        $wrap1 = $this->crypto->wrapDekForUser($dek, $key1);
        $wrap2 = $this->crypto->wrapDekForUser($dek, $key2);

        // Wrapped DEK ciphertexts should differ
        $this->assertNotEquals($wrap1['wrapped_dek'], $wrap2['wrapped_dek']);

        // But both should unwrap to same DEK
        $unwrap1 = $this->crypto->unwrapDekForUser($wrap1['wrapped_dek'], $wrap1['iv'], $wrap1['auth_tag'], $key1);
        $unwrap2 = $this->crypto->unwrapDekForUser($wrap2['wrapped_dek'], $wrap2['iv'], $wrap2['auth_tag'], $key2);

        $this->assertEquals($unwrap1, $unwrap2);
        $this->assertEquals($dek, $unwrap1);
    }

    /**
     * Test wrap rejects invalid DEK lengths.
     */
    public function test_wrap_rejects_invalid_dek(): void
    {
        $masterKey = $this->crypto->deriveMasterKey('test_password')['masterKey'];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('64-character hex string');

        $this->crypto->wrapDekForUser('tooshort', $masterKey);
    }

    /**
     * Test wrap rejects invalid master key lengths.
     */
    public function test_wrap_rejects_invalid_master_key(): void
    {
        $dek = $this->crypto->generateDEK();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('64-character hex string');

        $this->crypto->wrapDekForUser($dek, 'tooshort');
    }
}
