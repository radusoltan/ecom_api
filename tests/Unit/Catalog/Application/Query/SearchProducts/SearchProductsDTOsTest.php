<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Query\SearchProducts;

use App\Catalog\Application\Query\SearchProducts\ProductSearchDTO;
use App\Catalog\Application\Query\SearchProducts\SearchProductsResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductSearchDTO::class)]
#[CoversClass(SearchProductsResult::class)]
final class SearchProductsDTOsTest extends TestCase
{
    // --- ProductSearchDTO ---

    #[Test]
    public function productSearchDtoConstruction(): void
    {
        $dto = new ProductSearchDTO(
            id: 'prod-1',
            tenantId: 't-1',
            sku: 'SKU-123456',
            name: 'Widget',
            description: 'A great widget',
            slug: 'widget',
            priceInCents: 2999,
            currency: 'USD',
            status: 'active',
            categoryIds: ['cat-1', 'cat-2'],
            imageUrl: '/images/widget.jpg',
            locale: 'en',
            score: 1.5,
            averageRating: 4.2,
            reviewCount: 15,
            isFeatured: true,
        );

        self::assertSame('prod-1', $dto->id);
        self::assertSame('t-1', $dto->tenantId);
        self::assertSame('SKU-123456', $dto->sku);
        self::assertSame('Widget', $dto->name);
        self::assertSame('A great widget', $dto->description);
        self::assertSame('widget', $dto->slug);
        self::assertSame(2999, $dto->priceInCents);
        self::assertSame('USD', $dto->currency);
        self::assertSame('active', $dto->status);
        self::assertCount(2, $dto->categoryIds);
        self::assertSame('/images/widget.jpg', $dto->imageUrl);
        self::assertSame('en', $dto->locale);
        self::assertSame(1.5, $dto->score);
        self::assertSame(4.2, $dto->averageRating);
        self::assertSame(15, $dto->reviewCount);
        self::assertTrue($dto->isFeatured);
    }

    #[Test]
    public function productSearchDtoDefaults(): void
    {
        $dto = new ProductSearchDTO(
            id: 'p-1',
            tenantId: 't-1',
            sku: 'SKU-000001',
            name: 'Test',
            description: null,
            slug: 'test',
            priceInCents: 1000,
            currency: 'EUR',
            status: 'draft',
            categoryIds: [],
            imageUrl: null,
            locale: 'de',
            score: 0.0,
        );

        self::assertSame(0.0, $dto->averageRating);
        self::assertSame(0, $dto->reviewCount);
        self::assertFalse($dto->isFeatured);
        self::assertNull($dto->description);
        self::assertNull($dto->imageUrl);
        self::assertSame([], $dto->categoryIds);
    }

    #[Test]
    public function productSearchDtoFromElasticsearchHit(): void
    {
        $hit = [
            '_score' => 2.5,
            '_source' => [
                'id' => 'prod-es-1',
                'tenant_id' => 't-1',
                'sku' => 'ES-SKU-001',
                'name' => 'Elasticsearch Product',
                'description' => 'Found via search',
                'slug' => 'elasticsearch-product',
                'price' => 49.99,
                'currency' => 'USD',
                'status' => 'active',
                'category_ids' => ['cat-1'],
                'image_url' => '/img/es.jpg',
                'locale' => 'en',
                'average_rating' => 4.8,
                'review_count' => 100,
                'is_featured' => true,
            ],
        ];

        $dto = ProductSearchDTO::fromElasticsearchHit($hit);

        self::assertSame('prod-es-1', $dto->id);
        self::assertSame('t-1', $dto->tenantId);
        self::assertSame('ES-SKU-001', $dto->sku);
        self::assertSame('Elasticsearch Product', $dto->name);
        self::assertSame('Found via search', $dto->description);
        self::assertSame('elasticsearch-product', $dto->slug);
        self::assertSame(4999, $dto->priceInCents);
        self::assertSame('USD', $dto->currency);
        self::assertSame('active', $dto->status);
        self::assertSame(['cat-1'], $dto->categoryIds);
        self::assertSame('/img/es.jpg', $dto->imageUrl);
        self::assertSame('en', $dto->locale);
        self::assertSame(2.5, $dto->score);
        self::assertSame(4.8, $dto->averageRating);
        self::assertSame(100, $dto->reviewCount);
        self::assertTrue($dto->isFeatured);
    }

