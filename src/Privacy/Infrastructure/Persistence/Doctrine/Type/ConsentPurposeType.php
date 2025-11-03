<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\ValueObject\ConsentPurpose;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class ConsentPurposeType extends Type
{
    private const TYPE_NAME = 'consent_purpose';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?ConsentPurpose
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof ConsentPurpose) {
            return $value;
        }

        try {
            return ConsentPurpose::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'valid consent purpose');
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ConsentPurpose) {
            return $value->value();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', ConsentPurpose::class]);
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
