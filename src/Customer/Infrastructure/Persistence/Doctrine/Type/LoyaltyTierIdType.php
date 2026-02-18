<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\LoyaltyTierId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine Type for LoyaltyTierId Value Object.
 *
 * Converts LoyaltyTierId value object to/from string (UUID) database representation.
 */
final class LoyaltyTierIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LoyaltyTierId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof LoyaltyTierId) {
            return $value;
        }

        return LoyaltyTierId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof LoyaltyTierId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected LoyaltyTierId instance');
    }

}
