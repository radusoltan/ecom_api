<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\DeletionStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Custom Type for DeletionStatus Enum.
 *
 * Maps DeletionStatus enum to VARCHAR(20) in database.
 */
final class DeletionStatusType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DeletionStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof DeletionStatus) {
            return $value;
        }

        return DeletionStatus::from($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof DeletionStatus) {
            return $value->value;
        }

        return (string) $value;
    }
}
