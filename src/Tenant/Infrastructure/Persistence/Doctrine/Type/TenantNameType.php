<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Persistence\Doctrine\Type;

use App\Tenant\Domain\ValueObject\TenantName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class TenantNameType extends Type
{
    private const TYPE_NAME = 'tenant_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 100]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TenantName
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TenantName) {
            return $value;
        }

        try {
            return TenantName::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'valid tenant name string', $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TenantName) {
            return $value->value();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', TenantName::class]);
    }

    public function getName(): string
    {
        return self::TYPE_NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
