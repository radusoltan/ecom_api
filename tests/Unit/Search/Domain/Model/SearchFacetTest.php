<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Domain\Model;

use App\Search\Domain\Model\FacetBucket;
use App\Search\Domain\Model\SearchFacet;
use PHPUnit\Framework\TestCase;

final class SearchFacetTest extends TestCase
{
    public function testItCreatesWithValidData(): void
    {
        // Arrange
        $buckets = [
            new FacetBucket('electronics', 'Electronics', 42),
            new FacetBucket('clothing', 'Clothing', 28),
        ];

        // Act
        $facet = new SearchFacet(
            field: 'category',
            label: 'Category',
            buckets: $buckets
        );

        // Assert
        $this->assertSame('category', $facet->field);
        $this->assertSame('Category', $facet->label);
        $this->assertSame($buckets, $facet->buckets);
        $this->assertCount(2, $facet->buckets);
    }

    public function testItCreatesWithEmptyBuckets(): void
    {
        // Arrange & Act
        $facet = new SearchFacet(
            field: 'brand',
            label: 'Brand',
            buckets: []
        );

        // Assert
        $this->assertSame('brand', $facet->field);
        $this->assertSame('Brand', $facet->label);
        $this->assertEmpty($facet->buckets);
    }

    public function testHasBucketsReturnsTrueWhenBucketsExist(): void
    {
        // Arrange
        $facet = new SearchFacet(
            field: 'price',
            label: 'Price Range',
            buckets: [
                new FacetBucket('0-50', '$0-$50', 15),
                new FacetBucket('50-100', '$50-$100', 30),
            ]
        );

        // Act & Assert
        $this->assertTrue($facet->hasBuckets());
    }

    public function testHasBucketsReturnsFalseWhenNoBuckets(): void
    {
        // Arrange
        $facet = new SearchFacet(
            field: 'empty',
            label: 'Empty',
            buckets: []
        );

        // Act & Assert
        $this->assertFalse($facet->hasBuckets());
    }

    public function testGetBucketCountReturnsCorrectCount(): void
    {
        // Arrange
        $facet = new SearchFacet(
            field: 'category',
            label: 'Category',
            buckets: [
                new FacetBucket('cat1', 'Category 1', 10),
                new FacetBucket('cat2', 'Category 2', 20),
                new FacetBucket('cat3', 'Category 3', 30),
            ]
        );

        // Act & Assert
        $this->assertSame(3, $facet->getBucketCount());
    }

