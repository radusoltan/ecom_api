<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Type;

use App\Pricing\Domain\Model\PriceListId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for PriceListId value object.
 */
final class PriceListIdType extends Type
{
    public const NAME = 'price_list_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PriceListId
    {
        if (null === $value || $value instanceof PriceListId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to price_list_id');
        }

        try {
            return PriceListId::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert value to price_list_id: '.$e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PriceListId) {
            return $value->toString();
        }

        throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to price_list_id');
    }
}
