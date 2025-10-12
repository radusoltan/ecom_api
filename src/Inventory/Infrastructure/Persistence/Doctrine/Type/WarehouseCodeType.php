<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Model\WarehouseCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class WarehouseCodeType extends Type
{
    private const NAME = 'warehouse_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(10)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?WarehouseCode
    {
        if ($value === null || $value === '') {
            return null;
        }

        return WarehouseCode::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof WarehouseCode) {
            return $value->value();
        }

        throw new \InvalidArgumentException('Expected WarehouseCode instance');
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
