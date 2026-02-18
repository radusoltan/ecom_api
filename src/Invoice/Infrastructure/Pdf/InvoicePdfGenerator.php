<?php

declare(strict_types=1);

namespace App\Invoice\Infrastructure\Pdf;

use App\Invoice\Domain\Model\Invoice;
use App\Invoice\Domain\Service\InvoicePdfGeneratorInterface;
use Psr\Log\LoggerInterface;
use Twig\Environment;

/**
 * Infrastructure implementation of Invoice PDF generator using DomPDF.
 *
 * This adapter converts domain invoices into PDF documents using:
 * - Twig templates for HTML generation
 * - DomPDF for PDF rendering
 * - Filesystem for storage
 */
final class InvoicePdfGenerator implements InvoicePdfGeneratorInterface
{
    public function __construct(
        private readonly DompdfFactory $dompdfFactory,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $invoicesDir, // %kernel.project_dir%/var/invoices
        private readonly string $companyName,
        private readonly string $companyAddress,
        private readonly string $companyVatNumber,
        private readonly ?string $logoPath = null,
    ) {
    }

    public function generate(Invoice $invoice, string $locale = 'en'): string
    {
        $invoiceNumber = $invoice->number()?->toString() ?? 'DRAFT';

        $this->logger->info('Generating PDF for invoice', [
            'invoice_id' => $invoice->id()->toString(),
            'invoice_number' => $invoiceNumber,
            'locale' => $locale,
        ]);

        try {
            // 1. Render Twig template to HTML
            $html = $this->renderHtml($invoice, $locale);

            // 2. Create DomPDF instance via factory
            $dompdf = $this->dompdfFactory->create();

            // 3. Load HTML and render PDF
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            // 4. Return binary PDF content
            $pdfContent = $dompdf->output();

            $this->logger->info('PDF generated successfully', [
                'invoice_id' => $invoice->id()->toString(),
                'size_bytes' => strlen($pdfContent ?? ''),
            ]);

            return $pdfContent ?? '';
        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate PDF', [
                'invoice_id' => $invoice->id()->toString(),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(sprintf('Failed to generate PDF for invoice %s: %s', $invoiceNumber, $e->getMessage()), 0, $e);
        }
    }

    public function generateAndSave(Invoice $invoice, string $locale = 'en'): string
    {
        // 1. Generate PDF content
        $pdfContent = $this->generate($invoice, $locale);

        // 2. Build file path: var/invoices/{tenant_id}/{year}/{invoice_number}.pdf
        $filePath = $this->buildFilePath($invoice);

        // 3. Create directory if needed
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException(sprintf('Failed to create directory: %s', $directory));
            }
        }

        // 4. Save to filesystem
        $bytesWritten = file_put_contents($filePath, $pdfContent);

        if (false === $bytesWritten) {
            throw new \RuntimeException(sprintf('Failed to write PDF to: %s', $filePath));
        }

        $this->logger->info('PDF saved to filesystem', [
            'invoice_id' => $invoice->id()->toString(),
            'file_path' => $filePath,
            'size_bytes' => $bytesWritten,
        ]);

        return $filePath;
    }

    /**
     * Render Twig template to HTML.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function renderHtml(Invoice $invoice, string $locale): string
    {
        $template = $invoice->status()->isCredited() ? 'invoice/credit_note.html.twig' : 'invoice/invoice.html.twig';

        return $this->twig->render($template, [
            'invoice' => $invoice,
            'locale' => $locale,
            'company' => [
                'name' => $this->companyName,
                'address' => $this->companyAddress,
                'vatNumber' => $this->companyVatNumber,
            ],
            'logo' => $this->logoPath && file_exists($this->logoPath) ? $this->logoPath : null,
        ]);
    }

    /**
     * Build file path for invoice PDF.
     * Pattern: var/invoices/{tenant_id}/{year}/{invoice_number}.pdf.
     */
    private function buildFilePath(Invoice $invoice): string
    {
        $tenantId = $invoice->tenantId()->toString();

        // Use issue date year or current year if invoice is not yet issued
        $issueDate = $invoice->issueDate();
        $year = $issueDate?->format('Y') ?? (new \DateTimeImmutable())->format('Y');

        // Use invoice number or ID if invoice is in draft status
        $number = $invoice->number();
        $invoiceNumber = $number?->toString() ?? $invoice->id()->toString();

        // Sanitize invoice number for filesystem (replace / with -)
        $safeInvoiceNumber = str_replace('/', '-', $invoiceNumber);

        return sprintf(
            '%s/%s/%s/%s.pdf',
            $this->invoicesDir,
            $tenantId,
            $year,
            $safeInvoiceNumber
        );
    }
}
