<?php

declare(strict_types=1);

namespace App\Search\Domain\Model;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * SearchQuery Value Object
 *
 * Encapsulates all search parameters with validation.
 *
 * Business Rules:
 * - Query string: min 1 char (allow single-letter searches)
 * - Page: must be >= 1
 * - PerPage: must be between 1-100
 * - SortBy: must be valid field (relevance, price, name, created_at)
 * - Filters: optional category, price range, brands, stock
 */
final readonly class SearchQuery
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public TenantId $tenantId,
        public string $query,
        public Locale $locale,
        public int $page = 1,
        public int $perPage = 24,
        public ?string $sortBy = 'relevance',
        public ?string $sortOrder = 'desc',
        public array $filters = [],
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ('' === trim($this->query)) {
            throw new \InvalidArgumentException('Search query cannot be empty');
        }

        if ($this->page < 1) {
            throw new \InvalidArgumentException('Page must be >= 1');
        }

        if ($this->perPage < 1 || $this->perPage > 100) {
            throw new \InvalidArgumentException('Per page must be between 1 and 100');
        }

        $validSortFields = ['relevance', 'price', 'name', 'created_at'];
        if (null !== $this->sortBy && !\in_array($this->sortBy, $validSortFields, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid sort field: %s. Allowed: %s',
                $this->sortBy,
                implode(', ', $validSortFields)
            ));
        }

        if (null !== $this->sortOrder && !\in_array($this->sortOrder, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Sort order must be "asc" or "desc"');
        }
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    public function hasCategoryFilter(): bool
    {
        return isset($this->filters['category']) && '' !== $this->filters['category'];
    }

    public function hasPriceFilter(): bool
    {
        return isset($this->filters['price_min']) || isset($this->filters['price_max']);
    }

    public function hasBrandFilter(): bool
    {
        return isset($this->filters['brand']) && !empty($this->filters['brand']);
    }

    public function hasInStockOnlyFilter(): bool
    {
        return isset($this->filters['in_stock_only']) && true === $this->filters['in_stock_only'];
    }
}
