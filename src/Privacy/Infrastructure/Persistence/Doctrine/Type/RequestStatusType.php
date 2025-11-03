<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\ValueObject\RequestStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class RequestStatusType extends Type
{
    private const TYPE_NAME = 'request_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 50]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?RequestStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof RequestStatus) {
            return $value;
        }

        try {
            return RequestStatus::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailedFormat($value, $this->getName(), 'valid request status');
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof RequestStatus) {
            return $value->value();
        }

        throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', RequestStatus::class]);
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
