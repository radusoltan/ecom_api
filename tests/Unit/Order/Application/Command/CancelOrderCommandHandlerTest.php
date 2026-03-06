<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command;

use App\Order\Application\Command\CancelOrderCommand;
use App\Order\Application\Command\CancelOrderCommandHandler;
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

#[CoversClass(CancelOrderCommandHandler::class)]
final class CancelOrderCommandHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private OrderRepositoryInterface $orderRepository;
    private CancelOrderCommandHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->handler = new CancelOrderCommandHandler($this->orderRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itCancelsPendingOrder(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::pending());

        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository
            ->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Order $saved): bool {
                self::assertTrue($saved->status()->isCancelled());

                return true;
            }));

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($command);

        self::assertInstanceOf(Order::class, $result);
        self::assertTrue($result->status()->isCancelled());
    }

    #[Test]
    public function itCancelsDraftOrder(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::draft());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::once())->method('save');

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($command);

        self::assertTrue($result->status()->isCancelled());
    }

    #[Test]
    public function itCancelsProcessingOrder(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::processing());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::once())->method('save');

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($command);

        self::assertTrue($result->status()->isCancelled());
    }

    #[Test]
    public function itPassesCorrectIdsToRepository(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::pending());

        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->with(
                self::callback(fn (OrderId $id) => self::ORDER_ID === $id->toString()),
                self::callback(fn (TenantId $t) => self::TENANT_ID === $t->toString()),
            )
            ->willReturn($order);

        $this->orderRepository->method('save');

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itReturnsTheCancelledOrder(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::pending());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->method('save');

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($command);

        self::assertSame(self::ORDER_ID, $result->id()->toString());
        self::assertSame(self::TENANT_ID, $result->tenantId()->toString());
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

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenCancellingDeliveredOrder(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::delivered());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenCancellingAlreadyCancelledOrder(): void
    {
        $order = $this->buildOrderWithStatus(OrderStatus::cancelled());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsWhenCancellingShippedOrder(): void
    {
        // shipped -> cancelled is NOT a valid transition
        $order = $this->buildOrderWithStatus(OrderStatus::shipped());

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $this->orderRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $command = new CancelOrderCommand(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        ($this->handler)($command);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildOrderWithStatus(OrderStatus $status): Order
    {
        $line = OrderLine::create(
            productId: OrderProductId::generate(),
            productName: 'Test Product',
            quantity: 1,
            unitPrice: Money::fromScalars(1000, 'USD'),
        );

        return Order::reconstituteFromPersistence(
            id: OrderId::fromString(self::ORDER_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'buyer@example.com',
            status: $status,
            lines: [$line],
            shippingAddress: Address::create('1 Ship St', 'City', 'NY', '10001', 'US'),
            billingAddress: Address::create('1 Bill St', 'City', 'NY', '10001', 'US'),
            createdAt: new \DateTimeImmutable('2024-06-01'),
            updatedAt: new \DateTimeImmutable('2024-06-01'),
        );
    }
}
