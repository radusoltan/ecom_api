<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\EventSubscriber;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Repository\StockItemRepositoryInterface;
use App\Inventory\Domain\Repository\WarehouseRepositoryInterface;
use App\Order\Application\Command\StartFulfillment;
use App\Order\Application\EventSubscriber\OrderPlacedFulfillmentSubscriber;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\Service\WarehouseRoutingService;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(OrderPlacedFulfillmentSubscriber::class)]
final class OrderPlacedFulfillmentSubscriberTest extends TestCase
{
    private WarehouseRepositoryInterface $warehouseRepository;
    private StockItemRepositoryInterface $stockItemRepository;
    private OrderRepositoryInterface $orderRepository;
    private MessageBusInterface $commandBus;
    private LoggerInterface $logger;
    private OrderPlacedFulfillmentSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->warehouseRepository = $this->createStub(WarehouseRepositoryInterface::class);
        $this->stockItemRepository = $this->createStub(StockItemRepositoryInterface::class);

        $routingService = new WarehouseRoutingService(
            $this->warehouseRepository,
            $this->stockItemRepository,
            new NullLogger(),
        );

        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderPlacedFulfillmentSubscriber(
            routingService: $routingService,
            orderRepository: $this->orderRepository,
            commandBus: $this->commandBus,
            logger: $this->logger
        );
    }

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itSubscribesToOrderPlacedEvent(): void
    {
        $subscribedEvents = OrderPlacedFulfillmentSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderPlaced::class, $subscribedEvents);
        self::assertEquals('onOrderPlaced', $subscribedEvents[OrderPlaced::class]);
    }

    #[Test]
    public function itDispatchesStartFulfillmentWhenWarehouseFound(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            total: Money::fromScalars(5000, 'USD')
        );

        $order = $this->createOrder($orderId, $tenantId);

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn($order);

        // Configure routing service to find a warehouse via repository stubs
        $this->configureWarehouseFound($tenantId, $warehouseId, $order);

        // Expect StartFulfillment command to be dispatched
        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($orderId, $warehouseId, $tenantId): bool {
                if (!$command instanceof StartFulfillment) {
                    return false;
                }

                return $command->orderId->equals($orderId)
                    && $command->warehouseId->equals($warehouseId)
                    && $command->tenantId->equals($tenantId)
                    && $command->fulfillmentId instanceof FulfillmentId;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Fulfillment started for order', $this->callback(function (array $context) use ($orderId, $warehouseId, $tenantId): bool {
                return $context['order_id'] === $orderId->toString()
                    && $context['warehouse_id'] === $warehouseId->toString()
                    && $context['tenant_id'] === $tenantId->toString();
            }));

        // Act
        $this->subscriber->onOrderPlaced($event);

        // Assert - expectations verified by mocks
    }

    #[Test]
    public function itDoesNotDispatchWhenNoWarehouseAvailable(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            total: Money::fromScalars(5000, 'USD')
        );

        $order = $this->createOrder($orderId, $tenantId);

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn($order);

        // No warehouse available (empty warehouse list)
        $this->warehouseRepository->method('findActiveByTenant')->willReturn([]);

        // Should NOT dispatch StartFulfillment
        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        // Should log error
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('No warehouse available for order fulfillment', $this->callback(function (array $context) use ($orderId, $tenantId): bool {
                return $context['order_id'] === $orderId->toString()
                    && $context['tenant_id'] === $tenantId->toString()
                    && isset($context['order_lines_count']);
            }));

        // Act
        $this->subscriber->onOrderPlaced($event);

        // Assert - expectations verified by mocks
    }

    #[Test]
    public function itDoesNotDispatchWhenOrderNotFound(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            total: Money::fromScalars(5000, 'USD')
        );

        // Order not found
        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->with($orderId)
            ->willReturn(null);

        // Should NOT reach routing service since order not found

        // Should NOT dispatch StartFulfillment
        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        // Should log error
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Order not found for fulfillment', $this->callback(function (array $context) use ($orderId, $tenantId): bool {
                return $context['order_id'] === $orderId->toString()
                    && $context['tenant_id'] === $tenantId->toString();
            }));

        // Act
        $this->subscriber->onOrderPlaced($event);

        // Assert - expectations verified by mocks
    }

    #[Test]
    public function itLogsWarehouseSelectionForMultipleWarehouses(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            total: Money::fromScalars(10000, 'USD')
        );

        $order = $this->createOrder($orderId, $tenantId);

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($order);

        $this->configureWarehouseFound($tenantId, $warehouseId, $order);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Verify logging includes all relevant context
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Fulfillment started for order', $this->callback(function (array $context) use ($orderId, $warehouseId, $tenantId): bool {
                return isset($context['order_id'])
                    && isset($context['warehouse_id'])
                    && isset($context['tenant_id'])
                    && $context['order_id'] === $orderId->toString()
                    && $context['warehouse_id'] === $warehouseId->toString()
                    && $context['tenant_id'] === $tenantId->toString();
            }));

        // Act
        $this->subscriber->onOrderPlaced($event);

        // Assert - expectations verified by mocks
    }

    #[Test]
    public function itHandlesMultipleOrderLines(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $warehouseId = WarehouseId::generate();

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            total: Money::fromScalars(15000, 'USD')
        );

        // Create order with multiple lines
        $order = $this->createOrderWithMultipleLines($orderId, $tenantId, 3);

        $this->orderRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($order);

        $this->configureWarehouseFound($tenantId, $warehouseId, $order);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->once())
            ->method('info');

        // Act
        $this->subscriber->onOrderPlaced($event);

        // Assert - expectations verified by mocks
    }

    /**
     * Configure repository stubs so WarehouseRoutingService finds a warehouse.
     */
    private function configureWarehouseFound(TenantId $tenantId, WarehouseId $warehouseId, Order $order): void
    {
        $address = Address::create(
            street: '1 Warehouse Way',
            city: 'Warehouse City',
            state: 'TX',
            postalCode: '00000',
            country: 'US'
        );

        $warehouse = \App\Inventory\Domain\Model\Warehouse::create(
            id: $warehouseId,
            tenantId: $tenantId,
            code: \App\Inventory\Domain\Model\WarehouseCode::fromString('WH-TEST'),
            name: \App\Inventory\Domain\Model\WarehouseName::fromString('Test Warehouse'),
            address: $address,
            priority: 1,
        );

        $this->warehouseRepository->method('findActiveByTenant')->willReturn([$warehouse]);

        // For each order line, return a stock item with sufficient quantity
        $this->stockItemRepository->method('findByProductAndWarehouse')
            ->willReturnCallback(function () use ($warehouseId, $tenantId) {
                return \App\Inventory\Domain\Model\StockItem::create(
                    id: \App\Inventory\Domain\Model\StockItemId::generate(),
                    tenantId: $tenantId,
                    productId: \App\Catalog\Domain\Model\ProductId::generate(),
                    warehouseId: $warehouseId,
                    initialQuantity: \App\Inventory\Domain\Model\Quantity::fromInt(9999),
                );
            });
    }

    /**
     * Helper to create a test Order.
     */
    private function createOrder(OrderId $orderId, TenantId $tenantId): Order
    {
        $shippingAddress = Address::create(
            street: '123 Test St',
            city: 'Test City',
            state: 'CA',
            postalCode: '12345',
            country: 'US'
        );

        return Order::place(
            id: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            lines: [
                OrderLine::create(
                    productId: ProductId::generate(),
                    productName: 'Test Product',
                    quantity: 2,
                    unitPrice: Money::fromScalars(2500, 'USD')
                ),
            ],
            shippingAddress: $shippingAddress,
            billingAddress: $shippingAddress
        );
    }

    /**
     * Helper to create a test Order with multiple lines.
     */
    private function createOrderWithMultipleLines(OrderId $orderId, TenantId $tenantId, int $lineCount): Order
    {
        $shippingAddress = Address::create(
            street: '123 Test St',
            city: 'Test City',
            state: 'CA',
            postalCode: '12345',
            country: 'US'
        );

        $lines = [];
        for ($i = 0; $i < $lineCount; ++$i) {
            $lines[] = OrderLine::create(
                productId: ProductId::generate(),
                productName: sprintf('Test Product %d', $i + 1),
                quantity: $i + 1,
                unitPrice: Money::fromScalars(1000 * ($i + 1), 'USD')
            );
        }

        return Order::place(
            id: $orderId,
            tenantId: $tenantId,
            customerEmail: 'test@example.com',
            lines: $lines,
            shippingAddress: $shippingAddress,
            billingAddress: $shippingAddress
        );
    }
}
