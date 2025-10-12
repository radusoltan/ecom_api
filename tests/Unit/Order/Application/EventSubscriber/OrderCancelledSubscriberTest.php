<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\EventSubscriber;

use App\Order\Application\EventSubscriber\OrderCancelledSubscriber;
use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;

#[CoversClass(OrderCancelledSubscriber::class)]
final class OrderCancelledSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private OrderCancelledSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new OrderCancelledSubscriber(
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
    public function it_subscribes_to_order_cancelled_event(): void
    {
        $subscribedEvents = OrderCancelledSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderCancelled::class, $subscribedEvents);
        self::assertEquals('onOrderCancelled', $subscribedEvents[OrderCancelled::class]);
    }

    #[Test]
    public function it_logs_cancellation_information(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            previousStatus: OrderStatus::pending()
        );

        $this->logger
            ->expects(self::exactly(3))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use ($orderId): void {
                if ($message === 'Order cancelled') {
                    self::assertEquals($orderId->toString(), $context['orderId']);
                    self::assertEquals('pending', $context['previousStatus']);
                } elseif ($message === 'Refund process initiated') {
                    self::assertEquals($orderId->toString(), $context['orderId']);
                    self::assertEquals('pending', $context['previousStatus']);
                } elseif ($message === 'Order cancellation processed successfully') {
                    self::assertEquals($orderId->toString(), $context['orderId']);
                }
            });

        // Act
        $this->subscriber->onOrderCancelled($event);
    }

    #[Test]
    public function it_triggers_refund_process(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            previousStatus: OrderStatus::processing()
        );

        $logMessages = [];
        $this->logger
            ->expects(self::atLeastOnce())
            ->method('info')
            ->willReturnCallback(function (string $message) use (&$logMessages): void {
                $logMessages[] = $message;
            });

        // Act
        $this->subscriber->onOrderCancelled($event);

        // Assert
        self::assertContains('Refund process initiated', $logMessages);
    }

    #[Test]
    public function it_logs_warning_about_missing_customer_email(): void
    {
        // Arrange
        $event = new OrderCancelled(
            orderId: OrderId::generate(),
            previousStatus: OrderStatus::pending()
        );

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Cannot send cancellation email - customer email not available in event', self::anything());

        // Act
        $this->subscriber->onOrderCancelled($event);
    }

    #[Test]
    public function it_handles_errors_gracefully_without_throwing(): void
    {
        // Arrange
        $event = new OrderCancelled(
            orderId: OrderId::generate(),
            previousStatus: OrderStatus::processing()
        );

        $exception = new \RuntimeException('Logging system unavailable');

        // First info call throws exception
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->willThrowException($exception);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to process order cancellation notification', self::callback(function (array $context) use ($event, $exception): bool {
                self::assertEquals($event->orderId->toString(), $context['orderId']);
                self::assertEquals($exception->getMessage(), $context['error']);

                return true;
            }));

        // Act & Assert - should not throw
        $this->subscriber->onOrderCancelled($event);
    }

    #[Test]
    public function it_processes_cancellation_from_pending_status(): void
    {
        // Arrange
        $event = new OrderCancelled(
            orderId: OrderId::generate(),
            previousStatus: OrderStatus::pending()
        );

        $loggedStatuses = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$loggedStatuses): void {
                if (isset($context['previousStatus'])) {
                    $loggedStatuses[] = $context['previousStatus'];
                }
            });

        // Act
        $this->subscriber->onOrderCancelled($event);

        // Assert
        self::assertContains('pending', $loggedStatuses);
    }

    #[Test]
    public function it_processes_cancellation_from_processing_status(): void
    {
        // Arrange
        $event = new OrderCancelled(
            orderId: OrderId::generate(),
            previousStatus: OrderStatus::processing()
        );

        $loggedStatuses = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$loggedStatuses): void {
                if (isset($context['previousStatus'])) {
                    $loggedStatuses[] = $context['previousStatus'];
                }
            });

        // Act
        $this->subscriber->onOrderCancelled($event);

        // Assert
        self::assertContains('processing', $loggedStatuses);
    }

    #[Test]
    public function it_logs_successful_cancellation_processing(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $event = new OrderCancelled(
            orderId: $orderId,
            previousStatus: OrderStatus::pending()
        );

        $successLogged = false;
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use ($orderId, &$successLogged): void {
                if ($message === 'Order cancellation processed successfully') {
                    self::assertEquals($orderId->toString(), $context['orderId']);
                    $successLogged = true;
                }
            });

        // Act
        $this->subscriber->onOrderCancelled($event);

        // Assert
        self::assertTrue($successLogged, 'Success message was not logged');
    }

    #[Test]
    public function it_does_not_send_email_when_customer_email_unavailable(): void
    {
        // Arrange
        $event = new OrderCancelled(
            orderId: OrderId::generate(),
            previousStatus: OrderStatus::pending()
        );

        // mailer should never be called because customer email is not in the event
        $this->mailer
            ->expects(self::never())
            ->method('send');

        // Act
        $this->subscriber->onOrderCancelled($event);
    }
}
