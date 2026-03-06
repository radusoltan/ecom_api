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
        return 'UUID';
    }

    /**
     * @param ReturnRequestId|null $value
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ReturnRequestId) {
            throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to expected type');
        }

        return $value->toString();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ReturnRequestId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof ReturnRequestId) {
            return $value;
        }

        try {
            return ReturnRequestId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert database value to type: '.$e->getMessage(), 0, $e);
        }
    }
}
