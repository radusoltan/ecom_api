<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Type;

use App\Tax\Domain\Model\TaxRate;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type for TaxRate value object.
 *
 * Maps TaxRate percentage (0.00-100.00) to database DECIMAL(5,2) column.
 */
final class TaxRateType extends Type
{
    public const NAME = 'tax_rate';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'NUMERIC(5,2)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaxRate
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TaxRate) {
            return $value;
        }

        return TaxRate::fromPercentage((float) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TaxRate) {
            return (string) $value->percentage();
        }

        throw new \InvalidArgumentException('Expected TaxRate instance');
    }
}
