<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Elasticsearch;

use App\Search\Domain\Model\SearchQuery;

/**
 * QueryBuilder.
 *
 * Builds Elasticsearch query DSL from SearchQuery domain model.
 */
final class QueryBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(SearchQuery $query): array
    {
        return [
            'from' => $query->getOffset(),
            'size' => $query->perPage,
            'query' => $this->buildQuery($query),
            'sort' => $this->buildSort($query),
            'aggs' => $this->buildAggregations(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuery(SearchQuery $query): array
    {
        $mustClauses = [];

        // Full-text search
        $mustClauses[] = [
            'multi_match' => [
                'query' => $query->query,
                'fields' => [
                    'name^3',           // Boost name matches
                    'description',
                    'sku^2',            // Boost SKU matches
                ],
                'type' => 'best_fields',
                'fuzziness' => 'AUTO',  // Handle typos
            ],
        ];

        // Filters
        $filterClauses = $this->buildFilters($query);

        return [
            'bool' => [
                'must' => $mustClauses,
                'filter' => $filterClauses,
            ],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildFilters(SearchQuery $query): array
    {
        $filters = [];

        // Always filter by active status
        $filters[] = ['term' => ['status' => 'active']];

        // Category filter
        if ($query->hasCategoryFilter()) {
            $filters[] = ['term' => ['category_ids' => $query->filters['category']]];
        }

        // Price range filter (values are in major currency units matching ES index)
        if ($query->hasPriceFilter()) {
            $range = [];
            if (isset($query->filters['price_min'])) {
                $range['gte'] = (float) $query->filters['price_min'];
            }
            if (isset($query->filters['price_max'])) {
                $range['lte'] = (float) $query->filters['price_max'];
            }
            if (!empty($range)) {
                $filters[] = ['range' => ['price' => $range]];
            }
        }

        // In stock only filter (future: when stock data indexed)
        if ($query->hasInStockOnlyFilter()) {
            // TODO: Add when stock quantity is indexed
            // $filters[] = ['range' => ['stock_quantity' => ['gt' => 0]]];
        }

        return $filters;
    }

    /**
     * @return array<array<string, mixed>>|array<string>
     */
    private function buildSort(SearchQuery $query): array
    {
        $sortOrder = $query->sortOrder ?? 'desc';

        return match ($query->sortBy) {
            'price' => [['price' => ['order' => $sortOrder]]],
            'name' => [['name.keyword' => ['order' => $sortOrder]]],
            'created_at' => [['created_at' => ['order' => $sortOrder]]],
            default => ['_score'], // relevance (default)
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAggregations(): array
    {
        return [
            'categories' => [
                'terms' => [
                    'field' => 'category_ids',
                    'size' => 20,
                ],
            ],
            'price_ranges' => [
                'range' => [
                    'field' => 'price',
                    'ranges' => [
                        ['to' => 50, 'key' => 'under_50'],
                        ['from' => 50, 'to' => 100, 'key' => '50_to_100'],
                        ['from' => 100, 'to' => 200, 'key' => '100_to_200'],
                        ['from' => 200, 'key' => 'over_200'],
                    ],
                ],
            ],
        ];
    }
}
