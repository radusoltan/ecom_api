<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use App\Notifications\Domain\Repository\NotificationRepositoryInterface;
use App\Notifications\Infrastructure\Persistence\Doctrine\Entity\NotificationEntity;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Provider for notification collection (GET /api/v1/notifications).
 *
 * @implements ProviderInterface<object>
 */
final readonly class NotificationCollectionProvider implements ProviderInterface
{
    public function __construct(
        private NotificationRepositoryInterface $notificationRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array
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
        $entities = array_map(
            fn ($notification) => NotificationEntity::fromDomainModel($notification),
            $notifications
        );

        // Support pagination via API Platform
        $page = (int) ($context['filters']['page'] ?? 1);
        $itemsPerPage = $operation->getPaginationItemsPerPage() ?? 30;
        $offset = ($page - 1) * $itemsPerPage;

        return new ArrayPaginator($entities, $offset, $itemsPerPage);
    }
}
