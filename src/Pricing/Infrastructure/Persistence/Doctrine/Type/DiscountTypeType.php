<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Type;

use App\Pricing\Domain\ValueObject\DiscountType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class DiscountTypeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DiscountType
    {
        if (null === $value) {
            return null;
        }

        return DiscountType::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof DiscountType) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected DiscountType instance');
    }

}
