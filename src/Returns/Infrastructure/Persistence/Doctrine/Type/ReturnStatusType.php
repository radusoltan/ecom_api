<?php

declare(strict_types=1);

namespace App\Returns\Infrastructure\Persistence\Doctrine\Type;

use App\Returns\Domain\ValueObject\ReturnStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Custom Doctrine type for ReturnStatus value object.
 *
 * Stores as VARCHAR(20) in database.
 */
final class ReturnStatusType extends Type
{
    public const NAME = 'return_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    /**
     * @param ReturnStatus|null $value
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof ReturnStatus) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', ReturnStatus::class]);
        }

        return $value->value();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ReturnStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof ReturnStatus) {
            return $value;
        }

        try {
            return ReturnStatus::fromString((string) $value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
