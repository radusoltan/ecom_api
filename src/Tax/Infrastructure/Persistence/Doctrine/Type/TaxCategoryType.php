<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence\Doctrine\Type;

use App\Tax\Domain\Model\TaxCategory;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Type for TaxCategory enum.
 *
 * Maps TaxCategory enum to database VARCHAR(20) column.
 * Valid values: standard, reduced, super_reduced, zero, exempt
 */
final class TaxCategoryType extends Type
{
    public const NAME = 'tax_category';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(20)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TaxCategory
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TaxCategory) {
            return $value;
        }

        return TaxCategory::from((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TaxCategory) {
            return $value->value;
        }

        throw new \InvalidArgumentException('Expected TaxCategory instance');
    }
}
