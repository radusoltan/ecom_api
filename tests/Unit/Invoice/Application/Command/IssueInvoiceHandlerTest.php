<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Application\Command;

use App\Customer\Domain\ValueObject\CustomerId;
use App\Invoice\Application\Command\IssueInvoice\IssueInvoiceCommand;
use App\Invoice\Application\Command\IssueInvoice\IssueInvoiceHandler;
use App\Invoice\Domain\Model\BillingAddress;
use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Invoice\Domain\Service\InvoicePdfGeneratorInterface;
use App\Order\Domain\Model\OrderId;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(IssueInvoiceHandler::class)]
final class IssueInvoiceHandlerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;
    private InvoiceNumberGeneratorInterface $numberGenerator;
    private InvoicePdfGeneratorInterface $pdfGenerator;
    private IssueInvoiceHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->numberGenerator = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $this->pdfGenerator = $this->createMock(InvoicePdfGeneratorInterface::class);
        $this->handler = new IssueInvoiceHandler(
            $this->repository,
            $this->numberGenerator,
            $this->pdfGenerator,
            new NullLogger()
        );
    }

    #[Test]
    public function handleIssuesInvoiceWithGeneratedNumber(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = $this->createDraftInvoiceWithLine($invoiceId);
        $invoiceNumber = InvoiceNumber::create(2025, 42);

        $command = new IssueInvoiceCommand($invoiceId->toString());

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn($invoice);

        $this->numberGenerator->expects(self::once())
            ->method('generate')
            ->willReturn($invoiceNumber);

        $this->pdfGenerator->expects(self::once())
            ->method('generateAndSave')
            ->willReturn('/storage/invoices/INV-2025-000042.pdf');

        $this->repository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Invoice $saved): bool {
                return $saved->status()->isIssued()
                    && null !== $saved->number()
                    && null !== $saved->pdfPath();
            }));

        ($this->handler)($command);
    }

    #[Test]
    public function handleWithCustomDueDateSetsIt(): void
    {
        $invoiceId = InvoiceId::generate();
        $invoice = $this->createDraftInvoiceWithLine($invoiceId);

        $command = new IssueInvoiceCommand($invoiceId->toString(), '2027-06-01');

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn($invoice);

        $this->numberGenerator->expects(self::once())
            ->method('generate')
            ->willReturn(InvoiceNumber::create(2025, 1));

        $this->pdfGenerator->expects(self::once())
            ->method('generateAndSave')
            ->willReturn('/tmp/invoice.pdf');

        $this->repository->expects(self::once())
            ->method('save');

        ($this->handler)($command);
    }

    #[Test]
    public function handleThrowsExceptionWhenInvoiceNotFound(): void
    {
        $command = new IssueInvoiceCommand(InvoiceId::generate()->toString());

        $this->repository->expects(self::once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');

        ($this->handler)($command);
    }

    private function createDraftInvoiceWithLine(InvoiceId $id): Invoice
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

        return $invoice;
    }
}
