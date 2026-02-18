<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Infrastructure\Encryption\EncryptionService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type that transparently encrypts/decrypts string values.
 *
 * Values are stored as base64-encoded sodium ciphertext (TEXT column).
 * On read, values are automatically decrypted back to plaintext.
 *
 * The EncryptionService is injected statically via a boot listener because
 * Doctrine types are singletons and don't support constructor injection.
 */
final class EncryptedStringType extends Type
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

    public function convertToPHPValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        if (null === self::$encryptionService) {
            return $value;
        }

        try {
            return self::$encryptionService->decrypt($value);
        } catch (\RuntimeException) {
            // Value might not be encrypted yet (pre-migration data)
            return $value;
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        if (null === self::$encryptionService) {
            return $value;
        }

        return self::$encryptionService->encrypt($value);
    }
}
