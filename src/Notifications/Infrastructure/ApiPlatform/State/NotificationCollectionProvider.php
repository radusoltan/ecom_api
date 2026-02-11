<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Provider for notification collection (GET /api/v1/notifications).
 *
 * @implements ProviderInterface<NotificationEntity>
 */
final readonly class NotificationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    /**
     * @return array<NotificationEntity>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Get tenant ID from context (set by TenantContextProvider)
        $tenantIdString = $context['tenant_id'] ?? null;
        if (null === $tenantIdString) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);

        // Get all notifications for tenant
        $notifications = $this->notificationRepository->findByTenant($tenantId);

        // Convert domain models to entities
        return array_map(
            fn ($notification) => NotificationEntity::fromDomainModel($notification),
            $notifications
        );
    }
}
