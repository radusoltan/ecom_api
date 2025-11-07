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
                $toAddresses = $email->getTo();
                self::assertCount(1, $toAddresses);
                self::assertEquals('customer@example.com', $toAddresses[0]->getAddress());
                self::assertStringContainsString('Order Confirmation', $email->getSubject());
                self::assertStringContainsString($event->orderId->toString(), $email->getHtmlBody());
                self::assertStringContainsString('50', $email->getHtmlBody()); // 5000 cents = $50
                self::assertStringContainsString('USD', $email->getHtmlBody());

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
                $htmlBody = $email->getHtmlBody();
                self::assertStringContainsString('123.45', $htmlBody);
                self::assertStringContainsString('EUR', $htmlBody);

                $textBody = $email->getTextBody();
                self::assertStringContainsString('123.45', $textBody);
                self::assertStringContainsString('EUR', $textBody);

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
                self::assertNotNull($email->getHtmlBody());
                self::assertNotNull($email->getTextBody());
                self::assertStringContainsString('<html>', $email->getHtmlBody());
                self::assertStringNotContainsString('<html>', $email->getTextBody());

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
