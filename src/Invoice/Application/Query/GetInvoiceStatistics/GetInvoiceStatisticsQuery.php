<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoiceStatistics;

/**
 * Get Invoice Statistics Query.
 *
 * Query to retrieve aggregated invoice statistics for a tenant.
 *
 * Business Context:
 * - Used for dashboard metrics and financial reporting
 * - Provides overview of invoice states and financial health
 * - All amounts in minor units (cents) for precision
 *
 * Metrics Returned:
 * - Total invoice count
 * - Count by status (draft, issued, paid, cancelled, credited)
 * - Overdue count (issued invoices past due date)
 * - Total revenue (sum of paid invoices)
 * - Outstanding amount (sum of issued but unpaid invoices)
 *
 * CQRS Pattern: Read operation
 * Returns: InvoiceStatisticsDto with aggregated metrics
 */
final readonly class GetInvoiceStatisticsQuery
{
    public function __construct(
        public string $tenantId
    ) {
    }
}
