<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\Query\GetAllPayments;
use App\Payment\Application\Query\GetAllPaymentsHandler;
use App\Payment\Domain\Model\Payment;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Payment\Domain\ValueObject\PaymentGateway;
use App\Payment\Domain\ValueObject\PaymentId;
use App\Payment\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetAllPaymentsHandlerTest extends TestCase
{
    public function testItReturnsAllPaymentsAsDtos(): void
    {
        $tenantId = TenantId::generate();
        $payment = Payment::create(PaymentId::generate(), $tenantId, 'order-1', Money::fromScalars(5000, 'USD'), PaymentMethod::card(), PaymentGateway::stripe());

        $repository = $this->createStub(PaymentRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$payment]);

        $handler = new GetAllPaymentsHandler($repository);
        $result = ($handler)(new GetAllPayments($tenantId));

        self::assertCount(1, $result);
    }

    public function testItReturnsEmptyArrayWhenNoPayments(): void
    {
        $repository = $this->createStub(PaymentRepositoryInterface::class);
        $repository->method('findAll')->willReturn([]);

        $handler = new GetAllPaymentsHandler($repository);
        $result = ($handler)(new GetAllPayments(TenantId::generate()));

        self::assertSame([], $result);
    }
}
