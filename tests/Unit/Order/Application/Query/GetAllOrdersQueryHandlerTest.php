<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Query;

use App\Order\Application\DTO\OrderDTO;
use App\Order\Application\Query\GetAllOrdersQuery;
use App\Order\Application\Query\GetAllOrdersQueryHandler;
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

#[CoversClass(GetAllOrdersQueryHandler::class)]
final class GetAllOrdersQueryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private OrderRepositoryInterface $orderRepository;
    private GetAllOrdersQueryHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->handler = new GetAllOrdersQueryHandler($this->orderRepository);
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    #[Test]
    public function itReturnsArrayOfOrderDtosForTenant(): void
    {
        $orders = [
            $this->buildOrder('550e8400-e29b-41d4-a716-446655440000', 'a@example.com', 1000),
            $this->buildOrder('550e8400-e29b-41d4-a716-446655440001', 'b@example.com', 2000),
        ];

        $this->orderRepository
            ->expects(self::once())
            ->method('findAllByTenant')
            ->willReturn($orders);

        $query = new GetAllOrdersQuery(tenantId: self::TENANT_ID);

        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertContainsOnlyInstancesOf(OrderDTO::class, $result);
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoOrdersExist(): void
    {
        $this->orderRepository
            ->expects(self::once())
            ->method('findAllByTenant')
            ->willReturn([]);

        $query = new GetAllOrdersQuery(tenantId: self::TENANT_ID);

        $result = ($this->handler)($query);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    #[Test]
    public function itPassesCorrectTenantIdToRepository(): void
    {
        $this->orderRepository
            ->expects(self::once())
            ->method('findAllByTenant')
            ->with(self::callback(fn (TenantId $t) => self::TENANT_ID === $t->toString()))
            ->willReturn([]);

        $query = new GetAllOrdersQuery(tenantId: self::TENANT_ID);

        ($this->handler)($query);
    }

    #[Test]
    public function itMapsDomainOrdersToOrderDtos(): void
    {
        $orders = [
            $this->buildOrder('550e8400-e29b-41d4-a716-446655440000', 'first@example.com', 1500),
        ];

        $this->orderRepository
            ->method('findAllByTenant')
            ->willReturn($orders);

        $query = new GetAllOrdersQuery(tenantId: self::TENANT_ID);

        $result = ($this->handler)($query);

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $result[0]->id);
        self::assertSame(self::TENANT_ID, $result[0]->tenantId);
        self::assertSame('first@example.com', $result[0]->customerEmail);
        self::assertSame(OrderStatus::PENDING, $result[0]->status);
    }

    #[Test]
    public function itPreservesOrderOfReturnedDtos(): void
    {
        $orders = [];
        for ($i = 0; $i < 5; ++$i) {
            $uuid = sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i);
            $orders[] = $this->buildOrder($uuid, "user{$i}@example.com", ($i + 1) * 100);
        }

        $this->orderRepository
            ->method('findAllByTenant')
            ->willReturn($orders);

        $query = new GetAllOrdersQuery(tenantId: self::TENANT_ID);

        $result = ($this->handler)($query);

        self::assertCount(5, $result);

        for ($i = 0; $i < 5; ++$i) {
            self::assertSame("user{$i}@example.com", $result[$i]->customerEmail);
        }
    }

    #[Test]
    public function itMapsTotalAmountIntoDto(): void
    {
        // 3 units * 400 cents = 1200 cents total
        $orders = [
            $this->buildOrder('550e8400-e29b-41d4-a716-446655440000', 'buyer@example.com', 400, 3),
        ];

        $this->orderRepository
            ->method('findAllByTenant')
            ->willReturn($orders);

        $query = new GetAllOrdersQuery(tenantId: self::TENANT_ID);

        $result = ($this->handler)($query);

        self::assertSame(1200, $result[0]->totalAmount);
        self::assertSame('USD', $result[0]->totalCurrency);
    }

    // ---------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------

    private function buildOrder(
        string $orderId,
        string $customerEmail,
        int $unitPriceCents,
        int $quantity = 1,
    ): Order {
        $line = OrderLine::create(
            productId: OrderProductId::generate(),
            productName: 'Product',
            quantity: $quantity,
            unitPrice: Money::fromScalars($unitPriceCents, 'USD'),
        );

        return Order::reconstituteFromPersistence(
            id: OrderId::fromString($orderId),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: $customerEmail,
            status: OrderStatus::pending(),
            lines: [$line],
            shippingAddress: Address::create('1 St', 'City', 'CA', '90001', 'US'),
            billingAddress: Address::create('1 St', 'City', 'CA', '90001', 'US'),
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
        );
    }
}
