<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\ConfirmPayment\ConfirmPaymentCommand;
use App\Payment\Application\Command\ConfirmPayment\ConfirmPaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ConfirmPaymentHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $paymentRepository;
    private PaymentGatewayInterface $gateway;
    private LoggerInterface $logger;
    private ConfirmPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ConfirmPaymentHandler(
            $this->paymentRepository,
            $this->gateway,
            $this->logger
        );
    }

    public function testHandleConfirmsPaymentSuccessfully(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        // Create payment with gateway transaction ID (from CreatePaymentIntent)
        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'EUR'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: \App\Payment\Domain\ValueObject\PaymentStatus::pending(),
            gatewayTransactionId: 'pi_123abc',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'EUR'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            paymentMethodId: 'pm_card_visa'
        );

        $this->paymentRepository->expects($this->once())
            ->method('findById')
            ->with($paymentId)
            ->willReturn($payment);

        // Gateway confirms and returns authorized status
        $this->gateway->expects($this->once())
            ->method('confirmPaymentIntent')
            ->with('pi_123abc', 'pm_card_visa')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_123abc',
                status: 'requires_capture',
                amount: Money::fromScalars(10000, 'EUR')
            ));

        // Payment should be saved with authorized status
        $this->paymentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                return $p->status()->isAuthorized();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenPaymentNotFound(): void
    {
        // Arrange
        $command = new ConfirmPaymentCommand(
            paymentId: PaymentId::generate(),
            paymentMethodId: 'pm_card_visa'
        );

        $this->paymentRepository->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->gateway->expects($this->never())
            ->method('confirmPaymentIntent');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Payment not found/');

        // Act
        ($this->handler)($command);
    }

    public function testHandleThrowsExceptionWhenNoGatewayTransactionId(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        // Payment without gateway transaction ID
        $payment = Payment::create(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'EUR'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe()
        );

        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            paymentMethodId: 'pm_card_visa'
        );

        $this->paymentRepository->expects($this->once())
            ->method('findById')
            ->willReturn($payment);

        $this->gateway->expects($this->never())
            ->method('confirmPaymentIntent');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/has no gateway transaction ID/');

        // Act
        ($this->handler)($command);
    }

    public function testHandleMarksPaymentAsFailedWhenGatewayFails(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'EUR'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: \App\Payment\Domain\ValueObject\PaymentStatus::pending(),
            gatewayTransactionId: 'pi_123abc',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'EUR'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            paymentMethodId: 'pm_card_visa'
        );

        $this->paymentRepository->expects($this->once())
            ->method('findById')
            ->willReturn($payment);

        // Gateway returns failure
        $this->gateway->expects($this->once())
            ->method('confirmPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'card_declined',
                errorMessage: 'Your card was declined'
            ));

        // Payment should be saved as failed
        $this->paymentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                return $p->status()->isFailed();
            }));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Your card was declined');

        // Act
        ($this->handler)($command);
    }

    public function testHandleReturnsEarlyWhenPaymentRequiresAction(): void
    {
        // Arrange - Test 3D Secure scenario
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'EUR'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: \App\Payment\Domain\ValueObject\PaymentStatus::pending(),
            gatewayTransactionId: 'pi_123abc',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'EUR'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            paymentMethodId: 'pm_card_visa'
        );

        $this->paymentRepository->expects($this->once())
            ->method('findById')
            ->willReturn($payment);

        // Gateway returns requires_action status (3DS)
        $this->gateway->expects($this->once())
            ->method('confirmPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_123abc',
                status: 'requires_action',
                amount: Money::fromScalars(10000, 'EUR')
            ));

        // Payment should NOT be saved (status remains pending)
        $this->paymentRepository->expects($this->never())
            ->method('save');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Payment requires additional action (3DS/SCA)'),
                $this->callback(fn (array $context) => 'requires_action' === $context['status'])
            );

        // Act
        ($this->handler)($command);
    }

    public function testHandleAuthorizesPaymentWhenCaptureModeIsAutomatic(): void
    {
        // Arrange - Some gateways auto-capture (status = succeeded)
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'EUR'),
            method: PaymentMethod::fromString('paypal'),
            gateway: PaymentGateway::paypal(),
            status: \App\Payment\Domain\ValueObject\PaymentStatus::pending(),
            gatewayTransactionId: 'PAYID-123ABC',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'EUR'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            paymentMethodId: 'paypal-token'
        );

        $this->paymentRepository->expects($this->once())
            ->method('findById')
            ->willReturn($payment);

        // Gateway returns succeeded (already captured)
        $this->gateway->expects($this->once())
            ->method('confirmPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'PAYID-123ABC',
                status: 'succeeded',
                amount: Money::fromScalars(10000, 'EUR')
            ));

        // Payment should be authorized
        $this->paymentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                return $p->status()->isAuthorized();
            }));

        // Act
        ($this->handler)($command);
    }

    public function testHandleLogsUnexpectedStatus(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'EUR'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: \App\Payment\Domain\ValueObject\PaymentStatus::pending(),
            gatewayTransactionId: 'pi_123abc',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'EUR'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            paymentMethodId: 'pm_card_visa'
        );

        $this->paymentRepository->method('findById')->willReturn($payment);

        // Gateway returns unexpected status
        $this->gateway->method('confirmPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_123abc',
                status: 'unknown_status',
                amount: Money::fromScalars(10000, 'EUR')
            ));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->equalTo('Payment confirmation returned unexpected status'),
                $this->callback(fn (array $context) => 'unknown_status' === $context['status'])
            );

        // Act
        ($this->handler)($command);
    }

    public function testHandleMarksPaymentAsFailedOnUnexpectedException(): void
    {
        // Arrange
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();

        $payment = Payment::reconstituteFromPersistence(
            id: PaymentId::generate(),
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amount: Money::fromScalars(10000, 'EUR'),
            method: PaymentMethod::card(),
            gateway: PaymentGateway::stripe(),
            status: \App\Payment\Domain\ValueObject\PaymentStatus::pending(),
            gatewayTransactionId: 'pi_123abc',
            errorMessage: null,
            refundedAmount: Money::fromScalars(0, 'EUR'),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $command = new ConfirmPaymentCommand(
            paymentId: $paymentId,
            paymentMethodId: 'pm_card_visa'
        );

        $this->paymentRepository->method('findById')->willReturn($payment);

        // Gateway throws unexpected exception
        $this->gateway->method('confirmPaymentIntent')
            ->willThrowException(new \RuntimeException('Network error'));

        // Payment should be saved as failed
        $this->paymentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $p) {
                return $p->status()->isFailed();
            }));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->equalTo('Unexpected error confirming payment'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Network error');

        // Act
        ($this->handler)($command);
    }
}
