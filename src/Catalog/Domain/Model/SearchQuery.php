<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * SearchQuery aggregate for tracking search analytics.
 *
 * Business Rules:
 * - Track all search queries for analytics
 * - Record query terms, filters, results count
 * - Track response time for performance monitoring
 * - Support tenant isolation
 * - Enable zero-results query analysis
 *
 * Use Cases:
 * - Search performance monitoring
 * - Popular search terms analysis
 * - Zero-results query identification
 * - Search relevance improvement
 */
final class SearchQuery extends AggregateRoot
{
    private function __construct(
        private readonly SearchQueryId $id,
        private readonly TenantId $tenantId,
        private readonly Locale $locale,
        private readonly string $queryText,
        private readonly int $resultsCount,
        private readonly int $responseTimeMs,
        private readonly ?array $filters,
        private readonly ?string $sortBy,
        private readonly \DateTimeImmutable $executedAt
    ) {
    }

    public static function create(
        SearchQueryId $id,
        TenantId $tenantId,
        Locale $locale,
        string $queryText,
        int $resultsCount,
        int $responseTimeMs,
        ?array $filters = null,
        ?string $sortBy = null
    ): self {
        $searchQuery = new self(
            id: $id,
            tenantId: $tenantId,
            locale: $locale,
            queryText: trim($queryText),
            resultsCount: $resultsCount,
            responseTimeMs: $responseTimeMs,
            filters: $filters,
            sortBy: $sortBy,
            executedAt: new \DateTimeImmutable()
        );

        // Domain event could be recorded here if needed for further processing
        // $searchQuery->recordEvent(new SearchQueryExecuted(...));

        return $searchQuery;
    }

    public function id(): SearchQueryId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function queryText(): string
    {
        return $this->queryText;
    }

    public function resultsCount(): int
    {
        return $this->resultsCount;
    }

    public function responseTimeMs(): int
    {
        return $this->responseTimeMs;
    }

    public function filters(): ?array
    {
        return $this->filters;
    }

    public function sortBy(): ?string
    {
        return $this->sortBy;
    }

    public function executedAt(): \DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function isZeroResults(): bool
    {
        return 0 === $this->resultsCount;
    }

    public function isSlow(): bool
    {
        // Queries taking > 200ms are considered slow (PRD Section 9.1)
        return $this->responseTimeMs > 200;
    }

    public function hasFilters(): bool
    {
        return null !== $this->filters && count($this->filters) > 0;
    }
}
