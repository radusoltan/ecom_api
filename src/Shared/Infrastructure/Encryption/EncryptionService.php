<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Encryption;

/**
 * Encrypts and decrypts data using sodium_crypto_secretbox (XSalsa20-Poly1305 AEAD).
 *
 * Ciphertext format: base64(nonce + ciphertext)
 * The nonce (24 bytes) is prepended to the ciphertext for self-contained storage.
 */
final readonly class EncryptionService
{
    private string $key;

    public function __construct(EncryptionKeyProvider $keyProvider)
    {
        $this->key = $keyProvider->getEncryptionKey();
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);

        if (false === $decoded) {
            throw new \RuntimeException('Failed to base64-decode ciphertext.');
        }

        if (\strlen($decoded) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + \SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new \RuntimeException('Ciphertext too short — corrupted or not encrypted.');
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if (false === $plaintext) {
            throw new \RuntimeException('Decryption failed — wrong key or corrupted ciphertext.');
        }

        return $plaintext;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function encryptJson(array $data): string
    {
        $json = json_encode($data, \JSON_THROW_ON_ERROR);

        return $this->encrypt($json);
    }

    /**
     * @return array<string, mixed>
     */
    public function decryptJson(string $encoded): array
    {
        $json = $this->decrypt($encoded);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($data)) {
            throw new \RuntimeException('Decrypted JSON is not an array.');
        }

        return $data;
    }
}
