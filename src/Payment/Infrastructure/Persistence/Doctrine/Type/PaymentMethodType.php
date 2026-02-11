<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence\Doctrine\Type;

use App\Payment\Domain\Model\PaymentMethod;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for PaymentMethod enum.
 *
 * Converts between PaymentMethod (PHP) and string (database).
 */
final class PaymentMethodType extends Type
{
    private const TYPE_NAME = 'payment_method';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PaymentMethod
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof PaymentMethod) {
            return $value;
        }

        try {
            return PaymentMethod::from((string) $value);
        } catch (\ValueError $e) {
            throw ConversionException::conversionFailedFormat(
                $value,
                $this->getName(),
                'One of: ' . implode(', ', array_map(fn(PaymentMethod $case) => $case->value, PaymentMethod::cases())),
                $e
            );
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PaymentMethod) {
            return $value->value;
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', PaymentMethod::class]);
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
