<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\ConsentHistoryId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine custom type for ConsentHistoryId value object.
 */
final class ConsentHistoryIdType extends Type
{
    public const NAME = 'consent_history_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'UUID';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ConsentHistoryId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof ConsentHistoryId) {
            return $value;
        }

        return ConsentHistoryId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ConsentHistoryId) {
            return $value->toString();
        }

        return (string) $value;
    }
}
