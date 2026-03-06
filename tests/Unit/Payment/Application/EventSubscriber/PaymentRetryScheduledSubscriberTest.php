<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentRetryScheduledSubscriber;
use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Event\PaymentRetryScheduled;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PaymentRetryScheduledSubscriberTest extends TestCase
{
    private PaymentCustomerEmailResolver $emailResolver;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentRetryScheduledSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PaymentRetryScheduledSubscriber(
            emailResolver: $this->emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.local',
            senderName: 'Test Platform',
        );
    }

    public function testGetSubscribedEventsReturnsCorrectMapping(): void
    {
        $events = PaymentRetryScheduledSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PaymentRetryScheduled::class, $events);
        $this->assertSame('onPaymentRetryScheduled', $events[PaymentRetryScheduled::class]);
    }

    public function testOnPaymentRetryScheduledSendsNotificationEmail(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $scheduledFor = new \DateTimeImmutable('2026-03-10 14:00:00');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 1,
            scheduledFor: $scheduledFor,
            errorCode: 'processing_error',
            orderId: 'order-retry-123',
        );

        $this->emailResolver
            ->expects($this->once())
            ->method('resolveByOrderId')
            ->with('order-retry-123', $tenantId->toString())
            ->willReturn('customer@example.com');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($paymentId) {
                $toAddresses = $email->getTo();
                $this->assertCount(1, $toAddresses);
                $this->assertSame('customer@example.com', $toAddresses[0]->getAddress());

                $fromAddresses = $email->getFrom();
                $this->assertCount(1, $fromAddresses);
                $this->assertSame('payments@test.local', $fromAddresses[0]->getAddress());

                $this->assertSame('Payment Retry Scheduled - No Action Required', $email->getSubject());

                $htmlBody = $email->getHtmlBody();
                $this->assertStringContainsString($paymentId->toString(), $htmlBody);
                $this->assertStringContainsString('order-retry-123', $htmlBody);

                return true;
            }));

        $this->subscriber->onPaymentRetryScheduled($event);
    }

    public function testOnPaymentRetryScheduledLogsInfoWithRetryDetails(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $scheduledFor = new \DateTimeImmutable('2026-03-10 15:30:00');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 2,
            scheduledFor: $scheduledFor,
            errorCode: 'card_declined',
            orderId: 'order-log-test',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');
        $this->mailer->method('send');

        // Capture all info() calls to verify one of them contains retry details
        $loggedInfoCalls = [];
        $this->logger
            ->method('info')
            ->willReturnCallback(function (string $message, array $context = []) use (&$loggedInfoCalls) {
                $loggedInfoCalls[] = ['message' => $message, 'context' => $context];
            });

        $this->subscriber->onPaymentRetryScheduled($event);

        // Find the 'Payment retry scheduled' log entry
        $retryLog = array_filter(
            $loggedInfoCalls,
            fn (array $call) => 'Payment retry scheduled' === $call['message']
        );

        $this->assertNotEmpty($retryLog, 'Expected a log entry with message "Payment retry scheduled"');

        $logEntry = reset($retryLog);
        $context = $logEntry['context'];

        $this->assertSame($paymentId->toString(), $context['payment_id']);
        $this->assertSame('order-log-test', $context['order_id']);
        $this->assertSame(2, $context['retry_attempt']);
        $this->assertSame($scheduledFor->format('Y-m-d H:i:s'), $context['scheduled_for']);
        $this->assertSame('card_declined', $context['error_code']);
    }

    public function testOnPaymentRetryScheduledSkipsEmailWhenCustomerNotFound(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 1,
            scheduledFor: new \DateTimeImmutable('+30 minutes'),
            errorCode: 'processing_error',
            orderId: 'order-no-email',
        );

        $this->emailResolver
            ->method('resolveByOrderId')
            ->willReturn(null);

        $this->mailer->expects($this->never())->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                'Cannot send retry notification email: customer email not found',
                $this->arrayHasKey('payment_id')
            );

        $this->subscriber->onPaymentRetryScheduled($event);
    }

    public function testOnPaymentRetryScheduledDoesNotThrowWhenMailerFails(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 1,
            scheduledFor: new \DateTimeImmutable('+15 minutes'),
            errorCode: 'card_declined',
            orderId: 'order-smtp-fail',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');

        $this->mailer
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP unavailable'));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        // Should not throw
        $this->subscriber->onPaymentRetryScheduled($event);
    }

    public function testOnPaymentRetryScheduledDoesNotThrowWhenSubscriberFails(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 3,
            scheduledFor: new \DateTimeImmutable('+1 hour'),
            errorCode: 'processing_error',
            orderId: 'order-outer-fail',
        );

        $this->emailResolver
            ->method('resolveByOrderId')
            ->willThrowException(new \RuntimeException('Resolver failed'));

        $this->mailer->expects($this->never())->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        // Should not throw - outer catch handles it
        $this->subscriber->onPaymentRetryScheduled($event);
    }

    public function testOnPaymentRetryScheduledEmailContainsRetryInformation(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $scheduledFor = new \DateTimeImmutable('2026-04-15 10:00:00');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 2,
            scheduledFor: $scheduledFor,
            errorCode: 'processing_error',
            orderId: 'order-content-verify',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');

        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->subscriber->onPaymentRetryScheduled($event);

        $this->assertNotNull($sentEmail);

        $htmlBody = $sentEmail->getHtmlBody();
        $this->assertNotNull($htmlBody);
        $this->assertStringContainsString('<!DOCTYPE html>', $htmlBody);
        $this->assertStringContainsString($paymentId->toString(), $htmlBody);
        $this->assertStringContainsString('order-content-verify', $htmlBody);
        $this->assertStringContainsString('#2', $htmlBody);

        $textBody = $sentEmail->getTextBody();
        $this->assertNotNull($textBody);
        $this->assertStringContainsString($paymentId->toString(), $textBody);
        $this->assertStringContainsString('order-content-verify', $textBody);
        $this->assertStringContainsString('#2', $textBody);
    }

    public function testOnPaymentRetryScheduledEmailIncludesScheduledTime(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $scheduledFor = new \DateTimeImmutable('2026-05-20 09:30:00');

        $event = new PaymentRetryScheduled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            retryAttempt: 1,
            scheduledFor: $scheduledFor,
            errorCode: 'card_declined',
            orderId: 'order-time-check',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');

        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->subscriber->onPaymentRetryScheduled($event);

        $htmlBody = $sentEmail->getHtmlBody();
        // scheduledFor formatted as 'F j, Y \a\t g:i A' = 'May 20, 2026 at 9:30 AM'
        $this->assertStringContainsString('May', $htmlBody);
        $this->assertStringContainsString('2026', $htmlBody);
    }
}
