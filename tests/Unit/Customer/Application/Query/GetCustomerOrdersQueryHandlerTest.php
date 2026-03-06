<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\Query;

use App\Customer\Application\Query\GetCustomerOrdersQuery;
use App\Customer\Application\Query\GetCustomerOrdersQueryHandler;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetCustomerOrdersQueryHandler::class)]
final class GetCustomerOrdersQueryHandlerTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    private CustomerRepositoryInterface&MockObject $customerRepository;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private GetCustomerOrdersQueryHandler $handler;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->handler = new GetCustomerOrdersQueryHandler(
            $this->customerRepository,
            $this->orderRepository,
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeCustomer(string $email = 'john@example.com'): Customer
    {
        $customer = Customer::register(
            CustomerId::generate(),
            TenantId::fromString(self::TENANT_ID),
            Email::fromString($email),
            'John',
            'Doe',
        );
        $customer->popEvents();

        return $customer;
    }

    private function makeOrder(string $email = 'john@example.com'): Order
    {
        $address = Address::create('123 Main St', 'Springfield', 'IL', '62701', 'US');
        $line = OrderLine::create(
            OrderProductId::generate(),
            'Widget',
            1,
            Money::of('10.00', 'USD'),
        );

        $order = Order::place(
            OrderId::generate(),
            TenantId::fromString(self::TENANT_ID),
            $email,
            [$line],
            $address,
            $address,
        );
        $order->popEvents();

        return $order;
    }

    // -----------------------------------------------------------------------
    // Happy path: customer found with orders
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsMappedOrdersWhenCustomerAndOrdersExist(): void
    {
        $customer = $this->makeCustomer('john@example.com');
        $order = $this->makeOrder('john@example.com');
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->customerRepository
            ->expects(self::once())
            ->method('findById')
            ->willReturn($customer);

        $this->orderRepository
            ->expects(self::once())
            ->method('findByCustomerEmail')
            ->with('john@example.com', self::isInstanceOf(TenantId::class))
            ->willReturn([$order]);

        $query = new GetCustomerOrdersQuery($customer->id(), $tenantId);
        $result = ($this->handler)($query);

        self::assertCount(1, $result);
        self::assertArrayHasKey('id', $result[0]);
        self::assertArrayHasKey('status', $result[0]);
        self::assertArrayHasKey('total', $result[0]);
        self::assertArrayHasKey('createdAt', $result[0]);
        self::assertSame($order->id()->toString(), $result[0]['id']);
        self::assertSame(OrderStatus::PENDING, $result[0]['status']);
    }

    #[Test]
    public function itReturnsMultipleOrdersInArray(): void
    {
        $customer = $this->makeCustomer('multi@example.com');
        $order1 = $this->makeOrder('multi@example.com');
        $order2 = $this->makeOrder('multi@example.com');
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([$order1, $order2]);

        $result = ($this->handler)(new GetCustomerOrdersQuery($customer->id(), $tenantId));

        self::assertCount(2, $result);
    }

    // -----------------------------------------------------------------------
    // Customer not found
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenCustomerNotFound(): void
    {
        $this->customerRepository->method('findById')->willReturn(null);
        $this->orderRepository->expects(self::never())->method('findByCustomerEmail');

        $query = new GetCustomerOrdersQuery(
            CustomerId::generate(),
            TenantId::fromString(self::TENANT_ID),
        );
        $result = ($this->handler)($query);

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // Customer found but no orders
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyArrayWhenCustomerHasNoOrders(): void
    {
        $customer = $this->makeCustomer();
        $tenantId = TenantId::fromString(self::TENANT_ID);

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([]);

        $result = ($this->handler)(new GetCustomerOrdersQuery($customer->id(), $tenantId));

        self::assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // Mapped fields content
    // -----------------------------------------------------------------------

    #[Test]
    public function itMapsCreatedAtAsIso8601String(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder();

        $this->customerRepository->method('findById')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([$order]);

        $result = ($this->handler)(new GetCustomerOrdersQuery(
            $customer->id(),
            TenantId::fromString(self::TENANT_ID),
        ));

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/',
            $result[0]['createdAt'],
        );
    }
}
