<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\Model\TransactionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for TransactionId value object.
 *
 * Converts between TransactionId (PHP) and UUID string (database).
 */
final class TransactionIdType extends Type
{
    private const TYPE_NAME = 'payment_transaction_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TransactionId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TransactionId) {
            return $value;
        }

        try {
            return TransactionId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'UUID v4 string', $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TransactionId) {
            return $value->toString();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', TransactionId::class]);
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
