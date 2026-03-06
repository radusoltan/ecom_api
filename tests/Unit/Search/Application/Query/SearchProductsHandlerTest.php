<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application\Query;

use App\Internationalization\Domain\Model\Locale;
use App\Search\Application\Query\SearchProducts;
use App\Search\Application\Query\SearchProductsHandler;
use App\Search\Domain\Model\SearchQuery;
use App\Search\Domain\Model\SearchResult;
use App\Search\Domain\Service\SearchServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchProductsHandler::class)]
final class SearchProductsHandlerTest extends TestCase
{
    private SearchServiceInterface $searchService;
    private SearchProductsHandler $handler;

    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchServiceInterface::class);
        $this->handler = new SearchProductsHandler($this->searchService);
    }

    // -------------------------------------------------------
    // Happy path
    // -------------------------------------------------------

    #[Test]
    public function itDelegatesSearchToServiceAndReturnsResult(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $command = new SearchProducts(
            tenantId: $tenantId,
            query: 'laptop',
            locale: 'en_US',
            page: 1,
            perPage: 24,
        );

        $expectedResult = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 5.0,
        );

        $this->searchService
            ->expects(self::once())
            ->method('search')
            ->with(self::callback(function (SearchQuery $query) use ($tenantId): bool {
                return $query->tenantId->toString() === $tenantId
                    && 'laptop' === $query->query
                    && $query->locale->equals(Locale::fromString('en_US'))
                    && 1 === $query->page
                    && 24 === $query->perPage;
            }))
            ->willReturn($expectedResult);

        $result = ($this->handler)($command);

        self::assertSame($expectedResult, $result);
    }

    #[Test]
    public function itForwardsFiltersToSearchService(): void
    {
        $filters = ['category' => 'electronics', 'price_min' => 100.0];
        $command = new SearchProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'smartphone',
            locale: 'en_US',
            filters: $filters,
        );

        $expectedResult = new SearchResult(hits: [], total: 0, page: 1, perPage: 24, facets: [], took: 2.0);

        $this->searchService
            ->expects(self::once())
            ->method('search')
            ->with(self::callback(
                fn (SearchQuery $q) => $q->filters === $filters,
            ))
            ->willReturn($expectedResult);

        ($this->handler)($command);
    }

    #[Test]
    public function itForwardsSortingToSearchService(): void
    {
        $command = new SearchProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'camera',
            locale: 'en_US',
            sortBy: 'price',
            sortOrder: 'asc',
        );

        $expectedResult = new SearchResult(hits: [], total: 0, page: 1, perPage: 24, facets: [], took: 3.0);

        $this->searchService
            ->expects(self::once())
            ->method('search')
            ->with(self::callback(
                fn (SearchQuery $q) => 'price' === $q->sortBy && 'asc' === $q->sortOrder,
            ))
            ->willReturn($expectedResult);

        ($this->handler)($command);
    }

    #[Test]
    public function itForwardsPaginationToSearchService(): void
    {
        $command = new SearchProducts(
            tenantId: '00000000-0000-4000-8000-000000000001',
            query: 'monitor',
            locale: 'en_US',
            page: 3,
            perPage: 12,
        );

        $expectedResult = new SearchResult(hits: [], total: 0, page: 3, perPage: 12, facets: [], took: 1.0);

        $this->searchService
            ->expects(self::once())
            ->method('search')
            ->with(self::callback(
                fn (SearchQuery $q) => 3 === $q->page && 12 === $q->perPage,
            ))
            ->willReturn($expectedResult);

        ($this->handler)($command);
    }
}
