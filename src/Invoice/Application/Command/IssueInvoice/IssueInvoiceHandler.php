<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\IssueInvoice;

use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Invoice\Domain\Service\InvoicePdfGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for IssueInvoice command.
 *
 * Issues a draft invoice by assigning invoice number and generating PDF.
 * The invoice becomes immutable after issuing.
 */
#[AsMessageHandler]
final readonly class IssueInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private InvoiceNumberGeneratorInterface $numberGenerator,
        private InvoicePdfGeneratorInterface $pdfGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(IssueInvoiceCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);

        // Load invoice
        $invoice = $this->repository->findById($invoiceId);

        if (null === $invoice) {
            throw new \RuntimeException(sprintf('Invoice with ID %s not found', $invoiceId->toString()));
        }

        // Generate sequential invoice number
        $invoiceNumber = $this->numberGenerator->generate($invoice->tenantId());

        // Parse due date if provided
        $dueDate = null;
        if (null !== $command->dueDate) {
            $dueDate = new \DateTimeImmutable($command->dueDate);
        }

        // Issue invoice (assigns number, sets dates, changes status)
        $issueDate = new \DateTimeImmutable();
        $invoice->issue($invoiceNumber, $issueDate, $dueDate);

        // Generate and save PDF
        $pdfPath = $this->pdfGenerator->generateAndSave($invoice);
        $invoice->setPdfPath($pdfPath);

        // Save invoice (will dispatch InvoiceIssued event)
        $this->repository->save($invoice);

        $this->logger->info('Invoice issued', [
            'invoice_id' => $invoiceId->toString(),
            'invoice_number' => $invoiceNumber->toString(),
            'tenant_id' => $invoice->tenantId()->toString(),
            'issue_date' => $issueDate->format('Y-m-d'),
            'due_date' => $invoice->dueDate()?->format('Y-m-d'),
            'total' => $invoice->total()->getAmount(),
            'pdf_path' => $pdfPath,
        ]);
    }
}
