<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Application\EventSubscriber;

use App\Notifications\Application\EventSubscriber\OrderNotificationSubscriber;
use App\Notifications\Domain\Service\EmailSenderInterface;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class OrderNotificationSubscriberTest extends TestCase
{
    private MockObject&EmailSenderInterface $emailSender;
    private MockObject&LoggerInterface $logger;
    private OrderNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->emailSender = $this->createMock(EmailSenderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderNotificationSubscriber(
            $this->emailSender,
            $this->logger
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OrderNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(OrderPlaced::class, $events);
        $this->assertArrayHasKey(OrderStatusChanged::class, $events);
        $this->assertArrayHasKey(OrderCancelled::class, $events);
        $this->assertEquals('onOrderPlaced', $events[OrderPlaced::class]);
        $this->assertEquals('onOrderStatusChanged', $events[OrderStatusChanged::class]);
        $this->assertEquals('onOrderCancelled', $events[OrderCancelled::class]);
    }

    public function testOnOrderPlacedSendsEmail(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $total = Money::fromScalars(10000, 'USD');

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: $customerEmail,
            total: $total
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo($customerEmail),
                $this->equalTo('Order Confirmation'),
                $this->equalTo('order/order_placed'),
                $this->callback(function (array $context) use ($orderId) {
                    return $context['orderId'] === $orderId->toString()
                        && '100.00' === $context['total']
                        && 'USD' === $context['currency'];
                })
            );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Order placed notification email sent'),
                $this->callback(function (array $context) use ($orderId, $customerEmail, $tenantId) {
                    return $context['orderId'] === $orderId->toString()
                        && $context['customerEmail'] === $customerEmail
                        && $context['tenantId'] === $tenantId->toString();
                })
            );

        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderPlacedHandlesEmailFailureGracefully(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $customerEmail = 'customer@example.com';
        $total = Money::fromScalars(10000, 'USD');

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: $customerEmail,
            total: $total
        );

        $exception = new \RuntimeException('Email service unavailable');

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Failed to send order placed email'),
                $this->callback(function (array $context) use ($orderId, $customerEmail) {
                    return $context['orderId'] === $orderId->toString()
                        && $context['customerEmail'] === $customerEmail
                        && 'Email service unavailable' === $context['error'];
                })
            );

        // Should not throw exception
        $this->subscriber->onOrderPlaced($event);
    }

    public function testOnOrderStatusChangedSendsEmailForPaidStatus(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $oldStatus = OrderStatus::pending();
        $newStatus = OrderStatus::paid();

        $event = new OrderStatusChanged(
            orderId: $orderId,
            tenantId: $tenantId,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Order Update'),
                $this->equalTo('order/order_paid'),
                $this->callback(function (array $context) use ($orderId) {
                    return $context['orderId'] === $orderId->toString()
                        && 'paid' === $context['status']
                        && 'pending' === $context['previousStatus'];
                })
            );

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->subscriber->onOrderStatusChanged($event);
    }

    public function testOnOrderStatusChangedSendsEmailForShippedStatus(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $oldStatus = OrderStatus::paid();
        $newStatus = OrderStatus::shipped();

        $event = new OrderStatusChanged(
            orderId: $orderId,
            tenantId: $tenantId,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Order Update'),
                $this->equalTo('order/order_shipped')
            );

        $this->subscriber->onOrderStatusChanged($event);
    }

    public function testOnOrderStatusChangedSendsEmailForDeliveredStatus(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $oldStatus = OrderStatus::shipped();
        $newStatus = OrderStatus::delivered();

        $event = new OrderStatusChanged(
            orderId: $orderId,
            tenantId: $tenantId,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Order Update'),
                $this->equalTo('order/order_delivered')
            );

        $this->subscriber->onOrderStatusChanged($event);
    }

    public function testOnOrderStatusChangedSkipsEmailForPendingStatus(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $oldStatus = OrderStatus::pending();
        $newStatus = OrderStatus::pending();

        $event = new OrderStatusChanged(
            orderId: $orderId,
            tenantId: $tenantId,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $this->emailSender
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('No email template for order status change'),
                $this->callback(function (array $context) {
                    return 'pending' === $context['newStatus'];
                })
            );

        $this->subscriber->onOrderStatusChanged($event);
    }

    public function testOnOrderStatusChangedHandlesEmailFailureGracefully(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $oldStatus = OrderStatus::pending();
        $newStatus = OrderStatus::paid();

        $event = new OrderStatusChanged(
            orderId: $orderId,
            tenantId: $tenantId,
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        $exception = new \RuntimeException('Email service unavailable');

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error');

        // Should not throw exception
        $this->subscriber->onOrderStatusChanged($event);
    }

    public function testOnOrderCancelledSendsEmail(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $previousStatus = OrderStatus::pending();
        $reason = 'Customer requested cancellation';

        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: $previousStatus,
            reason: $reason
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Order Cancelled'),
                $this->equalTo('order/order_cancelled'),
                $this->callback(function (array $context) use ($orderId, $reason) {
                    return $context['orderId'] === $orderId->toString()
                        && $context['reason'] === $reason;
                })
            );

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->subscriber->onOrderCancelled($event);
    }

    public function testOnOrderCancelledWithNoReason(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $previousStatus = OrderStatus::pending();

        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: $previousStatus,
            reason: null
        );

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->with(
                $this->anything(),
                $this->equalTo('Order Cancelled'),
                $this->equalTo('order/order_cancelled'),
                $this->callback(function (array $context) {
                    return 'No reason provided' === $context['reason'];
                })
            );

        $this->subscriber->onOrderCancelled($event);
    }

    public function testOnOrderCancelledHandlesEmailFailureGracefully(): void
    {
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $previousStatus = OrderStatus::pending();

        $event = new OrderCancelled(
            orderId: $orderId,
            tenantId: $tenantId,
            previousStatus: $previousStatus
        );

        $exception = new \RuntimeException('Email service unavailable');

        $this->emailSender
            ->expects($this->once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error');

        // Should not throw exception
        $this->subscriber->onOrderCancelled($event);
    }
}
