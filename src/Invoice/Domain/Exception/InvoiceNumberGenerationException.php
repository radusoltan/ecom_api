<?php

declare(strict_types=1);

namespace App\Invoice\Domain\Exception;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Exception thrown when invoice number generation fails.
 *
 * This can occur due to:
 * - Concurrency errors (multiple processes trying to generate same number)
 * - Database errors (connection failure, lock timeout)
 * - Invalid year parameter
 * - System errors (e.g., distributed lock unavailable)
 */
final class InvoiceNumberGenerationException extends \DomainException
{
    /**
     * Generic failure to generate invoice number.
     *
     * @param TenantId $tenantId The tenant for which generation failed
     * @param string $reason Human-readable reason for failure
     * @return self
     */
    public static function failedToGenerate(TenantId $tenantId, string $reason): self
    {
        return new self(sprintf(
            'Failed to generate invoice number for tenant %s: %s',
            $tenantId->toString(),
            $reason
        ));
    }

    /**
     * Concurrency error - multiple processes tried to generate same number.
     *
     * This error is recoverable - the client should retry the operation.
     *
     * @param TenantId $tenantId The tenant for which concurrency error occurred
     * @return self
     */
    public static function concurrencyError(TenantId $tenantId): self
    {
        return new self(sprintf(
            'Concurrency error while generating invoice number for tenant %s. Please retry.',
            $tenantId->toString()
        ));
    }

    /**
     * Invalid year parameter.
     *
     * @param int $year The invalid year value
     * @return self
     */
    public static function invalidYear(int $year): self
    {
        return new self(sprintf(
            'Invalid year for invoice number generation: %d. Year must be between 2000 and 2100.',
            $year
        ));
    }
}
