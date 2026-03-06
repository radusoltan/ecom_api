<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Order\Application\Command\UpdateOrderStatusCommand;
use App\Order\Domain\Event\OrderPaid;
use App\Payment\Application\EventSubscriber\PaymentCapturedSubscriber;
use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;

#[CoversClass(PaymentCapturedSubscriber::class)]
final class PaymentCapturedSubscriberTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private EventDispatcherInterface $eventDispatcher;
    private PaymentCustomerEmailResolver $emailResolver;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentCapturedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $this->emailResolver->method('resolveByOrderId')->willReturn('john@example.com');
        $this->emailResolver->method('resolveByPaymentId')->willReturn('john@example.com');
        $this->subscriber = new PaymentCapturedSubscriber(
            commandBus: $this->commandBus,
            eventDispatcher: $this->eventDispatcher,
            emailResolver: $this->emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );
    }

    #[Test]
    public function itImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    #[Test]
    public function getSubscribedEventsReturnsCorrectMapping(): void
    {
        // Act
        $events = PaymentCapturedSubscriber::getSubscribedEvents();

        // Assert
        self::assertArrayHasKey(PaymentCaptured::class, $events);
        self::assertSame('onPaymentCaptured', $events[PaymentCaptured::class]);
    }

    #[Test]
    public function onPaymentCapturedSendsEmail(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(9999, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($paymentId): bool {
                self::assertSame('payments@test.com', $email->getFrom()[0]->getAddress());
                self::assertSame('Test Platform', $email->getFrom()[0]->getName());
                self::assertSame('john@example.com', $email->getTo()[0]->getAddress());
                self::assertStringContainsString('Payment Confirmation', $email->getSubject());
                self::assertStringContainsString($paymentId->toString(), $email->getHtmlBody());
                self::assertStringContainsString('$99.99', $email->getHtmlBody());

                return true;
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedLogsSuccess(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(5000, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');
        $this->mailer->method('send');

        $this->logger->expects(self::atLeastOnce())
            ->method('info')
            ->with(
                self::logicalOr(
                    self::stringContains('Payment captured successfully'),
                    self::stringContains('Payment captured subscriber completed')
                ),
                self::callback(function (array $context) use ($paymentId): bool {
                    // Verify payment_id is always present
                    self::assertArrayHasKey('payment_id', $context);
                    self::assertEquals($paymentId->toString(), $context['payment_id']);

                    // If captured_amount_in_cents is present, verify it
                    if (isset($context['captured_amount_in_cents'])) {
                        self::assertEquals(5000, $context['captured_amount_in_cents']);
                        self::assertArrayHasKey('occurred_on', $context);
                    }

                    return true;
                })
            );

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedHandlesEmailFailureGracefully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(9999, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');

        $this->mailer->expects(self::once())
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP server unavailable'));

        $this->logger->method('info');
        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to send payment confirmation email'),
                self::callback(function (array $context) use ($paymentId): bool {
                    self::assertArrayHasKey('payment_id', $context);
                    self::assertEquals($paymentId->toString(), $context['payment_id']);
                    self::assertArrayHasKey('error', $context);
                    self::assertStringContainsString('SMTP server unavailable', $context['error']);

                    return true;
                })
            );

        // Act - Should not throw exception (graceful failure)
        $this->subscriber->onPaymentCaptured($event);

        // Assert - Exception was caught and logged
        self::assertTrue(true); // If we reach here, exception was handled
    }

    #[Test]
    public function onPaymentCapturedDispatchesOrderPaidEvent(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = 'order-123';
        $capturedAmount = 10000;

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars($capturedAmount, 'USD'),
            orderId: $orderId
        );

        // Command bus must succeed for event to be dispatched
        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(UpdateOrderStatusCommand::class))
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $this->mailer->expects(self::once())
            ->method('send');

        // Allow all logger calls
        $this->logger->expects(self::atLeastOnce())
            ->method('info');

        $this->eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (OrderPaid $orderPaidEvent) use ($orderId, $paymentId, $tenantId, $capturedAmount): bool {
                self::assertEquals($orderId, $orderPaidEvent->orderId);
                self::assertTrue($tenantId->equals($orderPaidEvent->tenantId));
                self::assertEquals($paymentId->toString(), $orderPaidEvent->paymentId);
                self::assertEquals($capturedAmount, $orderPaidEvent->paidAmountInCents);
                self::assertInstanceOf(\DateTimeImmutable::class, $orderPaidEvent->occurredOn);

                return true;
            }));

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedIncludesPaymentDetails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(12345, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');
        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($paymentId): bool {
                $htmlBody = $email->getHtmlBody();

                // Verify payment ID is included
                self::assertStringContainsString($paymentId->toString(), $htmlBody);

                // Verify payment ID label
                self::assertStringContainsString('Payment ID:', $htmlBody);

                return true;
            }));

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedIncludesAmountInEmail(): void
    {
        // Arrange
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(12345, 'USD') // $123.45
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');
        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                // Verify formatted amount in both versions
                self::assertStringContainsString('$123.45', $htmlBody);
                self::assertStringContainsString('$123.45', $textBody);

                // Verify amount label
                self::assertStringContainsString('Amount Paid:', $htmlBody);
                self::assertStringContainsString('Amount Paid:', $textBody);

                return true;
            }));

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedFormatsAmountCorrectly(): void
    {
        // Test different amounts
        $testCases = [
            ['amountInCents' => 9999, 'expectedFormat' => '$99.99'],
            ['amountInCents' => 10000, 'expectedFormat' => '$100.00'],
            ['amountInCents' => 1, 'expectedFormat' => '$0.01'],
            ['amountInCents' => 123456, 'expectedFormat' => '$1,234.56'],
        ];

        foreach ($testCases as $testCase) {
            // Re-setup mocks for each iteration
            $this->setUp();

            $event = new PaymentCaptured(
                paymentId: PaymentId::generate(),
                tenantId: TenantId::generate(),
                capturedAmount: Money::fromScalars($testCase['amountInCents'], 'USD')
            );

            $this->commandBus->method('dispatch');
            $this->eventDispatcher->method('dispatch');
            $this->logger->method('info');

            $this->mailer->expects(self::once())
                ->method('send')
                ->with(self::callback(function (Email $email) use ($testCase): bool {
                    self::assertStringContainsString($testCase['expectedFormat'], $email->getHtmlBody());

                    return true;
                }));

            // Act
            $this->subscriber->onPaymentCaptured($event);
        }
    }

    #[Test]
    public function onPaymentCapturedEmailContainsPlainTextAlternative(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(9999, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');
        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($paymentId): bool {
                $textBody = $email->getTextBody();

                self::assertNotNull($textBody);
                self::assertStringContainsString('PAYMENT CONFIRMED', $textBody);
                self::assertStringContainsString('Payment ID:', $textBody);
                self::assertStringContainsString($paymentId->toString(), $textBody);
                self::assertStringContainsString('$99.99', $textBody);

                // Verify no HTML in text body
                self::assertStringNotContainsString('<html>', $textBody);
                self::assertStringNotContainsString('<div>', $textBody);

                return true;
            }));

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedSendsBothHtmlAndTextVersions(): void
    {
        // Arrange
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(5000, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');
        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertNotNull($email->getHtmlBody());
                self::assertNotNull($email->getTextBody());
                self::assertStringContainsString('<html>', $email->getHtmlBody());
                self::assertStringNotContainsString('<html>', $email->getTextBody());

                return true;
            }));

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedUpdatesOrderStatusToProcessing(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = 'order-456';

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: $orderId
        );

        $this->mailer->method('send');
        $this->eventDispatcher->method('dispatch');

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (UpdateOrderStatusCommand $command) use ($orderId, $tenantId): bool {
                self::assertEquals($orderId, $command->orderId);
                self::assertEquals($tenantId->toString(), $command->tenantId);
                self::assertEquals('processing', $command->newStatus);

                return true;
            }));

        $this->logger->expects(self::atLeastOnce())
            ->method('info')
            ->with(
                self::logicalOr(
                    self::stringContains('Payment captured'),
                    self::stringContains('Order status updated')
                ),
                self::anything()
            );

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedHandlesOrderStatusUpdateFailureGracefully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $orderId = 'order-789';

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: $orderId
        );

        $this->commandBus->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Order not found'));

        $this->mailer->expects(self::once())
            ->method('send');

        $this->eventDispatcher->method('dispatch');

        $this->logger->method('info');
        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to update order status'),
                self::callback(function (array $context) use ($paymentId, $orderId): bool {
                    self::assertEquals($paymentId->toString(), $context['payment_id']);
                    self::assertEquals($orderId, $context['order_id']);
                    self::assertStringContainsString('Order not found', $context['error']);

                    return true;
                })
            );

        // Act - Should not throw, email should still be sent
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedLogsPaymentIdAndTenantId(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmount: Money::fromScalars(1000, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');
        $this->mailer->method('send');

        $this->logger->expects(self::atLeastOnce())
            ->method('info')
            ->with(
                self::anything(),
                self::callback(function (array $context) use ($paymentId): bool {
                    self::assertArrayHasKey('payment_id', $context);
                    self::assertEquals($paymentId->toString(), $context['payment_id']);

                    return true;
                })
            );

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedDoesNotUpdateOrderStatusWhenOrderIdIsNull(): void
    {
        // Arrange
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: null
        );

        $this->commandBus->expects(self::never())
            ->method('dispatch');

        $this->eventDispatcher->expects(self::never())
            ->method('dispatch');

        $this->mailer->method('send');
        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedUsesConfiguredSenderEmailAndName(): void
    {
        // Arrange
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(1000, 'USD')
        );

        $this->commandBus->method('dispatch');
        $this->eventDispatcher->method('dispatch');
        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $from = $email->getFrom();
                self::assertCount(1, $from);
                self::assertEquals('payments@test.com', $from[0]->getAddress());
                self::assertEquals('Test Platform', $from[0]->getName());

                return true;
            }));

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedSkipsEmailWhenCustomerEmailNotResolved(): void
    {
        $emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $emailResolver->method('resolveByOrderId')->willReturn(null);
        $emailResolver->method('resolveByPaymentId')->willReturn(null);

        $subscriber = new PaymentCapturedSubscriber(
            commandBus: $this->commandBus,
            eventDispatcher: $this->eventDispatcher,
            emailResolver: $emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );

        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: 'order-123'
        );

        $this->commandBus->method('dispatch')
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));
        $this->eventDispatcher->method('dispatch');
        $this->mailer->expects(self::never())->method('send');
        $this->logger->method('info');
        $this->logger->expects(self::atLeastOnce())->method('warning');

        $subscriber->onPaymentCaptured($event);
    }

    #[Test]
    public function onPaymentCapturedUsesResolvedCustomerEmail(): void
    {
        $emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $emailResolver->method('resolveByOrderId')->willReturn('alice@store.com');

        $subscriber = new PaymentCapturedSubscriber(
            commandBus: $this->commandBus,
            eventDispatcher: $this->eventDispatcher,
            emailResolver: $emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );

        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            capturedAmount: Money::fromScalars(5000, 'USD'),
            orderId: 'order-abc'
        );

        $this->commandBus->method('dispatch')
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));
        $this->eventDispatcher->method('dispatch');
        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('alice@store.com', $email->getTo()[0]->getAddress());

                return true;
            }));

        $subscriber->onPaymentCaptured($event);
    }
}
