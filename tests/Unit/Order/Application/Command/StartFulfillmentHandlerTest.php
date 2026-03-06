<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Command;

use App\Inventory\Application\Command\AllocateStock\AllocateStockCommand;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Application\Command\StartFulfillment;
use App\Order\Application\Command\StartFulfillmentHandler;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Repository\FulfillmentRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(StartFulfillmentHandler::class)]
final class StartFulfillmentHandlerTest extends TestCase
{
    private FulfillmentRepositoryInterface $fulfillmentRepository;
    private OrderRepositoryInterface $orderRepository;
    private MessageBusInterface $commandBus;
    private LoggerInterface $logger;
    private StartFulfillmentHandler $handler;

    protected function setUp(): void
    {
        $this->fulfillmentRepository = $this->createMock(FulfillmentRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new StartFulfillmentHandler(
            fulfillmentRepository: $this->fulfillmentRepository,
            orderRepository: $this->orderRepository,
            commandBus: $this->commandBus,
            logger: $this->logger
        );
    }

    #[Test]
    public function itAllocatesStockForAllOrderLines(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();
        $fulfillmentId = FulfillmentId::generate();

        $productId1 = OrderProductId::generate();
        $productId2 = OrderProductId::generate();

        // Create order with 2 lines
        $order = $this->createOrder($orderId, $tenantId, [
            ['productId' => $productId1, 'quantity' => 3],
            ['productId' => $productId2, 'quantity' => 5],
        ]);

        $command = new StartFulfillment(
            fulfillmentId: $fulfillmentId,
            orderId: $orderId,
            warehouseId: $warehouseId,
            tenantId: $tenantId
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn($order);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId)
            ->willReturn(null);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('save');

        // Expect AllocateStockCommand to be dispatched for each order line
        $this->commandBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($productId1, $productId2, $warehouseId, $orderId, $tenantId): bool {
                if (!$command instanceof AllocateStockCommand) {
                    return false;
                }

                // Verify command fields (productId is now a string)
                return ($command->productId === $productId1->toString() && 3 === $command->quantity->value())
                    || ($command->productId === $productId2->toString() && 5 === $command->quantity->value())
                    && $command->warehouseId->equals($warehouseId)
                    && $command->orderId === $orderId->toString()
                    && $command->tenantId->equals($tenantId);
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug')
            ->with('Stock allocated for order line', $this->isType('array'));

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Fulfillment started with stock allocation', $this->callback(function (array $context) use ($fulfillmentId, $orderId): bool {
                return $context['fulfillment_id'] === $fulfillmentId->toString()
                    && $context['order_id'] === $orderId->toString()
                    && 2 === $context['allocated_lines']
                    && 2 === $context['total_lines'];
            }));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified by mocks
    }

    #[Test]
    public function itThrowsExceptionWhenOrderNotFound(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();

        $command = new StartFulfillment(
            fulfillmentId: FulfillmentId::generate(),
            orderId: $orderId,
            warehouseId: WarehouseId::generate(),
            tenantId: $tenantId
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn(null);

        // Should not attempt to save or dispatch
        $this->fulfillmentRepository
            ->expects($this->never())
            ->method('save');

        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(sprintf('Order not found: %s', $orderId->toString()));

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function itThrowsExceptionWhenFulfillmentAlreadyExists(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();

        $order = $this->createOrder($orderId, $tenantId, [
            ['productId' => OrderProductId::generate(), 'quantity' => 1],
        ]);

        $command = new StartFulfillment(
            fulfillmentId: FulfillmentId::generate(),
            orderId: $orderId,
            warehouseId: $warehouseId,
            tenantId: $tenantId
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn($order);

        // Simulate existing fulfillment using a real Fulfillment instance (class is final)
        $existingFulfillment = \App\Order\Domain\Model\Fulfillment::start(
            FulfillmentId::generate(),
            $orderId,
            $warehouseId,
            $tenantId,
        );
        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId)
            ->willReturn($existingFulfillment);

        // Should not attempt to save new fulfillment or dispatch
        $this->fulfillmentRepository
            ->expects($this->never())
            ->method('save');

        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(sprintf('Fulfillment already exists for order: %s', $orderId->toString()));

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function itRethrowsDomainExceptionOnStockAllocationFailure(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();

        $productId = OrderProductId::generate();
        $order = $this->createOrder($orderId, $tenantId, [
            ['productId' => $productId, 'quantity' => 10],
        ]);

        $command = new StartFulfillment(
            fulfillmentId: FulfillmentId::generate(),
            orderId: $orderId,
            warehouseId: $warehouseId,
            tenantId: $tenantId
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn($order);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId)
            ->willReturn(null);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('save');

        // Simulate stock allocation failure
        $stockException = new \DomainException('Insufficient stock available');
        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException($stockException);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Stock allocation failed for order line', $this->callback(function (array $context) use ($orderId, $productId): bool {
                return $context['order_id'] === $orderId->toString()
                    && $context['product_id'] === $productId->toString()
                    && 10 === $context['quantity']
                    && 'Insufficient stock available' === $context['error'];
            }));

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient stock available');

        // Act
        ($this->handler)($command);
    }

    #[Test]
    public function itLogsSuccessfulStockAllocation(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();
        $productId = OrderProductId::generate();

        $order = $this->createOrder($orderId, $tenantId, [
            ['productId' => $productId, 'quantity' => 2],
        ]);

        $command = new StartFulfillment(
            fulfillmentId: FulfillmentId::generate(),
            orderId: $orderId,
            warehouseId: $warehouseId,
            tenantId: $tenantId
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn($order);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->with($orderId)
            ->willReturn(null);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('save');

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('Stock allocated for order line', $this->callback(function (array $context) use ($orderId, $productId, $warehouseId): bool {
                return $context['order_id'] === $orderId->toString()
                    && $context['product_id'] === $productId->toString()
                    && 2 === $context['quantity']
                    && $context['warehouse_id'] === $warehouseId->toString();
            }));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified by mocks
    }

    #[Test]
    public function itLogsStockAllocationFailure(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();
        $productId = OrderProductId::generate();

        $order = $this->createOrder($orderId, $tenantId, [
            ['productId' => $productId, 'quantity' => 100],
        ]);

        $command = new StartFulfillment(
            fulfillmentId: FulfillmentId::generate(),
            orderId: $orderId,
            warehouseId: $warehouseId,
            tenantId: $tenantId
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($order);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('findByOrderId')
            ->willReturn(null);

        $this->fulfillmentRepository
            ->expects($this->once())
            ->method('save');

        $exception = new \DomainException('Stock not available in warehouse');
        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Stock allocation failed for order line', $this->callback(function (array $context) use ($orderId, $productId, $warehouseId, $exception): bool {
                return $context['order_id'] === $orderId->toString()
                    && $context['product_id'] === $productId->toString()
                    && 100 === $context['quantity']
                    && $context['warehouse_id'] === $warehouseId->toString()
                    && $context['error'] === $exception->getMessage();
            }));

        // Assert - should re-throw
        $this->expectException(\DomainException::class);

        // Act
        ($this->handler)($command);
    }

    /**
     * Helper to create a test Order with lines.
     *
     * @param array<array{productId: ProductId, quantity: int}> $lines
     */
    private function createOrder(OrderId $orderId, TenantId $tenantId, array $lines): Order
    {
        $shippingAddress = Address::create(
            street: '123 Test St',
            city: 'Test City',
            state: 'CA',
            postalCode: '12345',
            country: 'US'
        );

        $orderLines = [];
        foreach ($lines as $lineData) {
            $orderLines[] = OrderLine::create(
                productId: $lineData['productId'],
                productName: 'Test Product',
                quantity: $lineData['quantity'],
                unitPrice: Money::fromScalars(1000, 'USD')
            );
        }

        return Order::place(
            id: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            lines: $orderLines,
            shippingAddress: $shippingAddress,
            billingAddress: $shippingAddress
        );
    }
}
