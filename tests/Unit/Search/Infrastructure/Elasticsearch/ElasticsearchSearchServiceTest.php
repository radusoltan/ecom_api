<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Infrastructure\Elasticsearch;

use App\Internationalization\Domain\Model\Locale;
use App\Search\Domain\Model\SearchQuery;
use App\Search\Domain\Model\SearchResult;
use App\Search\Infrastructure\Elasticsearch\ElasticsearchSearchService;
use App\Search\Infrastructure\Elasticsearch\QueryBuilder;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Elasticsearch\IndexManager;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ElasticsearchSearchServiceTest extends TestCase
{
    private Client $client;
    private IndexManager $indexManager;
    private QueryBuilder $queryBuilder;
    private LoggerInterface $logger;
    private ElasticsearchSearchService $service;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->indexManager = $this->createMock(IndexManager::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ElasticsearchSearchService(
            $this->client,
            $this->indexManager,
            $this->queryBuilder,
            $this->logger,
        );
    }

    private function createSearchQuery(array $filters = []): SearchQuery
    {
        return new SearchQuery(
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            query: 'laptop',
            locale: Locale::fromString('en_US'),
            page: 1,
            perPage: 24,
            filters: $filters,
        );
    }

    public function testSearchReturnsEmptyResultWhenIndexDoesNotExist(): void
    {
        $query = $this->createSearchQuery();

        $this->indexManager->method('getProductIndexName')->willReturn('products_tenant_en');
        $this->indexManager->method('indexExists')->willReturn(false);

        $this->logger->expects(self::once())->method('warning');

        $result = $this->service->search($query);

        self::assertInstanceOf(SearchResult::class, $result);
        self::assertSame(0, $result->total);
        self::assertSame([], $result->hits);
        self::assertSame(1, $result->page);
        self::assertSame(24, $result->perPage);
        self::assertSame(0.0, $result->took);
    }

    public function testSearchReturnsMappedResults(): void
    {
        $query = $this->createSearchQuery();

        $this->indexManager->method('getProductIndexName')->willReturn('products_tenant_en');
        $this->indexManager->method('indexExists')->willReturn(true);
        $this->queryBuilder->method('build')->willReturn(['query' => ['match_all' => new \stdClass()]]);

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $esResponse->method('asArray')->willReturn([
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [
                    [
                        '_score' => 1.5,
                        '_source' => [
                            'id' => '00000000-0000-4000-8000-000000000002',
                            'sku' => 'LAPTOP-001',
                            'name' => 'Gaming Laptop',
                            'description' => 'A great laptop',
                            'price' => 999.99,
                            'currency' => 'USD',
                            'image_url' => 'https://example.com/img.jpg',
                            'status' => 'active',
                            'is_featured' => true,
                            'category_ids' => ['cat-1'],
                            'average_rating' => 4.5,
                            'review_count' => 10,
                        ],
                    ],
                ],
            ],
            'aggregations' => [],
        ]);

        $this->client->method('search')->willReturn($esResponse);

        $result = $this->service->search($query);

        self::assertSame(1, $result->total);
        self::assertCount(1, $result->hits);
        self::assertSame('LAPTOP-001', $result->hits[0]->sku);
        self::assertSame('Gaming Laptop', $result->hits[0]->name);
        self::assertSame(99999, $result->hits[0]->priceInCents);
        self::assertTrue($result->hits[0]->isActive);
        self::assertTrue($result->hits[0]->isFeatured);
        self::assertSame(4.5, $result->hits[0]->averageRating);
        self::assertSame(10, $result->hits[0]->reviewCount);
    }

    public function testSearchMapsCategoryFacets(): void
    {
        $query = $this->createSearchQuery(['category' => 'electronics']);

        $this->indexManager->method('getProductIndexName')->willReturn('products_tenant_en');
        $this->indexManager->method('indexExists')->willReturn(true);
        $this->queryBuilder->method('build')->willReturn(['query' => []]);

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $esResponse->method('asArray')->willReturn([
            'hits' => [
                'total' => ['value' => 5],
                'hits' => [],
            ],
            'aggregations' => [
                'categories' => [
                    'buckets' => [
                        ['key' => 'electronics', 'doc_count' => 3],
                        ['key' => 'accessories', 'doc_count' => 2],
                    ],
                ],
            ],
        ]);

        $this->client->method('search')->willReturn($esResponse);

        $result = $this->service->search($query);

        self::assertCount(1, $result->facets);
        self::assertSame('category', $result->facets[0]->field);
        self::assertSame('Categories', $result->facets[0]->label);
        self::assertCount(2, $result->facets[0]->buckets);
        self::assertTrue($result->facets[0]->buckets[0]->selected);
        self::assertFalse($result->facets[0]->buckets[1]->selected);
    }

    public function testSearchMapsPriceRangeFacets(): void
    {
        $query = $this->createSearchQuery();

        $this->indexManager->method('getProductIndexName')->willReturn('products_tenant_en');
        $this->indexManager->method('indexExists')->willReturn(true);
        $this->queryBuilder->method('build')->willReturn(['query' => []]);

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $esResponse->method('asArray')->willReturn([
            'hits' => [
                'total' => ['value' => 0],
                'hits' => [],
            ],
            'aggregations' => [
                'price_ranges' => [
                    'buckets' => [
                        ['key' => 'under_50', 'doc_count' => 10],
                        ['key' => '50_to_100', 'doc_count' => 5],
                        ['key' => 'unknown_range', 'doc_count' => 1],
                    ],
                ],
            ],
        ]);

        $this->client->method('search')->willReturn($esResponse);

        $result = $this->service->search($query);

        self::assertCount(1, $result->facets);
        self::assertSame('price', $result->facets[0]->field);
        self::assertSame('Under $50', $result->facets[0]->buckets[0]->label);
        self::assertSame('$50 - $100', $result->facets[0]->buckets[1]->label);
        self::assertSame('unknown_range', $result->facets[0]->buckets[2]->label);
    }

    public function testSearchReturnsEmptyResultOnClientException(): void
    {
        $query = $this->createSearchQuery();

        $this->indexManager->method('getProductIndexName')->willReturn('products_tenant_en');
        $this->indexManager->method('indexExists')->willReturn(true);
        $this->queryBuilder->method('build')->willReturn(['query' => []]);

        $this->client->method('search')->willThrowException(
            new ClientResponseException('Client error')
        );

        $this->logger->expects(self::once())->method('error');

        $result = $this->service->search($query);

        self::assertSame(0, $result->total);
        self::assertSame([], $result->hits);
    }

    public function testSearchHitWithMissingOptionalFields(): void
    {
        $query = $this->createSearchQuery();

        $this->indexManager->method('getProductIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(true);
        $this->queryBuilder->method('build')->willReturn(['query' => []]);

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $esResponse->method('asArray')->willReturn([
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [
                    [
                        '_source' => [
                            'id' => '00000000-0000-4000-8000-000000000003',
                            'sku' => 'SKU-1',
                            'name' => 'Product',
                            'price' => 10.0,
                            'currency' => 'EUR',
                            'status' => 'inactive',
                        ],
                    ],
                ],
            ],
        ]);

        $this->client->method('search')->willReturn($esResponse);

        $result = $this->service->search($query);

        self::assertCount(1, $result->hits);
        $hit = $result->hits[0];
        self::assertNull($hit->description);
        self::assertNull($hit->imageUrl);
        self::assertFalse($hit->isActive);
        self::assertFalse($hit->isFeatured);
        self::assertSame(0.0, $hit->score);
        self::assertSame([], $hit->categoryIds);
        self::assertNull($hit->averageRating);
        self::assertNull($hit->reviewCount);
    }

    public function testAutocompleteReturnsEmptyWhenIndexNotExists(): void
    {
        $this->indexManager->method('getProductIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(false);

        $result = $this->service->autocomplete('lap', '00000000-0000-4000-8000-000000000001', 'en_US');

        self::assertSame([], $result);
    }

    public function testAutocompleteReturnsMappedHits(): void
    {
        $this->indexManager->method('getProductIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(true);

        $esResponse = $this->createMock(ElasticsearchResponse::class);
        $esResponse->method('asArray')->willReturn([
            'hits' => [
                'hits' => [
                    [
                        '_score' => 2.0,
                        '_source' => [
                            'id' => '00000000-0000-4000-8000-000000000004',
                            'sku' => 'LAP-1',
                            'name' => 'Laptop Pro',
                            'description' => 'Great laptop',
                            'price' => 1299.99,
                            'currency' => 'USD',
                            'image_url' => null,
                            'status' => 'active',
                            'is_featured' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $this->client->method('search')->willReturn($esResponse);

        $result = $this->service->autocomplete('lap', '00000000-0000-4000-8000-000000000001', 'en_US', 5);

        self::assertCount(1, $result);
        self::assertSame('LAP-1', $result[0]->sku);
        self::assertSame('Laptop Pro', $result[0]->name);
    }

    public function testAutocompleteReturnsEmptyOnException(): void
    {
        $this->indexManager->method('getProductIndexName')->willReturn('idx');
        $this->indexManager->method('indexExists')->willReturn(true);

        $this->client->method('search')->willThrowException(
            new ClientResponseException('error')
        );

        $this->logger->expects(self::once())->method('error');

        $result = $this->service->autocomplete('lap', '00000000-0000-4000-8000-000000000001', 'en_US');

        self::assertSame([], $result);
    }
}
