<?php

declare(strict_types=1);

namespace App\Search\Domain\Model;

/**
 * SearchResult Aggregate
 *
 * Encapsulates search results with metadata and facets.
 */
final readonly class SearchResult
{
    /**
     * @param array<ProductSearchHit> $hits
     * @param array<SearchFacet> $facets
     */
    public function __construct(
        public array $hits,
        public int $total,
        public int $page,
        public int $perPage,
        public array $facets,
        public float $took, // milliseconds
    ) {}

    public function hasResults(): bool
    {
        return $this->total > 0;
    }

    public function isEmpty(): bool
    {
        return 0 === $this->total;
    }

    public function totalPages(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function hasFacets(): bool
    {
        return !empty($this->facets);
    }

    public function getHitCount(): int
    {
        return \count($this->hits);
    }

    /**
     * Convert to array for API serialization
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hits' => array_map(fn (ProductSearchHit $hit) => $hit->toArray(), $this->hits),
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalPages' => $this->totalPages(),
            'facets' => array_map(fn (SearchFacet $facet) => $facet->toArray(), $this->facets),
            'took' => $this->took,
        ];
    }
}
