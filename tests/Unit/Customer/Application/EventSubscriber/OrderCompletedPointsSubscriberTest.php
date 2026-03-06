<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\EventSubscriber;

use App\Customer\Application\EventSubscriber\OrderCompletedPointsSubscriber;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Model\LoyaltyProgram;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Repository\LoyaltyProgramRepositoryInterface;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Customer\Domain\ValueObject\EarningRate;
use App\Customer\Domain\ValueObject\RedemptionRule;
use App\Order\Domain\Event\OrderPaid;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(OrderCompletedPointsSubscriber::class)]
final class OrderCompletedPointsSubscriberTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private LoyaltyProgramRepositoryInterface&MockObject $loyaltyProgramRepository;
    private LoggerInterface&MockObject $logger;
    private OrderCompletedPointsSubscriber $subscriber;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->loyaltyProgramRepository = $this->createMock(LoyaltyProgramRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderCompletedPointsSubscriber(
            customerRepository: $this->customerRepository,
            orderRepository: $this->orderRepository,
            loyaltyProgramRepository: $this->loyaltyProgramRepository,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToOrderPaidEvent(): void
    {
        $events = OrderCompletedPointsSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderPaid::class, $events);
        self::assertSame('onOrderPaid', $events[OrderPaid::class]);
    }

    #[Test]
    public function itReturnsExactlyOneSubscribedEvent(): void
    {
        $events = OrderCompletedPointsSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // No loyalty program: skip silently
    // -------------------------------------------------------

    #[Test]
    public function itSkipsWhenNoLoyaltyProgramExists(): void
    {
        $event = $this->buildOrderPaidEvent();

        $this->loyaltyProgramRepository
            ->expects(self::once())
            ->method('findByTenantId')
            ->willReturn(null);

        $this->orderRepository->expects(self::never())->method('findByIdAndTenant');
        $this->customerRepository->expects(self::never())->method('findByEmail');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('No loyalty program found for tenant', self::anything());

        $this->subscriber->onOrderPaid($event);
    }

    // -------------------------------------------------------
    // Inactive program: skip silently
    // -------------------------------------------------------

    #[Test]
    public function itSkipsWhenLoyaltyProgramIsInactive(): void
    {
        $event = $this->buildOrderPaidEvent();

        $program = LoyaltyProgram::create(
            tenantId: TenantId::fromString(self::TENANT_ID),
            name: 'Points Program',
            earningRate: EarningRate::fromFloat(1.0),
            minOrderValue: Money::fromScalars(0, 'EUR'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );
        // Deactivate: create activates by default, so deactivate manually
        // LoyaltyProgram::create() sets isActive=true, call deactivate()
        $program->deactivate();

        $this->loyaltyProgramRepository
            ->method('findByTenantId')
            ->willReturn($program);

        $this->orderRepository->expects(self::never())->method('findByIdAndTenant');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('Loyalty program is not active, skipping points award', self::anything());

        $this->subscriber->onOrderPaid($event);
    }

    // -------------------------------------------------------
    // Order not found: logs warning, no points awarded
    // -------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenOrderNotFound(): void
    {
        $event = $this->buildOrderPaidEvent();
        $program = $this->buildActiveLoyaltyProgram();

        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);

        $this->orderRepository
            ->expects(self::once())
            ->method('findByIdAndTenant')
            ->willReturn(null);

        $this->customerRepository->expects(self::never())->method('findByEmail');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Order not found for points award', self::anything());

        $this->subscriber->onOrderPaid($event);
    }

    // -------------------------------------------------------
    // Customer not found: logs warning, no points awarded
    // -------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenCustomerNotFound(): void
    {
        $orderId = \App\Order\Domain\Model\OrderId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $event = $this->buildOrderPaidEvent(orderId: $orderId->toString());

        $program = $this->buildActiveLoyaltyProgram();
        $order = $this->buildOrderMock('customer@example.com', 5000);

        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);
        $this->orderRepository->method('findByIdAndTenant')->willReturn($order);
        $this->customerRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Customer not found for points award', self::anything());

        $this->subscriber->onOrderPaid($event);
    }

    // -------------------------------------------------------
    // Happy path: points awarded to customer
    // -------------------------------------------------------

    #[Test]
    public function itAwardsLoyaltyPointsToCustomer(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $event = $this->buildOrderPaidEvent();

        // 1 point per EUR (minor units), order total 5000 cents = 5000 points
        $program = $this->buildActiveLoyaltyProgram(earningRate: 1.0, minOrderValueCents: 0);
        $order = $this->buildOrderMock('customer@example.com', 5000);

        $customer = $this->buildCustomerMock('customer@example.com', $tenantId);
        $customer->expects(self::once())
            ->method('awardLoyaltyPoints')
            ->with(
                self::isInt(),
                self::stringContains($event->orderId),
            );

        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);
        $this->orderRepository->method('findByIdAndTenant')->willReturn($order);
        $this->customerRepository->method('findByEmail')->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $this->logger->expects(self::once())->method('info')
            ->with('Loyalty points awarded for order payment', self::anything());

        $this->subscriber->onOrderPaid($event);
    }

    #[Test]
    public function itSavesCustomerAfterAwardingPoints(): void
    {
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $event = $this->buildOrderPaidEvent();

        $program = $this->buildActiveLoyaltyProgram();
        $order = $this->buildOrderMock('customer@example.com', 5000);
        $customer = $this->buildCustomerMock('customer@example.com', $tenantId);
        $customer->method('awardLoyaltyPoints');

        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);
        $this->orderRepository->method('findByIdAndTenant')->willReturn($order);
        $this->customerRepository->method('findByEmail')->willReturn($customer);

        $this->customerRepository
            ->expects(self::once())
            ->method('save')
            ->with($customer);

        $this->subscriber->onOrderPaid($event);
    }

    // -------------------------------------------------------
    // Zero points: below minimum order value — skip
    // -------------------------------------------------------

    #[Test]
    public function itSkipsWhenOrderTotalIsBelowMinimumOrderValue(): void
    {
        $event = $this->buildOrderPaidEvent();

        // Min order value = 200 EUR (20000 cents), order total = 5000 cents — below minimum
        $program = $this->buildActiveLoyaltyProgram(earningRate: 1.0, minOrderValueCents: 20000);
        $order = $this->buildOrderMock('customer@example.com', 5000);

        $this->loyaltyProgramRepository->method('findByTenantId')->willReturn($program);
        $this->orderRepository->method('findByIdAndTenant')->willReturn($order);

        $this->customerRepository->expects(self::never())->method('findByEmail');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('No points to award for order (below minimum or zero)', self::anything());

        $this->subscriber->onOrderPaid($event);
    }

    // -------------------------------------------------------
    // Error handling: exception does not propagate
    // -------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenExceptionOccurs(): void
    {
        $event = $this->buildOrderPaidEvent();

        $this->loyaltyProgramRepository
            ->method('findByTenantId')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        // Must not throw
        $this->subscriber->onOrderPaid($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenExceptionOccurs(): void
    {
        $event = $this->buildOrderPaidEvent();

        $this->loyaltyProgramRepository
            ->method('findByTenantId')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to award loyalty points for order payment', self::anything());

        $this->subscriber->onOrderPaid($event);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function buildOrderPaidEvent(?string $orderId = null): OrderPaid
    {
        return new OrderPaid(
            orderId: $orderId ?? \App\Order\Domain\Model\OrderId::generate()->toString(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            paymentId: 'pay_test_123',
            paidAmountInCents: 5000,
            occurredOn: new \DateTimeImmutable(),
        );
    }

    private function buildActiveLoyaltyProgram(float $earningRate = 1.0, int $minOrderValueCents = 100): LoyaltyProgram
    {
        return LoyaltyProgram::create(
            tenantId: TenantId::fromString(self::TENANT_ID),
            name: 'Test Points Program',
            earningRate: EarningRate::fromFloat($earningRate),
            minOrderValue: Money::fromScalars($minOrderValueCents, 'EUR'),
            redemptionRule: RedemptionRule::create(100.0, 100),
        );
    }

    private function buildOrderMock(string $customerEmail, int $totalCents): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('customerEmail')->willReturn($customerEmail);
        $order->method('total')->willReturn(Money::fromScalars($totalCents, 'EUR'));

        return $order;
    }

    private function buildCustomerMock(string $email, TenantId $tenantId): Customer&MockObject
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('id')->willReturn(CustomerId::generate());
        $customer->method('email')->willReturn(Email::fromString($email));
        $customer->method('loyaltyPoints')->willReturn(0);
        $customer->method('segment')->willReturn(CustomerSegment::regular());

        return $customer;
    }
}
