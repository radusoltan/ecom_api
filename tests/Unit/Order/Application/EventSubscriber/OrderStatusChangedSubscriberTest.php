<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\EventSubscriber;

use App\Order\Application\EventSubscriber\OrderStatusChangedSubscriber;
use App\Order\Domain\Event\OrderStatusChanged;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;

#[CoversClass(OrderStatusChangedSubscriber::class)]
final class OrderStatusChangedSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private OrderStatusChangedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderStatusChangedSubscriber(
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'test@example.com',
            senderName: 'Test Platform'
        );
    }

    #[Test]
    public function it_implements_event_subscriber_interface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function it_subscribes_to_order_status_changed_event(): void
    {
        $subscribedEvents = OrderStatusChangedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderStatusChanged::class, $subscribedEvents);
        self::assertEquals('onOrderStatusChanged', $subscribedEvents[OrderStatusChanged::class]);
    }

    #[Test]
    #[DataProvider('statusesThatShouldTriggerNotification')]
    public function it_logs_notification_attempt_for_valid_status_changes(OrderStatus $newStatus): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: OrderId::generate(),
            oldStatus: OrderStatus::pending(),
            newStatus: $newStatus
        );

        $this->logger
            ->expects(self::atLeastOnce())
            ->method('warning')
            ->with('Cannot send status change email - customer email not available in event', self::anything());

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }

    public static function statusesThatShouldTriggerNotification(): array
    {
        return [
            'processing' => [OrderStatus::processing()],
            'shipped' => [OrderStatus::shipped()],
            'delivered' => [OrderStatus::delivered()],
        ];
    }

    #[Test]
    #[DataProvider('statusesThatShouldNotTriggerNotification')]
    public function it_does_not_send_notification_for_certain_statuses(OrderStatus $oldStatus, OrderStatus $newStatus): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: $oldStatus === OrderStatus::processing() ? OrderId::generate() : OrderId::generate(),
            oldStatus: $oldStatus,
            newStatus: $newStatus
        );

        // mailer should never be called
        $this->mailer
            ->expects(self::never())
            ->method('send');

        // logger.warning should not be called for non-notifiable statuses
        if ($newStatus->isPending() || $newStatus->isCancelled()) {
            $this->logger
                ->expects(self::never())
                ->method('warning');
        }

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }

    public static function statusesThatShouldNotTriggerNotification(): array
    {
        return [
            'pending to pending' => [OrderStatus::pending(), OrderStatus::pending()],
            'pending to cancelled' => [OrderStatus::pending(), OrderStatus::cancelled()],
            'processing to cancelled' => [OrderStatus::processing(), OrderStatus::cancelled()],
        ];
    }

    #[Test]
    public function it_logs_error_when_notification_fails_but_does_not_throw(): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: OrderId::generate(),
            oldStatus: OrderStatus::pending(),
            newStatus: OrderStatus::processing()
        );

        $exception = new \RuntimeException('Mailer error');

        // Make logger throw exception during warning (simulating failure scenario)
        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->willThrowException($exception);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to send order status change notification', self::callback(function (array $context) use ($event, $exception): bool {
                self::assertEquals($event->orderId->toString(), $context['orderId']);
                self::assertEquals($event->oldStatus->value(), $context['oldStatus']);
                self::assertEquals($event->newStatus->value(), $context['newStatus']);
                self::assertEquals($exception->getMessage(), $context['error']);

                return true;
            }));

        // Act & Assert - should not throw
        $this->subscriber->onOrderStatusChanged($event);
    }

    #[Test]
    public function it_logs_status_change_information(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $event = new OrderStatusChanged(
            orderId: $orderId,
            oldStatus: OrderStatus::pending(),
            newStatus: OrderStatus::processing()
        );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Cannot send status change email - customer email not available in event', self::callback(function (array $context) use ($orderId): bool {
                self::assertEquals($orderId->toString(), $context['orderId']);
                self::assertEquals('processing', $context['newStatus']);

                return true;
            }));

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }

    #[Test]
    public function it_handles_processing_status(): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: OrderId::generate(),
            oldStatus: OrderStatus::pending(),
            newStatus: OrderStatus::processing()
        );

        $this->logger
            ->expects(self::once())
            ->method('warning');

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }

    #[Test]
    public function it_handles_shipped_status(): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: OrderId::generate(),
            oldStatus: OrderStatus::processing(),
            newStatus: OrderStatus::shipped()
        );

        $this->logger
            ->expects(self::once())
            ->method('warning');

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }

    #[Test]
    public function it_handles_delivered_status(): void
    {
        // Arrange
        $event = new OrderStatusChanged(
            orderId: OrderId::generate(),
            oldStatus: OrderStatus::shipped(),
            newStatus: OrderStatus::delivered()
        );

        $this->logger
            ->expects(self::once())
            ->method('warning');

        // Act
        $this->subscriber->onOrderStatusChanged($event);
    }
}
