<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Model\WarehouseId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class WarehouseIdType extends Type
{
    private const NAME = 'warehouse_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'CHAR(26)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WarehouseId
    {
        if ($value === null || $value === '') {
            return null;
        }

        return WarehouseId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof WarehouseId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected WarehouseId instance');
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
