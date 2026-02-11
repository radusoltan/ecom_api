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
    private const NAME = 'notification_type';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 20]);
    }

    /**
     * @param mixed $value
     */
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

    /**
     * @param mixed $value
     */
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

    public function getName(): string
    {
        return self::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
