<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\ValueObject\ConsentId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class ConsentIdType extends Type
{
    private const TYPE_NAME = 'consent_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 26]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ConsentId
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ConsentId) {
            return $value;
        }

        try {
            return ConsentId::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'ULID string');
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ConsentId) {
            return $value->toString();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', ConsentId::class]);
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
