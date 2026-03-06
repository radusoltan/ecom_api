<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentRetryExhaustedSubscriber;
use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Event\PaymentRetryExhausted;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PaymentRetryExhaustedSubscriberTest extends TestCase
{
    private PaymentCustomerEmailResolver $emailResolver;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentRetryExhaustedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PaymentRetryExhaustedSubscriber(
            emailResolver: $this->emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.local',
            senderName: 'Test Platform',
        );
    }

    public function testGetSubscribedEventsReturnsCorrectMapping(): void
    {
        $events = PaymentRetryExhaustedSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(PaymentRetryExhausted::class, $events);
        $this->assertSame('onPaymentRetryExhausted', $events[PaymentRetryExhausted::class]);
    }

    public function testOnPaymentRetryExhaustedSendsEmailToCustomer(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 3,
            lastErrorCode: 'card_declined',
            lastErrorMessage: 'Your card was declined',
            orderId: 'order-abc-123',
        );

        $this->emailResolver
            ->expects($this->once())
            ->method('resolveByOrderId')
            ->with('order-abc-123', $tenantId->toString())
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

                $this->assertSame('Payment Failed - Action Required', $email->getSubject());

                $htmlBody = $email->getHtmlBody();
                $this->assertStringContainsString($paymentId->toString(), $htmlBody);
                $this->assertStringContainsString('order-abc-123', $htmlBody);
                $this->assertStringContainsString('3', $htmlBody);

                return true;
            }));

        $this->subscriber->onPaymentRetryExhausted($event);
    }

    public function testOnPaymentRetryExhaustedLogsErrorForMonitoring(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 5,
            lastErrorCode: 'insufficient_funds',
            lastErrorMessage: 'insufficient_funds',
            orderId: 'order-xyz',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');
        $this->mailer->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                'Payment retry attempts exhausted',
                $this->callback(function (array $context) use ($paymentId) {
                    return $context['payment_id'] === $paymentId->toString()
                        && 'order-xyz' === $context['order_id']
                        && 5 === $context['total_attempts']
                        && 'insufficient_funds' === $context['last_error_code'];
                })
            );

        $this->subscriber->onPaymentRetryExhausted($event);
    }

    public function testOnPaymentRetryExhaustedSkipsEmailWhenCustomerEmailNotFound(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 3,
            lastErrorCode: 'processing_error',
            lastErrorMessage: 'A processing error occurred',
            orderId: 'order-missing',
        );

        $this->emailResolver
            ->method('resolveByOrderId')
            ->willReturn(null);

        $this->mailer->expects($this->never())->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('warning')
            ->with(
                'Cannot send retry exhausted email: customer email not found',
                $this->arrayHasKey('payment_id')
            );

        $this->subscriber->onPaymentRetryExhausted($event);
    }

    public function testOnPaymentRetryExhaustedDoesNotThrowWhenMailerFails(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 3,
            lastErrorCode: 'card_declined',
            lastErrorMessage: 'card declined',
            orderId: 'order-smtp-fail',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');

        $this->mailer
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP server unavailable'));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        // Should not throw
        $this->subscriber->onPaymentRetryExhausted($event);
    }

    public function testOnPaymentRetryExhaustedDoesNotThrowWhenEmailResolverThrows(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 2,
            lastErrorCode: 'expired_card',
            lastErrorMessage: 'expired card',
            orderId: 'order-resolver-fail',
        );

        $this->emailResolver
            ->method('resolveByOrderId')
            ->willThrowException(new \RuntimeException('Query bus failed'));

        $this->mailer->expects($this->never())->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error');

        // Should not throw - outer catch handles it
        $this->subscriber->onPaymentRetryExhausted($event);
    }

    public function testOnPaymentRetryExhaustedEmailContainsHtmlAndTextBodies(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 4,
            lastErrorCode: 'card_declined',
            lastErrorMessage: 'card_declined',
            orderId: 'order-content-check',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');

        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->subscriber->onPaymentRetryExhausted($event);

        $this->assertNotNull($sentEmail);

        $htmlBody = $sentEmail->getHtmlBody();
        $this->assertNotNull($htmlBody);
        $this->assertStringContainsString('<!DOCTYPE html>', $htmlBody);
        $this->assertStringContainsString($paymentId->toString(), $htmlBody);
        $this->assertStringContainsString('order-content-check', $htmlBody);
        $this->assertStringContainsString('4', $htmlBody);

        $textBody = $sentEmail->getTextBody();
        $this->assertNotNull($textBody);
        $this->assertStringContainsString($paymentId->toString(), $textBody);
        $this->assertStringContainsString('order-content-check', $textBody);
    }

    public function testOnPaymentRetryExhaustedSanitizesApiKeyInErrorMessage(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 1,
            lastErrorCode: 'auth_error',
            lastErrorMessage: 'Invalid api_key provided',
            orderId: 'order-sanitize',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');

        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->subscriber->onPaymentRetryExhausted($event);

        $htmlBody = $sentEmail->getHtmlBody();
        $this->assertStringNotContainsString('api_key', $htmlBody);
        $this->assertStringContainsString('[REDACTED]', $htmlBody);
    }

    public function testOnPaymentRetryExhaustedMapsKnownErrorCodesToFriendlyMessages(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        $event = new PaymentRetryExhausted(
            paymentId: $paymentId,
            tenantId: $tenantId,
            totalAttempts: 2,
            lastErrorCode: 'insufficient_funds',
            lastErrorMessage: 'insufficient_funds',
            orderId: 'order-friendly',
        );

        $this->emailResolver->method('resolveByOrderId')->willReturn('customer@test.com');

        $sentEmail = null;
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (Email $email) use (&$sentEmail) {
                $sentEmail = $email;
            });

        $this->subscriber->onPaymentRetryExhausted($event);

        $htmlBody = $sentEmail->getHtmlBody();
        $this->assertStringContainsString('Insufficient funds in your account', $htmlBody);
    }
}
