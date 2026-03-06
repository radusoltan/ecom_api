<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\ValueObject\RefundId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for RefundId value object.
 *
 * Converts between RefundId (PHP) and UUID string (database).
 */
final class RefundIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RefundId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof RefundId) {
            return $value;
        }

        try {
            return RefundId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert database value to type: '.$e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof RefundId) {
            return $value->toString();
        }

        throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to expected type');
    }
}
