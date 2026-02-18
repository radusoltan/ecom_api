<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence\Doctrine\Type;

use App\Pricing\Domain\ValueObject\CouponCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class CouponCodeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?CouponCode
    {
        if (null === $value) {
            return null;
        }

        return CouponCode::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CouponCode) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected CouponCode instance');
    }

}
