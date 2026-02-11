<?php

declare(strict_types=1);

namespace App\Pricing\Application\DTO;

use App\Shared\Domain\ValueObject\TenantId;

/**
 * Filter criteria for export operations.
 *
 * Used to filter which records should be included in the export
 */
final readonly class ExportFilter
{
    public function __construct(
        public TenantId $tenantId,
        public ?\DateTimeImmutable $dateFrom = null,
        public ?\DateTimeImmutable $dateTo = null,
        public ?bool $isActive = null,
        public ?string $status = null
    ) {
        $this->validateDateRange();
    }

    private function validateDateRange(): void
    {
        if (null !== $this->dateFrom && null !== $this->dateTo) {
            if ($this->dateFrom > $this->dateTo) {
                throw new \InvalidArgumentException('dateFrom must be before or equal to dateTo');
            }
        }
    }

    /**
     * Check if export should include only active items.
     */
    public function onlyActive(): bool
    {
        return $this->isActive === true;
    }

    /**
     * Check if date range filter is applied.
     */
    public function hasDateRange(): bool
    {
        return null !== $this->dateFrom || null !== $this->dateTo;
    }
}
