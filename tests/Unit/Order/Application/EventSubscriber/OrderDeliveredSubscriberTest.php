<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\EventSubscriber;

use App\Order\Application\EventSubscriber\OrderDeliveredSubscriber;
use App\Order\Domain\Event\OrderDelivered;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(OrderDeliveredSubscriber::class)]
final class OrderDeliveredSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private TranslatorInterface $translator;
    private LoggerInterface $logger;
    private OrderDeliveredSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Configure translator to return translated strings
        $this->translator
            ->method('trans')
            ->willReturnCallback(function (string $id): string {
                return match ($id) {
                    'emails.order.delivered.title' => 'Delivery Confirmation',
                    default => $id,
                };
            });

        $this->subscriber = new OrderDeliveredSubscriber(
            mailer: $this->mailer,
            translator: $this->translator,
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
    public function it_subscribes_to_order_delivered_event(): void
    {
        $subscribedEvents = OrderDeliveredSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderDelivered::class, $subscribedEvents);
        self::assertEquals('onOrderDelivered', $subscribedEvents[OrderDelivered::class]);
    }

    #[Test]
    public function it_sends_delivery_confirmation_email_when_order_is_delivered(): void
    {
        // Arrange
        $event = new OrderDelivered(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            deliveryDate: new \DateTimeImmutable('2025-11-07 14:30:00'),
            deliveryMethod: 'express',
            customerEmail: 'customer@example.com',
            occurredOn: new \DateTimeImmutable()
        );

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $toAddresses = $email->getTo();
                self::assertCount(1, $toAddresses);
                self::assertEquals('customer@example.com', $toAddresses[0]->getAddress());
                self::assertStringContainsString('Delivery Confirmation', $email->getSubject());

                return true;
            }));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Order delivery confirmation email sent', self::anything());

        // Act
        $this->subscriber->onOrderDelivered($event);
    }

    #[Test]
    public function it_logs_error_when_email_fails_but_does_not_throw(): void
    {
        // Arrange
        $event = new OrderDelivered(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            deliveryDate: new \DateTimeImmutable(),
            deliveryMethod: 'standard',
            customerEmail: 'customer@example.com',
            occurredOn: new \DateTimeImmutable()
        );

        $exception = new \RuntimeException('SMTP server unavailable');

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to send order delivery confirmation email', self::callback(function (array $context) use ($event, $exception): bool {
                self::assertEquals($event->orderId->toString(), $context['orderId']);
                self::assertEquals($event->customerEmail, $context['customerEmail']);
                self::assertEquals($exception->getMessage(), $context['error']);

                return true;
            }));

        // Act & Assert - should not throw
        $this->subscriber->onOrderDelivered($event);
    }

    #[Test]
    public function it_sends_email_to_customer(): void
    {
        // Arrange
        $event = new OrderDelivered(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            deliveryDate: new \DateTimeImmutable('2025-11-07 14:30:00'),
            deliveryMethod: 'express',
            customerEmail: 'customer@example.com',
            occurredOn: new \DateTimeImmutable()
        );

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $toAddresses = $email->getTo();
                self::assertCount(1, $toAddresses);
                self::assertEquals('customer@example.com', $toAddresses[0]->getAddress());

                return true;
            }));

        // Act
        $this->subscriber->onOrderDelivered($event);
    }

    #[Test]
    public function it_uses_configured_sender_email_and_name(): void
    {
        // Arrange
        $event = new OrderDelivered(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            deliveryDate: new \DateTimeImmutable(),
            deliveryMethod: 'standard',
            customerEmail: 'customer@example.com',
            occurredOn: new \DateTimeImmutable()
        );

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $from = $email->getFrom();
                self::assertCount(1, $from);
                self::assertEquals('test@example.com', $from[0]->getAddress());
                self::assertEquals('Test Platform', $from[0]->getName());

                return true;
            }));

        // Act
        $this->subscriber->onOrderDelivered($event);
    }

    #[Test]
    public function it_logs_order_id_tenant_id_and_delivery_details(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();
        $deliveryDate = new \DateTimeImmutable('2025-11-07 14:30:00');

        $event = new OrderDelivered(
            orderId: $orderId,
            tenantId: $tenantId,
            deliveryDate: $deliveryDate,
            deliveryMethod: 'express',
            customerEmail: 'customer@example.com',
            occurredOn: new \DateTimeImmutable()
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Order delivery confirmation email sent', self::callback(function (array $context) use ($orderId, $tenantId, $deliveryDate): bool {
                self::assertEquals($orderId->toString(), $context['orderId']);
                self::assertEquals($tenantId->toString(), $context['tenantId']);
                self::assertEquals('customer@example.com', $context['customerEmail']);
                self::assertEquals($deliveryDate->format('Y-m-d H:i:s'), $context['deliveryDate']);
                self::assertEquals('express', $context['deliveryMethod']);

                return true;
            }));

        // Act
        $this->subscriber->onOrderDelivered($event);
    }

}
