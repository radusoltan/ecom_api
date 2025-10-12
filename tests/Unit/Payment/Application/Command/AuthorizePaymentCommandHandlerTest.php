<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\AuthorizePayment;
use App\Payment\Application\Command\AuthorizePaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Infrastructure\Gateway\PaymentGatewayFactory;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AuthorizePaymentCommandHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private PaymentGatewayFactory $gatewayFactory;
    private LoggerInterface $logger;
    private AuthorizePaymentHandler $handler;
    private \App\Payment\Domain\Service\PaymentGatewayInterface $stripeGateway;
    private \App\Payment\Domain\Service\PaymentGatewayInterface $paypalGateway;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Create mock gateways
        $this->stripeGateway = $this->createMock(\App\Payment\Domain\Service\PaymentGatewayInterface::class);
        $this->paypalGateway = $this->createMock(\App\Payment\Domain\Service\PaymentGatewayInterface::class);

        // Create real factory instance with mock gateways
        $this->gatewayFactory = new PaymentGatewayFactory([
            'stripe' => $this->stripeGateway,
            'paypal' => $this->paypalGateway,
        ]);

        $this->handler = new AuthorizePaymentHandler(
            $this->repository,
            $this->gatewayFactory,
            $this->logger
        );
    }

    public function testHandleAuthorizesPayment(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $gatewayTransactionId = 'pi_abc123xyz';

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 9999,
            currency: 'USD',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $command = new AuthorizePayment(
            id: $paymentId,
            tenantId: $tenantId
        );

        // Set expectations on the stripe gateway
        $this->stripeGateway->expects($this->once())
            ->method('authorize')
            ->willReturn([
                'transaction_id' => $gatewayTransactionId,
                'status' => 'authorized'
            ]);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with($paymentId, $tenantId)
            ->willReturn($payment);

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) use ($gatewayTransactionId) {
                return $p->status()->isAuthorized()
                    && $p->gatewayTransactionId() === $gatewayTransactionId;
            }));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }

    public function testHandleThrowsExceptionWhenPaymentNotFound(): void
    {
        // Arrange
        $command = new AuthorizePayment(
            id: PaymentId::generate(),
            tenantId: TenantId::generate()
        );

        $this->repository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->repository->expects($this->never())
            ->method('save');

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Payment.*not found/');

        // Act
        ($this->handler)($command);
    }

    public function testHandleWithDifferentGatewayTransactionIds(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $gatewayTransactionId = 'ch_stripe_123456789';

        $payment = Payment::create(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX' . bin2hex(random_bytes(10)),
            amountInCents: 5000,
            currency: 'EUR',
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $command = new AuthorizePayment(
            id: $paymentId,
            tenantId: $tenantId
        );

        // Set expectations on the stripe gateway
        $this->stripeGateway->method('authorize')
            ->willReturn([
                'transaction_id' => $gatewayTransactionId,
                'status' => 'authorized'
            ]);

        $this->repository->method('findById')->willReturn($payment);
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(fn(Payment $p) =>
                $p->gatewayTransactionId() === $gatewayTransactionId
            ));

        // Act
        ($this->handler)($command);

        // Assert - expectations verified
    }
}
