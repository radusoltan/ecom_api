<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\DownloadInvoicePdf;

/**
 * Invoice PDF Result DTO.
 *
 * Data Transfer Object containing PDF content and metadata.
 *
 * Usage:
 * - Returned by DownloadInvoicePdfHandler
 * - Used by API processors to return PDF as HTTP response
 * - Binary content should be sent with appropriate Content-Type header
 *
 * Example:
 * ```php
 * $result = new InvoicePdfResult(
 *     content: $binaryPdfData,
 *     fileName: 'INV-2025-000001.pdf',
 *     mimeType: 'application/pdf'
 * );
 * ```
 */
final readonly class InvoicePdfResult
{
    public function __construct(
        public string $content,    // Binary PDF content
        public string $fileName,   // e.g., "INV-2025-000001.pdf"
        public string $mimeType    // "application/pdf"
    ) {
    }
}
