<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\InitiatePayment;
use App\Payment\Application\Command\InitiatePaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Model\PaymentId as ModelPaymentId;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class InitiatePaymentHandlerTest extends TestCase
{
    /** @var PaymentRepositoryInterface&Stub */
    private Stub $repository;

    /** @var PaymentGatewayInterface&Stub */
    private Stub $paymentGateway;

    /** @var LoggerInterface&Stub */
    private Stub $logger;

    private InitiatePaymentHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(PaymentRepositoryInterface::class);
        $this->paymentGateway = $this->createStub(PaymentGatewayInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

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
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 9999,
            currency: 'USD',
            customerEmail: 'customer@example.com',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $gatewayResult = PaymentIntentResult::success(
            gatewayPaymentIntentId: 'pi_test_123',
            status: 'requires_payment_method',
            amount: Money::fromScalars(9999, 'USD'),
            clientSecret: 'pi_test_123_secret_abc',
        );

        /** @var PaymentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(PaymentRepositoryInterface::class);
        // Expect repository save to be called twice: once for create, once for authorize
        $repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Payment $payment): void {
                // No-op, just capture the call
            });

        /** @var PaymentGatewayInterface&MockObject $paymentGateway */
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createPaymentIntent')
            ->with(
                $this->isInstanceOf(ModelPaymentId::class),
                $this->callback(fn (Money $m) => true),
                'USD',
                $this->callback(fn (string $key) => str_starts_with($key, 'initiate_')),
                null,
                $this->callback(function (array $metadata) use ($command): bool {
                    return $metadata['tenant_id'] === $command->tenantId->toString()
                        && $metadata['payment_id'] === $command->paymentId->toString()
                        && $metadata['order_id'] === $command->orderId
                        && 'customer@example.com' === $metadata['customer_email'];
                })
            )
            ->willReturn($gatewayResult);

        $handler = new InitiatePaymentHandler($repository, $paymentGateway, $this->logger);

        // Act
        $result = $handler($command);

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
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'EUR',
            customerEmail: 'test@example.com',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        /** @var PaymentRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(PaymentRepositoryInterface::class);
        $repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Payment $payment): void {
                // Verify payment is marked as failed on second save
                static $callCount = 0;
                ++$callCount;
                if (2 === $callCount) {
                    $this->assertTrue($payment->status()->isFailed());
                    $this->assertNotNull($payment->errorMessage());
                }
            });

        /** @var PaymentGatewayInterface&MockObject $paymentGateway */
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willThrowException(new \RuntimeException('Gateway connection failed'));

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Payment initiation failed',
                $this->callback(fn (array $context) => isset($context['payment_id']) && isset($context['error']))
            );

        $handler = new InitiatePaymentHandler($repository, $paymentGateway, $logger);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gateway connection failed');

        $handler($command);
    }

    public function testHandleLogsSuccessfulPaymentInitiation(): void
    {
        // Arrange
        $command = new InitiatePayment(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 12000,
            currency: 'GBP',
            customerEmail: 'uk@example.com',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $this->paymentGateway->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_success_456',
                status: 'requires_payment_method',
                amount: Money::fromScalars(12000, 'GBP'),
                clientSecret: 'pi_success_456_secret_xyz',
            ));

        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Payment initiated successfully',
                $this->callback(function (array $context) use ($command): bool {
                    return $context['payment_id'] === $command->paymentId->toString()
                        && $context['order_id'] === $command->orderId
                        && 'pi_success_456' === $context['gateway_transaction_id'];
                })
            );

        $handler = new InitiatePaymentHandler($this->repository, $this->paymentGateway, $logger);

        // Act
        $handler($command);

        // Assert - expectations verified by mock
    }

    public function testHandleWithPaypalGateway(): void
    {
        // Arrange
        $command = new InitiatePayment(
            paymentId: PaymentId::generate(),
            tenantId: TenantId::generate(),
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 7500,
            currency: 'USD',
            customerEmail: 'paypal@example.com',
            method: PaymentMethod::paypal(),
            gateway: PaymentGateway::paypal()
        );

        /** @var PaymentGatewayInterface&MockObject $paymentGateway */
        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createPaymentIntent')
            ->with(
                $this->isInstanceOf(ModelPaymentId::class),
                $this->callback(fn (Money $m) => true),
                'USD',
                $this->anything(),
                null,
                $this->anything()
            )
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pp_test_789',
                status: 'pending',
                amount: Money::fromScalars(7500, 'USD'),
                clientSecret: 'pp_test_789_secret',
            ));

        $handler = new InitiatePaymentHandler($this->repository, $paymentGateway, $this->logger);

        // Act
        $result = $handler($command);

        // Assert
        $this->assertSame('pp_test_789', $result['paymentIntentId']);
        $this->assertSame('pending', $result['status']);
    }
}
