<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Infrastructure\Encryption\EncryptionService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type that transparently encrypts/decrypts JSON values.
 *
 * PHP arrays are JSON-encoded, then encrypted. On read, values are
 * decrypted and JSON-decoded back to arrays.
 *
 * Stored as TEXT columns containing base64-encoded sodium ciphertext.
 */
final class EncryptedJsonType extends Type
{
    private static ?EncryptionService $encryptionService = null;

    public static function setEncryptionService(EncryptionService $service): void
    {
        self::$encryptionService = $service;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?array
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (null === self::$encryptionService) {
            // Fallback: try plain JSON decode for unencrypted data
            $decoded = json_decode($value, true);

            return \is_array($decoded) ? $decoded : null;
        }

        try {
            return self::$encryptionService->decryptJson($value);
        } catch (\RuntimeException) {
            // Value might not be encrypted yet (pre-migration data)
            $decoded = json_decode($value, true);

            return \is_array($decoded) ? $decoded : null;
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Expected array value for EncryptedJsonType.');
        }

        if (null === self::$encryptionService) {
            return json_encode($value, \JSON_THROW_ON_ERROR);
        }

        return self::$encryptionService->encryptJson($value);
    }
}
