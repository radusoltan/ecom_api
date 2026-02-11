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
    private const TYPE_NAME = 'payment_transaction_status';

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
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                'One of: ' . implode(', ', array_map(fn(TransactionStatus $case) => $case->value, TransactionStatus::cases())),
                $e
            );
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

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', TransactionStatus::class]);
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
