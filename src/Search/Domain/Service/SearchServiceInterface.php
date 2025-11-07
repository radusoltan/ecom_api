<?php

declare(strict_types=1);

namespace App\Search\Domain\Service;

use App\Search\Domain\Model\SearchQuery;
use App\Search\Domain\Model\SearchResult;

/**
 * SearchServiceInterface - Port.
 *
 * Defines the contract for search operations.
 * Implementations (adapters) can use Elasticsearch, Algolia, or any other search engine.
 */
interface SearchServiceInterface
{
    /**
     * Perform full-text search with filters, sort, and pagination.
     */
    public function search(SearchQuery $query): SearchResult;

    /**
     * Get autocomplete suggestions
     * Returns top N product suggestions (typically 5-10).
     *
     * @return array<\App\Search\Domain\Model\ProductSearchHit>
     */
    public function autocomplete(string $query, string $tenantId, string $locale, int $limit = 5): array;
}
