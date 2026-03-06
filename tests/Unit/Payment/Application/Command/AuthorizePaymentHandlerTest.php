<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\AuthorizePayment;
use App\Payment\Application\Command\AuthorizePaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayFactoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\PaymentIntentResult;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AuthorizePaymentHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private PaymentGatewayFactoryInterface $gatewayFactory;
    private AuthorizePaymentHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->gatewayFactory = $this->createMock(PaymentGatewayFactoryInterface::class);
        $this->handler = new AuthorizePaymentHandler($this->repository, $this->gatewayFactory, new NullLogger());
    }

    public function testItThrowsWhenPaymentNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\RuntimeException::class);

        ($this->handler)(new AuthorizePayment(PaymentId::generate(), TenantId::generate()));
    }

    public function testItAuthorizesPaymentSuccessfully(): void
    {
        $paymentId = PaymentId::generate();
        $tenantId = TenantId::generate();
        $payment = Payment::create($paymentId, $tenantId, 'order-123', Money::fromScalars(5000, 'USD'), PaymentMethod::card(), PaymentGateway::stripe());

        $this->repository->method('findById')->willReturn($payment);

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $result = PaymentIntentResult::success('pi_test', 'requires_capture', Money::fromScalars(5000, 'USD'));
        $gateway->method('createPaymentIntent')->willReturn($result);

        $this->gatewayFactory->method('create')->willReturn($gateway);
        $this->repository->expects($this->once())->method('save');

        ($this->handler)(new AuthorizePayment($paymentId, $tenantId));

        self::assertSame('pi_test', $payment->gatewayTransactionId());
    }
}
