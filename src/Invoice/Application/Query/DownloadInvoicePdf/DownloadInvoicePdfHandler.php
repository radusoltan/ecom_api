<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\DownloadInvoicePdf;

use App\Invoice\Domain\Model\InvoiceId;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Invoice\Domain\Service\InvoicePdfGeneratorInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Download Invoice PDF Query Handler.
 *
 * Generates or retrieves PDF content for an invoice.
 *
 * Business Rules:
 * - Invoice must exist and be in issued, paid, or cancelled state
 * - PDF is generated with appropriate localization
 * - PDF content is returned as binary string
 *
 * Implementation:
 * - Uses InvoicePdfGeneratorInterface (domain service)
 * - PDF generation delegated to infrastructure layer
 *
 * @throws \InvalidArgumentException If invoice not found
 * @throws \RuntimeException         If PDF generation fails
 */
#[AsMessageHandler]
final readonly class DownloadInvoicePdfHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private InvoicePdfGeneratorInterface $pdfGenerator,
    ) {
    }

    public function __invoke(DownloadInvoicePdfQuery $query): InvoicePdfResult
    {
        $invoiceId = InvoiceId::fromString($query->invoiceId);

        $invoice = $this->repository->findById($invoiceId);
        if (null === $invoice) {
            throw new \InvalidArgumentException(sprintf('Invoice not found: %s', $query->invoiceId));
        }

        // Generate PDF with specified locale
        $pdfContent = $this->pdfGenerator->generate($invoice, $query->locale);

        // Generate filename from invoice number or ID
        $fileName = $invoice->number()
            ? sprintf('%s.pdf', $invoice->number()->toString())
            : sprintf('invoice-%s.pdf', $invoice->id()->toString());

        return new InvoicePdfResult(
            content: $pdfContent,
            fileName: $fileName,
            mimeType: 'application/pdf'
        );
    }
}
