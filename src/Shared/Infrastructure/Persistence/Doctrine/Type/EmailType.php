<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class EmailType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 255]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Email
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof Email) {
            return $value;
        }

        try {
            return Email::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert value to email: ' . $e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Email) {
            return $value->value();
        }

        throw new ConversionException('Could not convert PHP value of type ' . get_debug_type($value) . ' to email');
    }
}
