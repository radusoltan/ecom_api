<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Elasticsearch;

use App\Catalog\Domain\Model\ProductId;
use App\Internationalization\Domain\Model\Locale;
use App\Search\Domain\Model\FacetBucket;
use App\Search\Domain\Model\ProductSearchHit;
use App\Search\Domain\Model\SearchFacet;
use App\Search\Domain\Model\SearchQuery;
use App\Search\Domain\Model\SearchResult;
use App\Search\Domain\Service\SearchServiceInterface;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Psr\Log\LoggerInterface;

/**
 * ElasticsearchSearchService.
 *
 * Elasticsearch adapter implementing SearchServiceInterface.
 */
final readonly class ElasticsearchSearchService implements SearchServiceInterface
{
    public function __construct(
        private Client $client,
        private IndexManager $indexManager,
        private QueryBuilder $queryBuilder,
        private LoggerInterface $logger,
    ) {
    }

    public function search(SearchQuery $query): SearchResult
    {
        $indexName = $this->indexManager->getProductIndexName($query->tenantId, $query->locale);

        // Check if index exists
        if (!$this->indexManager->indexExists($indexName)) {
            $this->logger->warning('Search index does not exist', [
                'index' => $indexName,
                'tenant_id' => $query->tenantId->toString(),
                'locale' => $query->locale->toString(),
            ]);

            return new SearchResult(
                hits: [],
                total: 0,
                page: $query->page,
                perPage: $query->perPage,
                facets: [],
                took: 0.0,
            );
        }

        $params = [
            'index' => $indexName,
            'body' => $this->queryBuilder->build($query),
        ];

        try {
            $startTime = microtime(true);
            $response = $this->client->search($params);
            $took = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            return $this->mapResponse($response->asArray(), $query, $took);
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logger->error('Elasticsearch search error', [
                'exception' => $e->getMessage(),
                'query' => $query->query,
                'tenant_id' => $query->tenantId->toString(),
            ]);

            // Return empty result on error (graceful degradation)
            return new SearchResult(
                hits: [],
                total: 0,
                page: $query->page,
                perPage: $query->perPage,
                facets: [],
                took: 0.0,
            );
        }
    }

    /**
     * @return array<ProductSearchHit>
     */
    public function autocomplete(string $query, string $tenantId, string $locale, int $limit = 5): array
    {
        $tenantIdVO = TenantId::fromString($tenantId);
        $localeVO = Locale::fromString($locale);
        $indexName = $this->indexManager->getProductIndexName($tenantIdVO, $localeVO);

        if (!$this->indexManager->indexExists($indexName)) {
            return [];
        }

        $params = [
            'index' => $indexName,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $query,
                                    'fields' => ['name^3', 'sku^2'],
                                    'type' => 'bool_prefix',
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                        ],
                        'filter' => [
                            ['term' => ['status' => 'active']],
                        ],
                    ],
                ],
                'size' => $limit,
                '_source' => ['id', 'sku', 'name', 'description', 'price', 'currency', 'image_url', 'is_featured'],
            ],
        ];

        try {
            $response = $this->client->search($params);
            $hits = $response->asArray()['hits']['hits'] ?? [];

            return array_map(fn (array $hit) => $this->mapHit($hit), $hits);
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logger->error('Elasticsearch autocomplete error', [
                'exception' => $e->getMessage(),
                'query' => $query,
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function mapResponse(array $response, SearchQuery $query, float $took): SearchResult
    {
        $hits = array_map(
            fn (array $hit) => $this->mapHit($hit),
            $response['hits']['hits'] ?? []
        );

        $total = $response['hits']['total']['value'] ?? 0;

        $facets = $this->mapFacets(
            $response['aggregations'] ?? [],
            $query
        );

        return new SearchResult(
            hits: $hits,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            facets: $facets,
            took: $took,
        );
    }

    /**
     * @param array<string, mixed> $hit
     */
    private function mapHit(array $hit): ProductSearchHit
    {
        $source = $hit['_source'];

        return new ProductSearchHit(
            productId: ProductId::fromString($source['id']),
            sku: $source['sku'],
            name: $source['name'],
            description: $source['description'] ?? null,
            price: (float) $source['price'],
            currency: $source['currency'],
            imageUrl: $source['image_url'] ?? null,
            isActive: 'active' === $source['status'],
            isFeatured: $source['is_featured'] ?? false,
            score: $hit['_score'] ?? 0.0,
            categoryIds: $source['category_ids'] ?? [],
            averageRating: $source['average_rating'] ?? null,
            reviewCount: $source['review_count'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<SearchFacet>
     */
    private function mapFacets(array $aggregations, SearchQuery $query): array
    {
        $facets = [];

        // Category facet
        if (isset($aggregations['categories']['buckets'])) {
            $buckets = array_map(
                fn (array $bucket) => new FacetBucket(
                    key: $bucket['key'],
                    label: $bucket['key'], // TODO: Load actual category names
                    count: $bucket['doc_count'],
                    selected: $query->hasCategoryFilter() && $query->filters['category'] === $bucket['key'],
                ),
                $aggregations['categories']['buckets']
            );

            $facets[] = new SearchFacet(
                field: 'category',
                label: 'Categories',
                buckets: $buckets,
            );
        }

        // Price range facet
        if (isset($aggregations['price_ranges']['buckets'])) {
            $priceLabels = [
                'under_50' => 'Under $50',
                '50_to_100' => '$50 - $100',
                '100_to_200' => '$100 - $200',
                'over_200' => 'Over $200',
            ];

            $buckets = array_map(
                fn (array $bucket) => new FacetBucket(
                    key: $bucket['key'],
                    label: $priceLabels[$bucket['key']] ?? $bucket['key'],
                    count: $bucket['doc_count'],
                    selected: false, // TODO: Check if price range selected
                ),
                $aggregations['price_ranges']['buckets']
            );

            $facets[] = new SearchFacet(
                field: 'price',
                label: 'Price Range',
                buckets: $buckets,
            );
        }

        return $facets;
    }
}
