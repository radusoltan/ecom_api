<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Command;

use App\Payment\Application\Command\RefundPayment;
use App\Payment\Application\Command\RefundPaymentHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\Service\PaymentGatewayInterface;
use App\Payment\Domain\Service\RefundResult;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Payment\Domain\ValueObject\PaymentStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RefundPaymentHandlerTest extends TestCase
{
    private PaymentRepositoryInterface $repository;
    private PaymentGatewayInterface $gateway;
    private RefundPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentRepositoryInterface::class);
        $this->gateway = $this->createMock(PaymentGatewayInterface::class);
        $this->handler = new RefundPaymentHandler($this->repository, $this->gateway, new NullLogger());
    }

    public function testItThrowsWhenPaymentNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)(new RefundPayment(PaymentId::generate(), Money::fromScalars(1000, 'USD'), 'test'));
    }

    public function testItThrowsWhenPaymentNotCaptured(): void
    {
        $payment = Payment::create(PaymentId::generate(), TenantId::generate(), 'order-1', Money::fromScalars(5000, 'USD'), PaymentMethod::card(), PaymentGateway::stripe());
        $this->repository->method('findById')->willReturn($payment);

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)(new RefundPayment(PaymentId::generate(), Money::fromScalars(1000, 'USD'), 'test'));
    }

    public function testItRefundsCapturedPayment(): void
    {
        $paymentId = PaymentId::generate();
        $payment = Payment::reconstituteFromPersistence(
            $paymentId, TenantId::generate(), 'order-1', Money::fromScalars(5000, 'USD'),
            PaymentMethod::card(), PaymentGateway::stripe(), PaymentStatus::captured(),
            'pi_test_123', null, Money::fromScalars(0, 'USD'),
            new \DateTimeImmutable(), new \DateTimeImmutable()
        );

        $this->repository->method('findById')->willReturn($payment);

        $result = RefundResult::success('re_test', 'succeeded', Money::fromScalars(1000, 'USD'));
        $this->gateway->method('createRefund')->willReturn($result);
        $this->repository->expects($this->once())->method('save');

        ($this->handler)(new RefundPayment($paymentId, Money::fromScalars(1000, 'USD'), 'customer request'));
    }

    public function testItThrowsWhenGatewayRefundFails(): void
    {
        $payment = Payment::reconstituteFromPersistence(
            PaymentId::generate(), TenantId::generate(), 'order-1', Money::fromScalars(5000, 'USD'),
            PaymentMethod::card(), PaymentGateway::stripe(), PaymentStatus::captured(),
            'pi_test_123', null, Money::fromScalars(0, 'USD'),
            new \DateTimeImmutable(), new \DateTimeImmutable()
        );
        $this->repository->method('findById')->willReturn($payment);

        $result = RefundResult::failure('card_declined', 'Card was declined');
        $this->gateway->method('createRefund')->willReturn($result);

        $this->expectException(\RuntimeException::class);

        ($this->handler)(new RefundPayment(PaymentId::generate(), Money::fromScalars(1000, 'USD'), 'test'));
    }
}
