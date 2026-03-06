<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\Query\GetPaymentById;
use App\Payment\Application\Query\GetPaymentByIdHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetPaymentByIdHandlerTest extends TestCase
{
    public function testItReturnsPaymentDtoWhenFound(): void
    {
        $paymentId = PaymentId::generate();
        $payment = Payment::create($paymentId, TenantId::generate(), 'order-1', Money::fromScalars(5000, 'USD'), PaymentMethod::card(), PaymentGateway::stripe());

        $repository = $this->createStub(PaymentRepositoryInterface::class);
        $repository->method('findById')->willReturn($payment);

        $handler = new GetPaymentByIdHandler($repository);
        $result = ($handler)(new GetPaymentById($paymentId));

        self::assertNotNull($result);
        self::assertSame($paymentId->toString(), $result->id);
    }

    public function testItReturnsNullWhenNotFound(): void
    {
        $repository = $this->createStub(PaymentRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new GetPaymentByIdHandler($repository);
        $result = ($handler)(new GetPaymentById(PaymentId::generate()));

        self::assertNull($result);
    }
}
