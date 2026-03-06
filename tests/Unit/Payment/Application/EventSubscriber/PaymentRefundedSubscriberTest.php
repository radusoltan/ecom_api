<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentRefundedSubscriber;
use App\Payment\Application\Service\PaymentCustomerEmailResolver;
use App\Payment\Domain\Event\PaymentRefunded;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[CoversClass(PaymentRefundedSubscriber::class)]
final class PaymentRefundedSubscriberTest extends TestCase
{
    private PaymentCustomerEmailResolver $emailResolver;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentRefundedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $this->emailResolver->method('resolveByPaymentId')->willReturn('john@example.com');
        $this->subscriber = new PaymentRefundedSubscriber(
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
        $events = PaymentRefundedSubscriber::getSubscribedEvents();

        // Assert
        self::assertArrayHasKey(PaymentRefunded::class, $events);
        self::assertSame('onPaymentRefunded', $events[PaymentRefunded::class]);
    }

    #[Test]
    public function onPaymentRefundedSendsEmail(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer requested refund'
        );

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($paymentId): bool {
                self::assertSame('payments@test.com', $email->getFrom()[0]->getAddress());
                self::assertSame('Test Platform', $email->getFrom()[0]->getName());
                self::assertSame('john@example.com', $email->getTo()[0]->getAddress());
                self::assertStringContainsString('Refund Processed', $email->getSubject());
                self::assertStringContainsString($paymentId->toString(), $email->getHtmlBody());
                self::assertStringContainsString('$50.00', $email->getHtmlBody());
                self::assertStringContainsString('Customer requested refund', $email->getHtmlBody());

                return true;
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedLogsSuccess(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(3000, 'USD'),
            reason: 'Damaged item'
        );

        $this->mailer->method('send');

        $this->logger->expects(self::atLeastOnce())
            ->method('info')
            ->with(
                self::logicalOr(
                    self::stringContains('Payment refunded'),
                    self::stringContains('Payment refunded subscriber completed')
                ),
                self::callback(function (array $context) use ($paymentId): bool {
                    // Verify payment_id is always present
                    self::assertArrayHasKey('payment_id', $context);
                    self::assertEquals($paymentId->toString(), $context['payment_id']);

                    // If refunded_amount_in_cents is present, verify all fields
                    if (isset($context['refunded_amount_in_cents'])) {
                        self::assertEquals(3000, $context['refunded_amount_in_cents']);
                        self::assertArrayHasKey('reason', $context);
                        self::assertEquals('Damaged item', $context['reason']);
                        self::assertArrayHasKey('occurred_on', $context);
                    }

                    return true;
                })
            );

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedHandlesEmailFailureGracefully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Test refund'
        );

        $this->mailer->expects(self::once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Email service down'));

        $this->logger->method('info');
        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to send refund notification email'),
                self::callback(function (array $context) use ($paymentId): bool {
                    self::assertArrayHasKey('payment_id', $context);
                    self::assertEquals($paymentId->toString(), $context['payment_id']);
                    self::assertArrayHasKey('error', $context);
                    self::assertStringContainsString('Email service down', $context['error']);

                    return true;
                })
            );

        // Act - Should not throw
        $this->subscriber->onPaymentRefunded($event);

