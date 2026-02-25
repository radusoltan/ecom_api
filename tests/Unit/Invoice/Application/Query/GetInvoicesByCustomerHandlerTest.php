<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Query\GetInvoicesByCustomer\GetInvoicesByCustomerHandler;
use App\Invoice\Application\Query\GetInvoicesByCustomer\GetInvoicesByCustomerQuery;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetInvoicesByCustomerHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private GetInvoicesByCustomerHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->handler = new GetInvoicesByCustomerHandler($this->repository);
    }

    public function testReturnsInvoicesForCustomer(): void
    {
        $customerId = CustomerId::generate();
        $invoice1 = Invoice::createDraft(
            InvoiceId::generate(),
            TenantId::generate(),
            OrderId::generate(),
            $customerId,
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );
        $invoice2 = Invoice::createDraft(
            InvoiceId::generate(),
            TenantId::generate(),
            OrderId::generate(),
            $customerId,
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );

        $this->repository->method('findByCustomerId')->willReturn([$invoice1, $invoice2]);

        $result = ($this->handler)(new GetInvoicesByCustomerQuery($customerId->toString()));

        $this->assertCount(2, $result);
    }

    public function testReturnsEmptyArrayWhenNoneFound(): void
    {
        $this->repository->method('findByCustomerId')->willReturn([]);

        $result = ($this->handler)(new GetInvoicesByCustomerQuery(CustomerId::generate()->toString()));

        $this->assertEmpty($result);
    }
}
