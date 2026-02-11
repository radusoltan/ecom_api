<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Notifications\Domain\Model\NotificationId;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider for single notification (GET /api/v1/notifications/{id}).
 *
 * @implements ProviderInterface<NotificationEntity>
 */
final readonly class NotificationItemProvider implements ProviderInterface
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $id = $uriVariables['id'] ?? null;

        if (null === $id) {
            throw new NotFoundHttpException('Notification ID is required');
        }

        // Get tenant ID from context (set by TenantContextProvider)
        $tenantIdString = $context['tenant_id'] ?? null;
        if (null === $tenantIdString) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);
        $notificationId = NotificationId::fromString($id);

        $notification = $this->notificationRepository->findById($notificationId, $tenantId);

        if (null === $notification) {
            throw new NotFoundHttpException(sprintf('Notification with ID "%s" not found', $id));
        }

        // Verify tenant ownership
        if (!$notification->tenantId()->equals($tenantId)) {
            throw new NotFoundHttpException(sprintf('Notification with ID "%s" not found', $id));
        }

        // Convert domain model to entity
        return NotificationEntity::fromDomainModel($notification);
    }
}