    #[Test]
    public function productSearchDtoFromElasticsearchHitWithMissingOptionals(): void
    {
        $hit = [
            '_source' => [
                'id' => 'prod-es-2',
                'tenant_id' => 't-1',
                'sku' => 'ES-SKU-002',
                'name' => 'Minimal Product',
                'slug' => 'minimal',
                'price' => 5.0,
                'currency' => 'EUR',
                'status' => 'active',
                'locale' => 'en',
            ],
        ];

        $dto = ProductSearchDTO::fromElasticsearchHit($hit);

        self::assertSame('prod-es-2', $dto->id);
        self::assertNull($dto->description);
        self::assertSame([], $dto->categoryIds);
        self::assertNull($dto->imageUrl);
        self::assertSame(0.0, $dto->score);
        self::assertSame(0.0, $dto->averageRating);
        self::assertSame(0, $dto->reviewCount);
        self::assertFalse($dto->isFeatured);
    }

    // --- SearchProductsResult ---

    #[Test]
    public function searchProductsResultConstruction(): void
    {
        $result = new SearchProductsResult(
            products: [],
            total: 0,
            facets: [],
            page: 1,
            limit: 20,
        );

        self::assertSame([], $result->products);
        self::assertSame(0, $result->total);
        self::assertSame([], $result->facets);
        self::assertSame(1, $result->page);
        self::assertSame(20, $result->limit);
    }

    #[Test]
    public function searchProductsResultTotalPages(): void
    {
        $result = new SearchProductsResult(
            products: [],
            total: 55,
            facets: [],
            page: 1,
            limit: 20,
        );

        self::assertSame(3, $result->getTotalPages());
    }

    #[Test]
    public function searchProductsResultTotalPagesExact(): void
    {
        $result = new SearchProductsResult(
            products: [],
            total: 40,
            facets: [],
            page: 1,
            limit: 20,
        );

        self::assertSame(2, $result->getTotalPages());
    }

    #[Test]
    public function searchProductsResultHasNextPage(): void
    {
        $result = new SearchProductsResult(
            products: [],
            total: 50,
            facets: [],
            page: 1,
            limit: 20,
        );

        self::assertTrue($result->hasNextPage());
    }

    #[Test]
    public function searchProductsResultNoNextPage(): void
    {
        $result = new SearchProductsResult(
            products: [],
            total: 50,
            facets: [],
            page: 3,
            limit: 20,
        );

        self::assertFalse($result->hasNextPage());
    }

    #[Test]
    public function searchProductsResultHasPreviousPage(): void
    {
        $result = new SearchProductsResult(
            products: [],
            total: 50,
            facets: [],
            page: 2,
            limit: 20,
        );

        self::assertTrue($result->hasPreviousPage());
    }

    #[Test]
    public function searchProductsResultNoPreviousPage(): void
    {
        $result = new SearchProductsResult(
            products: [],
            total: 50,
            facets: [],
            page: 1,
            limit: 20,
        );

        self::assertFalse($result->hasPreviousPage());
    }

    #[Test]
    public function searchProductsResultWithFacets(): void
    {
        $facets = [
            'categories' => [
                ['key' => 'electronics', 'count' => 15],
                ['key' => 'clothing', 'count' => 10],
            ],
            'price_ranges' => [
                ['key' => '0-50', 'count' => 20],
            ],
        ];

        $result = new SearchProductsResult(
            products: [],
            total: 25,
            facets: $facets,
            page: 1,
            limit: 20,
        );

        self::assertCount(2, $result->facets);
        self::assertArrayHasKey('categories', $result->facets);
        self::assertArrayHasKey('price_ranges', $result->facets);
    }
}
