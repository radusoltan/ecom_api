<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\LanguageCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class LanguageCodeType extends Type
{
    public const NAME = 'language_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 2]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?LanguageCode
    {
        if (null === $value) {
            return null;
        }

        return LanguageCode::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof LanguageCode) {
            throw new \InvalidArgumentException('Expected LanguageCode instance');
        }

        return $value->toString();
    }

}
