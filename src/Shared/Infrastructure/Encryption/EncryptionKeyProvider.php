<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Encryption;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides cryptographic keys from environment (backed by Symfony Secrets Vault).
 */
final readonly class EncryptionKeyProvider
{
    private string $encryptionKeyBinary;
    private string $blindIndexKeyBinary;

    public function __construct(
        #[Autowire(env: 'ENCRYPTION_KEY')]
        string $encryptionKey,
        #[Autowire(env: 'BLIND_INDEX_KEY')]
        string $blindIndexKey,
    ) {
        $this->encryptionKeyBinary = sodium_hex2bin($encryptionKey);
        $this->blindIndexKeyBinary = sodium_hex2bin($blindIndexKey);

        if (\SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($this->encryptionKeyBinary)) {
            throw new \InvalidArgumentException(sprintf(
                'ENCRYPTION_KEY must be %d bytes (hex-encoded). Got %d bytes.',
                \SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                \strlen($this->encryptionKeyBinary)
            ));
        }

        if (32 !== \strlen($this->blindIndexKeyBinary)) {
            throw new \InvalidArgumentException('BLIND_INDEX_KEY must be 32 bytes (hex-encoded).');
        }
    }

    public function getEncryptionKey(): string
    {
        return $this->encryptionKeyBinary;
    }

    public function getBlindIndexKey(): string
    {
        return $this->blindIndexKeyBinary;
    }
}
