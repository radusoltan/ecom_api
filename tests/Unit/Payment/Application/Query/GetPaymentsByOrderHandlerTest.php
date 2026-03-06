<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Application\Query;

use App\Payment\Application\Query\GetPaymentsByOrder;
use App\Payment\Application\Query\GetPaymentsByOrderHandler;
use App\Payment\Domain\Repository\PaymentRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetPaymentsByOrderHandlerTest extends TestCase
{
    public function testItReturnsPaymentsForOrder(): void
    {
        $repository = $this->createStub(PaymentRepositoryInterface::class);
        $repository->method('findAllByOrderId')->willReturn([]);

        $handler = new GetPaymentsByOrderHandler($repository);
        $result = ($handler)(new GetPaymentsByOrder('order-123', TenantId::generate()));

        self::assertSame([], $result);
    }
}
