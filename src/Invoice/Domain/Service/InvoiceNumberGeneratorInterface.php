<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Service;

use App\Invoice\Domain\Exception\InvoiceNumberGenerationException;
use App\Invoice\Domain\Model\InvoiceNumber;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Port interface for generating sequential invoice numbers.
 *
 * Business Rules:
 * - Invoice numbers must be sequential per tenant per year
 * - Format: INV-{YEAR}-{SEQUENCE} e.g., INV-2025-000001
 * - Sequence resets to 1 at the start of each year
 * - No gaps allowed in sequence (legal requirement in EU)
 * - Must be atomic to prevent duplicate numbers under concurrency
 *
 * This is a domain port (hexagonal architecture) - implementations belong in Infrastructure layer.
 */
interface InvoiceNumberGeneratorInterface
{
    /**
     * Generate the next invoice number for a tenant.
     *
     * This method MUST be atomic and thread-safe to prevent duplicate numbers.
     * In multi-instance deployments, use database locks or distributed locks.
     *
     * @param TenantId $tenantId The tenant for which to generate the number
     * @param int|null $year Optional year override (defaults to current year)
     * @return InvoiceNumber The next sequential invoice number
     * @throws InvoiceNumberGenerationException If generation fails (e.g., concurrency error, database error)
     */
    public function generate(TenantId $tenantId, ?int $year = null): InvoiceNumber;

    /**
     * Get the current sequence number without incrementing.
     * Useful for preview/display purposes.
     *
     * @param TenantId $tenantId The tenant
     * @param int|null $year Optional year (defaults to current year)
     * @return int The current sequence number (0 if no invoices yet for this tenant/year)
     */
    public function getCurrentSequence(TenantId $tenantId, ?int $year = null): int;

    /**
     * Preview what the next number would be without reserving it.
     *
     * Returns a preview of the next invoice number that would be generated,
     * without actually incrementing the sequence. Useful for UI display.
     *
     * @param TenantId $tenantId The tenant
     * @param int|null $year Optional year (defaults to current year)
     * @return InvoiceNumber The preview of next number (not reserved)
     */
    public function previewNext(TenantId $tenantId, ?int $year = null): InvoiceNumber;
}
