<?php

declare(strict_types=1);

namespace App\Tests\Integration\Payment\Application\EventSubscriber;

use App\Order\Application\Command\UpdateOrderStatusCommand;
use App\Order\Domain\Event\OrderPaid;
use App\Payment\Application\EventSubscriber\PaymentCapturedSubscriber;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Integration tests for PaymentCapturedSubscriber
 *
 * Tests the integration between Payment and Order modules:
 * - Command bus integration (UpdateOrderStatusCommand)
 * - Event dispatcher integration (OrderPaid event)
 * - Email service integration
 * - Error handling and graceful degradation
 */
final class PaymentCapturedSubscriberIntegrationTest extends TestCase
{
    private MessageBusInterface $commandBus;
    private EventDispatcherInterface $eventDispatcher;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentCapturedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PaymentCapturedSubscriber(
            commandBus: $this->commandBus,
            eventDispatcher: $this->eventDispatcher,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );
    }

    public function testOnPaymentCapturedDispatchesUpdateOrderStatusCommand(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01J9TEST12345';

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 10000,
            orderId: $orderId
        );

        // Expect UpdateOrderStatusCommand to be dispatched
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($orderId, $tenantId) {
                return $command instanceof UpdateOrderStatusCommand
                    && $command->orderId === $orderId
                    && $command->tenantId === $tenantId->toString()
                    && $command->newStatus === 'processing';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->eventDispatcher->method('dispatch');
        $this->mailer->method('send');
        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);

        // Assert - verified by mock expectation
    }

    public function testOnPaymentCapturedDispatchesOrderPaidEvent(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01J9TEST12345';
        $capturedAmount = 15000;

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: $capturedAmount,
            orderId: $orderId
        );

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Expect OrderPaid event to be dispatched
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($dispatchedEvent) use ($orderId, $tenantId, $paymentId, $capturedAmount) {
                return $dispatchedEvent instanceof OrderPaid
                    && $dispatchedEvent->orderId === $orderId
                    && $dispatchedEvent->tenantId->equals($tenantId)
                    && $dispatchedEvent->paymentId === $paymentId->toString()
                    && $dispatchedEvent->paidAmountInCents === $capturedAmount;
            }))
            ->willReturn(new \stdClass());

        $this->mailer->method('send');
        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);

        // Assert - verified by mock expectation
    }

    public function testOnPaymentCapturedSendsConfirmationEmail(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01J9TEST12345';

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 9999,
            orderId: $orderId
        );

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $this->eventDispatcher->method('dispatch');

        // Expect email to be sent
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) use ($paymentId) {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                return $email->getSubject() === 'Payment Confirmation - Order Paid Successfully'
                    && str_contains($htmlBody, $paymentId->toString())
                    && str_contains($htmlBody, '$99.99')
                    && str_contains($textBody, $paymentId->toString())
                    && str_contains($textBody, '$99.99');
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);

        // Assert - verified by mock expectation
    }

    public function testOnPaymentCapturedLogsAllSteps(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01J9TEST12345';

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 10000,
            orderId: $orderId
        );

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $this->eventDispatcher->method('dispatch');
        $this->mailer->method('send');

        // Expect multiple log entries
        $this->logger->expects($this->exactly(4))
            ->method('info')
            ->willReturnCallback(function ($message, $context) use ($paymentId, $orderId) {
                // Verify log messages
                if (str_contains($message, 'Payment captured successfully')) {
                    $this->assertSame($paymentId->toString(), $context['payment_id']);
                    $this->assertSame(10000, $context['captured_amount_in_cents']);
                } elseif (str_contains($message, 'Order status updated')) {
                    $this->assertSame($paymentId->toString(), $context['payment_id']);
                    $this->assertSame($orderId, $context['order_id']);
                } elseif (str_contains($message, 'confirmation email sent')) {
                    $this->assertSame($paymentId->toString(), $context['payment_id']);
                } elseif (str_contains($message, 'subscriber completed')) {
                    $this->assertSame($paymentId->toString(), $context['payment_id']);
                }
            });

        // Act
        $this->subscriber->onPaymentCaptured($event);

        // Assert - verified by mock expectation
    }

    public function testOnPaymentCapturedSkipsOrderUpdateWhenNoOrderId(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 10000,
            orderId: null // No order ID
        );

        // Command bus should NOT be called
        $this->commandBus->expects($this->never())
            ->method('dispatch');

        // Event dispatcher should NOT be called
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->mailer->method('send');
        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);

        // Assert - verified by mock expectations
    }

    public function testOnPaymentCapturedContinuesWhenCommandBusFails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01J9TEST12345';

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 10000,
            orderId: $orderId
        );

        // Simulate command bus failure
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Command bus error'));

        // Event dispatcher should NOT be called because command failed
        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        // Email should still be sent (graceful degradation)
        $this->mailer->expects($this->once())
            ->method('send');

        // Error should be logged
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to update order status'),
                $this->callback(function ($context) use ($paymentId, $orderId) {
                    return $context['payment_id'] === $paymentId->toString()
                        && $context['order_id'] === $orderId
                        && str_contains($context['error'], 'Command bus error');
                })
            );

        $this->logger->method('info');

        // Act - Should not throw exception
        $this->subscriber->onPaymentCaptured($event);

        // Assert - verified by mock expectations
    }

    public function testOnPaymentCapturedContinuesWhenEmailFails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01J9TEST12345';

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 10000,
            orderId: $orderId
        );

        $this->commandBus->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $this->eventDispatcher->method('dispatch');

        // Simulate email failure
        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP server unavailable'));

        // Error should be logged
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to send payment confirmation email'),
                $this->callback(function ($context) use ($paymentId) {
                    return $context['payment_id'] === $paymentId->toString()
                        && str_contains($context['error'], 'SMTP server unavailable');
                })
            );

        $this->logger->method('info');

        // Act - Should not throw exception (graceful degradation)
        $this->subscriber->onPaymentCaptured($event);

        // Assert - verified by mock expectations
    }

    public function testOnPaymentCapturedHandlesCompleteFailureGracefully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: 10000,
            orderId: '01J9TEST12345'
        );

        // Simulate catastrophic failure
        $this->logger->expects($this->once())
            ->method('info')
            ->willThrowException(new \RuntimeException('Logger crashed'));

        // Final error should be logged
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('PaymentCapturedSubscriber failed'),
                $this->callback(function ($context) use ($paymentId) {
                    return $context['payment_id'] === $paymentId->toString()
                        && isset($context['error'])
                        && isset($context['trace']);
                })
            );

        // Act - Should not throw exception
        $this->subscriber->onPaymentCaptured($event);

        // Assert - No exception thrown
        $this->assertTrue(true);
    }

    public function testCompleteIntegrationFlow(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $orderId = '01J9ORDER123';
        $capturedAmount = 25000;

        $event = new PaymentCaptured(
            paymentId: $paymentId,
            tenantId: $tenantId,
            capturedAmountInCents: $capturedAmount,
            orderId: $orderId
        );

        // Track execution order
        $executionOrder = [];

        // 1. Command bus should be called
        $this->commandBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$executionOrder) {
                $executionOrder[] = 'command_dispatched';
                return new Envelope(new \stdClass());
            });

        // 2. Event dispatcher should be called after command
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function () use (&$executionOrder) {
                $executionOrder[] = 'event_dispatched';
                return new \stdClass();
            });

        // 3. Email should be sent after event
        $this->mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function () use (&$executionOrder) {
                $executionOrder[] = 'email_sent';
            });

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);

        // Assert - Verify execution order
        $this->assertSame(
            ['command_dispatched', 'event_dispatched', 'email_sent'],
            $executionOrder,
            'Integration flow should execute in correct order: Command → Event → Email'
        );
    }
}
