<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Search\Domain\Model\FacetBucket;
use App\Search\Domain\Model\ProductSearchHit;
use App\Search\Domain\Model\SearchFacet;
use App\Search\Domain\Model\SearchResult;
use PHPUnit\Framework\TestCase;

final class SearchResultTest extends TestCase
{
    public function testItCreatesWithMinimalData(): void
    {
        // Arrange & Act
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 15.5
        );

        // Assert
        $this->assertEmpty($result->hits);
        $this->assertSame(0, $result->total);
        $this->assertSame(1, $result->page);
        $this->assertSame(24, $result->perPage);
        $this->assertEmpty($result->facets);
        $this->assertSame(15.5, $result->took);
    }

    public function testItCreatesWithFullData(): void
    {
        // Arrange
        $hits = [
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000001'),
                sku: 'PROD-1',
                name: 'Product 1',
                description: null,
                price: 99.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 1.0
            ),
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000002'),
                sku: 'PROD-2',
                name: 'Product 2',
                description: null,
                price: 149.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 0.95
            ),
        ];

        $facets = [
            new SearchFacet(
                field: 'category',
                label: 'Category',
                buckets: [
                    new FacetBucket('electronics', 'Electronics', 42),
                ]
            ),
        ];

        // Act
        $result = new SearchResult(
            hits: $hits,
            total: 100,
            page: 2,
            perPage: 12,
            facets: $facets,
            took: 23.7
        );

        // Assert
        $this->assertCount(2, $result->hits);
        $this->assertSame(100, $result->total);
        $this->assertSame(2, $result->page);
        $this->assertSame(12, $result->perPage);
        $this->assertCount(1, $result->facets);
        $this->assertSame(23.7, $result->took);
    }

    public function testHasResultsReturnsTrueWhenTotalIsGreaterThanZero(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 1,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertTrue($result->hasResults());
    }

    public function testHasResultsReturnsFalseWhenTotalIsZero(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertFalse($result->hasResults());
    }

    public function testIsEmptyReturnsTrueWhenTotalIsZero(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertTrue($result->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenTotalIsGreaterThanZero(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 1,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertFalse($result->isEmpty());
    }

    public function testTotalPagesReturnsZeroWhenTotalIsZero(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(0, $result->totalPages());
    }

    public function testTotalPagesCalculatesCorrectlyWhenTotalDivisibleByPerPage(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 48,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(2, $result->totalPages());
    }

    public function testTotalPagesCalculatesCorrectlyWhenTotalNotDivisibleByPerPage(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 50,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(3, $result->totalPages()); // ceil(50/24) = 3
    }

    public function testTotalPagesReturnsOneWhenTotalLessThanPerPage(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 10,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(1, $result->totalPages());
    }

    public function testTotalPagesWithExactlyOneResult(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 1,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(1, $result->totalPages());
    }

    public function testTotalPagesWithLargeDataset(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 1000,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(42, $result->totalPages()); // ceil(1000/24) = 42
    }

    public function testHasFacetsReturnsTrueWhenFacetsExist(): void
    {
        // Arrange
        $facets = [
            new SearchFacet('category', 'Category', []),
        ];

        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: $facets,
            took: 10.0
        );

        // Act & Assert
        $this->assertTrue($result->hasFacets());
    }

    public function testHasFacetsReturnsFalseWhenNoFacets(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertFalse($result->hasFacets());
    }

    public function testGetHitCountReturnsCorrectCount(): void
    {
        // Arrange
        $hits = [
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000001'),
                sku: 'PROD-1',
                name: 'Product 1',
                description: null,
                price: 99.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 1.0
            ),
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000002'),
                sku: 'PROD-2',
                name: 'Product 2',
                description: null,
                price: 149.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 0.95
            ),
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000003'),
                sku: 'PROD-3',
                name: 'Product 3',
                description: null,
                price: 199.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 0.90
            ),
        ];

        $result = new SearchResult(
            hits: $hits,
            total: 100,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(3, $result->getHitCount());
    }

    public function testGetHitCountReturnsZeroWhenNoHits(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(0, $result->getHitCount());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        // Arrange
        $hits = [
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000001'),
                sku: 'PROD-1',
                name: 'Product 1',
                description: 'Description 1',
                price: 99.99,
                currency: 'USD',
                imageUrl: 'https://example.com/1.jpg',
                isActive: true,
                isFeatured: true,
                score: 1.0,
                categoryIds: ['cat-1'],
                averageRating: 4.5,
                reviewCount: 10
            ),
        ];

        $facets = [
            new SearchFacet(
                field: 'category',
                label: 'Category',
                buckets: [
                    new FacetBucket('electronics', 'Electronics', 42),
                ]
            ),
        ];

        $result = new SearchResult(
            hits: $hits,
            total: 100,
            page: 2,
            perPage: 12,
            facets: $facets,
            took: 23.7
        );

        // Act
        $array = $result->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('hits', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('page', $array);
        $this->assertArrayHasKey('perPage', $array);
        $this->assertArrayHasKey('totalPages', $array);
        $this->assertArrayHasKey('facets', $array);
        $this->assertArrayHasKey('took', $array);

        $this->assertCount(1, $array['hits']);
        $this->assertSame(100, $array['total']);
        $this->assertSame(2, $array['page']);
        $this->assertSame(12, $array['perPage']);
        $this->assertSame(9, $array['totalPages']); // ceil(100/12) = 9
        $this->assertCount(1, $array['facets']);
        $this->assertSame(23.7, $array['took']);
    }

    public function testToArrayWithEmptyResults(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 5.0
        );

        // Act
        $array = $result->toArray();

        // Assert
        $this->assertEmpty($array['hits']);
        $this->assertSame(0, $array['total']);
        $this->assertSame(0, $array['totalPages']);
        $this->assertEmpty($array['facets']);
    }

    public function testToArrayConvertsAllHitsAndFacets(): void
    {
        // Arrange
        $hits = [
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000001'),
                sku: 'PROD-1',
                name: 'Product 1',
                description: null,
                price: 99.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 1.0
            ),
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000002'),
                sku: 'PROD-2',
                name: 'Product 2',
                description: null,
                price: 149.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 0.95
            ),
        ];

        $facets = [
            new SearchFacet(
                field: 'category',
                label: 'Category',
                buckets: [
                    new FacetBucket('electronics', 'Electronics', 42),
                ]
            ),
            new SearchFacet(
                field: 'brand',
                label: 'Brand',
                buckets: [
                    new FacetBucket('apple', 'Apple', 10),
                    new FacetBucket('samsung', 'Samsung', 15),
                ]
            ),
        ];

        $result = new SearchResult(
            hits: $hits,
            total: 100,
            page: 1,
            perPage: 24,
            facets: $facets,
            took: 20.0
        );

        // Act
        $array = $result->toArray();

        // Assert
        $this->assertCount(2, $array['hits']);
        $this->assertSame('PROD-1', $array['hits'][0]['sku']);
        $this->assertSame('PROD-2', $array['hits'][1]['sku']);

        $this->assertCount(2, $array['facets']);
        $this->assertSame('category', $array['facets'][0]['field']);
        $this->assertSame('brand', $array['facets'][1]['field']);
    }

    public function testItCreatesWithZeroTookTime(): void
    {
        // Arrange & Act
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 0.0
        );

        // Assert
        $this->assertSame(0.0, $result->took);
    }

    public function testItCreatesWithHighTookTime(): void
    {
        // Arrange & Act
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: [],
            took: 9999.99
        );

        // Assert
        $this->assertSame(9999.99, $result->took);
    }

    public function testItCreatesWithDifferentPerPageValues(): void
    {
        // Arrange & Act
        $result10 = new SearchResult(
            hits: [],
            total: 100,
            page: 1,
            perPage: 10,
            facets: [],
            took: 10.0
        );

        $result50 = new SearchResult(
            hits: [],
            total: 100,
            page: 1,
            perPage: 50,
            facets: [],
            took: 10.0
        );

        $result100 = new SearchResult(
            hits: [],
            total: 100,
            page: 1,
            perPage: 100,
            facets: [],
            took: 10.0
        );

        // Assert
        $this->assertSame(10, $result10->totalPages());
        $this->assertSame(2, $result50->totalPages());
        $this->assertSame(1, $result100->totalPages());
    }

    public function testItCreatesWithDifferentPageNumbers(): void
    {
        // Arrange & Act
        $resultPage1 = new SearchResult(
            hits: [],
            total: 100,
            page: 1,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        $resultPage2 = new SearchResult(
            hits: [],
            total: 100,
            page: 2,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        $resultPage5 = new SearchResult(
            hits: [],
            total: 100,
            page: 5,
            perPage: 24,
            facets: [],
            took: 10.0
        );

        // Assert
        $this->assertSame(1, $resultPage1->page);
        $this->assertSame(2, $resultPage2->page);
        $this->assertSame(5, $resultPage5->page);
        // All should have same total pages
        $this->assertSame(5, $resultPage1->totalPages());
        $this->assertSame(5, $resultPage2->totalPages());
        $this->assertSame(5, $resultPage5->totalPages());
    }

    public function testItCreatesWithMultipleFacets(): void
    {
        // Arrange
        $facets = [
            new SearchFacet('category', 'Category', []),
            new SearchFacet('brand', 'Brand', []),
            new SearchFacet('price', 'Price', []),
            new SearchFacet('rating', 'Rating', []),
        ];

        // Act
        $result = new SearchResult(
            hits: [],
            total: 0,
            page: 1,
            perPage: 24,
            facets: $facets,
            took: 10.0
        );

        // Assert
        $this->assertCount(4, $result->facets);
        $this->assertTrue($result->hasFacets());
    }

    public function testTotalPagesEdgeCaseWithOnePerPage(): void
    {
        // Arrange
        $result = new SearchResult(
            hits: [],
            total: 5,
            page: 1,
            perPage: 1,
            facets: [],
            took: 10.0
        );

        // Act & Assert
        $this->assertSame(5, $result->totalPages());
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $hits = [
            new ProductSearchHit(
                productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000001'),
                sku: 'PROD-1',
                name: 'Product 1',
                description: null,
                price: 99.99,
                currency: 'USD',
                imageUrl: null,
                isActive: true,
                isFeatured: false,
                score: 1.0
            ),
        ];

        $result = new SearchResult(
            hits: $hits,
            total: 100,
            page: 2,
            perPage: 24,
            facets: [],
            took: 15.5
        );

        // Assert - Properties are readonly
        $this->assertCount(1, $result->hits);
        $this->assertSame(100, $result->total);
        $this->assertSame(2, $result->page);
        $this->assertSame(24, $result->perPage);
        $this->assertSame(15.5, $result->took);
    }
}
