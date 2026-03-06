<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeTzImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Overrides the built-in datetimetz_immutable type to handle microsecond precision
 * returned by PostgreSQL (e.g., "2026-03-03 12:10:19.657371+02").
 */
final class MicroDateTimeTzImmutableType extends DateTimeTzImmutableType
{
    private const FORMATS_WITH_MICROSECONDS = [
        'Y-m-d H:i:s.uO',
        'Y-m-d H:i:s.uP',
    ];

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        foreach (self::FORMATS_WITH_MICROSECONDS as $format) {
            $dateTime = \DateTimeImmutable::createFromFormat($format, $value);
            if (false !== $dateTime) {
                return $dateTime;
            }
        }

        $dateTime = \DateTimeImmutable::createFromFormat($platform->getDateTimeTzFormatString(), $value);

        if (false !== $dateTime) {
            return $dateTime;
        }

        throw InvalidFormat::new($value, static::class, $platform->getDateTimeTzFormatString());
    }
}
