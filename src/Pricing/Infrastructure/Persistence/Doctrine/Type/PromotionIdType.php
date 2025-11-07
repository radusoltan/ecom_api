<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Type;

use App\Pricing\Domain\ValueObject\PromotionId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class PromotionIdType extends Type
{
    private const NAME = 'promotion_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 26]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?PromotionId
    {
        if (null === $value) {
            return null;
        }

        return PromotionId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof PromotionId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected PromotionId instance');
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
