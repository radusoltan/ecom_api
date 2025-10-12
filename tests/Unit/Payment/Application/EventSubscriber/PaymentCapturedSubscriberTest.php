<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentCapturedSubscriber;
use App\Payment\Domain\Event\PaymentCaptured;
use App\Payment\Domain\ValueObject\PaymentId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PaymentCapturedSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentCapturedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new PaymentCapturedSubscriber(
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );
    }

    public function testSubscribedEvents(): void
    {
        // Act
        $events = PaymentCapturedSubscriber::getSubscribedEvents();

        // Assert
        $this->assertArrayHasKey(PaymentCaptured::class, $events);
        $this->assertSame('onPaymentCaptured', $events[PaymentCaptured::class]);
    }

    public function testOnPaymentCapturedSendsEmail(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            capturedAmountInCents: 9999
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($paymentId) {
                return $email->getFrom()[0]->getAddress() === 'payments@test.com'
                    && $email->getTo()[0]->getAddress() === 'customer@example.com'
                    && str_contains($email->getSubject(), 'Payment Confirmation')
                    && str_contains($email->getHtmlBody(), $paymentId->toString())
                    && str_contains($email->getHtmlBody(), '$99.99');
            }));

        $this->logger->expects($this->atLeastOnce())
            ->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    public function testOnPaymentCapturedLogsSuccess(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            capturedAmountInCents: 5000
        );

        $this->mailer->method('send');

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Payment captured'),
                $this->callback(function (array $context) use ($paymentId) {
                    // Accept if either:
                    // 1. Initial log with captured_amount_in_cents
                    // 2. Completion log with just payment_id
                    $hasPaymentId = isset($context['payment_id'])
                        && $context['payment_id'] === $paymentId->toString();

                    if (!$hasPaymentId) {
                        return false;
                    }

                    // If it has captured_amount_in_cents, verify it
                    if (isset($context['captured_amount_in_cents'])) {
                        return $context['captured_amount_in_cents'] === 5000;
                    }

                    // Otherwise it's the completion log, which is also fine
                    return true;
                })
            );

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }

    public function testOnPaymentCapturedFormatsAmountCorrectly(): void
    {
        // Arrange - Test different amounts
        $testCases = [
            ['amountInCents' => 9999, 'expectedFormat' => '$99.99'],
            ['amountInCents' => 10000, 'expectedFormat' => '$100.00'],
            ['amountInCents' => 1, 'expectedFormat' => '$0.01'],
            ['amountInCents' => 123456, 'expectedFormat' => '$1,234.56'],
        ];

        foreach ($testCases as $testCase) {
            $event = new PaymentCaptured(
                paymentId: PaymentId::generate(),
                capturedAmountInCents: $testCase['amountInCents']
            );

            $this->mailer->expects($this->once())
                ->method('send')
                ->with($this->callback(function (Email $email) use ($testCase) {
                    return str_contains($email->getHtmlBody(), $testCase['expectedFormat']);
                }));

            $this->logger->method('info');

            // Act
            $this->subscriber->onPaymentCaptured($event);

            // Reset mocks for next iteration
            $this->setUp();
        }
    }

    public function testOnPaymentCapturedHandlesEmailFailureGracefully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $event = new PaymentCaptured(
            paymentId: $paymentId,
            capturedAmountInCents: 9999
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('SMTP server unavailable'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to send payment confirmation email'),
                $this->callback(function (array $context) {
                    return isset($context['payment_id'])
                        && isset($context['error'])
                        && str_contains($context['error'], 'SMTP server unavailable');
                })
            );

        // Act - Should not throw exception (graceful failure)
        $this->subscriber->onPaymentCaptured($event);

        // Assert - Exception was caught and logged
        $this->assertTrue(true); // If we reach here, exception was handled
    }

    public function testOnPaymentCapturedEmailContainsPlainTextAlternative(): void
    {
        // Arrange
        $event = new PaymentCaptured(
            paymentId: PaymentId::generate(),
            capturedAmountInCents: 9999
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $textBody = $email->getTextBody();
                return $textBody !== null
                    && str_contains($textBody, 'Payment ID:')
                    && str_contains($textBody, '$99.99')
                    && str_contains($textBody, 'PAYMENT CONFIRMED');
            }));

        $this->logger->method('info');

        // Act
        $this->subscriber->onPaymentCaptured($event);
    }
}
