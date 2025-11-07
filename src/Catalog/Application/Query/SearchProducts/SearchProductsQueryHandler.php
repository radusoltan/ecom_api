<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

use App\Catalog\Infrastructure\Analytics\SearchQueryLogger;
use App\Catalog\Infrastructure\Elasticsearch\SearchBoostingService;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchProductsQueryHandler
{
    public function __construct(
        private Client $client,
        private IndexManager $indexManager,
        private SearchBoostingService $boostingService,
        private SearchQueryLogger $searchLogger
    ) {
    }

    public function __invoke(SearchProductsQuery $query): SearchProductsResult
    {
        $startTime = (int) (microtime(true) * 1000);

        $indexName = $this->indexManager->getProductIndexName($query->tenantId, $query->locale);

        // Check if index exists
        if (!$this->indexManager->indexExists($indexName)) {
            $result = new SearchProductsResult(
                products: [],
                total: 0,
                facets: [],
                page: $query->page,
                limit: $query->limit,
            );

            // Log even zero-result searches for analytics
            $responseTime = (int) (microtime(true) * 1000) - $startTime;
            $this->searchLogger->logSearch($query, $result, $responseTime);

            return $result;
        }

        $baseQuery = $this->buildQuery($query);
        $enhancedQuery = 'relevance' === $query->sortBy
            ? $this->boostingService->applyFunctionScores($baseQuery, $query->query)
            : $baseQuery;

        $searchParams = [
            'index' => $indexName,
            'body' => [
                'query' => $enhancedQuery,
                'sort' => $this->buildSort($query->sortBy),
                'from' => ($query->page - 1) * $query->limit,
                'size' => $query->limit,
                'aggs' => $this->buildAggregations(),
            ],
        ];

        $response = $this->client->search($searchParams);
        $result = $response->asArray();

        $parsedResult = $this->parseResponse($result, $query);

        // Log search with performance metrics
        $responseTime = (int) (microtime(true) * 1000) - $startTime;
        $this->searchLogger->logSearch($query, $parsedResult, $responseTime);

        return $parsedResult;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildQuery(SearchProductsQuery $query): array
    {
        $mustClauses = [
            ['term' => ['tenant_id' => $query->tenantId->toString()]],
            ['term' => ['locale' => $query->locale->toString()]],
        ];

        if (null !== $query->status) {
            $mustClauses[] = ['term' => ['status' => $query->status]];
        }

        $filterClauses = [];

        // Category filter
        if (null !== $query->categoryIds && count($query->categoryIds) > 0) {
            $filterClauses[] = ['terms' => ['category_ids' => $query->categoryIds]];
        }

        // Price range filter
        if (null !== $query->minPrice || null !== $query->maxPrice) {
            $rangeQuery = ['range' => ['price' => []]];
            if (null !== $query->minPrice) {
                $rangeQuery['range']['price']['gte'] = $query->minPrice;
            }
            if (null !== $query->maxPrice) {
                $rangeQuery['range']['price']['lte'] = $query->maxPrice;
            }
            $filterClauses[] = $rangeQuery;
        }

        // Product options filter (color, size, etc.)
        if (null !== $query->options && count($query->options) > 0) {
            foreach ($query->options as $optionCode => $optionValues) {
                if (!empty($optionValues)) {
                    // Use "terms" query to match any of the selected values for this option
                    $filterClauses[] = [
                        'terms' => [
                            'options.'.$optionCode => $optionValues,
                        ],
                    ];
                }
            }
        }

        // Rating filter
        if (null !== $query->minRating && $query->minRating > 0) {
            $filterClauses[] = [
                'range' => [
                    'average_rating' => ['gte' => $query->minRating],
                ],
            ];
        }

        // Featured filter
        if (true === $query->featured) {
            $filterClauses[] = ['term' => ['is_featured' => true]];
        }

        $boolQuery = [
            'bool' => [
                'must' => $mustClauses,
                'filter' => $filterClauses,
            ],
        ];

        // Full-text search with enhanced relevance
        if (null !== $query->query && '' !== trim($query->query)) {
            $fuzzyConfig = $this->boostingService->getFuzzyConfig();
            $fieldBoosts = $this->boostingService->getFieldBoosts();

            $boolQuery['bool']['must'][] = [
                'multi_match' => [
                    'query' => $query->query,
                    'fields' => [
                        'name^'.$fieldBoosts['name'],
                        'sku^'.$fieldBoosts['sku'],
                        'description^'.$fieldBoosts['description'],
                        'category_names^'.$fieldBoosts['category_names'],
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => $fuzzyConfig['fuzziness'],
                    'prefix_length' => $fuzzyConfig['prefix_length'],
                    'max_expansions' => $fuzzyConfig['max_expansions'],
                    'fuzzy_transpositions' => $fuzzyConfig['fuzzy_transpositions'],
                    'operator' => 'or',
                    'minimum_should_match' => '75%',
                ],
            ];
        }

        return $boolQuery;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildSort(string $sortBy): array
    {
        return match ($sortBy) {
            'price_asc' => [['price' => ['order' => 'asc']]],
            'price_desc' => [['price' => ['order' => 'desc']]],
            'newest' => [['created_at' => ['order' => 'desc']]],
            'relevance' => [['_score' => ['order' => 'desc']]],
            default => [['_score' => ['order' => 'desc']]],
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
                    'size' => 50,
                ],
            ],
            'price_ranges' => [
                'range' => [
                    'field' => 'price',
                    'ranges' => [
                        ['key' => 'under_50', 'to' => 50],
                        ['key' => '50_to_100', 'from' => 50, 'to' => 100],
                        ['key' => '100_to_200', 'from' => 100, 'to' => 200],
                        ['key' => '200_to_500', 'from' => 200, 'to' => 500],
                        ['key' => 'over_500', 'from' => 500],
                    ],
                ],
            ],
            'price_stats' => [
                'stats' => [
                    'field' => 'price',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseResponse(array $response, SearchProductsQuery $query): SearchProductsResult
    {
        $hits = $response['hits']['hits'] ?? [];
        $total = $response['hits']['total']['value'] ?? 0;
        $aggregations = $response['aggregations'] ?? [];

        $products = array_map(
            fn (array $hit) => ProductSearchDTO::fromElasticsearchHit($hit),
            $hits
        );

        $facets = $this->parseFacets($aggregations);

        return new SearchProductsResult(
            products: $products,
            total: $total,
            facets: $facets,
            page: $query->page,
            limit: $query->limit,
        );
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<string, mixed>
     */
    private function parseFacets(array $aggregations): array
    {
        $facets = [];

        // Categories facet
        if (isset($aggregations['categories']['buckets'])) {
            $facets['categories'] = array_map(
                fn (array $bucket) => [
                    'id' => $bucket['key'],
                    'count' => $bucket['doc_count'],
                ],
                $aggregations['categories']['buckets']
            );
        }

        // Price ranges facet
        if (isset($aggregations['price_ranges']['buckets'])) {
            $facets['price_ranges'] = array_map(
                fn (array $bucket) => [
                    'key' => $bucket['key'],
                    'count' => $bucket['doc_count'],
                    'from' => $bucket['from'] ?? null,
                    'to' => $bucket['to'] ?? null,
                ],
                $aggregations['price_ranges']['buckets']
            );
        }

        // Price stats
        if (isset($aggregations['price_stats'])) {
            $facets['price_stats'] = [
                'min' => $aggregations['price_stats']['min'] ?? 0,
                'max' => $aggregations['price_stats']['max'] ?? 0,
                'avg' => $aggregations['price_stats']['avg'] ?? 0,
            ];
        }

        return $facets;
    }
}
