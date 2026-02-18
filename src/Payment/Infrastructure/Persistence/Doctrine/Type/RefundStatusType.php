<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\ValueObject\RefundStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for RefundStatus value object.
 *
 * Converts between RefundStatus (PHP) and string (database).
 */
final class RefundStatusType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RefundStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof RefundStatus) {
            return $value;
        }

        try {
            return RefundStatus::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert database value to type: '.$e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof RefundStatus) {
            return $value->value();
        }

        throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to expected type');
    }
}
