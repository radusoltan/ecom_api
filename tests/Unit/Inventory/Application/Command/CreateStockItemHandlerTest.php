<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\Command\CreateStockItem\CreateStockItemCommand;
use App\Inventory\Application\Command\CreateStockItem\CreateStockItemHandler;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateStockItemHandler::class)]
#[CoversClass(CreateStockItemCommand::class)]
final class CreateStockItemHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private StockItemRepositoryInterface $repository;
    private CreateStockItemHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockItemRepositoryInterface::class);
        $this->handler = new CreateStockItemHandler($this->repository);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function buildCommand(
        ?ProductId $productId = null,
        ?WarehouseId $warehouseId = null,
        int $quantity = 100,
        ?int $threshold = null,
    ): CreateStockItemCommand {
        return new CreateStockItemCommand(
            stockItemId: StockItemId::generate(),
            productId: $productId ?? ProductId::generate(),
            warehouseId: $warehouseId ?? WarehouseId::generate(),
            initialQuantity: Quantity::fromInt($quantity),
            lowStockThreshold: null !== $threshold ? Quantity::fromInt($threshold) : null,
            tenantId: TenantId::fromString(self::TENANT_ID),
        );
    }

    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesStockItemSuccessfully(): void
    {
        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save');

        ($this->handler)($this->buildCommand());
    }

    #[Test]
    public function itCreatesStockItemWithLowStockThreshold(): void
    {
        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save');

        ($this->handler)($this->buildCommand(quantity: 50, threshold: 5));
    }

    #[Test]
    public function itCreatesStockItemWithNullLowStockThreshold(): void
    {
        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn(null);

        $this->repository
            ->expects(self::once())
            ->method('save');

        ($this->handler)($this->buildCommand(quantity: 200, threshold: null));
    }

    #[Test]
    public function itPassesCorrectLookupParametersToRepository(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->repository
            ->expects(self::once())
            ->method('findByProductAndWarehouse')
            ->with(
                self::callback(fn ($p) => $p instanceof ProductId && $p->toString() === $productId->toString()),
                self::callback(fn ($w) => $w instanceof WarehouseId && $w->toString() === $warehouseId->toString()),
                self::callback(fn ($t) => $t instanceof TenantId && self::TENANT_ID === $t->toString()),
            )
            ->willReturn(null);

        $this->repository->method('save');

        $command = new CreateStockItemCommand(
            stockItemId: StockItemId::generate(),
            productId: $productId,
            warehouseId: $warehouseId,
            initialQuantity: Quantity::fromInt(50),
            lowStockThreshold: null,
            tenantId: $tenantId,
        );

        ($this->handler)($command);
    }

    // -----------------------------------------------------------------------
    // Duplicate guard
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsWhenStockItemAlreadyExists(): void
    {
        $productId = ProductId::generate();
        $warehouseId = WarehouseId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $existingStockItem = StockItem::create(
            StockItemId::generate(),
            $tenantId,
            $productId,
            $warehouseId,
            Quantity::fromInt(10),
        );

        $this->repository
            ->method('findByProductAndWarehouse')
            ->willReturn($existingStockItem);

        $this->repository
            ->expects(self::never())
            ->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Stock item already exists');

        $command = new CreateStockItemCommand(
            stockItemId: StockItemId::generate(),
            productId: $productId,
            warehouseId: $warehouseId,
            initialQuantity: Quantity::fromInt(100),
            lowStockThreshold: null,
            tenantId: $tenantId,
        );

        ($this->handler)($command);
    }
}
