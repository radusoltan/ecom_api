<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Infrastructure\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Event\StockAdjusted;
use App\Inventory\Domain\Event\StockTransferred;
use App\Inventory\Domain\Event\WarehouseActivated;
use App\Inventory\Domain\Event\WarehouseUpdated;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Inventory\Infrastructure\EventSubscriber\InventoryCacheInvalidationSubscriber;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Cache\CacheService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[CoversClass(InventoryCacheInvalidationSubscriber::class)]
final class InventoryCacheInvalidationSubscriberTest extends TestCase
{
    private TagAwareCacheInterface $cache;
    private InventoryCacheInvalidationSubscriber $subscriber;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->subscriber = new InventoryCacheInvalidationSubscriber(
            new CacheService($this->cache, new NullLogger()),
            new NullLogger()
        );
        $this->tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    #[Test]
    public function itInvalidatesTenantScopedWarehouseTagsOnWarehouseUpdate(): void
    {
        $warehouseId = WarehouseId::generate();

        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant.00000000-0000-4000-8000-000000000001.warehouses'])
            ->willReturn(true);

        $this->subscriber->onWarehouseUpdated(new WarehouseUpdated(
            warehouseId: $warehouseId,
            tenantId: $this->tenantId,
            name: WarehouseName::fromString('Main Warehouse'),
            occurredOn: new \DateTimeImmutable('2026-03-10T10:00:00+00:00')
        ));
    }

    #[Test]
    public function itInvalidatesTenantScopedWarehouseTagsOnWarehouseActivation(): void
    {
        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with(['tenant.00000000-0000-4000-8000-000000000001.warehouses'])
            ->willReturn(true);

        $this->subscriber->onWarehouseActivated(new WarehouseActivated(
            warehouseId: WarehouseId::generate(),
            tenantId: $this->tenantId,
            occurredOn: new \DateTimeImmutable('2026-03-10T10:30:00+00:00')
        ));
    }

    #[Test]
    public function itInvalidatesTenantScopedProductTagsOnStockAdjustment(): void
    {
        $productId = ProductId::fromString('11111111-1111-4111-8111-111111111111');

        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with([
                'tenant.00000000-0000-4000-8000-000000000001.products',
                'product.11111111-1111-4111-8111-111111111111',
            ])
            ->willReturn(true);

        $this->subscriber->onStockAdjusted(new StockAdjusted(
            stockItemId: StockItemId::generate(),
            tenantId: $this->tenantId,
            productId: $productId,
            previousQuantity: Quantity::fromInt(10),
            newQuantity: Quantity::fromInt(7),
            reason: 'Cycle count',
            occurredOn: new \DateTimeImmutable('2026-03-10T11:00:00+00:00')
        ));
    }

    #[Test]
    public function itInvalidatesProductAndWarehouseTagsOnStockTransfer(): void
    {
        $productId = ProductId::fromString('11111111-1111-4111-8111-111111111111');

        $this->cache
            ->expects(self::once())
            ->method('invalidateTags')
            ->with([
                'tenant.00000000-0000-4000-8000-000000000001.products',
                'product.11111111-1111-4111-8111-111111111111',
                'tenant.00000000-0000-4000-8000-000000000001.warehouses',
            ])
            ->willReturn(true);

        $this->subscriber->onStockTransferred(new StockTransferred(
            stockItemId: StockItemId::generate(),
            productId: $productId,
            sourceWarehouseId: WarehouseId::generate(),
            destinationWarehouseId: WarehouseId::generate(),
            quantity: Quantity::fromInt(3),
            tenantId: $this->tenantId,
            occurredOn: new \DateTimeImmutable('2026-03-10T11:30:00+00:00')
        ));
    }
}
