<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentCancelledSubscriber;
use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Event\PaymentCancelled;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PaymentCancelledSubscriberTest extends TestCase
{
    private PaymentCustomerEmailResolver $emailResolver;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentCancelledSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $this->emailResolver->method('resolveByPaymentId')->willReturn('john@example.com');
        $this->subscriber = new PaymentCancelledSubscriber(
            emailResolver: $this->emailResolver,
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

    #[Test]
    public function onPaymentCancelledSkipsEmailWhenCustomerEmailNotResolved(): void
    {
        $emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $emailResolver->method('resolveByPaymentId')->willReturn(null);

        $subscriber = new PaymentCancelledSubscriber(
            emailResolver: $emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );

        $event = new PaymentCancelled(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            reason: 'Customer cancelled order'
        );

        $this->mailer->expects($this->never())->method('send');
        $this->logger->method('info');
        $this->logger->expects($this->atLeastOnce())->method('warning');

        $subscriber->onPaymentCancelled($event);
    }

    #[Test]
    public function onPaymentCancelledUsesResolvedCustomerEmail(): void
    {
        $emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $emailResolver->method('resolveByPaymentId')->willReturn('alice@store.com');

        $subscriber = new PaymentCancelledSubscriber(
            emailResolver: $emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );

        $event = new PaymentCancelled(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            reason: 'Customer cancelled order'
        );

        $this->logger->method('info');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email): bool {
                $this->assertSame('alice@store.com', $email->getTo()[0]->getAddress());

                return true;
            }));

        $subscriber->onPaymentCancelled($event);
    }
}
