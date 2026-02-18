<?php

declare(strict_types=1);

namespace App\Notifications\Application\Query\GetNotificationsByTenant;

use App\Notifications\Application\DTO\NotificationDTO;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * GetNotificationsByTenant Query Handler.
 */
#[AsMessageHandler]
final readonly class GetNotificationsByTenantHandler
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    /**
     * @return NotificationDTO[]
     */
    public function __invoke(GetNotificationsByTenant $query): array
    {
        $notifications = $this->notificationRepository->findByTenant($query->tenantId);

        return array_map(
            fn ($notification) => NotificationDTO::fromDomainModel($notification),
            $notifications
        );
    }
}
