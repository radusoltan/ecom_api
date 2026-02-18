<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\Model\TransactionStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for TransactionStatus enum.
 *
 * Converts between TransactionStatus (PHP) and string (database).
 */
final class TransactionStatusType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 10]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TransactionStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TransactionStatus) {
            return $value;
        }

        try {
            return TransactionStatus::from((string) $value);
        } catch (\ValueError $e) {
            throw new ConversionException('Could not convert database value to type: '.$e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TransactionStatus) {
            return $value->value;
        }

        throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to expected type');
    }
}
