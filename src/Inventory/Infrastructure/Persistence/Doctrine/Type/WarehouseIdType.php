<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Model\WarehouseId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class WarehouseIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'CHAR(26)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WarehouseId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return WarehouseId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof WarehouseId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected WarehouseId instance');
    }
}
