<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\Doctrine\Type;

use App\Notifications\Domain\Model\NotificationType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine custom type for NotificationType enum.
 */
final class NotificationTypeType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?NotificationType
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof NotificationType) {
            return $value;
        }

        return NotificationType::from((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof NotificationType) {
            return $value->value;
        }

        return (string) $value;
    }

}
