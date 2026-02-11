<?php

declare(strict_types=1);

namespace App\Notifications\Domain\Model;

/**
 * NotificationType Enum.
 *
 * Supported notification delivery channels.
 */
enum NotificationType: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case WEBHOOK = 'webhook';
    case PUSH = 'push';

    public function isEmail(): bool
    {
        return self::EMAIL === $this;
    }

    public function isSms(): bool
    {
        return self::SMS === $this;
    }

    public function isWebhook(): bool
    {
        return self::WEBHOOK === $this;
    }

    public function isPush(): bool
    {
        return self::PUSH === $this;
    }
}
