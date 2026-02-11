<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\ExportCustomers;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Query to export customers to CSV or JSON format.
 *
 * Supports filtering by segment, status, and date range.
 */
final readonly class ExportCustomersQuery
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public TenantId $tenantId,
        public string $format,
        public array $filters = []
    ) {
        if (!in_array($format, ['csv', 'json'], true)) {
            throw new \InvalidArgumentException('Format must be either "csv" or "json"');
        }
    }

    public function isCsvFormat(): bool
    {
        return 'csv' === $this->format;
    }

    public function isJsonFormat(): bool
    {
        return 'json' === $this->format;
    }
}
