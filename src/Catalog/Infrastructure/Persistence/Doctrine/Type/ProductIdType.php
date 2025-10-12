<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Model\ProductId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ProductIdType extends Type
{
    private const NAME = 'product_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'CHAR(26)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ProductId
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ProductId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ProductId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected ProductId instance');
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
