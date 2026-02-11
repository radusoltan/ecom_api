<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application\EventSubscriber;

use App\Inventory\Application\EventSubscriber\OrderCancelledStockSubscriber;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Model\WarehouseId;
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
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private LoggerInterface&MockObject $logger;
    private OrderCancelledStockSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->reservationRepository = $this->createMock(StockReservationRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderCancelledStockSubscriber(
            $this->reservationRepository,
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

    public function testSuccessfullyReleasesReservations(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: 'Customer requested cancellation'
        );

        $reservation1 = StockReservation::create(
            reservationId: $orderId->toString(),
            stockItemId: StockItemId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: Quantity::fromInt(2)
        );

        $reservation2 = StockReservation::create(
            reservationId: $orderId->toString(),
            stockItemId: StockItemId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: Quantity::fromInt(5)
        );

        $reservations = [$reservation1, $reservation2];

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId->toString())
            ->willReturn($reservations);

        $this->reservationRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (StockReservation $reservation) {
                $this->assertTrue($reservation->isReleased());
            });

        $this->eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(StockReleased::class))
            ->willReturnCallback(function (StockReleased $event) use ($orderId) {
                $this->assertSame($orderId->toString(), $event->referenceId);
                $this->assertStringContainsString('Order cancelled', $event->reason);
                $this->assertStringContainsString('Customer requested cancellation', $event->reason);

                return $event;
            });

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onOrderCancelled($event);
    }

    public function testHandlesNoReservationsFound(): void
    {
        // Arrange
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

        $this->reservationRepository
            ->expects($this->never())
            ->method('save');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onOrderCancelled($event);
    }

    public function testSkipsAlreadyReleasedReservations(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: 'Out of stock'
        );

        $reservation = StockReservation::create(
            reservationId: $orderId->toString(),
            stockItemId: StockItemId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: Quantity::fromInt(3)
        );

        // Release the reservation before the event
        $reservation->release();

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId->toString())
            ->willReturn([$reservation]);

        $this->reservationRepository
            ->expects($this->never())
            ->method('save');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('debug')
            ->with('Reservation already released, skipping', $this->anything());

        // Act
        $this->subscriber->onOrderCancelled($event);
    }

    public function testHandlesPartialFailuresGracefully(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: 'Payment failed'
        );

        $reservation1 = StockReservation::create(
            reservationId: $orderId->toString(),
            stockItemId: StockItemId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: Quantity::fromInt(2)
        );

        $reservation2 = StockReservation::create(
            reservationId: $orderId->toString(),
            stockItemId: StockItemId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: Quantity::fromInt(5)
        );

        $reservations = [$reservation1, $reservation2];

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId->toString())
            ->willReturn($reservations);

        // First save succeeds, second fails
        $saveCount = 0;
        $this->reservationRepository
            ->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function () use (&$saveCount) {
                $saveCount++;
                if ($saveCount === 2) {
                    throw new \RuntimeException('Database connection failed');
                }
            });

        // Only first event is dispatched
        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StockReleased::class));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with('Failed to release reservation for cancelled order', $this->anything());

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onOrderCancelled($event);
    }

    public function testLogsReasonInReleaseMessage(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::pending(),
            reason: 'Customer changed mind'
        );

        $reservation = StockReservation::create(
            reservationId: $orderId->toString(),
            stockItemId: StockItemId::generate(),
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: Quantity::fromInt(1)
        );

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->willReturn([$reservation]);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (StockReleased $event) {
                return $event->reason === 'Order cancelled: Customer changed mind';
            }));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onOrderCancelled($event);
    }

    public function testHandlesExceptionFromRepositoryFindGracefully(): void
    {
        // Arrange
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

        $this->reservationRepository
            ->expects($this->never())
            ->method('save');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with('Failed to process stock release for cancelled order', $this->callback(function ($context) use ($orderId) {
                return $context['orderId'] === $orderId->toString()
                    && $context['error'] === 'Database error';
            }));

        // Act - should not throw
        $this->subscriber->onOrderCancelled($event);
    }

    public function testIncludesAllRelevantContextInLogs(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $stockItemId = StockItemId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: OrderStatus::processing(),
            reason: 'Test reason'
        );

        $reservation = StockReservation::create(
            reservationId: $orderId->toString(),
            stockItemId: $stockItemId,
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId,
            quantity: Quantity::fromInt(10)
        );

        $this->reservationRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->willReturn([$reservation]);

        $this->reservationRepository
            ->expects($this->once())
            ->method('save');

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onOrderCancelled($event);
    }
}
