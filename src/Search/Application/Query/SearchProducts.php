<?php

declare(strict_types=1);

namespace App\Search\Application\Query;

/**
 * SearchProducts Query DTO.
 *
 * Represents a request to search for products.
 */
final readonly class SearchProducts
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public string $tenantId,
        public string $query,
        public string $locale = 'en',
        public int $page = 1,
        public int $perPage = 24,
        public ?string $sortBy = 'relevance',
        public ?string $sortOrder = 'desc',
        public array $filters = [],
    ) {
    }
}
