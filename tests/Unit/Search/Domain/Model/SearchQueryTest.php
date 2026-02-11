<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Domain\Model;

use App\Internationalization\Domain\Model\Locale;
use App\Search\Domain\Model\SearchQuery;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class SearchQueryTest extends TestCase
{
    private TenantId $tenantId;
    private Locale $locale;

    protected function setUp(): void
    {
        $this->tenantId = TenantId::fromString('018c6e60-e270-7e43-9f19-000000000001');
        $this->locale = Locale::fromString('en_US');
    }

    public function testItCreatesWithMinimalData(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'laptop',
            locale: $this->locale
        );

        // Assert
        $this->assertTrue($this->tenantId->equals($query->tenantId));
        $this->assertSame('laptop', $query->query);
        $this->assertTrue($this->locale->equals($query->locale));
        $this->assertSame(1, $query->page);
        $this->assertSame(24, $query->perPage);
        $this->assertSame('relevance', $query->sortBy);
        $this->assertSame('desc', $query->sortOrder);
        $this->assertEmpty($query->filters);
    }

    public function testItCreatesWithFullData(): void
    {
        // Arrange
        $filters = [
            'category' => 'electronics',
            'price_min' => 100.0,
            'price_max' => 500.0,
            'brand' => ['apple', 'samsung'],
            'in_stock_only' => true,
        ];

        // Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'smartphone',
            locale: $this->locale,
            page: 2,
            perPage: 12,
            sortBy: 'price',
            sortOrder: 'asc',
            filters: $filters
        );

        // Assert
        $this->assertSame('smartphone', $query->query);
        $this->assertSame(2, $query->page);
        $this->assertSame(12, $query->perPage);
        $this->assertSame('price', $query->sortBy);
        $this->assertSame('asc', $query->sortOrder);
        $this->assertSame($filters, $query->filters);
    }

    public function testItThrowsExceptionWhenQueryIsEmpty(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: '',
            locale: $this->locale
        );
    }

    public function testItThrowsExceptionWhenQueryIsOnlyWhitespace(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: '   ',
            locale: $this->locale
        );
    }

    public function testItAcceptsSingleCharacterQuery(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'a',
            locale: $this->locale
        );

        // Assert
        $this->assertSame('a', $query->query);
    }

    public function testItThrowsExceptionWhenPageIsZero(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be >= 1');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            page: 0
        );
    }

    public function testItThrowsExceptionWhenPageIsNegative(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page must be >= 1');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            page: -1
        );
    }

    public function testItThrowsExceptionWhenPerPageIsZero(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Per page must be between 1 and 100');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            perPage: 0
        );
    }

    public function testItThrowsExceptionWhenPerPageIsNegative(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Per page must be between 1 and 100');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            perPage: -10
        );
    }

    public function testItThrowsExceptionWhenPerPageExceeds100(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Per page must be between 1 and 100');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            perPage: 101
        );
    }

    public function testItAcceptsPerPageOf1(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            perPage: 1
        );

        // Assert
        $this->assertSame(1, $query->perPage);
    }

    public function testItAcceptsPerPageOf100(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            perPage: 100
        );

        // Assert
        $this->assertSame(100, $query->perPage);
    }

    public function testItAcceptsValidSortByRelevance(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortBy: 'relevance'
        );

        // Assert
        $this->assertSame('relevance', $query->sortBy);
    }

    public function testItAcceptsValidSortByPrice(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortBy: 'price'
        );

        // Assert
        $this->assertSame('price', $query->sortBy);
    }

    public function testItAcceptsValidSortByName(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortBy: 'name'
        );

        // Assert
        $this->assertSame('name', $query->sortBy);
    }

    public function testItAcceptsValidSortByCreatedAt(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortBy: 'created_at'
        );

        // Assert
        $this->assertSame('created_at', $query->sortBy);
    }

    public function testItThrowsExceptionForInvalidSortBy(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort field: invalid. Allowed: relevance, price, name, created_at');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortBy: 'invalid'
        );
    }

    public function testItAcceptsNullSortBy(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortBy: null
        );

        // Assert
        $this->assertNull($query->sortBy);
    }

    public function testItAcceptsSortOrderAsc(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortOrder: 'asc'
        );

        // Assert
        $this->assertSame('asc', $query->sortOrder);
    }

    public function testItAcceptsSortOrderDesc(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortOrder: 'desc'
        );

        // Assert
        $this->assertSame('desc', $query->sortOrder);
    }

    public function testItThrowsExceptionForInvalidSortOrder(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sort order must be "asc" or "desc"');

        // Act
        new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortOrder: 'invalid'
        );
    }

    public function testItAcceptsNullSortOrder(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            sortOrder: null
        );

        // Assert
        $this->assertNull($query->sortOrder);
    }

    public function testGetOffsetReturnsZeroForFirstPage(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            page: 1,
            perPage: 24
        );

        // Act & Assert
        $this->assertSame(0, $query->getOffset());
    }

    public function testGetOffsetReturnsCorrectValueForSecondPage(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            page: 2,
            perPage: 24
        );

        // Act & Assert
        $this->assertSame(24, $query->getOffset());
    }

    public function testGetOffsetReturnsCorrectValueForThirdPage(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            page: 3,
            perPage: 24
        );

        // Act & Assert
        $this->assertSame(48, $query->getOffset());
    }

    public function testGetOffsetWithDifferentPerPage(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            page: 5,
            perPage: 10
        );

        // Act & Assert
        $this->assertSame(40, $query->getOffset()); // (5-1) * 10 = 40
    }

    public function testHasFiltersReturnsFalseWhenNoFilters(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale
        );

        // Act & Assert
        $this->assertFalse($query->hasFilters());
    }

    public function testHasFiltersReturnsFalseWhenFiltersEmpty(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: []
        );

        // Act & Assert
        $this->assertFalse($query->hasFilters());
    }

    public function testHasFiltersReturnsTrueWhenFiltersPresent(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['category' => 'electronics']
        );

        // Act & Assert
        $this->assertTrue($query->hasFilters());
    }

    public function testHasCategoryFilterReturnsFalseWhenNoFilters(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale
        );

        // Act & Assert
        $this->assertFalse($query->hasCategoryFilter());
    }

    public function testHasCategoryFilterReturnsTrueWhenCategoryPresent(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['category' => 'electronics']
        );

        // Act & Assert
        $this->assertTrue($query->hasCategoryFilter());
    }

    public function testHasCategoryFilterReturnsFalseWhenCategoryEmpty(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['category' => '']
        );

        // Act & Assert
        $this->assertFalse($query->hasCategoryFilter());
    }

    public function testHasPriceFilterReturnsFalseWhenNoFilters(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale
        );

        // Act & Assert
        $this->assertFalse($query->hasPriceFilter());
    }

    public function testHasPriceFilterReturnsTrueWhenPriceMinPresent(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['price_min' => 100.0]
        );

        // Act & Assert
        $this->assertTrue($query->hasPriceFilter());
    }

    public function testHasPriceFilterReturnsTrueWhenPriceMaxPresent(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['price_max' => 500.0]
        );

        // Act & Assert
        $this->assertTrue($query->hasPriceFilter());
    }

    public function testHasPriceFilterReturnsTrueWhenBothPriceMinAndMaxPresent(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['price_min' => 100.0, 'price_max' => 500.0]
        );

        // Act & Assert
        $this->assertTrue($query->hasPriceFilter());
    }

    public function testHasBrandFilterReturnsFalseWhenNoFilters(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale
        );

        // Act & Assert
        $this->assertFalse($query->hasBrandFilter());
    }

    public function testHasBrandFilterReturnsTrueWhenBrandPresent(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['brand' => ['apple']]
        );

        // Act & Assert
        $this->assertTrue($query->hasBrandFilter());
    }

    public function testHasBrandFilterReturnsFalseWhenBrandEmpty(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['brand' => []]
        );

        // Act & Assert
        $this->assertFalse($query->hasBrandFilter());
    }

    public function testHasInStockOnlyFilterReturnsFalseWhenNoFilters(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale
        );

        // Act & Assert
        $this->assertFalse($query->hasInStockOnlyFilter());
    }

    public function testHasInStockOnlyFilterReturnsTrueWhenSetToTrue(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['in_stock_only' => true]
        );

        // Act & Assert
        $this->assertTrue($query->hasInStockOnlyFilter());
    }

    public function testHasInStockOnlyFilterReturnsFalseWhenSetToFalse(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            filters: ['in_stock_only' => false]
        );

        // Act & Assert
        $this->assertFalse($query->hasInStockOnlyFilter());
    }

    public function testItCreatesWithLongQuery(): void
    {
        // Arrange
        $longQuery = str_repeat('laptop ', 100);

        // Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: $longQuery,
            locale: $this->locale
        );

        // Assert
        $this->assertSame($longQuery, $query->query);
    }

    public function testItCreatesWithSpecialCharactersInQuery(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'laptop @#$% & special-chars_123',
            locale: $this->locale
        );

        // Assert
        $this->assertSame('laptop @#$% & special-chars_123', $query->query);
    }

    public function testItCreatesWithUnicodeCharactersInQuery(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'élégant café françàis',
            locale: $this->locale
        );

        // Assert
        $this->assertSame('élégant café françàis', $query->query);
    }

    public function testItCreatesWithDifferentLocales(): void
    {
        // Arrange
        $localeEn = Locale::fromString('en_US');
        $localeFr = Locale::fromString('fr_FR');
        $localeDe = Locale::fromString('de_DE');

        // Act
        $queryEn = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $localeEn
        );

        $queryFr = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $localeFr
        );

        $queryDe = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $localeDe
        );

        // Assert
        $this->assertTrue($localeEn->equals($queryEn->locale));
        $this->assertTrue($localeFr->equals($queryFr->locale));
        $this->assertTrue($localeDe->equals($queryDe->locale));
    }

    public function testItCreatesWithComplexFilters(): void
    {
        // Arrange
        $filters = [
            'category' => 'electronics',
            'price_min' => 100.0,
            'price_max' => 500.0,
            'brand' => ['apple', 'samsung', 'sony'],
            'in_stock_only' => true,
            'rating' => 4,
            'shipping' => 'free',
            'color' => ['black', 'white'],
        ];

        // Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'smartphone',
            locale: $this->locale,
            filters: $filters
        );

        // Assert
        $this->assertSame($filters, $query->filters);
        $this->assertTrue($query->hasFilters());
        $this->assertTrue($query->hasCategoryFilter());
        $this->assertTrue($query->hasPriceFilter());
        $this->assertTrue($query->hasBrandFilter());
        $this->assertTrue($query->hasInStockOnlyFilter());
    }

    public function testItCreatesWithVeryHighPageNumber(): void
    {
        // Arrange & Act
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test',
            locale: $this->locale,
            page: 9999
        );

        // Assert
        $this->assertSame(9999, $query->page);
        $this->assertSame(239952, $query->getOffset()); // (9999-1) * 24
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $query = new SearchQuery(
            tenantId: $this->tenantId,
            query: 'test query',
            locale: $this->locale,
            page: 2,
            perPage: 12,
            sortBy: 'price',
            sortOrder: 'asc',
            filters: ['category' => 'electronics']
        );

        // Assert - Properties are readonly
        $this->assertTrue($this->tenantId->equals($query->tenantId));
        $this->assertSame('test query', $query->query);
        $this->assertTrue($this->locale->equals($query->locale));
        $this->assertSame(2, $query->page);
        $this->assertSame(12, $query->perPage);
        $this->assertSame('price', $query->sortBy);
        $this->assertSame('asc', $query->sortOrder);
        $this->assertSame(['category' => 'electronics'], $query->filters);
    }
}
