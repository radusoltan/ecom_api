<?php

declare(strict_types=1);

namespace App\Customer\Infrastructure\Persistence\Doctrine\Type;

use App\Customer\Domain\ValueObject\ExportStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine Type for ExportStatus Enum.
 */
final class ExportStatusType extends Type
{
    public const NAME = 'export_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ExportStatus
    {
        if (null === $value) {
            return null;
        }

        return ExportStatus::from((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ExportStatus) {
            throw new \InvalidArgumentException('Expected ExportStatus instance');
        }

        return $value->value;
    }

}
