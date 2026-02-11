<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command\SendNotification;

use App\Notifications\Domain\Model\Notification;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * SendNotification Command Handler.
 */
#[AsMessageHandler]
final readonly class SendNotificationHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository
    ) {
    }

    public function __invoke(SendNotification $command): void
    {
        $notification = Notification::create(
            id: $command->id,
            tenantId: $command->tenantId,
            type: $command->type,
            recipientEmail: $command->recipientEmail,
            recipientPhone: $command->recipientPhone,
            subject: $command->subject,
            body: $command->body
        );

        $this->notificationRepository->save($notification);
    }
}
