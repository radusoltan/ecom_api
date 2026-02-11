<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\DownloadInvoicePdf;

/**
 * Download Invoice PDF Query.
 *
 * Query to generate or retrieve the PDF content for an invoice.
 *
 * Business Context:
 * - PDF is generated on-the-fly if not already cached
 * - Generated PDFs can be cached on filesystem
 * - Supports localization (invoice in customer's language)
 *
 * CQRS Pattern: Read operation
 * Returns: InvoicePdfResult DTO with binary PDF content
 */
final readonly class DownloadInvoicePdfQuery
{
    public function __construct(
        public string $invoiceId,
        public string $locale = 'en'
    ) {
    }
}
