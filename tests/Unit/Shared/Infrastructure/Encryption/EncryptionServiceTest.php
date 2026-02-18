<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Encryption;

use App\Shared\Infrastructure\Encryption\BlindIndexService;
use App\Shared\Infrastructure\Encryption\EncryptionKeyProvider;
use App\Shared\Infrastructure\Encryption\EncryptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the encryption infrastructure services.
 *
 * All three classes are covered here because they are tightly coupled
 * (EncryptionService and BlindIndexService both depend on EncryptionKeyProvider)
 * and instantiating a real KeyProvider is cheap — no mocks needed.
 *
 * Key material is hard-coded so the suite is fully deterministic and never
 * hits the filesystem or environment variables.
 */
#[CoversClass(EncryptionService::class)]
#[CoversClass(BlindIndexService::class)]
#[CoversClass(EncryptionKeyProvider::class)]
final class EncryptionServiceTest extends TestCase
{
    /**
     * 32-byte encryption key, hex-encoded (64 hex chars).
     * Generated once via: sodium_bin2hex(sodium_crypto_secretbox_keygen())
     */
    private const ENCRYPTION_KEY = 'bd3d5dbf5f588a8fb6ddaa55b6a71847ceb98b8832ee4aa59476f52e94c301a3';

    /**
     * 32-byte blind index key, hex-encoded (64 hex chars).
     * Generated once via: sodium_bin2hex(random_bytes(32))
     */
    private const BLIND_INDEX_KEY = '5ab73f0c32502e63cf86de6dc231c3f2e80267b647945689bd235d7dd758dd57';

    /**
     * A different 32-byte key used to verify decryption with the wrong key fails.
     * Generated once via: sodium_bin2hex(sodium_crypto_secretbox_keygen())
     */
    private const WRONG_ENCRYPTION_KEY = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    private EncryptionKeyProvider $keyProvider;
    private EncryptionService $encryption;
    private BlindIndexService $blindIndex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyProvider = new EncryptionKeyProvider(
            encryptionKey: self::ENCRYPTION_KEY,
            blindIndexKey: self::BLIND_INDEX_KEY,
        );

