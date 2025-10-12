<?php

declare(strict_types=1);

namespace App\Returns\Infrastructure\Persistence\Doctrine\Type;

use App\Returns\Domain\ValueObject\ReturnRequestId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for ReturnRequestId value object.
 *
 * Stores as VARCHAR(36) in database (ULID format).
 */
final class ReturnRequestIdType extends Type
{
    public const NAME = 'return_request_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    /**
     * @param ReturnRequestId|null $value
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof ReturnRequestId) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', ReturnRequestId::class]);
        }

        return $value->toString();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ReturnRequestId
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ReturnRequestId) {
            return $value;
        }

        try {
            return ReturnRequestId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
