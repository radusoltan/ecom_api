<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoiceStatistics;

/**
 * Invoice Statistics DTO.
 *
 * Aggregated invoice metrics for dashboard/reporting purposes.
 *
 * Business Metrics:
 * - Total invoices across all states
 * - Count by status (draft, issued, paid, cancelled, credited)
 * - Overdue invoices count (issued invoices past due date)
 * - Total revenue (sum of paid invoices)
 * - Outstanding amount (sum of issued but not paid invoices)
 *
 * Usage:
 * - Dashboard widgets
 * - Financial reports
 * - Tenant analytics
 *
 * Example:
 * ```php
 * $stats = new InvoiceStatisticsDto(
 *     totalInvoices: 150,
 *     draftCount: 5,
 *     issuedCount: 20,
 *     paidCount: 120,
 *     cancelledCount: 3,
 *     creditedCount: 2,
 *     overdueCount: 8,
 *     totalRevenueAmount: 1500000, // $15,000.00 in minor units
 *     totalRevenueCurrency: 'USD',
 *     outstandingAmount: 200000,   // $2,000.00 in minor units
 *     outstandingCurrency: 'USD'
 * );
 * ```
 */
final readonly class InvoiceStatisticsDto
{
    public function __construct(
        public int $totalInvoices,
        public int $draftCount,
        public int $issuedCount,
        public int $paidCount,
        public int $cancelledCount,
        public int $creditedCount,
        public int $overdueCount,
        public int $totalRevenueAmount,      // Sum of paid invoices (minor units)
        public string $totalRevenueCurrency,
        public int $outstandingAmount,       // Sum of issued invoices (minor units)
        public string $outstandingCurrency
    ) {
    }
}
