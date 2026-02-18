<?php

declare(strict_types=1);

namespace App\Pricing\Application\Command\ImportPriceList;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to import PriceLists from a file.
 *
 * Business Rules:
 * - Maximum file size: 10MB
 * - Maximum rows: 10,000
 * - Import is idempotent (can re-run)
 * - Updates existing PriceLists with same name
 */
final readonly class ImportPriceListCommand
{
    /**
     * @param array<array<string, mixed>> $rows Parsed rows from import file
     */
    public function __construct(
        public TenantId $tenantId,
        public array $rows,
        public bool $updateExisting = true,
    ) {
    }
}
