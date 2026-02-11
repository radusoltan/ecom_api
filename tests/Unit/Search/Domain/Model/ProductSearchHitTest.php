<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Search\Domain\Model\ProductSearchHit;
use PHPUnit\Framework\TestCase;

final class ProductSearchHitTest extends TestCase
{
    public function testItCreatesWithMinimalData(): void
    {
        // Arrange
        $productId = ProductId::fromString('018c6e60-e270-7e43-9f19-000000000001');

        // Act
        $hit = new ProductSearchHit(
            productId: $productId,
            sku: 'PROD-123456',
            name: 'Test Product',
            description: null,
            price: 99.99,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Assert
        $this->assertTrue($productId->equals($hit->productId));
        $this->assertSame('PROD-123456', $hit->sku);
        $this->assertSame('Test Product', $hit->name);
        $this->assertNull($hit->description);
        $this->assertSame(99.99, $hit->price);
        $this->assertSame('USD', $hit->currency);
        $this->assertNull($hit->imageUrl);
        $this->assertTrue($hit->isActive);
        $this->assertFalse($hit->isFeatured);
        $this->assertSame(1.0, $hit->score);
    }

    public function testItCreatesWithFullData(): void
    {
        // Arrange
        $productId = ProductId::fromString('018c6e60-e270-7e43-9f19-000000000002');

        // Act
        $hit = new ProductSearchHit(
            productId: $productId,
            sku: 'PROD-789012',
            name: 'Premium Product',
            description: 'A detailed description of the product',
            price: 199.99,
            currency: 'EUR',
            imageUrl: 'https://example.com/image.jpg',
            isActive: true,
            isFeatured: true,
            score: 0.95,
            categoryIds: ['cat-1', 'cat-2'],
            averageRating: 4.5,
            reviewCount: 42
        );

        // Assert
        $this->assertSame('Premium Product', $hit->name);
        $this->assertSame('A detailed description of the product', $hit->description);
        $this->assertSame(199.99, $hit->price);
        $this->assertSame('EUR', $hit->currency);
        $this->assertSame('https://example.com/image.jpg', $hit->imageUrl);
        $this->assertTrue($hit->isFeatured);
        $this->assertSame(0.95, $hit->score);
        $this->assertSame(['cat-1', 'cat-2'], $hit->categoryIds);
        $this->assertSame(4.5, $hit->averageRating);
        $this->assertSame(42, $hit->reviewCount);
    }

    public function testHasImageReturnsTrueWhenImageUrlIsPresent(): void
    {
        // Arrange
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000003'),
            sku: 'PROD-123',
            name: 'Product with Image',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: 'https://example.com/image.jpg',
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Act & Assert
        $this->assertTrue($hit->hasImage());
    }

    public function testHasImageReturnsFalseWhenImageUrlIsNull(): void
    {
        // Arrange
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000004'),
            sku: 'PROD-456',
            name: 'Product without Image',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Act & Assert
        $this->assertFalse($hit->hasImage());
    }

    public function testHasImageReturnsFalseWhenImageUrlIsEmpty(): void
    {
        // Arrange
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000005'),
            sku: 'PROD-789',
            name: 'Product with Empty Image',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: '',
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Act & Assert
        $this->assertFalse($hit->hasImage());
    }

    public function testHasReviewsReturnsTrueWhenReviewCountIsGreaterThanZero(): void
    {
        // Arrange
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000006'),
            sku: 'PROD-111',
            name: 'Popular Product',
            description: null,
            price: 100.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0,
            categoryIds: [],
            averageRating: 4.0,
            reviewCount: 1
        );

        // Act & Assert
        $this->assertTrue($hit->hasReviews());
    }

    public function testHasReviewsReturnsFalseWhenReviewCountIsZero(): void
    {
        // Arrange
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000007'),
            sku: 'PROD-222',
            name: 'New Product',
            description: null,
            price: 100.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0,
            categoryIds: [],
            averageRating: null,
            reviewCount: 0
        );