        // Assert - Exception was caught and logged
        self::assertTrue(true); // If we reach here, exception was handled
    }

    #[Test]
    public function onPaymentRefundedIncludesRefundAmount(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(12345, 'USD'), // $123.45
            reason: 'Test'
        );

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                // Verify formatted amount in both versions
                self::assertStringContainsString('$123.45', $htmlBody);
                self::assertStringContainsString('$123.45', $textBody);

                // Verify amount label
                self::assertStringContainsString('Refund Amount:', $htmlBody);
                self::assertStringContainsString('Refund Amount:', $textBody);

                return true;
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedIncludesRefundReason(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(2500, 'USD'),
            reason: 'Product not as described'
        );

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                // Verify reason in both versions
                self::assertStringContainsString('Product not as described', $htmlBody);
                self::assertStringContainsString('Product not as described', $textBody);

                // Verify reason label
                self::assertStringContainsString('Reason:', $htmlBody);
                self::assertStringContainsString('Reason:', $textBody);

                return true;
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedIncludesProcessingTimeInfo(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(9999, 'USD'),
            reason: 'Refund test'
        );

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                // Verify processing time notice in both versions
                self::assertStringContainsString('5-10 business days', $htmlBody);
                self::assertStringContainsString('5-10 business days', $textBody);

                // Verify processing time label/header
                self::assertStringContainsString('Processing Time', $htmlBody);
                self::assertStringContainsString('PROCESSING TIME', $textBody);

                return true;
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedFormatsAmountCorrectly(): void
    {
        // Test different amounts
        $testCases = [
            ['amountInCents' => 9999, 'expectedFormat' => '$99.99'],
            ['amountInCents' => 10000, 'expectedFormat' => '$100.00'],
            ['amountInCents' => 1, 'expectedFormat' => '$0.01'],
            ['amountInCents' => 123456, 'expectedFormat' => '$1,234.56'],
        ];

        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');

        foreach ($testCases as $testCase) {
            // Re-setup mocks for each iteration
            $this->setUp();

            $event = new PaymentRefunded(
                paymentId: PaymentId::generate(),
                tenantId: $tenantId,
                refundedAmount: Money::fromScalars($testCase['amountInCents'], 'USD'),
                reason: 'Test refund'
            );

            $this->logger->method('info');

            $this->mailer->expects(self::once())
                ->method('send')
                ->with(self::callback(function (Email $email) use ($testCase): bool {
                    self::assertStringContainsString($testCase['expectedFormat'], $email->getHtmlBody());
                    self::assertStringContainsString($testCase['expectedFormat'], $email->getTextBody());

                    return true;
                }));

            // Act
            $this->subscriber->onPaymentRefunded($event);
        }
    }

    #[Test]
    public function onPaymentRefundedEmailContainsPlainTextAlternative(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer request'
        );

        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($paymentId): bool {
                $textBody = $email->getTextBody();

                self::assertNotNull($textBody);
                self::assertStringContainsString('REFUND PROCESSED', $textBody);
                self::assertStringContainsString('Payment ID:', $textBody);
                self::assertStringContainsString($paymentId->toString(), $textBody);
                self::assertStringContainsString('$50.00', $textBody);
                self::assertStringContainsString('Customer request', $textBody);

                // Verify no HTML in text body
                self::assertStringNotContainsString('<html>', $textBody);
                self::assertStringNotContainsString('<div>', $textBody);

                return true;
            }));

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedSendsBothHtmlAndTextVersions(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Test'
        );

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
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedIncludesPaymentIdInEmail(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Test refund'
        );

        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($paymentId): bool {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                // Verify payment ID is included in both versions
                self::assertStringContainsString($paymentId->toString(), $htmlBody);
                self::assertStringContainsString($paymentId->toString(), $textBody);

                // Verify payment ID label
                self::assertStringContainsString('Payment ID:', $htmlBody);
                self::assertStringContainsString('Payment ID:', $textBody);

                return true;
            }));

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedLogsPaymentIdAndReason(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(1000, 'USD'),
            reason: 'Wrong item shipped'
        );

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
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedUsesConfiguredSenderEmailAndName(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(1000, 'USD'),
            reason: 'Test'
        );

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
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedIncludesApologyMessage(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Product defect'
        );

        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                // Verify apology message in both versions
                self::assertStringContainsString('apologize', strtolower($htmlBody));
                self::assertStringContainsString('apologize', strtolower($textBody));

                return true;
            }));

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedIncludesCustomerSupportInfo(): void
    {
        // Arrange
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer request'
        );

        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $htmlBody = $email->getHtmlBody();
                $textBody = $email->getTextBody();

                // Verify customer support mention in both versions
                self::assertStringContainsString('customer support', strtolower($htmlBody));
                self::assertStringContainsString('customer support', strtolower($textBody));

                return true;
            }));

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedLogsRefundDetails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(7500, 'USD'),
            reason: 'Order cancelled by customer'
        );

        $this->mailer->method('send');

        $this->logger->expects(self::atLeastOnce())
            ->method('info')
            ->with(
                self::stringContains('Payment refunded'),
                self::callback(function (array $context) use ($paymentId): bool {
                    // Verify all required fields are logged
                    self::assertArrayHasKey('payment_id', $context);
                    self::assertEquals($paymentId->toString(), $context['payment_id']);

                    // Check initial log with full details
                    if (isset($context['refunded_amount_in_cents'])) {
                        self::assertEquals(7500, $context['refunded_amount_in_cents']);
                        self::assertEquals('Order cancelled by customer', $context['reason']);
                        self::assertArrayHasKey('occurred_on', $context);
                    }

                    return true;
                })
            );

        // Act
        $this->subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedHandlesOuterExceptionGracefully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $event = new PaymentRefunded(
            paymentId: $paymentId,
            tenantId: $tenantId,
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Test'
        );

        // Make mailer throw to trigger outer catch block
        $this->mailer->expects(self::once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Critical error'));

        $this->logger->method('info');
        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to send refund notification'),
                self::anything()
            );

        // Act - Should not throw
        $this->subscriber->onPaymentRefunded($event);

        // Assert - Exception was caught and logged
        self::assertTrue(true);
    }

    #[Test]
    public function onPaymentRefundedSkipsEmailWhenCustomerEmailNotResolved(): void
    {
        $emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $emailResolver->method('resolveByPaymentId')->willReturn(null);

        $subscriber = new PaymentRefundedSubscriber(
            emailResolver: $emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );

        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer request'
        );

        $this->mailer->expects(self::never())->method('send');
        $this->logger->method('info');
        $this->logger->expects(self::atLeastOnce())->method('warning');

        $subscriber->onPaymentRefunded($event);
    }

    #[Test]
    public function onPaymentRefundedUsesResolvedCustomerEmail(): void
    {
        $emailResolver = $this->createMock(PaymentCustomerEmailResolver::class);
        $emailResolver->method('resolveByPaymentId')->willReturn('alice@store.com');

        $subscriber = new PaymentRefundedSubscriber(
            emailResolver: $emailResolver,
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );

        $event = new PaymentRefunded(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            refundedAmount: Money::fromScalars(5000, 'USD'),
            reason: 'Customer request'
        );

        $this->logger->method('info');

        $this->mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('alice@store.com', $email->getTo()[0]->getAddress());

                return true;
            }));

        $subscriber->onPaymentRefunded($event);
    }
}
