<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use App\Shared\Domain\ValueObject\LanguageCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine Custom Type for LanguageCode Value Object.
 *
 * Maps LanguageCode to VARCHAR(2) in database
 */
final class LanguageCodeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 2]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LanguageCode
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof LanguageCode) {
            return $value;
        }

        return LanguageCode::fromString($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof LanguageCode) {
            return $value->value();
        }

        return (string) $value;
    }

}
