<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Service;

use App\Invoice\Domain\Model\Invoice;

/**
 * Domain service interface for generating invoice PDFs.
 *
 * This is a port (hexagonal architecture) - the infrastructure layer
 * provides the concrete implementation using DomPDF.
 */
interface InvoicePdfGeneratorInterface
{
    /**
     * Generate PDF for an invoice.
     *
     * @param Invoice $invoice The invoice to generate PDF for
     * @param string  $locale  The locale for the PDF (e.g., 'en', 'fr', 'de')
     *
     * @return string The PDF content as binary string
     */
    public function generate(Invoice $invoice, string $locale = 'en'): string;

    /**
     * Generate and save PDF to filesystem.
     *
     * @param Invoice $invoice The invoice to generate PDF for
     * @param string  $locale  The locale for the PDF (e.g., 'en', 'fr', 'de')
     *
     * @return string The absolute path where PDF was saved
     */
    public function generateAndSave(Invoice $invoice, string $locale = 'en'): string;
}
