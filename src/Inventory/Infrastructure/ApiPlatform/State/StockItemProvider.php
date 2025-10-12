<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Query\GetStockByProduct\GetStockByProductQuery;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Infrastructure\ApiPlatform\Resource\StockItemResource;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProviderInterface<StockItemResource>
 */
final class StockItemProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        private readonly StockItemRepositoryInterface $stockItemRepository,
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get tenant from header/context (would be injected by tenant middleware)
        $tenantId = $this->getTenantIdFromContext($context);

        // Single item GET
        if (isset($uriVariables['id'])) {
            return $this->getStockItem($uriVariables['id']);
        }

        // Collection GET - filter by product/warehouse
        $filters = $context['filters'] ?? [];

        if (isset($filters['productId'])) {
            return $this->getStockByProduct($filters['productId'], $tenantId);
        }

        if (isset($filters['warehouseId'])) {
            return $this->getStockByWarehouse($filters['warehouseId'], $tenantId);
        }

        // Return all stock items for tenant
        return $this->getAllStockItems($tenantId);
    }

    private function getStockItem(string $id): ?StockItemResource
    {
        $stockItem = $this->stockItemRepository->findById(StockItemId::fromString($id));

        if ($stockItem === null) {
            return null;
        }

        $available = $stockItem->calculateAvailable();

        return new StockItemResource(
            id: $stockItem->id()->toString(),
            tenantId: $stockItem->tenantId()->toString(),
            productId: $stockItem->productId()->toString(),
            warehouseId: $stockItem->warehouseId()->toString(),
            initialQuantity: null,
            lowStockThreshold: $stockItem->lowStockThreshold()->value(),
            onHand: $stockItem->onHand()->value(),
            reserved: $stockItem->reserved()->value(),
            allocated: $stockItem->allocated()->value(),
            available: $available->value(),
            isLowStock: $available->isLessThanOrEqual($stockItem->lowStockThreshold()),
            createdAt: $stockItem->createdAt(),
            updatedAt: $stockItem->updatedAt(),
        );
    }

    private function getStockByProduct(string $productId, TenantId $tenantId): array
    {
        $query = new GetStockByProductQuery(
            ProductId::fromString($productId),
            $tenantId
        );

        $dtos = $this->handle($query);

        return array_map(function ($dto) {
            return new StockItemResource(
                id: $dto->stockItemId,
                tenantId: $dto->tenantId,
                productId: $dto->productId,
                warehouseId: $dto->warehouseId,
                initialQuantity: null,
                lowStockThreshold: $dto->lowStockThreshold,
                onHand: $dto->onHand,
                reserved: $dto->reserved,
                allocated: $dto->allocated,
                available: $dto->available,
                isLowStock: $dto->isLowStock,
                createdAt: $dto->createdAt,
                updatedAt: $dto->updatedAt,
            );
        }, $dtos);
    }

    private function getStockByWarehouse(string $warehouseId, TenantId $tenantId): array
    {
        $stockItems = $this->stockItemRepository->findByWarehouse(
            \App\Inventory\Domain\Model\WarehouseId::fromString($warehouseId),
            $tenantId
        );

        return array_map(function ($stockItem) {
            $available = $stockItem->calculateAvailable();

            return new StockItemResource(
                id: $stockItem->id()->toString(),
                tenantId: $stockItem->tenantId()->toString(),
                productId: $stockItem->productId()->toString(),
                warehouseId: $stockItem->warehouseId()->toString(),
                initialQuantity: null,
                lowStockThreshold: $stockItem->lowStockThreshold()->value(),
                onHand: $stockItem->onHand()->value(),
                reserved: $stockItem->reserved()->value(),
                allocated: $stockItem->allocated()->value(),
                available: $available->value(),
                isLowStock: $available->isLessThanOrEqual($stockItem->lowStockThreshold()),
                createdAt: $stockItem->createdAt(),
                updatedAt: $stockItem->updatedAt(),
            );
        }, $stockItems);
    }

    private function getAllStockItems(TenantId $tenantId): array
    {
        $stockItems = $this->stockItemRepository->findByTenant($tenantId);

        return array_map(function ($stockItem) {
            $available = $stockItem->calculateAvailable();

            return new StockItemResource(
                id: $stockItem->id()->toString(),
                tenantId: $stockItem->tenantId()->toString(),
                productId: $stockItem->productId()->toString(),
                warehouseId: $stockItem->warehouseId()->toString(),
                initialQuantity: null,
                lowStockThreshold: $stockItem->lowStockThreshold()->value(),
                onHand: $stockItem->onHand()->value(),
                reserved: $stockItem->reserved()->value(),
                allocated: $stockItem->allocated()->value(),
                available: $available->value(),
                isLowStock: $available->isLessThanOrEqual($stockItem->lowStockThreshold()),
                createdAt: $stockItem->createdAt(),
                updatedAt: $stockItem->updatedAt(),
            );
        }, $stockItems);
    }

    private function getTenantIdFromContext(array $context): TenantId
    {
        // Tenant ID is injected by TenantContextProvider decorator from X-Tenant-ID header
        if (isset($context['tenant_id'])) {
            return TenantId::fromString($context['tenant_id']);
        }

        // In production, tenant ID is required for all operations
        throw new \RuntimeException('Tenant ID not found in context. Ensure X-Tenant-ID header is provided.');
    }
}
