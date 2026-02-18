<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\ConsentType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine custom type for ConsentType enum.
 */
final class ConsentTypeType extends Type
{
    public const NAME = 'consent_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ConsentType
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof ConsentType) {
            return $value;
        }

        return ConsentType::from((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ConsentType) {
            return $value->value;
        }

        return (string) $value;
    }
}