        $this->encryption = new EncryptionService($this->keyProvider);
        $this->blindIndex = new BlindIndexService($this->keyProvider);
    }

    // -------------------------------------------------------------------------
    // EncryptionKeyProvider
    // -------------------------------------------------------------------------

    #[Test]
    public function itConstructsKeyProviderWithValidKeys(): void
    {
        // Should not throw — a successfully constructed object proves validity.
        $provider = new EncryptionKeyProvider(
            encryptionKey: self::ENCRYPTION_KEY,
            blindIndexKey: self::BLIND_INDEX_KEY,
        );

        self::assertInstanceOf(EncryptionKeyProvider::class, $provider);
    }

    #[Test]
    public function itThrowsWhenEncryptionKeyIsTooShort(): void
    {
        // 16 bytes → 32 hex chars; SODIUM_CRYPTO_SECRETBOX_KEYBYTES requires 32 bytes.
        $shortKey = sodium_bin2hex(random_bytes(16)); // 32 hex chars

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ENCRYPTION_KEY must be');

        new EncryptionKeyProvider(
            encryptionKey: $shortKey,
            blindIndexKey: self::BLIND_INDEX_KEY,
        );
    }

    #[Test]
    public function itThrowsWhenEncryptionKeyIsTooLong(): void
    {
        // 64 bytes → 128 hex chars; too long.
        $longKey = sodium_bin2hex(random_bytes(64)); // 128 hex chars

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ENCRYPTION_KEY must be');

        new EncryptionKeyProvider(
            encryptionKey: $longKey,
            blindIndexKey: self::BLIND_INDEX_KEY,
        );
    }

    #[Test]
    public function itThrowsWhenBlindIndexKeyIsTooShort(): void
    {
        // 8 bytes → 16 hex chars; blind index requires 32 bytes.
        $shortBlindKey = sodium_bin2hex(random_bytes(8)); // 16 hex chars

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BLIND_INDEX_KEY must be 32 bytes');

        new EncryptionKeyProvider(
            encryptionKey: self::ENCRYPTION_KEY,
            blindIndexKey: $shortBlindKey,
        );
    }

    #[Test]
    public function itThrowsWhenBlindIndexKeyIsTooLong(): void
    {
        // 64 bytes → 128 hex chars; too long.
        $longBlindKey = sodium_bin2hex(random_bytes(64)); // 128 hex chars

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BLIND_INDEX_KEY must be 32 bytes');

        new EncryptionKeyProvider(
            encryptionKey: self::ENCRYPTION_KEY,
            blindIndexKey: $longBlindKey,
        );
    }

    // -------------------------------------------------------------------------
    // EncryptionService::encrypt
    // -------------------------------------------------------------------------

    #[Test]
    public function itEncryptPlaintextToNonEmptyString(): void
    {
        $ciphertext = $this->encryption->encrypt('hello world');

        self::assertNotEmpty($ciphertext);
    }

    #[Test]
    public function itEncryptedOutputDiffersFromInput(): void
    {
        $plaintext = 'sensitive data';
        $ciphertext = $this->encryption->encrypt($plaintext);

        self::assertNotSame($plaintext, $ciphertext);
    }

    #[Test]
    public function itProducesValidBase64Output(): void
    {
        $ciphertext = $this->encryption->encrypt('test value');

        // base64_decode with strict mode must succeed.
        self::assertNotFalse(base64_decode($ciphertext, strict: true));
    }

    #[Test]
    public function itProducesDifferentCiphertextOnEachCall(): void
    {
        // XSalsa20-Poly1305 uses a random 24-byte nonce per call, so the
        // ciphertext must differ even for identical plaintext.
        $plaintext = 'non-deterministic test';

        $first = $this->encryption->encrypt($plaintext);
        $second = $this->encryption->encrypt($plaintext);

        self::assertNotSame($first, $second);
    }

    // -------------------------------------------------------------------------
    // EncryptionService::decrypt
    // -------------------------------------------------------------------------

    #[Test]
    public function itDecryptionRoundtripRestoresOriginalString(): void
    {
        $plaintext = 'round-trip value';

        $ciphertext = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($ciphertext);

        self::assertSame($plaintext, $decrypted);
    }

    #[Test]
    public function itRoundtripPreservesUnicodeContent(): void
    {
        $plaintext = 'Héllo Wörld — UTF-8 ✓';

        $decrypted = $this->encryption->decrypt($this->encryption->encrypt($plaintext));

        self::assertSame($plaintext, $decrypted);
    }

    #[Test]
    public function itThrowsWhenDecryptingWithWrongKey(): void
    {
        $wrongKeyProvider = new EncryptionKeyProvider(
            encryptionKey: self::WRONG_ENCRYPTION_KEY,
            blindIndexKey: self::BLIND_INDEX_KEY,
        );
        $wrongKeyService = new EncryptionService($wrongKeyProvider);

        $ciphertext = $this->encryption->encrypt('secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $wrongKeyService->decrypt($ciphertext);
    }

    #[Test]
    public function itThrowsWhenDecryptingInvalidBase64(): void
    {
        $this->expectException(\RuntimeException::class);

        // The string below is not valid base64 (contains characters outside the base64 alphabet
        // and the strict flag inside decrypt() will return false).
        $this->encryption->decrypt('!!!not-valid-base64!!!');
    }

    #[Test]
    public function itThrowsWhenDecryptingTooShortPayload(): void
    {
        // A valid base64 string that decodes to fewer bytes than nonce + mac.
        $tooShort = base64_encode('short');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ciphertext too short');

        $this->encryption->decrypt($tooShort);
    }

    // -------------------------------------------------------------------------
    // EncryptionService — empty string edge case
    // -------------------------------------------------------------------------

    #[Test]
    public function itEncryptsAndDecryptsEmptyString(): void
    {
        $ciphertext = $this->encryption->encrypt('');
        $decrypted = $this->encryption->decrypt($ciphertext);

        self::assertSame('', $decrypted);
    }

    #[Test]
    public function itEncryptedEmptyStringIsNotEmpty(): void
    {
        // Even an empty plaintext produces a non-empty ciphertext (nonce + mac overhead).
        $ciphertext = $this->encryption->encrypt('');

        self::assertNotEmpty($ciphertext);
    }

    // -------------------------------------------------------------------------
    // EncryptionService::encryptJson / decryptJson
    // -------------------------------------------------------------------------

    #[Test]
    public function itJsonRoundtripRestoresOriginalArray(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 42,
            'active' => true,
        ];

        $ciphertext = $this->encryption->encryptJson($data);
        $decrypted = $this->encryption->decryptJson($ciphertext);

        self::assertSame($data, $decrypted);
    }

    #[Test]
    public function itJsonRoundtripPreservesNestedStructure(): void
    {
        $data = [
            'user' => [
                'id' => 'abc-123',
                'roles' => ['admin', 'editor'],
            ],
            'meta' => ['version' => 2],
        ];

        $decrypted = $this->encryption->decryptJson(
            $this->encryption->encryptJson($data)
        );

        self::assertSame($data, $decrypted);
    }

    #[Test]
    public function itEncryptJsonProducesNonEmptyString(): void
    {
        $ciphertext = $this->encryption->encryptJson(['key' => 'value']);

        self::assertNotEmpty($ciphertext);
    }

    #[Test]
    public function itEncryptJsonOutputDiffersFromJsonEncoded(): void
    {
        $data = ['secret' => 'value'];

        $ciphertext = $this->encryption->encryptJson($data);

        self::assertNotSame(json_encode($data), $ciphertext);
    }

    // -------------------------------------------------------------------------
    // BlindIndexService::generate
    // -------------------------------------------------------------------------

    #[Test]
    public function itGeneratesA64CharacterHexString(): void
    {
        $index = $this->blindIndex->generate('test@example.com');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $index);
    }

    #[Test]
    public function itIsDeterministic(): void
    {
        $value = 'deterministic-value';

        $first = $this->blindIndex->generate($value);
        $second = $this->blindIndex->generate($value);

        self::assertSame($first, $second);
    }

    #[Test]
    public function itIsCaseInsensitive(): void
    {
        // generate() normalises to lowercase before hashing.
        $lower = $this->blindIndex->generate('user@example.com');
        $upper = $this->blindIndex->generate('USER@EXAMPLE.COM');
        $mixed = $this->blindIndex->generate('User@Example.Com');

        self::assertSame($lower, $upper);
        self::assertSame($lower, $mixed);
    }

    #[Test]
    public function itTrimsLeadingAndTrailingWhitespace(): void
    {
        $bare = $this->blindIndex->generate('test@example.com');
        $padded = $this->blindIndex->generate('  test@example.com  ');
        $tabbed = $this->blindIndex->generate("\ttest@example.com\t");

        self::assertSame($bare, $padded);
        self::assertSame($bare, $tabbed);
    }

    #[Test]
    public function itProducesDifferentIndexesForDifferentInputs(): void
    {
        $index1 = $this->blindIndex->generate('alice@example.com');
        $index2 = $this->blindIndex->generate('bob@example.com');

        self::assertNotSame($index1, $index2);
    }

    #[Test]
    public function itProducesDifferentIndexesForSimilarInputs(): void
    {
        // Inputs that differ only by one character must hash to different values.
        $index1 = $this->blindIndex->generate('password1');
        $index2 = $this->blindIndex->generate('password2');

        self::assertNotSame($index1, $index2);
    }

    #[Test]
    public function itGeneratesIndexForEmptyString(): void
    {
        // An empty string is a valid input; the HMAC result is still 64 hex chars.
        $index = $this->blindIndex->generate('');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $index);
    }
}
