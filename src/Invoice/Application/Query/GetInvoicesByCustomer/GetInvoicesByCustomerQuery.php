<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoicesByCustomer;

/**
 * Get Invoices by Customer Query.
 *
 * Query to retrieve all invoices for a specific customer.
 *
 * Business Context:
 * - Returns invoices across all states (draft, issued, paid, cancelled)
 * - Results ordered by creation date descending (newest first)
 * - Multi-tenancy enforced at database level
 *
 * CQRS Pattern: Read operation
 * Returns: Array of Invoice domain models (may be empty)
 */
final readonly class GetInvoicesByCustomerQuery
{
    public function __construct(
        public string $customerId,
    ) {
    }
}
