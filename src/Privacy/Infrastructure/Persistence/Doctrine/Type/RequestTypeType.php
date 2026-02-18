<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\ValueObject\RequestType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class RequestTypeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RequestType
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof RequestType) {
            return $value;
        }

        try {
            return RequestType::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert database value to type: ' . $e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof RequestType) {
            return $value->value();
        }

        throw new ConversionException('Could not convert PHP value of type ' . get_debug_type($value) . ' to expected type');
    }
}
