<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Infrastructure\Analytics;

use App\Catalog\Application\Query\SearchProducts\SearchProductsQuery;
use App\Catalog\Application\Query\SearchProducts\SearchProductsResult;
use App\Catalog\Infrastructure\Analytics\SearchQueryLogger;
use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('catalog')]
final class SearchQueryLoggerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private SearchQueryLogger $searchLogger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->searchLogger = new SearchQueryLogger($this->logger);
    }

    public function testLogSearchSuccessfulQuery(): void
    {
        $query = new SearchProductsQuery(
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            locale: Locale::fromString('en_US'),
            query: 'laptop',
        );
        $result = new SearchProductsResult([], 5, [], 1, 20);

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Search query executed successfully', self::isType('array'));

        $searchQuery = $this->searchLogger->logSearch($query, $result, 50);

        self::assertSame('laptop', $searchQuery->queryText());
        self::assertSame(5, $searchQuery->resultsCount());
    }

    public function testLogSearchZeroResults(): void
    {
        $query = new SearchProductsQuery(
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            locale: Locale::fromString('en_US'),
            query: 'nonexistent product xyz',
        );
        $result = new SearchProductsResult([], 0, [], 1, 20);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Search returned zero results', self::isType('array'));

        $searchQuery = $this->searchLogger->logSearch($query, $result, 30);

        self::assertTrue($searchQuery->isZeroResults());
    }

    public function testLogSearchSlowQuery(): void
    {
        $query = new SearchProductsQuery(
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            locale: Locale::fromString('en_US'),
            query: 'slow search',
        );
        $result = new SearchProductsResult([], 10, [], 1, 20);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('Slow search query detected', self::isType('array'));

        $searchQuery = $this->searchLogger->logSearch($query, $result, 5000);

        self::assertTrue($searchQuery->isSlow());
    }

    public function testLogSearchWithFilters(): void
    {
        $query = new SearchProductsQuery(
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            locale: Locale::fromString('en_US'),
            query: 'phone',
            categoryIds: ['cat-1', 'cat-2'],
            minPrice: 100.0,
            maxPrice: 500.0,
            status: 'active',
        );
        $result = new SearchProductsResult([], 3, [], 1, 20);

        $searchQuery = $this->searchLogger->logSearch($query, $result, 45);

        self::assertTrue($searchQuery->hasFilters());
    }

    public function testLogSearchNoFilters(): void
    {
        $query = new SearchProductsQuery(
            tenantId: TenantId::fromString('00000000-0000-4000-8000-000000000001'),
            locale: Locale::fromString('en_US'),
            status: null,
        );
        $result = new SearchProductsResult([], 100, [], 1, 20);

        $searchQuery = $this->searchLogger->logSearch($query, $result, 20);

        self::assertFalse($searchQuery->hasFilters());
    }

    public function testLogAutocomplete(): void
    {
        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with('Autocomplete query executed', self::callback(function (array $ctx) {
                return 'lap' === $ctx['query'] && 5 === $ctx['suggestions_count'];
            }));

        $this->searchLogger->logAutocomplete(
            '00000000-0000-4000-8000-000000000001',
            'en_US',
            'lap',
            5,
            15,
        );
    }
}
