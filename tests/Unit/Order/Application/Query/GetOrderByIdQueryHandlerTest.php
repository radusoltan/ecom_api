<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Query;

use App\Order\Application\DTO\OrderDTO;
use App\Order\Application\Query\GetOrderByIdQuery;
use App\Order\Application\Query\GetOrderByIdQueryHandler;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Application\Service\PerformanceProfiler;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(GetOrderByIdQueryHandler::class)]
final class GetOrderByIdQueryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';
    private const ORDER_ID = '550e8400-e29b-41d4-a716-446655440000';

    private OrderRepositoryInterface $orderRepository;
    private PerformanceProfiler $profiler;
    private LoggerInterface $logger;
    private GetOrderByIdQueryHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        // PerformanceProfiler is final — instantiate with a mock logger
        $this->profiler = new PerformanceProfiler($this->createMock(LoggerInterface::class));

        $this->handler = new GetOrderByIdQueryHandler(
            orderRepository: $this->orderRepository,
            profiler: $this->profiler,
            logger: $this->logger,
        );
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsOrderDtoWhenOrderExists(): void
    {
        $order = $this->buildOrder();

        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $query = new GetOrderByIdQuery(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertInstanceOf(OrderDTO::class, $result);
        self::assertSame(self::ORDER_ID, $result->id);
        self::assertSame(self::TENANT_ID, $result->tenantId);
        self::assertSame('customer@example.com', $result->customerEmail);
        self::assertSame(OrderStatus::PENDING, $result->status);
    }

    #[Test]
    public function itReturnsNullWhenOrderDoesNotExist(): void
    {
        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->willReturn(null);

        $query = new GetOrderByIdQuery(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertNull($result);
    }

    #[Test]
    public function itPassesCorrectIdsToRepository(): void
    {
        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->with(
                self::callback(fn (OrderId $id) => self::ORDER_ID === $id->toString()),
                self::callback(fn (TenantId $t) => self::TENANT_ID === $t->toString()),
            )
            ->willReturn(null);

        $query = new GetOrderByIdQuery(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        ($this->handler)($query);
    }

    #[Test]
    public function itMapsOrderLinesIntoDto(): void
    {
        $order = $this->buildOrder();

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $query = new GetOrderByIdQuery(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertNotNull($result);
        self::assertCount(1, $result->lines);
        self::assertSame('Widget', $result->lines[0]['productName']);
        self::assertSame(2, $result->lines[0]['quantity']);
        self::assertSame(500, $result->lines[0]['unitPriceAmount']);
        self::assertSame('USD', $result->lines[0]['unitPriceCurrency']);
    }

    #[Test]
    public function itMapsAddressesIntoDto(): void
    {
        $order = $this->buildOrder();

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $query = new GetOrderByIdQuery(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertNotNull($result);
        self::assertSame('123 Main St', $result->shippingAddress['street']);
        self::assertSame('Springfield', $result->shippingAddress['city']);
        self::assertSame('US', $result->shippingAddress['country']);
    }

    #[Test]
    public function itReturnsCorrectTotalInDto(): void
    {
        $order = $this->buildOrder();

        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willReturn($order);

        $query = new GetOrderByIdQuery(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $result = ($this->handler)($query);

        self::assertNotNull($result);
        // 2 units * 500 cents = 1000 cents
        self::assertSame(1000, $result->totalAmount);
        self::assertSame('USD', $result->totalCurrency);
    }

    #[Test]
    public function itRethrowsExceptionsFromRepository(): void
    {
        $this->orderRepository
            ->method('findByIdAndTenant')
            ->willThrowException(new \RuntimeException('DB error'));

        $query = new GetOrderByIdQuery(
            orderId: self::ORDER_ID,
            tenantId: self::TENANT_ID,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        ($this->handler)($query);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildOrder(): Order
    {
        $line = OrderLine::create(
            productId: OrderProductId::generate(),
            productName: 'Widget',
            quantity: 2,
            unitPrice: Money::fromScalars(500, 'USD'),
        );

        return Order::reconstituteFromPersistence(
            id: OrderId::fromString(self::ORDER_ID),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'customer@example.com',
            status: OrderStatus::pending(),
            lines: [$line],
            shippingAddress: Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US'),
            billingAddress: Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US'),
            createdAt: new \DateTimeImmutable('2024-03-01 10:00:00'),
            updatedAt: new \DateTimeImmutable('2024-03-01 10:00:00'),
        );
    }
}
