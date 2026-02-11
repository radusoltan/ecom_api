<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\Model\PaymentId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for PaymentId value object.
 *
 * Converts between PaymentId (PHP) and UUID string (database).
 */
final class PaymentIdType extends Type
{
    private const TYPE_NAME = 'payment_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PaymentId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof PaymentId) {
            return $value;
        }

        try {
            return PaymentId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'UUID v4 string', $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PaymentId) {
            return $value->toString();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', PaymentId::class]);
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
