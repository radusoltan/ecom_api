<?php

declare(strict_types=1);

namespace App\Tenant\Infrastructure\Persistence\Doctrine\Type;

use App\Tenant\Domain\ValueObject\TenantStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class TenantStatusType extends Type
{
    private const TYPE_NAME = 'tenant_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?TenantStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof TenantStatus) {
            return $value;
        }

        try {
            return TenantStatus::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), '"active" or "inactive"', $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof TenantStatus) {
            return $value->value();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', TenantStatus::class]);
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
