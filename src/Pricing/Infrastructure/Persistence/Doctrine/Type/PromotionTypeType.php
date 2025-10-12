<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Type;

use App\Pricing\Domain\ValueObject\PromotionType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PromotionTypeType extends Type
{
    private const NAME = 'promotion_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PromotionType
    {
        if ($value === null) {
            return null;
        }

        return PromotionType::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof PromotionType) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected PromotionType instance');
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
