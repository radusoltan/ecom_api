<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\ValueObject\ConsentPurpose;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class ConsentPurposeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ConsentPurpose
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof ConsentPurpose) {
            return $value;
        }

        try {
            return ConsentPurpose::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert value to consent_purpose: '.$e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ConsentPurpose) {
            return $value->value();
        }

        throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to consent_purpose');
    }
}
