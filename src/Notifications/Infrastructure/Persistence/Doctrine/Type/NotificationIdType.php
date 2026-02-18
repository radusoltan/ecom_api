<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\Doctrine\Type;

use App\Notifications\Domain\Model\NotificationId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine custom type for NotificationId value object.
 */
final class NotificationIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 26]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?NotificationId
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof NotificationId) {
            return $value;
        }

        return NotificationId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof NotificationId) {
            return $value->toString();
        }

        return (string) $value;
    }

}
