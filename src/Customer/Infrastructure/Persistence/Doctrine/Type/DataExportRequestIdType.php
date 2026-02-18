<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\DataExportRequestId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine Type for DataExportRequestId Value Object.
 */
final class DataExportRequestIdType extends Type
{
    public const NAME = 'data_export_request_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 36]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DataExportRequestId
    {
        if (null === $value) {
            return null;
        }

        return DataExportRequestId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof DataExportRequestId) {
            throw new \InvalidArgumentException('Expected DataExportRequestId instance');
        }

        return $value->toString();
    }

}