        // Act & Assert
        $this->assertFalse($hit->hasReviews());
    }

    public function testHasReviewsReturnsFalseWhenReviewCountIsNull(): void
    {
        // Arrange
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000008'),
            sku: 'PROD-333',
            name: 'Product without Reviews',
            description: null,
            price: 100.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0,
            categoryIds: [],
            averageRating: null,
            reviewCount: null
        );

        // Act & Assert
        $this->assertFalse($hit->hasReviews());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        // Arrange
        $productId = ProductId::fromString('018c6e60-e270-7e43-9f19-000000000009');

        $hit = new ProductSearchHit(
            productId: $productId,
            sku: 'PROD-999',
            name: 'Test Product',
            description: 'A description',
            price: 79.99,
            currency: 'GBP',
            imageUrl: 'https://example.com/test.jpg',
            isActive: true,
            isFeatured: true,
            score: 0.88,
            categoryIds: ['cat-1', 'cat-2', 'cat-3'],
            averageRating: 4.7,
            reviewCount: 123
        );

        // Act
        $array = $hit->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('productId', $array);
        $this->assertArrayHasKey('sku', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertArrayHasKey('currency', $array);
        $this->assertArrayHasKey('imageUrl', $array);
        $this->assertArrayHasKey('isActive', $array);
        $this->assertArrayHasKey('isFeatured', $array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('categoryIds', $array);
        $this->assertArrayHasKey('averageRating', $array);
        $this->assertArrayHasKey('reviewCount', $array);

        $this->assertSame($productId->toString(), $array['productId']);
        $this->assertSame('PROD-999', $array['sku']);
        $this->assertSame('Test Product', $array['name']);
        $this->assertSame('A description', $array['description']);
        $this->assertSame(79.99, $array['price']);
        $this->assertSame('GBP', $array['currency']);
        $this->assertSame('https://example.com/test.jpg', $array['imageUrl']);
        $this->assertTrue($array['isActive']);
        $this->assertTrue($array['isFeatured']);
        $this->assertSame(0.88, $array['score']);
        $this->assertSame(['cat-1', 'cat-2', 'cat-3'], $array['categoryIds']);
        $this->assertSame(4.7, $array['averageRating']);
        $this->assertSame(123, $array['reviewCount']);
    }

    public function testToArrayWithNullValues(): void
    {
        // Arrange
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000010'),
            sku: 'PROD-000',
            name: 'Minimal Product',
            description: null,
            price: 10.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 0.5
        );

        // Act
        $array = $hit->toArray();

        // Assert
        $this->assertNull($array['description']);
        $this->assertNull($array['imageUrl']);
        $this->assertNull($array['averageRating']);
        $this->assertNull($array['reviewCount']);
    }

    public function testItCreatesWithEmptyCategoryIds(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000011'),
            sku: 'PROD-111',
            name: 'Uncategorized',
            description: null,
            price: 20.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0,
            categoryIds: []
        );

        // Assert
        $this->assertEmpty($hit->categoryIds);
        $array = $hit->toArray();
        $this->assertEmpty($array['categoryIds']);
    }

    public function testItCreatesWithMultipleCategoryIds(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000012'),
            sku: 'PROD-222',
            name: 'Multi-category Product',
            description: null,
            price: 30.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0,
            categoryIds: ['cat-1', 'cat-2', 'cat-3', 'cat-4', 'cat-5']
        );

        // Assert
        $this->assertCount(5, $hit->categoryIds);
    }

    public function testItCreatesWithZeroPrice(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000013'),
            sku: 'PROD-FREE',
            name: 'Free Product',
            description: null,
            price: 0.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Assert
        $this->assertSame(0.0, $hit->price);
    }

    public function testItCreatesWithVeryHighPrice(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000014'),
            sku: 'PROD-LUXURY',
            name: 'Luxury Product',
            description: null,
            price: 999999.99,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Assert
        $this->assertSame(999999.99, $hit->price);
    }

    public function testItCreatesWithZeroScore(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000015'),
            sku: 'PROD-LOW',
            name: 'Low Relevance',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 0.0
        );

        // Assert
        $this->assertSame(0.0, $hit->score);
    }

    public function testItCreatesWithPerfectScore(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000016'),
            sku: 'PROD-HIGH',
            name: 'High Relevance',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Assert
        $this->assertSame(1.0, $hit->score);
    }

    public function testItCreatesWithInactiveProduct(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000017'),
            sku: 'PROD-INACTIVE',
            name: 'Inactive Product',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: null,
            isActive: false,
            isFeatured: false,
            score: 1.0
        );

        // Assert
        $this->assertFalse($hit->isActive);
    }

    public function testItCreatesWithDifferentCurrencies(): void
    {
        // Arrange & Act
        $hitUSD = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000018'),
            sku: 'PROD-USD',
            name: 'USD Product',
            description: null,
            price: 100.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        $hitEUR = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000019'),
            sku: 'PROD-EUR',
            name: 'EUR Product',
            description: null,
            price: 100.0,
            currency: 'EUR',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0
        );

        // Assert
        $this->assertSame('USD', $hitUSD->currency);
        $this->assertSame('EUR', $hitEUR->currency);
    }

    public function testItCreatesWithZeroAverageRating(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000020'),
            sku: 'PROD-BAD',
            name: 'Bad Product',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0,
            categoryIds: [],
            averageRating: 0.0,
            reviewCount: 10
        );

        // Assert
        $this->assertSame(0.0, $hit->averageRating);
        $this->assertTrue($hit->hasReviews());
    }

    public function testItCreatesWithMaximumAverageRating(): void
    {
        // Arrange & Act
        $hit = new ProductSearchHit(
            productId: ProductId::fromString('018c6e60-e270-7e43-9f19-000000000021'),
            sku: 'PROD-PERFECT',
            name: 'Perfect Product',
            description: null,
            price: 50.0,
            currency: 'USD',
            imageUrl: null,
            isActive: true,
            isFeatured: false,
            score: 1.0,
            categoryIds: [],
            averageRating: 5.0,
            reviewCount: 100
        );

        // Assert
        $this->assertSame(5.0, $hit->averageRating);
        $this->assertTrue($hit->hasReviews());
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        // Arrange
        $productId = ProductId::fromString('018c6e60-e270-7e43-9f19-000000000022');

        $hit = new ProductSearchHit(
            productId: $productId,
            sku: 'PROD-READONLY',
            name: 'Readonly Test',
            description: 'Test',
            price: 50.0,
            currency: 'USD',
            imageUrl: 'https://example.com/image.jpg',
            isActive: true,
            isFeatured: true,
            score: 0.95
        );

        // Assert - Properties are readonly
        $this->assertSame('PROD-READONLY', $hit->sku);
        $this->assertSame('Readonly Test', $hit->name);
        $this->assertSame(50.0, $hit->price);
    }
}
