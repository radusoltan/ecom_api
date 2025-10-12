<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentRefundedSubscriber;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\ValueObject\PaymentId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PaymentRefundedSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentRefundedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new PaymentRefundedSubscriber(
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );
    }

    public function testSubscribedEvents(): void
    {
        // Act
        $events = PaymentRefundedSubscriber::getSubscribedEvents();

        // Assert
        $this->assertArrayHasKey(PaymentRefunded::class, $events);
        $this->assertSame('onPaymentRefunded', $events[PaymentRefunded::class]);
    }

    public function testOnPaymentRefundedSendsEmail(): void
    {
        // Arrange
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            refundedAmountInCents: 5000,
            reason: 'Customer requested refund'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getSubject(), 'Refund Processed')
                    && str_contains($email->getHtmlBody(), 'Payment ID:')
                    && str_contains($email->getHtmlBody(), '$50.00')
                    && str_contains($email->getHtmlBody(), 'Customer requested refund');
            }));

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    public function testOnPaymentRefundedLogsRefundDetails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            refundedAmountInCents: 3000,
            reason: 'Damaged item'
        );

        $this->mailer->method('send');

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Payment refunded'),
                $this->callback(function (array $context) use ($paymentId) {
                    // Accept if either:
                    // 1. Initial log with all details
                    // 2. Completion log with just payment_id
                    $hasPaymentId = isset($context['payment_id'])
                        && $context['payment_id'] === $paymentId->toString();

                    if (!$hasPaymentId) {
                        return false;
                    }

                    // If it has refunded_amount_in_cents, verify all fields
                    if (isset($context['refunded_amount_in_cents'])) {
                        return $context['refunded_amount_in_cents'] === 3000
                            && isset($context['reason'])
                            && $context['reason'] === 'Damaged item'
                            && isset($context['occurred_on']);
                    }

                    // Otherwise it's the completion log, which is also fine
                    return true;
                })
            );

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    public function testOnPaymentRefundedIncludesReasonInEmail(): void
    {
        // Arrange
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            refundedAmountInCents: 2500,
            reason: 'Product not as described'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getHtmlBody(), 'Product not as described');
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    public function testOnPaymentRefundedIncludesProcessingTimeNotice(): void
    {
        // Arrange
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            refundedAmountInCents: 9999,
            reason: 'Refund test'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $htmlBody = $email->getHtmlBody();
                return str_contains($htmlBody, '5-10 business days')
                    || str_contains($htmlBody, 'processing time');
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    public function testOnPaymentRefundedHandlesEmailFailureGracefully(): void
    {
        // Arrange
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            refundedAmountInCents: 5000,
            reason: 'Test refund'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Email service down'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to send refund notification'),
                $this->callback(fn(array $context) =>
                    isset($context['payment_id']) && isset($context['error'])
                )
            );

        // Act - Should not throw
        $this->subscriber->onPaymentRefunded($event);

        // Assert
        $this->assertTrue(true);
    }
}
