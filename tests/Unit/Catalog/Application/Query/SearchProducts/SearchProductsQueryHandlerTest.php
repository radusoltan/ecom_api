<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query\SearchProducts;

use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use App\Catalog\Application\Query\SearchProducts\SearchProductsQueryHandler;
use App\Catalog\Application\Query\SearchProducts\SearchProductsResult;
use App\Catalog\Infrastructure\Analytics\SearchQueryLogger;
use App\Catalog\Infrastructure\Elasticsearch\SearchBoostingService;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchProductsQueryHandler::class)]
final class SearchProductsQueryHandlerTest extends TestCase
{
    private Client $client;
    private IndexManager $indexManager;
    private SearchBoostingService $boostingService;
    private SearchQueryLogger $searchLogger;
    private SearchProductsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->indexManager = $this->createMock(IndexManager::class);
        $this->boostingService = $this->createMock(SearchBoostingService::class);
        $this->searchLogger = $this->createMock(SearchQueryLogger::class);

        $this->handler = new SearchProductsQueryHandler(
            $this->client,
            $this->indexManager,
            $this->boostingService,
            $this->searchLogger,
        );
    }

    private function makeTenantId(): TenantId
    {
        return TenantId::fromString('00000000-0000-4000-8000-000000000001');
    }

    private function makeLocale(): Locale
    {
        return Locale::fromString('en');
    }

    private function makeElasticResponse(array $data): ElasticsearchResponse
    {
        $response = $this->createMock(ElasticsearchResponse::class);
        $response->method('asArray')->willReturn($data);

        return $response;
    }

    // -----------------------------------------------------------------------
    // Index does not exist → return empty result
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyResultWhenIndexDoesNotExist(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(false);

        $this->searchLogger->expects(self::once())->method('logSearch');
        $this->client->expects(self::never())->method('search');

        $result = ($this->handler)($query);

        self::assertInstanceOf(SearchProductsResult::class, $result);
        self::assertSame([], $result->products);
        self::assertSame(0, $result->total);
        self::assertSame([], $result->facets);
        self::assertSame(1, $result->page);
        self::assertSame(20, $result->limit);
    }

    // -----------------------------------------------------------------------
    // Basic search without query text (sortBy = relevance path → boostingService used)
    // -----------------------------------------------------------------------

    #[Test]
    public function itSearchesWithRelevanceSortApplyingFunctionScores(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'relevance',
        );

        $baseQuery = ['bool' => ['must' => [], 'filter' => []]];
        $enhancedQuery = ['function_score' => ['query' => $baseQuery]];

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $this->boostingService->method('applyFunctionScores')->willReturn($enhancedQuery);
        $this->boostingService->method('getFuzzyConfig')->willReturn([
            'fuzziness' => 'AUTO',
            'prefix_length' => 2,
            'max_expansions' => 50,
            'fuzzy_transpositions' => true,
        ]);
        $this->boostingService->method('getFieldBoosts')->willReturn([
            'name' => 3.0,
            'sku' => 2.0,
            'description' => 1.0,
            'category_names' => 1.5,
        ]);

        $esResponse = $this->makeElasticResponse([
            'hits' => ['hits' => [], 'total' => ['value' => 0]],
            'aggregations' => [],
        ]);

        $this->client->expects(self::once())->method('search')->willReturn($esResponse);
        $this->searchLogger->expects(self::once())->method('logSearch');

        $result = ($this->handler)($query);

        self::assertSame(0, $result->total);
        self::assertSame([], $result->products);
    }

    // -----------------------------------------------------------------------
    // Search with hits returned
    // -----------------------------------------------------------------------

    #[Test]
    public function itParsesHitsFromElasticsearchResponse(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
            page: 1,
            limit: 10,
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->makeElasticResponse([
            'hits' => [
                'hits' => [
                    [
                        '_score' => 1.5,
                        '_source' => [
                            'id' => 'prod-1',
                            'tenant_id' => '00000000-0000-4000-8000-000000000001',
                            'sku' => 'SKU-001',
                            'name' => 'Test Product',
                            'description' => 'A great product',
                            'slug' => 'test-product',
                            'price' => 29.99,
                            'currency' => 'USD',
                            'status' => 'active',
                            'category_ids' => ['cat-1'],
                            'image_url' => '/img/prod.jpg',
                            'locale' => 'en',
                            'average_rating' => 4.5,
                            'review_count' => 20,
                            'is_featured' => true,
                        ],
                    ],
                ],
                'total' => ['value' => 1],
            ],
            'aggregations' => [],
        ]);

        $this->client->method('search')->willReturn($esResponse);
        $this->searchLogger->expects(self::once())->method('logSearch');

        $result = ($this->handler)($query);

        self::assertSame(1, $result->total);
        self::assertCount(1, $result->products);
        self::assertSame('prod-1', $result->products[0]->id);
        self::assertSame('SKU-001', $result->products[0]->sku);
        self::assertSame('Test Product', $result->products[0]->name);
        self::assertSame(1, $result->page);
        self::assertSame(10, $result->limit);
    }

    // -----------------------------------------------------------------------
    // Sorting options
    // -----------------------------------------------------------------------

    #[Test]
    public function itBuildsPriceAscSort(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        self::assertSame([['price' => ['order' => 'asc']]], $capturedParams['body']['sort']);
    }

    #[Test]
    public function itBuildsPriceDescSort(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_desc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        self::assertSame([['price' => ['order' => 'desc']]], $capturedParams['body']['sort']);
    }

    #[Test]
    public function itBuildsNewestSort(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'newest',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        self::assertSame([['created_at' => ['order' => 'desc']]], $capturedParams['body']['sort']);
    }

    #[Test]
    public function itBuildsDefaultSortForUnknownSortBy(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'unknown_sort',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        self::assertSame([['_score' => ['order' => 'desc']]], $capturedParams['body']['sort']);
    }

    // -----------------------------------------------------------------------
    // Query filters
    // -----------------------------------------------------------------------

    #[Test]
    public function itAppliesStatusFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            status: 'active',
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $mustClauses = $capturedParams['body']['query']['bool']['must'];
        $hasStatusFilter = false;
        foreach ($mustClauses as $clause) {
            if (isset($clause['term']['status']) && 'active' === $clause['term']['status']) {
                $hasStatusFilter = true;
                break;
            }
        }
        self::assertTrue($hasStatusFilter, 'Status filter should be in must clauses');
    }

    #[Test]
    public function itAppliesCategoryFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            categoryIds: ['cat-1', 'cat-2'],
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $hasCategoryFilter = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['terms']['category_ids'])) {
                $hasCategoryFilter = true;
                self::assertSame(['cat-1', 'cat-2'], $clause['terms']['category_ids']);
                break;
            }
        }
        self::assertTrue($hasCategoryFilter, 'Category filter should be in filter clauses');
    }

    #[Test]
    public function itAppliesPriceRangeFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            minPrice: 10.0,
            maxPrice: 100.0,
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $hasPriceRange = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['range']['price'])) {
                $hasPriceRange = true;
                self::assertSame(10.0, $clause['range']['price']['gte']);
                self::assertSame(100.0, $clause['range']['price']['lte']);
                break;
            }
        }
        self::assertTrue($hasPriceRange, 'Price range filter should be in filter clauses');
    }

    #[Test]
    public function itAppliesMinPriceOnlyFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            minPrice: 50.0,
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $found = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['range']['price']['gte'])) {
                $found = true;
                self::assertArrayNotHasKey('lte', $clause['range']['price']);
                break;
            }
        }
        self::assertTrue($found);
    }

    #[Test]
    public function itAppliesMaxPriceOnlyFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            maxPrice: 200.0,
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $found = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['range']['price']['lte'])) {
                $found = true;
                self::assertArrayNotHasKey('gte', $clause['range']['price']);
                break;
            }
        }
        self::assertTrue($found);
    }

    #[Test]
    public function itAppliesOptionsFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            options: ['color' => ['red', 'blue'], 'size' => ['m']],
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $colorFound = false;
        $sizeFound = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['terms']['options.color'])) {
                $colorFound = true;
            }
            if (isset($clause['terms']['options.size'])) {
                $sizeFound = true;
            }
        }
        self::assertTrue($colorFound, 'Color option filter should be present');
        self::assertTrue($sizeFound, 'Size option filter should be present');
    }

    #[Test]
    public function itAppliesMinRatingFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            minRating: 4,
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $ratingFound = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['range']['average_rating']['gte'])) {
                $ratingFound = true;
                self::assertSame(4, $clause['range']['average_rating']['gte']);
                break;
            }
        }
        self::assertTrue($ratingFound, 'Rating filter should be present');
    }

    #[Test]
    public function itAppliesFeaturedFilter(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            featured: true,
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $featuredFound = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['term']['is_featured']) && true === $clause['term']['is_featured']) {
                $featuredFound = true;
                break;
            }
        }
        self::assertTrue($featuredFound, 'Featured filter should be present');
    }

    #[Test]
    public function itDoesNotApplyFeaturedFilterWhenNull(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            featured: null,
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        foreach ($filterClauses as $clause) {
            self::assertFalse(
                isset($clause['term']['is_featured']),
                'Featured filter should not be present when featured is null'
            );
        }
    }

    // -----------------------------------------------------------------------
    // Full-text search multi_match query
    // -----------------------------------------------------------------------

    #[Test]
    public function itBuildsMultiMatchQueryWhenSearchTermProvided(): void
    {
        $this->boostingService->method('getFuzzyConfig')->willReturn([
            'fuzziness' => 'AUTO',
            'prefix_length' => 2,
            'max_expansions' => 50,
            'fuzzy_transpositions' => true,
        ]);
        $this->boostingService->method('getFieldBoosts')->willReturn([
            'name' => 3.0,
            'sku' => 2.0,
            'description' => 1.0,
            'category_names' => 1.5,
        ]);

        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            query: 'laptop',
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $mustClauses = $capturedParams['body']['query']['bool']['must'];
        $hasMultiMatch = false;
        foreach ($mustClauses as $clause) {
            if (isset($clause['multi_match']) && 'laptop' === $clause['multi_match']['query']) {
                $hasMultiMatch = true;
                self::assertSame('best_fields', $clause['multi_match']['type']);
                self::assertSame('AUTO', $clause['multi_match']['fuzziness']);
                break;
            }
        }
        self::assertTrue($hasMultiMatch, 'Multi-match query should be present for search term');
    }

    #[Test]
    public function itDoesNotBuildMultiMatchForEmptyQuery(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            query: '',
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $mustClauses = $capturedParams['body']['query']['bool']['must'];
        foreach ($mustClauses as $clause) {
            self::assertFalse(
                isset($clause['multi_match']),
                'Multi-match should not be present for empty query'
            );
        }
    }

    #[Test]
    public function itDoesNotBuildMultiMatchForWhitespaceQuery(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            query: '   ',
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $mustClauses = $capturedParams['body']['query']['bool']['must'];
        foreach ($mustClauses as $clause) {
            self::assertFalse(isset($clause['multi_match']));
        }
    }

    // -----------------------------------------------------------------------
    // Pagination
    // -----------------------------------------------------------------------

    #[Test]
    public function itUsesCorrectPaginationOffset(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
            page: 3,
            limit: 20,
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        // page 3, limit 20 → from = (3-1)*20 = 40
        self::assertSame(40, $capturedParams['body']['from']);
        self::assertSame(20, $capturedParams['body']['size']);
    }

    // -----------------------------------------------------------------------
    // Facets parsing
    // -----------------------------------------------------------------------

    #[Test]
    public function itParsesCategoriesFacets(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->makeElasticResponse([
            'hits' => ['hits' => [], 'total' => ['value' => 0]],
            'aggregations' => [
                'categories' => [
                    'buckets' => [
                        ['key' => 'cat-1', 'doc_count' => 10],
                        ['key' => 'cat-2', 'doc_count' => 5],
                    ],
                ],
            ],
        ]);

        $this->client->method('search')->willReturn($esResponse);
        $this->searchLogger->method('logSearch');

        $result = ($this->handler)($query);

        self::assertArrayHasKey('categories', $result->facets);
        self::assertCount(2, $result->facets['categories']);
        self::assertSame('cat-1', $result->facets['categories'][0]['id']);
        self::assertSame(10, $result->facets['categories'][0]['count']);
    }

    #[Test]
    public function itParsesPriceRangesFacets(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->makeElasticResponse([
            'hits' => ['hits' => [], 'total' => ['value' => 0]],
            'aggregations' => [
                'price_ranges' => [
                    'buckets' => [
                        ['key' => 'under_50', 'doc_count' => 30, 'to' => 50.0],
                        ['key' => '50_to_100', 'doc_count' => 20, 'from' => 50.0, 'to' => 100.0],
                    ],
                ],
            ],
        ]);

        $this->client->method('search')->willReturn($esResponse);
        $this->searchLogger->method('logSearch');

        $result = ($this->handler)($query);

        self::assertArrayHasKey('price_ranges', $result->facets);
        self::assertCount(2, $result->facets['price_ranges']);
        self::assertSame('under_50', $result->facets['price_ranges'][0]['key']);
        self::assertSame(30, $result->facets['price_ranges'][0]['count']);
    }

    #[Test]
    public function itParsesPriceStatsFacets(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->makeElasticResponse([
            'hits' => ['hits' => [], 'total' => ['value' => 0]],
            'aggregations' => [
                'price_stats' => [
                    'min' => 10.0,
                    'max' => 500.0,
                    'avg' => 75.5,
                ],
            ],
        ]);

        $this->client->method('search')->willReturn($esResponse);
        $this->searchLogger->method('logSearch');

        $result = ($this->handler)($query);

        self::assertArrayHasKey('price_stats', $result->facets);
        self::assertSame(10.0, $result->facets['price_stats']['min']);
        self::assertSame(500.0, $result->facets['price_stats']['max']);
        self::assertSame(75.5, $result->facets['price_stats']['avg']);
    }

    #[Test]
    public function itHandlesMissingAggregations(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->makeElasticResponse([
            'hits' => ['hits' => [], 'total' => ['value' => 0]],
        ]);

        $this->client->method('search')->willReturn($esResponse);
        $this->searchLogger->method('logSearch');

        $result = ($this->handler)($query);

        self::assertSame([], $result->facets);
    }

    // -----------------------------------------------------------------------
    // Aggregations are always in the query
    // -----------------------------------------------------------------------

    #[Test]
    public function itIncludesAggregationsInSearchParams(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        self::assertArrayHasKey('aggs', $capturedParams['body']);
        self::assertArrayHasKey('categories', $capturedParams['body']['aggs']);
        self::assertArrayHasKey('price_ranges', $capturedParams['body']['aggs']);
        self::assertArrayHasKey('price_stats', $capturedParams['body']['aggs']);
    }

    // -----------------------------------------------------------------------
    // Result page/limit are preserved
    // -----------------------------------------------------------------------

    #[Test]
    public function itPreservesPageAndLimitInResult(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            sortBy: 'price_asc',
            page: 5,
            limit: 50,
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->makeElasticResponse([
            'hits' => ['hits' => [], 'total' => ['value' => 500]],
            'aggregations' => [],
        ]);

        $this->client->method('search')->willReturn($esResponse);
        $this->searchLogger->method('logSearch');

        $result = ($this->handler)($query);

        self::assertSame(5, $result->page);
        self::assertSame(50, $result->limit);
        self::assertSame(500, $result->total);
    }

    // -----------------------------------------------------------------------
    // Options filter with empty values ignored
    // -----------------------------------------------------------------------

    #[Test]
    public function itIgnoresEmptyOptionValues(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            options: ['color' => [], 'size' => ['m', 'l']],
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        $colorFound = false;
        $sizeFound = false;
        foreach ($filterClauses as $clause) {
            if (isset($clause['terms']['options.color'])) {
                $colorFound = true;
            }
            if (isset($clause['terms']['options.size'])) {
                $sizeFound = true;
            }
        }
        // Empty color values should be ignored, size should be present
        self::assertFalse($colorFound, 'Empty color option should be ignored');
        self::assertTrue($sizeFound, 'Non-empty size option should be present');
    }

    // -----------------------------------------------------------------------
    // Zero minRating ignored
    // -----------------------------------------------------------------------

    #[Test]
    public function itIgnoresZeroMinRating(): void
    {
        $query = new SearchProductsQuery(
            tenantId: $this->makeTenantId(),
            locale: $this->makeLocale(),
            minRating: 0,
            sortBy: 'price_asc',
        );

        $this->indexManager->method('getProductIndexName')->willReturn('idx_products_en');
        $this->indexManager->method('indexExists')->willReturn(true);

        $capturedParams = null;
        $this->client->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (array $params) use (&$capturedParams) {
                $capturedParams = $params;

                return $this->makeElasticResponse([
                    'hits' => ['hits' => [], 'total' => ['value' => 0]],
                    'aggregations' => [],
                ]);
            });

        $this->searchLogger->method('logSearch');

        ($this->handler)($query);

        $filterClauses = $capturedParams['body']['query']['bool']['filter'];
        foreach ($filterClauses as $clause) {
            self::assertFalse(
                isset($clause['range']['average_rating']),
                'Rating filter should not be present for minRating=0'
            );
        }
    }
}
