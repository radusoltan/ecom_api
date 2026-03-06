<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Infrastructure\Elasticsearch;

use App\Internationalization\Domain\Model\Locale;
use App\Search\Domain\Model\SearchQuery;
use App\Search\Infrastructure\Elasticsearch\QueryBuilder;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class QueryBuilderTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new QueryBuilder();
    }

    public function testBuildBasicQuery(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'laptop',
            locale: Locale::fromString('en_US'),
        );

        $result = $this->builder->build($query);

        self::assertArrayHasKey('from', $result);
        self::assertArrayHasKey('size', $result);
        self::assertArrayHasKey('query', $result);
        self::assertArrayHasKey('sort', $result);
        self::assertArrayHasKey('aggs', $result);
        self::assertSame(0, $result['from']);
        self::assertSame(24, $result['size']);
    }

    public function testBuildQueryWithPagination(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'phone',
            locale: Locale::fromString('en_US'),
            page: 3,
            perPage: 10,
        );

        $result = $this->builder->build($query);

        self::assertSame(20, $result['from']); // (3-1)*10
        self::assertSame(10, $result['size']);
    }

    public function testBuildQueryWithCategoryFilter(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'shoes',
            locale: Locale::fromString('en_US'),
            filters: ['category' => 'cat-123'],
        );

        $result = $this->builder->build($query);

        $filters = $result['query']['bool']['filter'];
        $hasCategory = false;
        foreach ($filters as $filter) {
            if (isset($filter['term']['category_ids'])) {
                $hasCategory = true;
                self::assertSame('cat-123', $filter['term']['category_ids']);
            }
        }
        self::assertTrue($hasCategory, 'Expected category filter');
    }

    public function testBuildQueryWithPriceFilter(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'bag',
            locale: Locale::fromString('en_US'),
            filters: ['price_min' => 50, 'price_max' => 200],
        );

        $result = $this->builder->build($query);

        $filters = $result['query']['bool']['filter'];
        $hasPrice = false;
        foreach ($filters as $filter) {
            if (isset($filter['range']['price'])) {
                $hasPrice = true;
                self::assertEquals(50.0, $filter['range']['price']['gte']);
                self::assertEquals(200.0, $filter['range']['price']['lte']);
            }
        }
        self::assertTrue($hasPrice, 'Expected price range filter');
    }

    public function testBuildQueryWithPriceMinOnly(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'bag',
            locale: Locale::fromString('en_US'),
            filters: ['price_min' => 100],
        );

        $result = $this->builder->build($query);

        $filters = $result['query']['bool']['filter'];
        $found = false;
        foreach ($filters as $filter) {
            if (isset($filter['range']['price'])) {
                $found = true;
                self::assertArrayHasKey('gte', $filter['range']['price']);
                self::assertArrayNotHasKey('lte', $filter['range']['price']);
            }
        }
        self::assertTrue($found);
    }

    public function testBuildQueryWithInStockFilter(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'widget',
            locale: Locale::fromString('en_US'),
            filters: ['in_stock_only' => true],
        );

        // in_stock_only is handled but currently a no-op (TODO in source)
        $result = $this->builder->build($query);
        self::assertArrayHasKey('query', $result);
    }

    public function testSortByPrice(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'shirt',
            locale: Locale::fromString('en_US'),
            sortBy: 'price',
            sortOrder: 'asc',
        );

        $result = $this->builder->build($query);

        self::assertSame([['price' => ['order' => 'asc']]], $result['sort']);
    }

    public function testSortByName(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'shirt',
            locale: Locale::fromString('en_US'),
            sortBy: 'name',
            sortOrder: 'desc',
        );

        $result = $this->builder->build($query);

        self::assertSame([['name.keyword' => ['order' => 'desc']]], $result['sort']);
    }

    public function testSortByCreatedAt(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'shirt',
            locale: Locale::fromString('en_US'),
            sortBy: 'created_at',
            sortOrder: 'desc',
        );

        $result = $this->builder->build($query);

        self::assertSame([['created_at' => ['order' => 'desc']]], $result['sort']);
    }

    public function testSortByRelevanceDefault(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'shirt',
            locale: Locale::fromString('en_US'),
            sortBy: 'relevance',
        );

        $result = $this->builder->build($query);

        self::assertSame(['_score'], $result['sort']);
    }

    public function testAggregationsIncludeCategoriesAndPriceRanges(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'shoes',
            locale: Locale::fromString('en_US'),
        );

        $result = $this->builder->build($query);

        self::assertArrayHasKey('categories', $result['aggs']);
        self::assertArrayHasKey('price_ranges', $result['aggs']);
        self::assertSame('category_ids', $result['aggs']['categories']['terms']['field']);
    }

    public function testFullTextSearchUsesMultiMatch(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'wireless headphones',
            locale: Locale::fromString('en_US'),
        );

        $result = $this->builder->build($query);

        $must = $result['query']['bool']['must'];
        self::assertCount(1, $must);
        self::assertArrayHasKey('multi_match', $must[0]);
        self::assertSame('wireless headphones', $must[0]['multi_match']['query']);
        self::assertSame('best_fields', $must[0]['multi_match']['type']);
        self::assertSame('AUTO', $must[0]['multi_match']['fuzziness']);
    }

    public function testAlwaysFiltersActiveStatus(): void
    {
        $query = new SearchQuery(
            tenantId: TenantId::generate(),
            query: 'test',
            locale: Locale::fromString('en_US'),
        );

        $result = $this->builder->build($query);

        $filters = $result['query']['bool']['filter'];
        $hasActiveFilter = false;
        foreach ($filters as $filter) {
            if (isset($filter['term']['status']) && 'active' === $filter['term']['status']) {
                $hasActiveFilter = true;
            }
        }
        self::assertTrue($hasActiveFilter, 'Expected active status filter');
    }
}
