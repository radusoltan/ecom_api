<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Encryption;

/**
 * Thrown when decryption fails due to wrong key, corrupted ciphertext, or invalid format.
 *
 * Callers must handle this explicitly — never fall back to returning raw ciphertext.
 */
final class DecryptionFailedException extends \RuntimeException
{
    public static function invalidBase64(): self
    {
        return new self('Failed to base64-decode ciphertext.');
    }

    public static function tooShort(): self
    {
        return new self('Ciphertext too short — corrupted or not encrypted.');
    }

    public static function wrongKeyOrCorrupted(): self
    {
        return new self('Decryption failed — wrong key or corrupted ciphertext.');
    }

    public static function invalidJson(): self
    {
        return new self('Decrypted JSON is not a valid array.');
    }
}
