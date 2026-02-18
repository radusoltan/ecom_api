<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\DeletionRequestId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Custom Type for DeletionRequestId Value Object.
 *
 * Maps DeletionRequestId to UUID (VARCHAR(36)) in database.
 */
final class DeletionRequestIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DeletionRequestId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof DeletionRequestId) {
            return $value;
        }

        return DeletionRequestId::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof DeletionRequestId) {
            return $value->toString();
        }

        return (string) $value;
    }

}
