<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\RegeneratePdf;

use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Domain\Service\InvoicePdfGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for RegeneratePdf command.
 *
 * Regenerates PDF document for an existing invoice.
 */
#[AsMessageHandler]
final readonly class RegeneratePdfHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private InvoicePdfGeneratorInterface $pdfGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RegeneratePdfCommand $command): void
    {
        $invoiceId = InvoiceId::fromString($command->invoiceId);

        // Load invoice
        $invoice = $this->repository->findById($invoiceId);

        if (null === $invoice) {
            throw new \RuntimeException(sprintf('Invoice with ID %s not found', $invoiceId->toString()));
        }

        // Verify invoice is in a state where PDF makes sense
        if ($invoice->status()->isDraft()) {
            throw new \InvalidArgumentException('Cannot regenerate PDF for draft invoice. Issue the invoice first.');
        }

        $oldPdfPath = $invoice->pdfPath();

        // Generate and save new PDF
        $newPdfPath = $this->pdfGenerator->generateAndSave($invoice);
        $invoice->setPdfPath($newPdfPath);

        // Save invoice
        $this->repository->save($invoice);

        $this->logger->info('Invoice PDF regenerated', [
            'invoice_id' => $invoiceId->toString(),
            'invoice_number' => $invoice->number()?->toString(),
            'tenant_id' => $invoice->tenantId()->toString(),
            'old_pdf_path' => $oldPdfPath,
            'new_pdf_path' => $newPdfPath,
        ]);
    }
}
