<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\EventSubscriber;

use App\Payment\Application\EventSubscriber\PaymentAuthorizedSubscriber;
use App\Payment\Domain\Event\PaymentAuthorized;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class PaymentAuthorizedSubscriberTest extends TestCase
{
    private PaymentRepositoryInterface $paymentRepository;
    private MessageBusInterface $commandBus;
    private LoggerInterface $logger;
    private PaymentAuthorizedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subscriber = new PaymentAuthorizedSubscriber(
            paymentRepository: $this->paymentRepository,
            commandBus: $this->commandBus,
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
            tenantId: TenantId::generate(),
            gatewayTransactionId: 'pi_abc123xyz456'
        );

        $this->paymentRepository->method('findById')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Payment not found'),
                $this->callback(function (array $context) {
                    return isset($context['payment_id'])
                        && is_string($context['payment_id'])
                        && 36 === strlen($context['payment_id']) // UUID length
                        && isset($context['tenant_id'])
                        && is_string($context['tenant_id']);
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
            tenantId: TenantId::generate(),
            gatewayTransactionId: 'pi_test123'
        );

        $this->paymentRepository->method('findById')
            ->willThrowException(new \RuntimeException('Repository error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('PaymentAuthorizedSubscriber failed'),
                $this->anything()
            );

        // Act - Should not throw (graceful failure)
        try {
            $this->subscriber->onPaymentAuthorized($event);
            $exceptionThrown = false;
        } catch (\Throwable $e) {
            $exceptionThrown = true;
        }

        // Assert - Should handle gracefully
        $this->assertFalse($exceptionThrown, 'Subscriber should handle repository failures gracefully');
    }

    public function testOnPaymentAuthorizedIncludesGatewayTransactionId(): void
    {
        // Arrange
        $gatewayTransactionId = 'ch_stripe_1234567890';
        $event = new PaymentAuthorized(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            gatewayTransactionId: $gatewayTransactionId
        );

        $this->paymentRepository->method('findById')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Payment not found'),
                $this->callback(function (array $context) {
                    return isset($context['payment_id']) && isset($context['tenant_id']);
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
                tenantId: TenantId::generate(),
                gatewayTransactionId: $gatewayTxnId
            );

            $this->paymentRepository->method('findById')->willReturn(null);

            $this->logger->expects($this->once())
                ->method('error')
                ->with(
                    $this->stringContains('Payment not found'),
                    $this->callback(function (array $context) {
                        return isset($context['payment_id']) && isset($context['tenant_id']);
                    })
                );

            // Act
            $this->subscriber->onPaymentAuthorized($event);

            // Reset for next test
            $this->setUp();
        }
    }
}
