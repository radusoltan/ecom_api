<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\EarningRate;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine Type for EarningRate Value Object.
 *
 * Converts EarningRate value object to/from decimal database representation.
 */
final class EarningRateType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDecimalTypeDeclarationSQL([
            'precision' => 10,
            'scale' => 4,
        ]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?EarningRate
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof EarningRate) {
            return $value;
        }

        return EarningRate::fromFloat((float) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof EarningRate) {
            return number_format($value->toFloat(), 4, '.', '');
        }

        throw new \InvalidArgumentException('Expected EarningRate instance');
    }

}
