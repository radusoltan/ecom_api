<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Event\StockAdjusted;
use App\Inventory\Domain\Event\StockAllocated;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Event\StockReserved;
use App\Inventory\Domain\Event\StockTransferred;
use App\Inventory\Domain\Event\WarehouseActivated;
use App\Inventory\Domain\Event\WarehouseCreated;
use App\Inventory\Domain\Event\WarehouseDeactivated;
use App\Inventory\Domain\Event\WarehouseUpdated;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class InventoryCacheInvalidationSubscriber
{
    public function __construct(
        private CacheService $cacheService,
        private LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onWarehouseCreated(WarehouseCreated $event): void
    {
        $this->invalidateWarehouseTags($event->tenantId, WarehouseCreated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onWarehouseUpdated(WarehouseUpdated $event): void
    {
        $this->invalidateWarehouseTags($event->tenantId, WarehouseUpdated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onWarehouseActivated(WarehouseActivated $event): void
    {
        $this->invalidateWarehouseTags($event->tenantId, WarehouseActivated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onWarehouseDeactivated(WarehouseDeactivated $event): void
    {
        $this->invalidateWarehouseTags($event->tenantId, WarehouseDeactivated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onStockAdjusted(StockAdjusted $event): void
    {
        $this->invalidateStockTags($event->tenantId, $event->productId, StockAdjusted::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onStockAllocated(StockAllocated $event): void
    {
        $this->invalidateStockTags($event->tenantId, $event->productId, StockAllocated::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onStockReleased(StockReleased $event): void
    {
        $this->invalidateStockTags($event->tenantId, $event->productId, StockReleased::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onStockReserved(StockReserved $event): void
    {
        $this->invalidateStockTags($event->tenantId, $event->productId, StockReserved::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onStockDepleted(StockDepleted $event): void
    {
        $this->invalidateStockTags($event->tenantId, $event->productId, StockDepleted::class);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onStockTransferred(StockTransferred $event): void
    {
        $tenantId = $event->tenantId->toString();

        $this->invalidateTags([
            $this->cacheService->tenantResourceTag($tenantId, 'products'),
            $this->cacheService->tag('product', $event->productId->toString()),
            $this->cacheService->tenantResourceTag($tenantId, 'warehouses'),
        ], StockTransferred::class);
    }

    private function invalidateWarehouseTags(TenantId $tenantId, string $eventName): void
    {
        $this->invalidateTags(
            [$this->cacheService->tenantResourceTag($tenantId->toString(), 'warehouses')],
            $eventName
        );
    }

    private function invalidateStockTags(TenantId $tenantId, ProductId $productId, string $eventName): void
    {
        $tenant = $tenantId->toString();

        $this->invalidateTags([
            $this->cacheService->tenantResourceTag($tenant, 'products'),
            $this->cacheService->tag('product', $productId->toString()),
        ], $eventName);
    }

    /**
     * @param array<string> $tags
     */
    private function invalidateTags(array $tags, string $eventName): void
    {
        $uniqueTags = array_values(array_unique($tags));
        $this->cacheService->invalidateTags($uniqueTags);

        $this->logger->debug('Cache invalidation triggered', [
            'event' => $eventName,
            'tags' => $uniqueTags,
        ]);
    }
}
