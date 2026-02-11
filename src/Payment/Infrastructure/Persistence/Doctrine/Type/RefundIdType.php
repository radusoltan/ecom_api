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
    private const TYPE_NAME = 'refund_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
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
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'UUID v4 string', $e);
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

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', RefundId::class]);
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
