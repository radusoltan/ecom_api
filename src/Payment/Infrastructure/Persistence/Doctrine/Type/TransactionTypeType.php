<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\Model\TransactionType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for TransactionType enum.
 *
 * Converts between TransactionType (PHP) and string (database).
 */
final class TransactionTypeType extends Type
{
    private const TYPE_NAME = 'payment_transaction_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TransactionType
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TransactionType) {
            return $value;
        }

        try {
            return TransactionType::from((string) $value);
        } catch (\ValueError $e) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                'One of: ' . implode(', ', array_map(fn(TransactionType $case) => $case->value, TransactionType::cases())),
                $e
            );
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TransactionType) {
            return $value->value;
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', TransactionType::class]);
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