    public function testGetBucketCountReturnsZeroForEmptyBuckets(): void
    {
        // Arrange
        $facet = new SearchFacet(
            field: 'empty',
            label: 'Empty',
            buckets: []
        );

        // Act & Assert
        $this->assertSame(0, $facet->getBucketCount());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        // Arrange
        $buckets = [
            new FacetBucket('electronics', 'Electronics', 42, false),
            new FacetBucket('clothing', 'Clothing', 28, true),
        ];

        $facet = new SearchFacet(
            field: 'category',
            label: 'Category',
            buckets: $buckets
        );

        // Act
        $array = $facet->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('field', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertArrayHasKey('buckets', $array);
        $this->assertSame('category', $array['field']);
        $this->assertSame('Category', $array['label']);
        $this->assertIsArray($array['buckets']);
        $this->assertCount(2, $array['buckets']);
    }

    public function testToArrayWithEmptyBuckets(): void
    {
        // Arrange
        $facet = new SearchFacet(
            field: 'brand',
            label: 'Brand',
            buckets: []
        );

        // Act
        $array = $facet->toArray();

        // Assert
        $this->assertEmpty($array['buckets']);
        $this->assertIsArray($array['buckets']);
    }

    public function testToArrayConvertsAllBuckets(): void
    {
        // Arrange
        $facet = new SearchFacet(
            field: 'price',
            label: 'Price Range',
            buckets: [
                new FacetBucket('0-50', '$0-$50', 15),
                new FacetBucket('50-100', '$50-$100', 30),
                new FacetBucket('100+', '$100+', 45),
            ]
        );

        // Act
        $array = $facet->toArray();

        // Assert
        $this->assertCount(3, $array['buckets']);
        $this->assertSame('0-50', $array['buckets'][0]['key']);
        $this->assertSame('$0-$50', $array['buckets'][0]['label']);
        $this->assertSame(15, $array['buckets'][0]['count']);
        $this->assertSame('50-100', $array['buckets'][1]['key']);
        $this->assertSame('100+', $array['buckets'][2]['key']);
    }

    public function testItCreatesWithSingleBucket(): void
    {
        // Arrange & Act
        $facet = new SearchFacet(
            field: 'in_stock',
            label: 'In Stock',
            buckets: [
                new FacetBucket('true', 'Yes', 100),
            ]
        );

        // Assert
        $this->assertCount(1, $facet->buckets);
        $this->assertTrue($facet->hasBuckets());
        $this->assertSame(1, $facet->getBucketCount());
    }

    public function testItCreatesWithManyBuckets(): void
    {
        // Arrange
        $buckets = [];
        for ($i = 0; $i < 20; ++$i) {
            $buckets[] = new FacetBucket("bucket-{$i}", "Bucket {$i}", $i * 10);
        }

        // Act
        $facet = new SearchFacet(
            field: 'test',
            label: 'Test',
            buckets: $buckets
        );

        // Assert
        $this->assertCount(20, $facet->buckets);
        $this->assertSame(20, $facet->getBucketCount());
        $this->assertTrue($facet->hasBuckets());
    }

    public function testItCreatesWithSpecialCharactersInField(): void
    {
        // Arrange & Act
        $facet = new SearchFacet(
            field: 'product_category_level_1',
            label: 'Category Level 1',
            buckets: []
        );

        // Assert
        $this->assertSame('product_category_level_1', $facet->field);
    }

    public function testItCreatesWithUnicodeCharactersInLabel(): void
    {
        // Arrange & Act
        $facet = new SearchFacet(
            field: 'category',
            label: 'Catégorie de Produits',
            buckets: []
        );

        // Assert
        $this->assertSame('Catégorie de Produits', $facet->label);
    }

    public function testItCreatesWithMixedSelectedBuckets(): void
    {
        // Arrange
        $buckets = [
            new FacetBucket('electronics', 'Electronics', 42, true),
            new FacetBucket('clothing', 'Clothing', 28, false),
            new FacetBucket('books', 'Books', 15, true),
        ];

        // Act
        $facet = new SearchFacet(
            field: 'category',
            label: 'Category',
            buckets: $buckets
        );

        // Assert
        $array = $facet->toArray();
        $this->assertTrue($array['buckets'][0]['selected']);
        $this->assertFalse($array['buckets'][1]['selected']);
        $this->assertTrue($array['buckets'][2]['selected']);
    }

    public function testItCreatesWithBucketsHavingZeroCounts(): void
    {
        // Arrange
        $buckets = [
            new FacetBucket('available', 'Available', 50),
            new FacetBucket('out-of-stock', 'Out of Stock', 0),
        ];

        // Act
        $facet = new SearchFacet(
            field: 'availability',
            label: 'Availability',
            buckets: $buckets
        );

        // Assert
        $this->assertCount(2, $facet->buckets);
        $array = $facet->toArray();
        $this->assertSame(0, $array['buckets'][1]['count']);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $buckets = [
            new FacetBucket('test', 'Test', 1),
        ];

        $facet = new SearchFacet(
            field: 'test_field',
            label: 'Test Label',
            buckets: $buckets
        );

        // Assert - Properties are readonly
        $this->assertSame('test_field', $facet->field);
        $this->assertSame('Test Label', $facet->label);
        $this->assertCount(1, $facet->buckets);
    }
}
