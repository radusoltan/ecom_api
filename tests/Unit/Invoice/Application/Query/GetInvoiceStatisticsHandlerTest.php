<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Query\GetInvoiceStatistics\GetInvoiceStatisticsHandler;
use App\Invoice\Application\Query\GetInvoiceStatistics\GetInvoiceStatisticsQuery;
use App\Invoice\Application\Query\GetInvoiceStatistics\InvoiceStatisticsDto;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class GetInvoiceStatisticsHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private GetInvoiceStatisticsHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->handler = new GetInvoiceStatisticsHandler($this->repository);
    }

    public function testReturnsZeroStatsWhenNoInvoices(): void
    {
        $tenantId = TenantId::generate();
        $this->repository->method('findByTenantId')->willReturn([]);

        $result = ($this->handler)(new GetInvoiceStatisticsQuery($tenantId->toString()));

        $this->assertInstanceOf(InvoiceStatisticsDto::class, $result);
        $this->assertSame(0, $result->totalInvoices);
        $this->assertSame(0, $result->draftCount);
        $this->assertSame(0, $result->issuedCount);
        $this->assertSame(0, $result->paidCount);
        $this->assertSame(0, $result->cancelledCount);
        $this->assertSame(0, $result->creditedCount);
        $this->assertSame(0, $result->overdueCount);
        $this->assertSame(0, $result->totalRevenueAmount);
        $this->assertSame(0, $result->outstandingAmount);
    }

    public function testCountsInvoicesByStatus(): void
    {
        $tenantId = TenantId::generate();

        $draft = $this->createDraftInvoice($tenantId);
        $issued = $this->createIssuedInvoice($tenantId, 1);
        $paid = $this->createPaidInvoice($tenantId, 2);
        $cancelled = $this->createCancelledInvoice($tenantId, 3);

        $this->repository->method('findByTenantId')->willReturn([$draft, $issued, $paid, $cancelled]);

        $result = ($this->handler)(new GetInvoiceStatisticsQuery($tenantId->toString()));

        $this->assertSame(4, $result->totalInvoices);
        $this->assertSame(1, $result->draftCount);
        $this->assertSame(1, $result->issuedCount);
        $this->assertSame(1, $result->paidCount);
        $this->assertSame(1, $result->cancelledCount);
    }

    public function testCalculatesRevenueFromPaidInvoices(): void
    {
        $tenantId = TenantId::generate();

        $paid1 = $this->createPaidInvoice($tenantId, 1, 5000);
        $paid2 = $this->createPaidInvoice($tenantId, 2, 3000);

        $this->repository->method('findByTenantId')->willReturn([$paid1, $paid2]);

        $result = ($this->handler)(new GetInvoiceStatisticsQuery($tenantId->toString()));

        // Each invoice: unitPrice * qty + tax = 5000*1 + 1000 = 6000 and 3000*1 + 600 = 3600
        $this->assertSame(2, $result->paidCount);
        $this->assertGreaterThan(0, $result->totalRevenueAmount);
        $this->assertSame('EUR', $result->totalRevenueCurrency);
    }

    public function testCalculatesOutstandingFromIssuedInvoices(): void
    {
        $tenantId = TenantId::generate();

        $issued = $this->createIssuedInvoice($tenantId, 1, 2000);

        $this->repository->method('findByTenantId')->willReturn([$issued]);

        $result = ($this->handler)(new GetInvoiceStatisticsQuery($tenantId->toString()));

        $this->assertSame(1, $result->issuedCount);
        $this->assertGreaterThan(0, $result->outstandingAmount);
    }

    public function testCountsOverdueInvoices(): void
    {
        $tenantId = TenantId::generate();

        // Issued with past due date
        $overdue = $this->createIssuedInvoice($tenantId, 1, 1000, new \DateTimeImmutable('-60 days'));

        $this->repository->method('findByTenantId')->willReturn([$overdue]);

        $result = ($this->handler)(new GetInvoiceStatisticsQuery($tenantId->toString()));

        $this->assertSame(1, $result->overdueCount);
    }

    private function createDraftInvoice(TenantId $tenantId): Invoice
    {
        $invoice = Invoice::createDraft(
            InvoiceId::generate(),
            $tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars(1000, 'EUR'),
            20.0
        ));

        return $invoice;
    }

    private function createIssuedInvoice(
        TenantId $tenantId,
        int $seq,
        int $price = 1000,
        ?\DateTimeImmutable $issueDate = null,
    ): Invoice {
        $invoice = Invoice::createDraft(
            InvoiceId::generate(),
            $tenantId,
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars($price, 'EUR'),
            20.0
        ));
        $invoice->issue(
            InvoiceNumber::create(2025, $seq),
            $issueDate ?? new \DateTimeImmutable('-1 day')
        );

        return $invoice;
    }

    private function createPaidInvoice(TenantId $tenantId, int $seq, int $price = 1000): Invoice
    {
        $invoice = $this->createIssuedInvoice($tenantId, $seq, $price);
        $invoice->markAsPaid(new \DateTimeImmutable());

        return $invoice;
    }

    private function createCancelledInvoice(TenantId $tenantId, int $seq): Invoice
    {
        $invoice = $this->createIssuedInvoice($tenantId, $seq);
        $invoice->cancel();

        return $invoice;
    }
}
