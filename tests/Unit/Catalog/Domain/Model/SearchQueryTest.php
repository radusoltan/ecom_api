<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Model;

use App\Catalog\Domain\Model\SearchQuery;
use App\Catalog\Domain\Model\SearchQueryId;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    public function testCreate(): void
    {
        $id = SearchQueryId::generate();
        $tenantId = TenantId::generate();
        $locale = Locale::fromString('en_US');

        $searchQuery = SearchQuery::create(
            id: $id,
            tenantId: $tenantId,
            locale: $locale,
            queryText: 'laptop',
            resultsCount: 42,
            responseTimeMs: 85,
            filters: ['category_ids' => ['cat-1']],
            sortBy: 'relevance'
        );

        $this->assertEquals($id, $searchQuery->id());
        $this->assertEquals($tenantId, $searchQuery->tenantId());
        $this->assertEquals($locale, $searchQuery->locale());
        $this->assertEquals('laptop', $searchQuery->queryText());
        $this->assertEquals(42, $searchQuery->resultsCount());
        $this->assertEquals(85, $searchQuery->responseTimeMs());
        $this->assertEquals(['category_ids' => ['cat-1']], $searchQuery->filters());
        $this->assertEquals('relevance', $searchQuery->sortBy());
        $this->assertInstanceOf(\DateTimeImmutable::class, $searchQuery->executedAt());
    }

    public function testCreateTrimsQueryText(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: '  laptop  ',
            resultsCount: 10,
            responseTimeMs: 50
        );

        $this->assertEquals('laptop', $searchQuery->queryText());
    }

    public function testCreateWithoutOptionalParameters(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 5,
            responseTimeMs: 100
        );

        $this->assertNull($searchQuery->filters());
        $this->assertNull($searchQuery->sortBy());
    }

    public function testIsZeroResultsWhenNoResults(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'nonexistent product',
            resultsCount: 0,
            responseTimeMs: 50
        );

        $this->assertTrue($searchQuery->isZeroResults());
    }

    public function testIsZeroResultsWhenHasResults(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'laptop',
            resultsCount: 10,
            responseTimeMs: 50
        );

        $this->assertFalse($searchQuery->isZeroResults());
    }

    public function testIsSlowWhenResponseTimeOver200Ms(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 10,
            responseTimeMs: 250
        );

        $this->assertTrue($searchQuery->isSlow());
    }

    public function testIsSlowWhenResponseTimeUnder200Ms(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 10,
            responseTimeMs: 150
        );

        $this->assertFalse($searchQuery->isSlow());
    }

    public function testIsSlowAtExactly200Ms(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 10,
            responseTimeMs: 200
        );

        $this->assertFalse($searchQuery->isSlow());
    }

    public function testHasFiltersWhenFiltersProvided(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 10,
            responseTimeMs: 50,
            filters: ['min_price' => 100]
        );

        $this->assertTrue($searchQuery->hasFilters());
    }

    public function testHasFiltersWhenNoFilters(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 10,
            responseTimeMs: 50,
            filters: null
        );

        $this->assertFalse($searchQuery->hasFilters());
    }

    public function testHasFiltersWhenEmptyArray(): void
    {
        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 10,
            responseTimeMs: 50,
            filters: []
        );

        $this->assertFalse($searchQuery->hasFilters());
    }

    public function testExecutedAtIsSetAutomatically(): void
    {
        $before = new \DateTimeImmutable();

        $searchQuery = SearchQuery::create(
            id: SearchQueryId::generate(),
            tenantId: TenantId::generate(),
            locale: Locale::fromString('en_US'),
            queryText: 'test',
            resultsCount: 10,
            responseTimeMs: 50
        );

        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $searchQuery->executedAt());
        $this->assertLessThanOrEqual($after, $searchQuery->executedAt());
    }
}
