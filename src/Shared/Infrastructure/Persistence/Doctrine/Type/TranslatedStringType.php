<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\TranslatedString;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TranslatedStringType extends Type
{
    public const NAME = 'translated_string';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TranslatedString
    {
        if (null === $value) {
            return null;
        }

        $data = is_string($value) ? json_decode($value, true) : $value;

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Expected JSON object for TranslatedString');
        }

        return TranslatedString::fromArray($data);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof TranslatedString) {
            throw new \InvalidArgumentException('Expected TranslatedString instance');
        }

        return json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
