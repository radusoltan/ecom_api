<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Query\GetInvoice\GetInvoiceHandler;
use App\Invoice\Application\Query\GetInvoice\GetInvoiceQuery;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetInvoiceHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private GetInvoiceHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->handler = new GetInvoiceHandler($this->repository);
    }

    public function testReturnsInvoiceWhenFound(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = Invoice::createDraft(
            $invoiceId,
            TenantId::generate(),
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );

        $this->repository->method('findById')->willReturn($invoice);

        $result = ($this->handler)(new GetInvoiceQuery($invoiceId->toString()));

        $this->assertNotNull($result);
        $this->assertSame($invoiceId->toString(), $result->id()->toString());
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $result = ($this->handler)(new GetInvoiceQuery(InvoiceId::generate()->toString()));

        $this->assertNull($result);
    }
}
