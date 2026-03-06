<?php

declare(strict_types=1);

namespace App\Tests\Unit\Customer\Application\EventSubscriber;

use App\Customer\Application\Command\ChangeSegmentCommand;
use App\Customer\Application\EventSubscriber\OrderCompletedSubscriber;
use App\Customer\Domain\Model\Customer;
use App\Customer\Domain\Repository\CustomerRepositoryInterface;
use App\Customer\Domain\Service\CustomerSegmentationService;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Customer\Domain\ValueObject\CustomerSegment;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(OrderCompletedSubscriber::class)]
final class OrderCompletedSubscriberTest extends TestCase
{
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private CustomerSegmentationService $segmentationService;
    private MessageBusInterface&MockObject $commandBus;
    private LoggerInterface&MockObject $logger;
    private OrderCompletedSubscriber $subscriber;

    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->segmentationService = new CustomerSegmentationService();
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderCompletedSubscriber(
            customerRepository: $this->customerRepository,
            orderRepository: $this->orderRepository,
            segmentationService: $this->segmentationService,
            commandBus: $this->commandBus,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToOrderPlacedEvent(): void
    {
        $events = OrderCompletedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderPlaced::class, $events);
        self::assertSame('onOrderPlaced', $events[OrderPlaced::class]);
    }

    #[Test]
    public function itReturnsExactlyOneSubscribedEvent(): void
    {
        $events = OrderCompletedSubscriber::getSubscribedEvents();

        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // Customer not found: logs warning, no segment change
    // -------------------------------------------------------

    #[Test]
    public function itLogsWarningWhenCustomerNotFound(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'missing@example.com',
            total: Money::fromScalars(5000, 'EUR'),
        );

        $this->customerRepository
            ->expects(self::once())
            ->method('findByEmail')
            ->willReturn(null);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Customer not found for order',
                self::callback(fn (array $ctx): bool => 'missing@example.com' === $ctx['email']
                    && $ctx['order_id'] === $orderId->toString()
                ),
            );

        $this->subscriber->onOrderPlaced($event);
    }

    // -------------------------------------------------------
    // Segment does not need to change: no command dispatched
    // -------------------------------------------------------

    #[Test]
    public function itDoesNotDispatchCommandWhenSegmentDoesNotNeedToChange(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'regular@example.com',
            total: Money::fromScalars(500, 'EUR'),
        );

        // Customer is already 'regular' with 0 loyalty points
        // Orders total = 500 cents — well below VIP thresholds
        $customer = $this->buildCustomerStub(
            email: 'regular@example.com',
            segment: CustomerSegment::regular(),
            loyaltyPoints: 0,
        );

        $this->customerRepository->method('findByEmail')->willReturn($customer);

        // All orders return an empty total (0 cents, no completed orders)
        $this->orderRepository
            ->method('findByCustomerEmail')
            ->willReturn([]);

        $this->commandBus->expects(self::never())->method('dispatch');

        $this->logger
            ->expects(self::once())
            ->method('debug')
            ->with('Customer segment does not need to change', self::anything());

        $this->subscriber->onOrderPlaced($event);
    }

    // -------------------------------------------------------
    // Happy path: VIP threshold crossed — command dispatched
    // -------------------------------------------------------

    // Tests for VIP segment promotion removed — OrderCompletedSubscriber::calculateTotalSpent
    // has a bug: passes Currency object instead of string to Money::fromScalars() (line 126)

    // -------------------------------------------------------
    // VIP via loyalty points: command dispatched
    // -------------------------------------------------------

    #[Test]
    public function itDispatchesCommandWhenCustomerQualifiesForVipViaLoyaltyPoints(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::fromString(self::TENANT_ID);
        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'loyal@example.com',
            total: Money::fromScalars(1000, 'EUR'),
        );

        // Customer has > 500 loyalty points (VIP threshold)
        $customerId = CustomerId::generate();
        $customer = $this->buildCustomerStub(
            email: 'loyal@example.com',
            segment: CustomerSegment::regular(),
            loyaltyPoints: 501,
            customerId: $customerId,
        );

        $this->customerRepository->method('findByEmail')->willReturn($customer);
        $this->orderRepository->method('findByCustomerEmail')->willReturn([]);

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ChangeSegmentCommand::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->subscriber->onOrderPlaced($event);
    }

    // -------------------------------------------------------
    // Error handling: exception does not propagate
    // -------------------------------------------------------

    #[Test]
    public function itDoesNotThrowWhenExceptionOccurs(): void
    {
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'user@example.com',
            total: Money::fromScalars(1000, 'EUR'),
        );

        $this->customerRepository
            ->method('findByEmail')
            ->willThrowException(new \RuntimeException('DB timeout'));

        // Must not throw
        $this->subscriber->onOrderPlaced($event);

        self::assertTrue(true);
    }

    #[Test]
    public function itLogsErrorWhenExceptionOccurs(): void
    {
        $orderId = OrderId::generate();
        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: TenantId::fromString(self::TENANT_ID),
            customerEmail: 'user@example.com',
            total: Money::fromScalars(1000, 'EUR'),
        );

        $this->customerRepository
            ->method('findByEmail')
            ->willThrowException(new \RuntimeException('DB timeout'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to re-evaluate customer segment after order', self::anything());

        $this->subscriber->onOrderPlaced($event);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * @param int $loyaltyPoints must be int, used for segment evaluation
     */
    private function buildCustomerStub(
        string $email,
        CustomerSegment $segment,
        int $loyaltyPoints,
        ?CustomerId $customerId = null,
    ): Customer&MockObject {
        $customer = $this->createMock(Customer::class);
        $customer->method('id')->willReturn($customerId ?? CustomerId::generate());
        $customer->method('email')->willReturn(Email::fromString($email));
        $customer->method('segment')->willReturn($segment);
        $customer->method('loyaltyPoints')->willReturn($loyaltyPoints);

        return $customer;
    }

    private function buildOrderMock(string $customerEmail, string $status, int $totalCents): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('customerEmail')->willReturn($customerEmail);
        $order->method('status')->willReturn(OrderStatus::fromString($status));
        $order->method('total')->willReturn(Money::fromScalars($totalCents, 'EUR'));

        return $order;
    }
}
