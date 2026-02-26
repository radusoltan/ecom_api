<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Infrastructure\ACL;

use App\Order\Domain\ValueObject\OrderProductId;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Infrastructure\ACL\OrderPersonalDataContributor;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('order')]
final class OrderPersonalDataContributorTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepository;
    private OrderPersonalDataContributor $contributor;

    private const EMAIL = 'customer@example.com';
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->contributor = new OrderPersonalDataContributor($this->orderRepository);
    }

    public function testContextNameReturnsOrders(): void
    {
        self::assertSame('orders', $this->contributor->contextName());
    }

    public function testCollectsOrderDataByEmail(): void
    {
        $order = $this->buildOrder(
            customerEmail: self::EMAIL,
            status: OrderStatus::delivered(),
            createdAt: new \DateTimeImmutable('2020-01-01T10:00:00+00:00'),
        );

        $this->orderRepository
            ->expects(self::once())
            ->method('findByCustomerEmail')
            ->with(
                self::EMAIL,
                self::callback(fn (TenantId $t) => $t->toString() === self::TENANT_ID),
            )
            ->willReturn([$order]);

        $result = $this->contributor->collectPersonalData([
            'email' => self::EMAIL,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertCount(1, $result);
        $orderData = $result[0];
        self::assertSame($order->id()->toString(), $orderData['id']);
        self::assertSame('delivered', $orderData['status']);
        self::assertSame(self::EMAIL, $orderData['customerEmail']);
        self::assertIsArray($orderData['shippingAddress']);
        self::assertIsArray($orderData['billingAddress']);
        self::assertArrayHasKey('street', $orderData['shippingAddress']);
        self::assertArrayHasKey('city', $orderData['shippingAddress']);
        self::assertArrayHasKey('state', $orderData['shippingAddress']);
        self::assertArrayHasKey('postalCode', $orderData['shippingAddress']);
        self::assertArrayHasKey('country', $orderData['shippingAddress']);
        self::assertArrayHasKey('total', $orderData);
        self::assertArrayHasKey('createdAt', $orderData);
        self::assertArrayHasKey('updatedAt', $orderData);
    }

    public function testReturnsEmptyArrayWhenMissingEmail(): void
    {
        $this->orderRepository
            ->expects(self::never())
            ->method('findByCustomerEmail');

        $result = $this->contributor->collectPersonalData([
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenMissingTenantId(): void
    {
        $this->orderRepository
            ->expects(self::never())
            ->method('findByCustomerEmail');

        $result = $this->contributor->collectPersonalData([
            'email' => self::EMAIL,
        ]);

        self::assertSame([], $result);
    }

    public function testCanDeleteWhenNoOrders(): void
    {
        $this->orderRepository
            ->expects(self::once())
            ->method('findByCustomerEmail')
            ->willReturn([]);

        $result = $this->contributor->canDeleteData([
            'email' => self::EMAIL,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertTrue($result);
    }

    public function testCannotDeleteWhenActiveOrderExists(): void
    {
        // pending is not a final status
        $order = $this->buildOrder(
            customerEmail: self::EMAIL,
            status: OrderStatus::pending(),
            createdAt: new \DateTimeImmutable('2010-01-01T00:00:00+00:00'),
        );

        $this->orderRepository
            ->expects(self::once())
            ->method('findByCustomerEmail')
            ->willReturn([$order]);

        $result = $this->contributor->canDeleteData([
            'email' => self::EMAIL,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertFalse($result);
    }

    public function testCannotDeleteWhenWithinRetentionPeriod(): void
    {
        // Delivered (final status) but within 7-year retention window
        $order = $this->buildOrder(
            customerEmail: self::EMAIL,
            status: OrderStatus::delivered(),
            createdAt: new \DateTimeImmutable('2023-01-01T00:00:00+00:00'),
        );

        $this->orderRepository
            ->expects(self::once())
            ->method('findByCustomerEmail')
            ->willReturn([$order]);

        $result = $this->contributor->canDeleteData([
            'email' => self::EMAIL,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertFalse($result);
    }

    public function testCanDeleteWhenAllOrdersBeyondRetention(): void
    {
        // Delivered (final status) and well beyond 7-year retention window
        $order = $this->buildOrder(
            customerEmail: self::EMAIL,
            status: OrderStatus::delivered(),
            createdAt: new \DateTimeImmutable('2010-01-01T00:00:00+00:00'),
        );

        $this->orderRepository
            ->expects(self::once())
            ->method('findByCustomerEmail')
            ->willReturn([$order]);

        $result = $this->contributor->canDeleteData([
            'email' => self::EMAIL,
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertTrue($result);
    }

    public function testCanDeleteReturnsTrueWhenEmailMissing(): void
    {
        $this->orderRepository
            ->expects(self::never())
            ->method('findByCustomerEmail');

        $result = $this->contributor->canDeleteData([
            'tenantId' => self::TENANT_ID,
        ]);

        self::assertTrue($result);
    }

    public function testCanDeleteReturnsTrueWhenTenantIdMissing(): void
    {
        $this->orderRepository
            ->expects(self::never())
            ->method('findByCustomerEmail');

        $result = $this->contributor->canDeleteData([
            'email' => self::EMAIL,
        ]);

        self::assertTrue($result);
    }

    private function buildOrder(
        string $customerEmail,
        OrderStatus $status,
        \DateTimeImmutable $createdAt,
    ): Order {
        $address = Address::create(
            street: '123 Main St',
            city: 'Springfield',
            state: 'IL',
            postalCode: '62701',
            country: 'US',
        );

        $line = OrderLine::create(
            productId: OrderProductId::generate(),
            productName: 'Test Product',
            quantity: 1,
            unitPrice: Money::fromScalars(1000, 'USD'),
        );

        return Order::reconstituteFromPersistence(
            id: OrderId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: $customerEmail,
            status: $status,
            lines: [$line],
            shippingAddress: $address,
            billingAddress: $address,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );
    }
}
