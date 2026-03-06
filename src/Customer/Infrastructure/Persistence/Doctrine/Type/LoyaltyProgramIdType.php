<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\LoyaltyProgramId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine Type for LoyaltyProgramId Value Object.
 *
 * Converts LoyaltyProgramId value object to/from string (UUID) database representation.
 */
final class LoyaltyProgramIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LoyaltyProgramId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof LoyaltyProgramId) {
            return $value;
        }

        return LoyaltyProgramId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof LoyaltyProgramId) {
            return $value->toString();
        }

        throw new \InvalidArgumentException('Expected LoyaltyProgramId instance');
    }
}
