<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoice;

/**
 * Get Invoice Query.
 *
 * Query to retrieve a single invoice by its unique identifier.
 *
 * CQRS Pattern: Read operation
 * Returns: Invoice domain model or null if not found
 */
final readonly class GetInvoiceQuery
{
    public function __construct(
        public string $invoiceId
    ) {
    }
}
