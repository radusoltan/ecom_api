<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart\Application\EventSubscriber;

use App\Cart\Application\EventSubscriber\OrderPlacedCartClearingSubscriber;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(OrderPlacedCartClearingSubscriber::class)]
final class OrderPlacedCartClearingSubscriberTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private OrderPlacedCartClearingSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderPlacedCartClearingSubscriber(
            cartRepository: $this->cartRepository,
            messageBus: $this->messageBus,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------
    // Subscribed events registration
    // -------------------------------------------------------

    #[Test]
    public function itSubscribesToOrderPlacedEvent(): void
    {
        $events = OrderPlacedCartClearingSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderPlaced::class, $events);
        self::assertSame('onOrderPlaced', $events[OrderPlaced::class]);
    }

    #[Test]
    public function getSubscribedEventsReturnsArray(): void
    {
        $events = OrderPlacedCartClearingSubscriber::getSubscribedEvents();

        self::assertIsArray($events);
        self::assertCount(1, $events);
    }

    // -------------------------------------------------------
    // Current behaviour: guest checkout path
    // (cart clearing is skipped, only info is logged)
    // -------------------------------------------------------

    #[Test]
    public function itLogsInfoAndSkipsCartClearingForGuestCheckout(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'guest@example.com',
            total: Money::fromScalars(4999, 'EUR'),
        );

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'OrderPlaced event received, cart clearing skipped (guest checkout)',
                self::callback(function (array $context) use ($orderId, $tenantId): bool {
                    return $context['order_id'] === $orderId->toString()
                        && $context['tenant_id'] === $tenantId->toString()
                        && 'guest@example.com' === $context['customer_email'];
                })
            );

        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itDoesNotDispatchClearCartCommandCurrently(): void
    {
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'user@example.com',
            total: Money::fromScalars(1999, 'USD'),
        );

        // Message bus must NOT be called (guest checkout path)
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->logger->method('info');

        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itDoesNotQueryCartRepositoryCurrently(): void
    {
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'user@example.com',
            total: Money::fromScalars(2500, 'GBP'),
        );

        // Repository must NOT be called (guest checkout path)
        $this->cartRepository->expects(self::never())->method('save');

        $this->logger->method('info');

        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itDoesNotThrowForAnyOrderPlacedEvent(): void
    {
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'any@example.com',
            total: Money::fromScalars(100, 'EUR'),
        );

        $this->logger->method('info');

        // Must complete without exception
        $this->subscriber->onOrderPlaced($event);

        self::assertTrue(true);
    }

    // -------------------------------------------------------
    // Logs include all expected context fields
    // -------------------------------------------------------

    #[Test]
    public function logContextIncludesOrderIdTenantIdAndCustomerEmail(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'shopper@store.com';

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: $customerEmail,
            total: Money::fromScalars(7500, 'EUR'),
        );

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context) use ($orderId, $tenantId, $customerEmail): bool {
                    return isset($context['order_id'], $context['tenant_id'], $context['customer_email'])
                        && $context['order_id'] === $orderId->toString()
                        && $context['tenant_id'] === $tenantId->toString()
                        && $context['customer_email'] === $customerEmail;
                })
            );

        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itHandlesDifferentCurrencies(): void
    {
        foreach (['EUR', 'USD', 'GBP', 'PLN'] as $currency) {
            $event = new OrderPlaced(
                orderId: OrderId::generate(),
                tenantId: TenantId::generate(),
                customerEmail: 'user@example.com',
                total: Money::fromScalars(1000, $currency),
            );

            $this->logger->method('info');

            // Must not throw for any currency
            $this->subscriber->onOrderPlaced($event);
        }

        self::assertTrue(true);
    }
}
