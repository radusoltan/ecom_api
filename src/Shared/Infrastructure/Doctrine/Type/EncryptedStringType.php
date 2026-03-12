<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Infrastructure\Encryption\DecryptionFailedException;
use App\Shared\Infrastructure\Encryption\EncryptionService;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
    private const MASKED_VALUE = '***encrypted***';

    private static ?EncryptionService $encryptionService = null;
    private static LoggerInterface $logger;

    public static function setEncryptionService(EncryptionService $service): void
    {
        self::$encryptionService = $service;
    }

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
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
        } catch (DecryptionFailedException $e) {
            // SECURITY: Never return raw ciphertext or plaintext on failure.
            // Log the error and return a masked placeholder.
            $logger = self::$logger ?? new NullLogger();
            $logger->error('Decryption failed for encrypted string field', [
                'error' => $e->getMessage(),
            ]);

            return self::MASKED_VALUE;
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
