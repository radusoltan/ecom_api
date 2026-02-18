<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Model\WarehouseCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class WarehouseCodeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(10)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WarehouseCode
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return WarehouseCode::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof WarehouseCode) {
            return $value->value();
        }

        throw new \InvalidArgumentException('Expected WarehouseCode instance');
    }
}
