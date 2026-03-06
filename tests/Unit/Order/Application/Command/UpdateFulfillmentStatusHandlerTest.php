<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Application\Command\UpdateFulfillmentStatus;
use App\Order\Application\Command\UpdateFulfillmentStatusHandler;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class UpdateFulfillmentStatusHandlerTest extends TestCase
{
    private FulfillmentRepositoryInterface $repo;
    private UpdateFulfillmentStatusHandler $handler;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(FulfillmentRepositoryInterface::class);
        $this->handler = new UpdateFulfillmentStatusHandler($this->repo, new NullLogger());
    }

    public function testItThrowsWhenFulfillmentNotFound(): void
    {
        $this->repo->method('findById')->willReturn(null);

        $this->expectException(\DomainException::class);

        ($this->handler)(new UpdateFulfillmentStatus(FulfillmentId::generate(), FulfillmentStatus::picking(), TenantId::generate()));
    }

    public function testItThrowsWhenTenantMismatch(): void
    {
        $fulfillment = Fulfillment::start(FulfillmentId::generate(), OrderId::generate(), WarehouseId::generate(), TenantId::generate());

        $this->repo->method('findById')->willReturn($fulfillment);

        $this->expectException(\DomainException::class);

        ($this->handler)(new UpdateFulfillmentStatus($fulfillment->id(), FulfillmentStatus::picking(), TenantId::generate()));
    }

    public function testItTransitionsToPicking(): void
    {
        $tenantId = TenantId::generate();
        $fulfillment = Fulfillment::start(FulfillmentId::generate(), OrderId::generate(), WarehouseId::generate(), $tenantId);

        $this->repo->method('findById')->willReturn($fulfillment);
        $this->repo->expects($this->once())->method('save');

        ($this->handler)(new UpdateFulfillmentStatus($fulfillment->id(), FulfillmentStatus::picking(), $tenantId));

        self::assertTrue($fulfillment->status()->equals(FulfillmentStatus::picking()));
    }

    public function testItTransitionsToShippingWithCarrier(): void
    {
        $tenantId = TenantId::generate();
        $fulfillment = Fulfillment::start(FulfillmentId::generate(), OrderId::generate(), WarehouseId::generate(), $tenantId);
        $fulfillment->startPicking();
        $fulfillment->startPacking();

        $this->repo->method('findById')->willReturn($fulfillment);
        $this->repo->expects($this->once())->method('save');

        ($this->handler)(new UpdateFulfillmentStatus($fulfillment->id(), FulfillmentStatus::shipping(), $tenantId, 'DHL', 'TRACK-123'));

        self::assertTrue($fulfillment->status()->equals(FulfillmentStatus::shipping()));
    }

    public function testItThrowsWhenShippingWithoutCarrier(): void
    {
        $tenantId = TenantId::generate();
        $fulfillment = Fulfillment::start(FulfillmentId::generate(), OrderId::generate(), WarehouseId::generate(), $tenantId);
        $fulfillment->startPicking();
        $fulfillment->startPacking();

        $this->repo->method('findById')->willReturn($fulfillment);

        $this->expectException(\DomainException::class);

        ($this->handler)(new UpdateFulfillmentStatus($fulfillment->id(), FulfillmentStatus::shipping(), $tenantId));
    }
}
