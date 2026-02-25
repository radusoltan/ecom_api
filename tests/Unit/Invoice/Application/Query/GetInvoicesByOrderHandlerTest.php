<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Query\GetInvoicesByOrder\GetInvoicesByOrderHandler;
use App\Invoice\Application\Query\GetInvoicesByOrder\GetInvoicesByOrderQuery;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetInvoicesByOrderHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private GetInvoicesByOrderHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->handler = new GetInvoicesByOrderHandler($this->repository);
    }

    public function testReturnsInvoiceWhenFound(): void
    {
        $orderId = OrderId::generate();
        $invoice = Invoice::createDraft(
            InvoiceId::generate(),
            TenantId::generate(),
            $orderId,
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );

        $this->repository->method('findByOrderId')->willReturn($invoice);

        $result = ($this->handler)(new GetInvoicesByOrderQuery($orderId->toString()));

        $this->assertNotNull($result);
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $this->repository->method('findByOrderId')->willReturn(null);

        $result = ($this->handler)(new GetInvoicesByOrderQuery(OrderId::generate()->toString()));

        $this->assertNull($result);
    }
}
