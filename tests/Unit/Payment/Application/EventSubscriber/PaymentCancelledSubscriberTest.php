<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentCancelledSubscriber;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PaymentCancelledSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentCancelledSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new PaymentCancelledSubscriber(
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );
    }

    public function testSubscribedEvents(): void
    {
        // Act
        $events = PaymentCancelledSubscriber::getSubscribedEvents();

        // Assert
        $this->assertArrayHasKey(PaymentCancelled::class, $events);
        $this->assertSame('onPaymentCancelled', $events[PaymentCancelled::class]);
    }

    public function testOnPaymentCancelledSendsEmail(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentCancelled(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            reason: 'Customer cancelled order'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getSubject(), 'Payment Cancelled')
                    && str_contains($email->getHtmlBody(), 'Payment ID:')
                    && str_contains($email->getHtmlBody(), 'Customer cancelled order');
            }));

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onPaymentCancelled($event);
    }

    public function testOnPaymentCancelledLogsDetails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentCancelled(
            paymentId: $paymentId,
            tenantId: $tenantId,
            reason: 'Timeout'
        );

        $this->mailer->method('send');

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Payment cancelled'),
                $this->callback(function (array $context) use ($paymentId) {
                    // Accept if payment_id matches
                    return isset($context['payment_id'])
                        && $context['payment_id'] === $paymentId->toString();
                })
            );

        // Act
        $this->subscriber->onPaymentCancelled($event);
    }

    public function testOnPaymentCancelledIncludesNoChargesNotice(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentCancelled(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            reason: 'User requested cancellation'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $htmlBody = $email->getHtmlBody();

                return str_contains($htmlBody, 'no charges')
                    || str_contains($htmlBody, 'not charged');
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCancelled($event);
    }

    public function testOnPaymentCancelledHandlesEmailFailureGracefully(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentCancelled(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            reason: 'Test cancellation'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Email failed'));

        $this->logger->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                $this->anything(),
                $this->callback(
                    fn (array $context) => isset($context['payment_id']) && isset($context['error'])
                )
            );

        // Act - Should not throw
        $this->subscriber->onPaymentCancelled($event);

        // Assert
        $this->assertTrue(true);
    }
}
