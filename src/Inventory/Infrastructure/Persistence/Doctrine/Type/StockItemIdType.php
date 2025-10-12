<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Model\StockItemId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class StockItemIdType extends Type
{
    private const NAME = 'stock_item_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'CHAR(26)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?StockItemId
    {
        if ($value === null || $value === '') {
            return null;
        }

        return StockItemId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StockItemId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected StockItemId instance');
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
