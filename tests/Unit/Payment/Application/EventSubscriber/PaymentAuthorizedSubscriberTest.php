<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentAuthorizedSubscriber;
use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\ValueObject\PaymentId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class PaymentAuthorizedSubscriberTest extends TestCase
{
    private LoggerInterface $logger;
    private PaymentAuthorizedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new PaymentAuthorizedSubscriber(
            logger: $this->logger
        );
    }

    public function testSubscribedEvents(): void
    {
        // Act
        $events = PaymentAuthorizedSubscriber::getSubscribedEvents();

        // Assert
        $this->assertArrayHasKey(PaymentAuthorized::class, $events);
        $this->assertSame('onPaymentAuthorized', $events[PaymentAuthorized::class]);
    }

    public function testOnPaymentAuthorizedLogsDetails(): void
    {
        // Arrange
        $event = new PaymentAuthorized(
            paymentId: PaymentId::generate(),
            gatewayTransactionId: 'pi_abc123xyz456'
        );

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Payment authorized'),
                $this->callback(function (array $context) {
                    return isset($context['payment_id'])
                        && is_string($context['payment_id'])
                        && strlen($context['payment_id']) === 26 // ULID length
                        && isset($context['gateway_transaction_id'])
                        && $context['gateway_transaction_id'] === 'pi_abc123xyz456'
                        && isset($context['occurred_on'])
                        && is_string($context['occurred_on']);
                })
            );

        // Act
        $this->subscriber->onPaymentAuthorized($event);
    }

    public function testOnPaymentAuthorizedHandlesLoggingFailureGracefully(): void
    {
        // Arrange
        $event = new PaymentAuthorized(
            paymentId: PaymentId::generate(),
            gatewayTransactionId: 'pi_test123'
        );

        $this->logger->expects($this->once())
            ->method('info')
            ->willThrowException(new \RuntimeException('Logger unavailable'));

        // Act - Should not throw (graceful failure)
        try {
            $this->subscriber->onPaymentAuthorized($event);
            $exceptionThrown = false;
        } catch (\Throwable $e) {
            $exceptionThrown = true;
        }

        // Assert - Should handle gracefully
        $this->assertFalse($exceptionThrown, 'Subscriber should handle logging failures gracefully');
    }

    public function testOnPaymentAuthorizedIncludesGatewayTransactionId(): void
    {
        // Arrange
        $gatewayTransactionId = 'ch_stripe_1234567890';
        $event = new PaymentAuthorized(
            paymentId: PaymentId::generate(),
            gatewayTransactionId: $gatewayTransactionId
        );

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->anything(),
                $this->callback(function (array $context) use ($gatewayTransactionId) {
                    return isset($context['gateway_transaction_id'])
                        && $context['gateway_transaction_id'] === $gatewayTransactionId;
                })
            );

        // Act
        $this->subscriber->onPaymentAuthorized($event);
    }

    public function testOnPaymentAuthorizedHandlesDifferentGatewayFormats(): void
    {
        // Arrange - Test various gateway transaction ID formats
        $testCases = [
            'pi_stripe_123abc',
            'ch_stripe_xyz789',
            'txn_paypal_ABCD1234',
            'auth_square_9876',
        ];

        foreach ($testCases as $gatewayTxnId) {
            $event = new PaymentAuthorized(
                paymentId: PaymentId::generate(),
                gatewayTransactionId: $gatewayTxnId
            );

            $this->logger->expects($this->once())
                ->method('info')
                ->with(
                    $this->stringContains('Payment authorized'),
                    $this->callback(function (array $context) use ($gatewayTxnId) {
                        return $context['gateway_transaction_id'] === $gatewayTxnId;
                    })
                );

            // Act
            $this->subscriber->onPaymentAuthorized($event);

            // Reset for next test
            $this->setUp();
        }
    }
}
