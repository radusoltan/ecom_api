<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class TenantIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TenantId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TenantId) {
            return $value;
        }

        try {
            return TenantId::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert database value to type: ' . $e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TenantId) {
            return $value->toString();
        }

        throw new ConversionException('Could not convert PHP value of type ' . get_debug_type($value) . ' to expected type');
    }
}
