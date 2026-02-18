<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\CreatePaymentIntent\CreatePaymentIntentCommand;
use App\Payment\Application\Command\CreatePaymentIntent\CreatePaymentIntentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CreatePaymentIntentHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $paymentRepository;
    private PaymentGatewayInterface $gateway;
    private LoggerInterface $logger;
    private CreatePaymentIntentHandler $handler;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepositoryInterface::class);
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CreatePaymentIntentHandler(
            $this->paymentRepository,
            $this->gateway,
            $this->logger
        );
    }

    public function testHandleCreatesPaymentIntentSuccessfully(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $orderId = '01JCEX'.bin2hex(random_bytes(10));
        $amountInCents = 10000;
        $currency = 'EUR';
        $idempotencyKey = 'idem-'.bin2hex(random_bytes(8));

        $command = new CreatePaymentIntentCommand(
            tenantId: $tenantId,
            orderId: $orderId,
            amountInCents: $amountInCents,
            currency: $currency,
            paymentMethod: 'card',
            idempotencyKey: $idempotencyKey,
            customerId: 'cus_123',
            customerEmail: 'test@example.com'
        );

        // No existing payment with this idempotency key
        $this->paymentRepository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with($idempotencyKey, $tenantId)
            ->willReturn(null);

        // Gateway returns success
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_123abc',
                status: 'requires_confirmation',
                amount: Money::fromScalars($amountInCents, $currency),
                clientSecret: 'pi_123abc_secret_xyz'
            ));

        // Payment should be saved
        $this->paymentRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Payment $payment) use ($orderId, $amountInCents, $currency) {
                return $payment->orderId() === $orderId
                    && $payment->amountInCents() === $amountInCents
                    && $payment->currency() === $currency;
            }));

        // Act
        $result = ($this->handler)($command);

        // Assert
        $this->assertTrue($result->isSuccessful());
        $this->assertFalse($result->wasAlreadyCreated());
        $this->assertEquals('pi_123abc_secret_xyz', $result->clientSecret);
        $this->assertEquals('pi_123abc', $result->gatewayPaymentId);
    }

    public function testHandleReturnsExistingPaymentForSameIdempotencyKey(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $paymentId = \App\Payment\Domain\ValueObject\PaymentId::generate();
        $idempotencyKey = 'idem-'.bin2hex(random_bytes(8));

        $command = new CreatePaymentIntentCommand(
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'EUR',
            paymentMethod: 'card',
            idempotencyKey: $idempotencyKey
        );

        // Existing payment found (reconstitute with gateway transaction ID)
        $existingPayment = Payment::reconstituteFromPersistence(
            id: $paymentId,
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'EUR',
            method: \App\Payment\Domain\ValueObject\PaymentMethod::card(),
            gateway: \App\Payment\Domain\ValueObject\PaymentGateway::stripe(),
            status: \App\Payment\Domain\ValueObject\PaymentStatus::pending(),
            gatewayTransactionId: 'pi_existing_123',
            errorMessage: null,
            refundedAmountInCents: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        $this->paymentRepository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with($idempotencyKey, $tenantId)
            ->willReturn($existingPayment);

        // Gateway should NOT be called (idempotency)
        $this->gateway->expects($this->never())
            ->method('createPaymentIntent');

        // Payment should NOT be saved (already exists)
        $this->paymentRepository->expects($this->never())
            ->method('save');

        // Act
        $result = ($this->handler)($command);

        // Assert
        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->wasAlreadyCreated());
    }

    public function testHandleReturnsFailureWhenGatewayFails(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $command = new CreatePaymentIntentCommand(
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'EUR',
            paymentMethod: 'card',
            idempotencyKey: 'idem-'.bin2hex(random_bytes(8))
        );

        // No existing payment
        $this->paymentRepository->expects($this->once())
            ->method('findByIdempotencyKey')
            ->willReturn(null);

        // Gateway returns failure
        $this->gateway->expects($this->once())
            ->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'card_declined',
                errorMessage: 'Your card was declined'
            ));

        // Payment should NOT be saved on failure
        $this->paymentRepository->expects($this->never())
            ->method('save');

        // Act
        $result = ($this->handler)($command);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->isFailed());
        $this->assertEquals('card_declined', $result->errorCode);
        $this->assertEquals('Your card was declined', $result->errorMessage);
    }

    public function testHandleLogsSuccessfulCreation(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $command = new CreatePaymentIntentCommand(
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'EUR',
            paymentMethod: 'card',
            idempotencyKey: 'idem-'.bin2hex(random_bytes(8))
        );

        $this->paymentRepository->method('findByIdempotencyKey')->willReturn(null);

        $this->gateway->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::success(
                gatewayPaymentIntentId: 'pi_123',
                status: 'requires_confirmation',
                amount: Money::fromScalars(10000, 'EUR'),
                clientSecret: 'secret_123'
            ));

        $this->paymentRepository->method('save');

        // Assert logger is called
        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->equalTo('Payment intent created successfully'),
                $this->callback(fn (array $context) => isset($context['payment_id']) && isset($context['gateway_payment_id']))
            );

        // Act
        ($this->handler)($command);
    }

    public function testHandleLogsGatewayFailure(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $command = new CreatePaymentIntentCommand(
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'EUR',
            paymentMethod: 'card',
            idempotencyKey: 'idem-'.bin2hex(random_bytes(8))
        );

        $this->paymentRepository->method('findByIdempotencyKey')->willReturn(null);

        $this->gateway->method('createPaymentIntent')
            ->willReturn(PaymentIntentResult::failure(
                errorCode: 'gateway_error',
                errorMessage: 'Connection failed'
            ));

        // Assert logger is called for failure
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Payment intent creation failed at gateway'),
                $this->callback(fn (array $context) => 'gateway_error' === $context['error_code'])
            );

        // Act
        ($this->handler)($command);
    }

    public function testHandleReturnsFailureOnUnexpectedException(): void
    {
        // Arrange
        $tenantId = TenantId::generate();
        $command = new CreatePaymentIntentCommand(
            tenantId: $tenantId,
            orderId: '01JCEX'.bin2hex(random_bytes(10)),
            amountInCents: 10000,
            currency: 'EUR',
            paymentMethod: 'card',
            idempotencyKey: 'idem-'.bin2hex(random_bytes(8))
        );

        $this->paymentRepository->method('findByIdempotencyKey')->willReturn(null);

        // Gateway throws unexpected exception
        $this->gateway->method('createPaymentIntent')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->equalTo('Unexpected error creating payment intent'));

        // Act
        $result = ($this->handler)($command);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('internal_error', $result->errorCode);
    }

    public function testHandleResolvesGatewayFromPaymentMethod(): void
    {
        // Arrange - Test different payment methods
        $testCases = [
            ['card', 'EUR'],
            ['card', 'USD'],
            ['paypal', 'GBP'],
            ['bank_transfer', 'EUR'],
        ];

        foreach ($testCases as [$paymentMethod, $currency]) {
            $tenantId = TenantId::generate();
            $command = new CreatePaymentIntentCommand(
                tenantId: $tenantId,
                orderId: '01JCEX'.bin2hex(random_bytes(10)),
                amountInCents: 10000,
                currency: $currency,
                paymentMethod: $paymentMethod,
                idempotencyKey: 'idem-'.bin2hex(random_bytes(8))
            );

            $this->paymentRepository->method('findByIdempotencyKey')->willReturn(null);

            $this->gateway->method('createPaymentIntent')
                ->willReturn(PaymentIntentResult::success(
                    gatewayPaymentIntentId: 'pi_test',
                    status: 'requires_confirmation',
                    amount: Money::fromScalars(10000, $currency),
                    clientSecret: 'secret_test'
                ));

            $savedPayment = null;
            $this->paymentRepository->method('save')
                ->willReturnCallback(function (Payment $payment) use (&$savedPayment) {
                    $savedPayment = $payment;
                });

            // Act
            ($this->handler)($command);

            // Assert gateway was resolved (payment was created)
            $this->assertNotNull($savedPayment, "Payment should be saved for method: {$paymentMethod}");
        }
    }
}
