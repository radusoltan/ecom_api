<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Model\Quantity;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class QuantityType extends Type
{
    private const NAME = 'quantity';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'INTEGER';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Quantity
    {
        if ($value === null) {
            return null;
        }

        return Quantity::fromInt((int) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Quantity) {
            return $value->value();
        }

        throw new \InvalidArgumentException('Expected Quantity instance');
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
