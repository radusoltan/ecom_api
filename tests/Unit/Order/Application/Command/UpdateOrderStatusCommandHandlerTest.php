<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command;

use App\Order\Application\Command\UpdateOrderStatusCommand;
use App\Order\Application\Command\UpdateOrderStatusCommandHandler;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateOrderStatusCommandHandler::class)]
final class UpdateOrderStatusCommandHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private OrderRepositoryInterface $orderRepository;
    private UpdateOrderStatusCommandHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->handler = new UpdateOrderStatusCommandHandler($this->orderRepository);
    }

    // ---------------------------------------------------------------
    // Happy path: status transitions
    // ---------------------------------------------------------------

    #[Test]
    public function itTransitionsOrderToProcessingStatus(): void
    {
        $order = $this->buildPendingOrder();

        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Order $saved): bool {
                self::assertSame(OrderStatus::PROCESSING, $saved->status()->value());

                return true;
            }));

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::PROCESSING,
        );

        $result = ($this->handler)($command);

        self::assertInstanceOf(Order::class, $result);
        self::assertSame(OrderStatus::PROCESSING, $result->status()->value());
    }

    #[Test]
    public function itTransitionsOrderToShippedStatus(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::processing());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::once())->method('save');

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::SHIPPED,
        );

        $result = ($this->handler)($command);

        self::assertSame(OrderStatus::SHIPPED, $result->status()->value());
    }

    #[Test]
    public function itTransitionsOrderToDeliveredStatus(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::shipped());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::once())->method('save');

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::DELIVERED,
        );

        $result = ($this->handler)($command);

        self::assertSame(OrderStatus::DELIVERED, $result->status()->value());
    }

    #[Test]
    public function itTransitionsOrderToPaidStatus(): void
    {
        $order = $this->buildPendingOrder();

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::once())->method('save');

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::PAID,
        );

        $result = ($this->handler)($command);

        self::assertSame(OrderStatus::PAID, $result->status()->value());
    }

    #[Test]
    public function itTransitionsOrderToPendingFromDraft(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::draft());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::once())->method('save');

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::PENDING,
        );

        $result = ($this->handler)($command);

        self::assertSame(OrderStatus::PENDING, $result->status()->value());
    }

    #[Test]
    public function itPassesCorrectIdsToRepository(): void
    {
        $order = $this->buildPendingOrder();

        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->with(
                self::callback(fn (OrderId $id) => self::ORDER_ID === $id->toString()),
                self::callback(fn (TenantId $t) => self::TENANT_ID === $t->toString()),
            )
            ->willReturn($order);

        $this->orderRepository->method('save');

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::PROCESSING,
        );

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------
    // Error cases
    // ---------------------------------------------------------------

    #[Test]
    public function itThrowsRuntimeExceptionWhenOrderNotFound(): void
    {
        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn(null);

        $this->orderRepository->expects(self::never())->method('save');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(self::ORDER_ID);

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::PROCESSING,
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsRuntimeExceptionForInvalidStatus(): void
    {
        $order = $this->buildPendingOrder();

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::never())->method('save');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid order status: "unknown_status"');

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: 'unknown_status',
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsInvalidArgumentExceptionForIllegalStatusTransition(): void
    {
        // pending -> shipped is not a valid transition
        $order = $this->buildPendingOrder();

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::SHIPPED,
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenAttemptingToChangeStatusOfCancelledOrder(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::cancelled());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $command = new UpdateOrderStatusCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
            newStatus: OrderStatus::PROCESSING,
        );

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function buildPendingOrder(): Order
    {
        return $this->buildOrderWithStatus(OrderStatus::pending());
    }

    private function buildOrderWithStatus(OrderStatus $status): Order
    {
        $line = OrderLine::create(
            productId: OrderProductId::generate(),
            productName: 'Test Product',
            quantity: 2,
            unitPrice: Money::fromScalars(500, 'USD'),
        );

        return Order::reconstituteFromPersistence(
            id: OrderId::fromString(self::ORDER_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'customer@example.com',
            status: $status,
            lines: [$line],
            shippingAddress: Address::create('123 Test St', 'City', 'CA', '12345', 'US'),
            billingAddress: Address::create('123 Test St', 'City', 'CA', '12345', 'US'),
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
        );
    }
}
