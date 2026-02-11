<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\Model\PaymentStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for PaymentStatus enum.
 *
 * Converts between PaymentStatus (PHP) and string (database).
 */
final class PaymentStatusType extends Type
{
    private const TYPE_NAME = 'payment_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PaymentStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof PaymentStatus) {
            return $value;
        }

        try {
            return PaymentStatus::from((string) $value);
        } catch (\ValueError $e) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                'One of: ' . implode(', ', array_map(fn(PaymentStatus $case) => $case->value, PaymentStatus::cases())),
                $e
            );
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PaymentStatus) {
            return $value->value;
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', PaymentStatus::class]);
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
