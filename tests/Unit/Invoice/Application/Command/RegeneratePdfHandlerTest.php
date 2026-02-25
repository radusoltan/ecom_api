<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Command\RegeneratePdf\RegeneratePdfCommand;
use App\Invoice\Application\Command\RegeneratePdf\RegeneratePdfHandler;
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
use Psr\Log\NullLogger;

final class RegeneratePdfHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private InvoicePdfGeneratorInterface $pdfGenerator;
    private RegeneratePdfHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->pdfGenerator = $this->createMock(InvoicePdfGeneratorInterface::class);
        $this->handler = new RegeneratePdfHandler($this->repository, $this->pdfGenerator, new NullLogger());
    }

    public function testRegeneratesPdfForIssuedInvoice(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = $this->createIssuedInvoice($invoiceId);

        $this->repository->method('findById')->willReturn($invoice);

        $this->pdfGenerator->expects($this->once())
            ->method('generateAndSave')
            ->willReturn('/storage/invoices/INV-2025-000001-v2.pdf');

        $this->repository->expects($this->once())
            ->method('save');

        ($this->handler)(new RegeneratePdfCommand($invoiceId->toString()));
    }

    public function testThrowsWhenInvoiceNotFound(): void
    {
        $this->repository->method('findById')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)(new RegeneratePdfCommand(InvoiceId::generate()->toString()));
    }

    public function testThrowsWhenInvoiceIsDraft(): void
    {
        $invoiceId = InvoiceId::generate();
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('draft');

        ($this->handler)(new RegeneratePdfCommand($invoiceId->toString()));
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
