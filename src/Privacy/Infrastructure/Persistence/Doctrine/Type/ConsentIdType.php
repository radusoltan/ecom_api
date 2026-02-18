<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\ValueObject\ConsentId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class ConsentIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 26]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ConsentId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof ConsentId) {
            return $value;
        }

        try {
            return ConsentId::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert value to consent_id: ' . $e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof ConsentId) {
            return $value->toString();
        }

        throw new ConversionException('Could not convert PHP value of type ' . get_debug_type($value) . ' to consent_id');
    }
}
