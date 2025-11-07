<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Analytics;

use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use App\Catalog\Application\Query\SearchProducts\SearchProductsResult;
use App\Catalog\Domain\Model\SearchQuery;
use App\Catalog\Domain\Model\SearchQueryId;
use Psr\Log\LoggerInterface;

/**
 * Service for logging search queries for analytics.
 *
 * Features:
 * - Log all search queries with metadata
 * - Track performance metrics
 * - Identify zero-results queries
 * - Support for future analytics aggregation
 */
final readonly class SearchQueryLogger
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function logSearch(
        SearchProductsQuery $query,
        SearchProductsResult $result,
        int $responseTimeMs
    ): SearchQuery {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: $query->tenantId,
            locale: $query->locale,
            queryText: $query->query ?? '',
            resultsCount: $result->total,
            responseTimeMs: $responseTimeMs,
            filters: $this->buildFiltersArray($query),
            sortBy: $query->sortBy
        );

        // Log for monitoring and analytics
        $context = [
            'search_query_id' => $searchQuery->id()->toString(),
            'tenant_id' => $searchQuery->tenantId()->toString(),
            'locale' => $searchQuery->locale()->toString(),
            'query_text' => $searchQuery->queryText(),
            'results_count' => $searchQuery->resultsCount(),
            'response_time_ms' => $searchQuery->responseTimeMs(),
            'has_filters' => $searchQuery->hasFilters(),
            'sort_by' => $searchQuery->sortBy(),
            'is_zero_results' => $searchQuery->isZeroResults(),
            'is_slow' => $searchQuery->isSlow(),
        ];

        // Different log levels based on outcomes
        if ($searchQuery->isZeroResults()) {
            $this->logger->warning('Search returned zero results', $context);
        } elseif ($searchQuery->isSlow()) {
            $this->logger->warning('Slow search query detected', $context);
        } else {
            $this->logger->info('Search query executed successfully', $context);
        }

        return $searchQuery;
    }

    /**
     * Build filters array from query for analytics.
     *
     * @return array<string, mixed>|null
     */
    private function buildFiltersArray(SearchProductsQuery $query): ?array
    {
        $filters = [];

        if (null !== $query->categoryIds && count($query->categoryIds) > 0) {
            $filters['category_ids'] = $query->categoryIds;
        }

        if (null !== $query->minPrice) {
            $filters['min_price'] = $query->minPrice;
        }

        if (null !== $query->maxPrice) {
            $filters['max_price'] = $query->maxPrice;
        }

        if (null !== $query->status) {
            $filters['status'] = $query->status;
        }

        return count($filters) > 0 ? $filters : null;
    }

    /**
     * Log autocomplete query (simpler version).
     */
    public function logAutocomplete(
        string $tenantId,
        string $locale,
        string $query,
        int $suggestionsCount,
        int $responseTimeMs
    ): void {
        $this->logger->info('Autocomplete query executed', [
            'tenant_id' => $tenantId,
            'locale' => $locale,
            'query' => $query,
            'suggestions_count' => $suggestionsCount,
            'response_time_ms' => $responseTimeMs,
        ]);
    }
}
