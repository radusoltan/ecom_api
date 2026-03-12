<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Application\EventSubscriber\OrderCancelledStockSubscriber;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\StockReservationRepositoryInterface;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class OrderCancelledStockSubscriberTest extends TestCase
{
    private StockReservationRepositoryInterface&MockObject $reservationRepository;
    private StockItemRepositoryInterface&MockObject $stockItemRepository;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private LoggerInterface&MockObject $logger;
    private OrderCancelledStockSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(StockReservationRepositoryInterface::class);
        $this->stockItemRepository = $this->createMock(StockItemRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderCancelledStockSubscriber(
            $this->reservationRepository,
            $this->stockItemRepository,
            $this->eventDispatcher,
            $this->logger
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OrderCancelledStockSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(OrderCancelled::class, $events);
        $this->assertSame('onOrderCancelled', $events[OrderCancelled::class]);
    }

    public function testSuccessfullyReleasesReservationsAndDispatchesTenantAwareEvents(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $productId = ProductId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: 'Customer requested cancellation'
        );

        $reservation1 = $this->createReservation($orderId->toString(), $tenantId, Quantity::fromInt(2));
        $reservation2 = $this->createReservation($orderId->toString(), $tenantId, Quantity::fromInt(5));
        $stockItem1 = $this->createStockItem($reservation1->stockItemId(), $tenantId, $productId);
        $stockItem2 = $this->createStockItem($reservation2->stockItemId(), $tenantId, $productId);

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId->toString())
            ->willReturn([$reservation1, $reservation2]);

        $this->reservationRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (StockReservation $reservation) {
                $this->assertTrue($reservation->isReleased());
            });

        $this->stockItemRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($stockItem1, $stockItem2);

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(function (StockReleased $event) use ($orderId, $tenantId, $productId): bool {
                return $event->referenceId === $orderId->toString()
                    && $event->tenantId->equals($tenantId)
                    && $event->productId->equals($productId)
                    && str_contains($event->reason, 'Order cancelled')
                    && str_contains($event->reason, 'Customer requested cancellation');
            }))
            ->willReturnArgument(0);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->subscriber->onOrderCancelled($event);
    }

    public function testHandlesNoReservationsFound(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: null
        );

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId->toString())
            ->willReturn([]);

        $this->reservationRepository->expects($this->never())->method('save');
        $this->stockItemRepository->expects($this->never())->method('findById');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->atLeastOnce())->method('info');

        $this->subscriber->onOrderCancelled($event);
    }

    public function testSkipsAlreadyReleasedReservations(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: 'Out of stock'
        );

        $reservation = $this->createReservation($orderId->toString(), $tenantId, Quantity::fromInt(3));
        $reservation->release();

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId->toString())
            ->willReturn([$reservation]);

        $this->reservationRepository->expects($this->never())->method('save');
        $this->stockItemRepository->expects($this->never())->method('findById');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('debug')
            ->with('Reservation already released, skipping', $this->anything());

        $this->subscriber->onOrderCancelled($event);
    }

    public function testLogsWarningWhenStockItemCannotBeResolved(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: 'Customer changed mind'
        );

        $reservation = $this->createReservation($orderId->toString(), $tenantId, Quantity::fromInt(1));

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->willReturn([$reservation]);

        $this->reservationRepository->expects($this->once())->method('save');
        $this->stockItemRepository
            ->expects($this->once())
            ->method('findById')
            ->with($reservation->stockItemId())
            ->willReturn(null);
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->atLeastOnce())->method('warning');

        $this->subscriber->onOrderCancelled($event);
    }

    public function testHandlesExceptionFromRepositoryFindGracefully(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: null
        );

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->reservationRepository->expects($this->never())->method('save');
        $this->stockItemRepository->expects($this->never())->method('findById');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with('Failed to process stock release for cancelled order', $this->callback(function ($context) use ($orderId) {
                return $context['orderId'] === $orderId->toString()
                    && 'Database error' === $context['error'];
            }));

        $this->subscriber->onOrderCancelled($event);
    }

    private function createReservation(string $orderId, TenantId $tenantId, Quantity $quantity): StockReservation
    {
        return StockReservation::create(
            reservationId: $orderId,
            stockItemId: StockItemId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: $quantity
        );
    }

    private function createStockItem(StockItemId $stockItemId, TenantId $tenantId, ProductId $productId): StockItem
    {
        return StockItem::create(
            id: $stockItemId,
            tenantId: $tenantId,
            productId: $productId,
            warehouseId: WarehouseId::generate(),
            initialQuantity: Quantity::fromInt(10)
        );
    }
}
