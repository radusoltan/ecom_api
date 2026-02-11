<?php

declare(strict_types=1);

namespace App\Notifications\Application\Command\RetryNotification;

use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * RetryNotification Command Handler.
 */
#[AsMessageHandler]
final readonly class RetryNotificationHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository
    ) {
    }

    public function __invoke(RetryNotification $command): void
    {
        $notification = $this->notificationRepository->findById($command->id, $command->tenantId);

        if (null === $notification) {
            throw new \DomainException(sprintf(
                'Notification with ID "%s" not found for tenant "%s"',
                $command->id->toString(),
                $command->tenantId->toString()
            ));
        }

        $notification->retry();
        $this->notificationRepository->save($notification);
    }
}
