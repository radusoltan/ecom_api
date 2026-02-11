<?php

declare(strict_types=1);

namespace App\Invoice\Application\Command\IssueInvoice;

/**
 * Command to issue a draft invoice.
 *
 * Issues a draft invoice by:
 * - Assigning a sequential invoice number
 * - Generating PDF document
 * - Changing status from DRAFT to ISSUED
 * - Making the invoice immutable
 *
 * Business Rules:
 * - Only draft invoices can be issued
 * - Invoice must have at least one line item
 * - Invoice number is sequential per tenant per year
 * - Due date defaults to issue date + 30 days
 * - Invoice becomes immutable after issue
 */
final readonly class IssueInvoiceCommand
{
    /**
     * @param string      $invoiceId Invoice identifier (UUID)
     * @param string|null $dueDate   Optional due date (ISO 8601 format), defaults to issue_date + 30 days
     */
    public function __construct(
        public string $invoiceId,
        public ?string $dueDate = null,
    ) {
    }
}
