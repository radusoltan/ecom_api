<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\Persistence\Doctrine\Type;

use App\Notifications\Domain\Model\NotificationStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Doctrine custom type for NotificationStatus enum.
 */
final class NotificationStatusType extends Type
{
    private const NAME = 'notification_status';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?NotificationStatus
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof NotificationStatus) {
            return $value;
        }

        return NotificationStatus::from((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof NotificationStatus) {
            return $value->value;
        }

        return (string) $value;
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
