<?php

declare(strict_types=1);

namespace App\Invoice\Application\Query\GetInvoiceStatistics;

use App\Invoice\Domain\Model\InvoiceStatus;
use App\Invoice\Domain\Repository\InvoiceRepositoryInterface;
use App\Shared\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Get Invoice Statistics Query Handler.
 *
 * Calculates aggregated invoice statistics for a tenant.
 *
 * Business Rules:
 * - Statistics calculated across all invoices for tenant
 * - Total revenue = sum of all PAID invoices
 * - Outstanding amount = sum of all ISSUED invoices
 * - Overdue count = ISSUED invoices where due_date < now
 * - All amounts in minor units (cents) for precision
 *
 * Performance:
 * - Loads all invoices into memory (acceptable for small-to-medium datasets)
 * - For large datasets, consider implementing repository method with SQL aggregation
 *
 * Implementation Notes:
 * - Currently uses in-memory aggregation from domain models
 * - Future optimization: Push aggregation to database layer
 */
#[AsMessageHandler]
final readonly class GetInvoiceStatisticsHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository
    ) {
    }

    public function __invoke(GetInvoiceStatisticsQuery $query): InvoiceStatisticsDto
    {
        $tenantId = TenantId::fromString($query->tenantId);

        // Load all invoices for tenant
        $invoices = $this->repository->findByTenantId($tenantId);

        // Initialize counters
        $totalInvoices = count($invoices);
        $draftCount = 0;
        $issuedCount = 0;
        $paidCount = 0;
        $cancelledCount = 0;
        $creditedCount = 0;
        $overdueCount = 0;
        $totalRevenueAmount = 0;
        $outstandingAmount = 0;
        $currency = 'USD'; // Default, will be overridden by first invoice

        // Aggregate statistics
        foreach ($invoices as $invoice) {
            // Get currency from first invoice
            if ($totalInvoices > 0 && 'USD' === $currency) {
                $currency = $invoice->total()->currency()->getCurrencyCode();
            }

            // Count by status
            switch ($invoice->status()) {
                case InvoiceStatus::DRAFT:
                    ++$draftCount;

                    break;
                case InvoiceStatus::ISSUED:
                    ++$issuedCount;
                    $outstandingAmount += $invoice->total()->getAmount();

                    // Check if overdue
                    if ($invoice->isOverdue()) {
                        ++$overdueCount;
                    }

                    break;
                case InvoiceStatus::PAID:
                    ++$paidCount;
                    $totalRevenueAmount += $invoice->total()->getAmount();

                    break;
                case InvoiceStatus::CANCELLED:
                    ++$cancelledCount;

                    break;
                case InvoiceStatus::CREDITED:
                    ++$creditedCount;

                    break;
            }
        }

        return new InvoiceStatisticsDto(
            totalInvoices: $totalInvoices,
            draftCount: $draftCount,
            issuedCount: $issuedCount,
            paidCount: $paidCount,
            cancelledCount: $cancelledCount,
            creditedCount: $creditedCount,
            overdueCount: $overdueCount,
            totalRevenueAmount: $totalRevenueAmount,
            totalRevenueCurrency: $currency,
            outstandingAmount: $outstandingAmount,
            outstandingCurrency: $currency
        );
    }
}
