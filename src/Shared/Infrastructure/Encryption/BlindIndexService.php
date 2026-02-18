<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Encryption;

/**
 * Generates deterministic blind indexes using HMAC-SHA256.
 *
 * Blind indexes allow searching encrypted fields without decrypting them.
 * The same plaintext always produces the same index (deterministic), but the
 * original value cannot be recovered from the index (one-way).
 */
final readonly class BlindIndexService
{
    private string $key;

    public function __construct(EncryptionKeyProvider $keyProvider)
    {
        $this->key = $keyProvider->getBlindIndexKey();
    }

    /**
     * Generate a blind index for a given value.
     *
     * @param string $value The plaintext value to index
     *
     * @return string 64-character hex string (SHA-256 output)
     */
    public function generate(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), $this->key);
    }
}
