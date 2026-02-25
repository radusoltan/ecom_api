<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Query;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Query\DownloadInvoicePdf\DownloadInvoicePdfHandler;
use App\Invoice\Application\Query\DownloadInvoicePdf\DownloadInvoicePdfQuery;
use App\Invoice\Application\Query\DownloadInvoicePdf\InvoicePdfResult;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Domain\Service\InvoicePdfGeneratorInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class DownloadInvoicePdfHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private InvoicePdfGeneratorInterface $pdfGenerator;
    private DownloadInvoicePdfHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->pdfGenerator = $this->createMock(InvoicePdfGeneratorInterface::class);
        $this->handler = new DownloadInvoicePdfHandler($this->repository, $this->pdfGenerator);
    }

    public function testReturnsPdfResultForIssuedInvoice(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = $this->createIssuedInvoice($invoiceId);

        $this->repository->method('findById')->willReturn($invoice);
        $this->pdfGenerator->method('generate')->willReturn('%PDF-1.4 binary content');

        $result = ($this->handler)(new DownloadInvoicePdfQuery($invoiceId->toString()));

        $this->assertInstanceOf(InvoicePdfResult::class, $result);
        $this->assertSame('%PDF-1.4 binary content', $result->content);
        $this->assertSame('application/pdf', $result->mimeType);
        $this->assertStringContainsString('INV-2025-000001', $result->fileName);
    }

    public function testUsesLocaleFromQuery(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = $this->createIssuedInvoice($invoiceId);

        $this->repository->method('findById')->willReturn($invoice);

        $this->pdfGenerator->expects($this->once())
            ->method('generate')
            ->with($invoice, 'de')
            ->willReturn('%PDF-1.4');

        ($this->handler)(new DownloadInvoicePdfQuery($invoiceId->toString(), 'de'));
    }

    public function testThrowsWhenInvoiceNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invoice not found');

        ($this->handler)(new DownloadInvoicePdfQuery(InvoiceId::generate()->toString()));
    }

    public function testFileNameFallsBackToIdWhenNoNumber(): void
    {
        $invoiceId = InvoiceId::generate();
        // Draft invoice - no invoice number
        $invoice = Invoice::createDraft(
            $invoiceId,
            TenantId::generate(),
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Item',
            1,
            Money::fromScalars(1000, 'USD'),
            20.0
        ));

        $this->repository->method('findById')->willReturn($invoice);
        $this->pdfGenerator->method('generate')->willReturn('%PDF');

        $result = ($this->handler)(new DownloadInvoicePdfQuery($invoiceId->toString()));

        $this->assertStringContainsString($invoiceId->toString(), $result->fileName);
    }

    private function createIssuedInvoice(InvoiceId $id): Invoice
    {
        $invoice = Invoice::createDraft(
            $id,
            TenantId::generate(),
            OrderId::generate(),
            CustomerId::generate(),
            BillingAddress::create('Test', '12345 Street', null, 'City', '12345', 'US')
        );
        $invoice->addLine(InvoiceLine::create(
            InvoiceLineId::generate(),
            'Test Item',
            1,
            Money::fromScalars(1000, 'USD'),
            20.0
        ));
        $invoice->issue(InvoiceNumber::create(2025, 1), new \DateTimeImmutable('2025-03-01'));

        return $invoice;
    }
}
