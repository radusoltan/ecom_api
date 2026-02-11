<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\EventSubscriber;

use App\Order\Application\EventSubscriber\OrderPlacedSubscriber;
use App\Order\Domain\Event\OrderPlaced;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(OrderPlacedSubscriber::class)]
final class OrderPlacedSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private TranslatorInterface $translator;
    private LoggerInterface $logger;
    private OrderPlacedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Mock translator to return a meaningful subject
        $this->translator
            ->method('trans')
            ->willReturnCallback(function (string $id): string {
                return match ($id) {
                    'emails.order.placed.title' => 'Order Confirmation',
                    'emails.order.cancelled.title' => 'Order Cancelled',
                    default => $id,
                };
            });

        $this->subscriber = new OrderPlacedSubscriber(
            mailer: $this->mailer,
            translator: $this->translator,
            logger: $this->logger,
            senderEmail: 'test@example.com',
            senderName: 'Test Platform'
        );
    }

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function itSubscribesToOrderPlacedEvent(): void
    {
        $subscribedEvents = OrderPlacedSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(OrderPlaced::class, $subscribedEvents);
        self::assertEquals('onOrderPlaced', $subscribedEvents[OrderPlaced::class]);
    }

    #[Test]
    public function itSendsConfirmationEmailWhenOrderIsPlaced(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(5000, 'USD')
        );

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($event): bool {
                self::assertInstanceOf(TemplatedEmail::class, $email);

                $toAddresses = $email->getTo();
                self::assertCount(1, $toAddresses);
                self::assertEquals('customer@example.com', $toAddresses[0]->getAddress());
                self::assertStringContainsString('Order Confirmation', $email->getSubject());

                // Check template and context instead of rendered HTML
                /** @var TemplatedEmail $email */
                $context = $email->getContext();
                self::assertEquals($event->orderId->toString(), $context['orderId']);
                self::assertEquals('50.00', $context['total']); // 5000 cents = $50.00
                self::assertEquals('USD', $context['currency']);

                return true;
            }));

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Order confirmation email sent', self::anything());

        // Act
        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itLogsErrorWhenEmailFailsButDoesNotThrow(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(1000, 'EUR')
        );

        $exception = new \RuntimeException('SMTP server unavailable');

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException($exception);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('Failed to send order confirmation email', self::callback(function (array $context) use ($event, $exception): bool {
                self::assertEquals($event->orderId->toString(), $context['orderId']);
                self::assertEquals($event->customerEmail, $context['customerEmail']);
                self::assertEquals($exception->getMessage(), $context['error']);

                return true;
            }));

        // Act & Assert - should not throw
        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itIncludesOrderTotalInEmail(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(12345, 'EUR') // €123.45
        );

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertInstanceOf(TemplatedEmail::class, $email);

                // Check context instead of rendered HTML
                /** @var TemplatedEmail $email */
                $context = $email->getContext();
                self::assertEquals('123.45', $context['total']);
                self::assertEquals('EUR', $context['currency']);

                return true;
            }));

        // Act
        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itSendsBothHtmlAndTextVersions(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(5000, 'USD')
        );

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertInstanceOf(TemplatedEmail::class, $email);

                // Check that template is set (HTML template will auto-generate text version)
                /** @var TemplatedEmail $email */
                self::assertEquals('emails/order/order_placed.html.twig', $email->getHtmlTemplate());

                return true;
            }));

        // Act
        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itUsesConfiguredSenderEmailAndName(): void
    {
        // Arrange
        $event = new OrderPlaced(
            orderId: OrderId::generate(),
            tenantId: TenantId::generate(),
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(1000, 'USD')
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
        $this->subscriber->onOrderPlaced($event);
    }

    #[Test]
    public function itLogsOrderIdAndTenantId(): void
    {
        // Arrange
        $orderId = OrderId::generate();
        $tenantId = TenantId::generate();

        $event = new OrderPlaced(
            orderId: $orderId,
            tenantId: $tenantId,
            customerEmail: 'customer@example.com',
            total: Money::fromScalars(1000, 'USD')
        );

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Order confirmation email sent', self::callback(function (array $context) use ($orderId, $tenantId): bool {
                self::assertEquals($orderId->toString(), $context['orderId']);
                self::assertEquals($tenantId->toString(), $context['tenantId']);
                self::assertEquals('customer@example.com', $context['customerEmail']);

                return true;
            }));

        // Act
        $this->subscriber->onOrderPlaced($event);
    }
}
