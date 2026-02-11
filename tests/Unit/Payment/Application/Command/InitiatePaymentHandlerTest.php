<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\InitiatePayment;
use App\Payment\Application\Command\InitiatePaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class InitiatePaymentHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private PaymentGatewayInterface $paymentGateway;
    private LoggerInterface $logger;
    private InitiatePaymentHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new InitiatePaymentHandler(
            $this->repository,
            $this->paymentGateway,
            $this->logger
        );
    }

    public function testHandleCreatesPaymentAndInitiatesWithGateway(): void
    {
        // Arrange
        $command = new InitiatePayment(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 9999,
            currency: 'USD',
            customerEmail: 'customer@example.com',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $gatewayResponse = [
            'transaction_id' => 'pi_test_123',
            'status' => 'requires_payment_method',
            'metadata' => [
                'client_secret' => 'pi_test_123_secret_abc',
                'amount' => 9999,
                'currency' => 'usd',
            ],
        ];

        // Expect repository save to be called twice: once for create, once for authorize
        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Payment $payment) {
                // No-op, just capture the call
            });

        $this->paymentGateway->expects($this->once())
            ->method('authorize')
            ->with(
                amountInCents: 9999,
                currency: 'USD',
                method: $this->callback(fn (PaymentMethod $m) => $m->isCard()),
                metadata: $this->callback(function ($metadata) use ($command) {
                    return $metadata['tenant_id'] === $command->tenantId->toString()
                        && $metadata['payment_id'] === $command->paymentId->toString()
                        && $metadata['order_id'] === $command->orderId
                        && $metadata['customer_email'] === 'customer@example.com';
                })
            )
            ->willReturn($gatewayResponse);

        // Act
        $result = ($this->handler)($command);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('paymentId', $result);
        $this->assertArrayHasKey('paymentIntentId', $result);
        $this->assertArrayHasKey('clientSecret', $result);
        $this->assertArrayHasKey('status', $result);

        $this->assertSame($command->paymentId->toString(), $result['paymentId']);
        $this->assertSame('pi_test_123', $result['paymentIntentId']);
        $this->assertSame('pi_test_123_secret_abc', $result['clientSecret']);
        $this->assertSame('requires_payment_method', $result['status']);
    }

    public function testHandleMarksPaymentAsFailedWhenGatewayThrowsException(): void
    {
        // Arrange
        $command = new InitiatePayment(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'EUR',
            customerEmail: 'test@example.com',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Payment $payment) {
                // Verify payment is marked as failed on second save
                static $callCount = 0;
                ++$callCount;
                if (2 === $callCount) {
                    $this->assertTrue($payment->status()->isFailed());
                    $this->assertNotNull($payment->errorMessage());
                }
            });

        $this->paymentGateway->expects($this->once())
            ->method('authorize')
            ->willThrowException(new \RuntimeException('Gateway connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Payment initiation failed',
                $this->callback(fn ($context) => isset($context['payment_id']) && isset($context['error']))
            );

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gateway connection failed');

        ($this->handler)($command);
    }

    public function testHandleLogsSuccessfulPaymentInitiation(): void
    {
        // Arrange
        $command = new InitiatePayment(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 12000,
            currency: 'GBP',
            customerEmail: 'uk@example.com',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->repository->method('save');

        $this->paymentGateway->method('authorize')
            ->willReturn([
                'transaction_id' => 'pi_success_456',
                'status' => 'requires_payment_method',
                'metadata' => [
                    'client_secret' => 'pi_success_456_secret_xyz',
                ],
            ]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Payment initiated successfully',
                $this->callback(function ($context) use ($command) {
                    return $context['payment_id'] === $command->paymentId->toString()
                        && $context['order_id'] === $command->orderId
                        && $context['gateway_transaction_id'] === 'pi_success_456';
                })
            );

        // Act
        ($this->handler)($command);

        // Assert - expectations verified by mock
    }

    public function testHandleWithPaypalGateway(): void
    {
        // Arrange
        $command = new InitiatePayment(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 7500,
            currency: 'USD',
            customerEmail: 'paypal@example.com',
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal()
        );

        $this->repository->method('save');

        $this->paymentGateway->expects($this->once())
            ->method('authorize')
            ->with(
                amountInCents: 7500,
                currency: 'USD',
                method: $this->callback(fn (PaymentMethod $m) => $m->isPaypal()),
                metadata: $this->anything()
            )
            ->willReturn([
                'transaction_id' => 'pp_test_789',
                'status' => 'pending',
                'metadata' => [
                    'client_secret' => 'pp_test_789_secret',
                ],
            ]);

        // Act
        $result = ($this->handler)($command);

        // Assert
        $this->assertSame('pp_test_789', $result['paymentIntentId']);
        $this->assertSame('pending', $result['status']);
    }
}
