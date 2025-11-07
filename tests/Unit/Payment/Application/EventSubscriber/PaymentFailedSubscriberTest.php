<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentFailedSubscriber;
use App\Payment\Domain\Event\PaymentFailed;
use App\Payment\Domain\ValueObject\PaymentId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class PaymentFailedSubscriberTest extends TestCase
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private PaymentFailedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new PaymentFailedSubscriber(
            mailer: $this->mailer,
            logger: $this->logger,
            senderEmail: 'payments@test.com',
            senderName: 'Test Platform'
        );
    }

    public function testSubscribedEvents(): void
    {
        // Act
        $events = PaymentFailedSubscriber::getSubscribedEvents();

        // Assert
        $this->assertArrayHasKey(PaymentFailed::class, $events);
        $this->assertSame('onPaymentFailed', $events[PaymentFailed::class]);
    }

    public function testOnPaymentFailedSendsEmail(): void
    {
        // Arrange
        $event = new PaymentFailed(
            paymentId: PaymentId::generate(),
            errorMessage: 'card_declined'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return str_contains($email->getSubject(), 'Payment Failed')
                    && str_contains($email->getHtmlBody(), 'Payment ID:');
            }));

        $this->logger->expects($this->atLeastOnce())
            ->method('warning');

        // Act
        $this->subscriber->onPaymentFailed($event);
    }

    public function testOnPaymentFailedLogsWarning(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $event = new PaymentFailed(
            paymentId: $paymentId,
            errorMessage: 'insufficient_funds'
        );

        $this->mailer->method('send');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('Payment failed'),
                $this->callback(function (array $context) use ($paymentId) {
                    return isset($context['payment_id'])
                        && $context['payment_id'] === $paymentId->toString()
                        && isset($context['error_message'])
                        && 'insufficient_funds' === $context['error_message']
                        && isset($context['occurred_on']);
                })
            );

        // Act
        $this->subscriber->onPaymentFailed($event);
    }

    public function testOnPaymentFailedSanitizesErrorMessage(): void
    {
        // Arrange - Error message with sensitive keywords
        $event = new PaymentFailed(
            paymentId: PaymentId::generate(),
            errorMessage: 'API Key verification failed'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $htmlBody = $email->getHtmlBody();

                // Should redact sensitive keywords like "API Key"
                return str_contains($htmlBody, '[REDACTED]')
                    && !str_contains($htmlBody, 'API Key');
            }));

        $this->logger->method('warning');

        // Act
        $this->subscriber->onPaymentFailed($event);
    }

    public function testOnPaymentFailedMapsCommonErrors(): void
    {
        // Arrange - Test error mapping
        $testCases = [
            ['error' => 'card_declined', 'expectedText' => 'card was declined'],
            ['error' => 'insufficient_funds', 'expectedText' => 'Insufficient funds'],
            ['error' => 'expired_card', 'expectedText' => 'card has expired'],
            ['error' => 'incorrect_cvc', 'expectedText' => 'security code'],
        ];

        foreach ($testCases as $testCase) {
            $event = new PaymentFailed(
                paymentId: PaymentId::generate(),
                errorMessage: $testCase['error']
            );

            $this->mailer->expects($this->once())
                ->method('send')
                ->with($this->callback(function (Email $email) use ($testCase) {
                    return str_contains($email->getHtmlBody(), $testCase['expectedText']);
                }));

            $this->logger->method('warning');

            // Act
            $this->subscriber->onPaymentFailed($event);

            // Reset for next test
            $this->setUp();
        }
    }

    public function testOnPaymentFailedIncludesActionableSteps(): void
    {
        // Arrange
        $event = new PaymentFailed(
            paymentId: PaymentId::generate(),
            errorMessage: 'card_declined'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                $htmlBody = $email->getHtmlBody();

                // Should include helpful steps
                return str_contains($htmlBody, 'payment information')
                    || str_contains($htmlBody, 'Try Again')
                    || str_contains($htmlBody, 'contact your bank');
            }));

        $this->logger->method('warning');

        // Act
        $this->subscriber->onPaymentFailed($event);
    }

    public function testOnPaymentFailedHandlesEmailFailureGracefully(): void
    {
        // Arrange
        $event = new PaymentFailed(
            paymentId: PaymentId::generate(),
            errorMessage: 'processing_error'
        );

        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Mailer error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Failed to send payment failure notification'),
                $this->callback(
                    fn (array $context) => isset($context['payment_id']) && isset($context['error'])
                )
            );

        // Act - Should not throw
        $this->subscriber->onPaymentFailed($event);

        // Assert
        $this->assertTrue(true);
    }
}
