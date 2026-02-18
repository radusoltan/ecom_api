<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Model\WarehouseName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class WarehouseNameType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(100)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WarehouseName
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return WarehouseName::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof WarehouseName) {
            return $value->value();
        }

        throw new \InvalidArgumentException('Expected WarehouseName instance');
    }

}
