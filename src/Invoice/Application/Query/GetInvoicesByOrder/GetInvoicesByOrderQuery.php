<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoicesByOrder;

/**
 * Get Invoice by Order Query.
 *
 * Query to retrieve the invoice associated with a specific order.
 *
 * Business Context:
 * - Typically one order has one invoice
 * - Invoice may not exist yet if order is not yet fulfilled
 *
 * CQRS Pattern: Read operation
 * Returns: Invoice domain model or null if not found
 */
final readonly class GetInvoicesByOrderQuery
{
    public function __construct(
        public string $orderId,
    ) {
    }
}
