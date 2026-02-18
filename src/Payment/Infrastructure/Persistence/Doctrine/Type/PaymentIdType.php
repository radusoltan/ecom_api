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
            throw new ConversionException('Could not convert database value to type: '.$e->getMessage(), 0, $e);
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

        throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to expected type');
    }
}
