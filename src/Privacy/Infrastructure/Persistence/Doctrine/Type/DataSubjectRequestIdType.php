<?php

declare(strict_types=1);

namespace App\Privacy\Infrastructure\Persistence\Doctrine\Type;

use App\Privacy\Domain\ValueObject\DataSubjectRequestId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class DataSubjectRequestIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 26]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DataSubjectRequestId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof DataSubjectRequestId) {
            return $value;
        }

        try {
            return DataSubjectRequestId::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw new ConversionException('Could not convert value to data_subject_request_id: '.$e->getMessage(), 0, $e);
        }
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof DataSubjectRequestId) {
            return $value->toString();
        }

        throw new ConversionException('Could not convert PHP value of type '.get_debug_type($value).' to data_subject_request_id');
    }
}
