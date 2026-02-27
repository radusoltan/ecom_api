<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider for fulfillment collection (GET /api/fulfillments).
 */
final readonly class FulfillmentCollectionProvider implements ProviderInterface
{
    public function __construct(
        private FulfillmentRepositoryInterface $fulfillmentRepository,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();

        // Get tenant ID from context or X-Tenant-ID header
        $tenantIdString = $context['tenant_id']
            ?? $request?->headers->get('X-Tenant-ID');
        if (null === $tenantIdString) {
            throw new \RuntimeException('Tenant ID not found in context');
        }
        $tenantId = TenantId::fromString($tenantIdString);

        // Get filter parameters (support both "orderId" and "order.id" for API Platform compatibility)
        $orderId = $request?->query->get('orderId') ?? $request?->query->get('order_id');
        $warehouseId = $request?->query->get('warehouseId');
        $status = $request?->query->get('status');

        $fulfillments = [];

        // Filter by specific criteria if provided
        if (null !== $orderId) {
            $orderId = \App\Order\Domain\Model\OrderId::fromString($orderId);
            $fulfillment = $this->fulfillmentRepository->findByOrderId($orderId);
            $fulfillments = null !== $fulfillment ? [$fulfillment] : [];
        } elseif (null !== $warehouseId) {
            $warehouseId = \App\Inventory\Domain\Model\WarehouseId::fromString($warehouseId);
            $fulfillments = $this->fulfillmentRepository->findByWarehouse($warehouseId, $tenantId);
        } elseif (null !== $status) {
            $status = \App\Order\Domain\ValueObject\FulfillmentStatus::fromString($status);
            $fulfillments = $this->fulfillmentRepository->findByStatus($status, $tenantId);
        } else {
            // Return all fulfillments for tenant
            $fulfillments = $this->fulfillmentRepository->findByTenant($tenantId);
        }

        // Convert domain models to entities
        return array_map(
            fn ($fulfillment) => \App\Order\Infrastructure\Persistence\Doctrine\Entity\FulfillmentEntity::fromDomainModel($fulfillment),
            $fulfillments
        );
    }
}
