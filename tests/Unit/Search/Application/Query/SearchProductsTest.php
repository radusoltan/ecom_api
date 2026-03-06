<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application\Query;

use App\Search\Application\Query\SearchProducts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchProducts::class)]
final class SearchProductsTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // -------------------------------------------------------
    // Constructor — explicit values
    // -------------------------------------------------------

    #[Test]
    public function itStoresTenantId(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        self::assertSame(self::TENANT_ID, $query->tenantId);
    }

    #[Test]
    public function itStoresSearchQuery(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'wireless headphones',
        );

        self::assertSame('wireless headphones', $query->query);
    }

    #[Test]
    public function itStoresLocale(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'camera',
            locale: 'fr',
        );

        self::assertSame('fr', $query->locale);
    }

    #[Test]
    public function itStoresPage(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'monitor',
            page: 3,
        );

        self::assertSame(3, $query->page);
    }

    #[Test]
    public function itStoresPerPage(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'keyboard',
            perPage: 48,
        );

        self::assertSame(48, $query->perPage);
    }

    #[Test]
    public function itStoresSortBy(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'tablet',
            sortBy: 'price',
        );

        self::assertSame('price', $query->sortBy);
    }

    #[Test]
    public function itStoresSortOrder(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'phone',
            sortOrder: 'asc',
        );

        self::assertSame('asc', $query->sortOrder);
    }

    #[Test]
    public function itStoresFilters(): void
    {
        $filters = ['category' => 'electronics', 'price_min' => 100];

        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'smartphone',
            filters: $filters,
        );

        self::assertSame($filters, $query->filters);
    }

    // -------------------------------------------------------
    // Default values
    // -------------------------------------------------------

    #[Test]
    public function defaultLocaleIsEn(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        self::assertSame('en', $query->locale);
    }

    #[Test]
    public function defaultPageIsOne(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        self::assertSame(1, $query->page);
    }

    #[Test]
    public function defaultPerPageIsTwentyFour(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        self::assertSame(24, $query->perPage);
    }

    #[Test]
    public function defaultSortByIsRelevance(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        self::assertSame('relevance', $query->sortBy);
    }

    #[Test]
    public function defaultSortOrderIsDesc(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        self::assertSame('desc', $query->sortOrder);
    }

    #[Test]
    public function defaultFiltersIsEmptyArray(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        self::assertSame([], $query->filters);
    }

    // -------------------------------------------------------
    // Nullable sort fields
    // -------------------------------------------------------

    #[Test]
    public function sortByCanBeNull(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
            sortBy: null,
        );

        self::assertNull($query->sortBy);
    }

    #[Test]
    public function sortOrderCanBeNull(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
            sortOrder: null,
        );

        self::assertNull($query->sortOrder);
    }

    // -------------------------------------------------------
    // Readonly — cannot mutate properties
    // -------------------------------------------------------

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'laptop',
        );

        $reflection = new \ReflectionClass($query);

        foreach ($reflection->getProperties() as $property) {
            self::assertTrue(
                $property->isReadOnly(),
                sprintf('Property $%s must be readonly', $property->getName()),
            );
        }
    }

    // -------------------------------------------------------
    // All properties set together
    // -------------------------------------------------------

    #[Test]
    public function itStoresAllPropertiesWhenFullySpecified(): void
    {
        $filters = ['brand' => 'sony', 'rating_min' => 4];

        $query = new SearchProducts(
            tenantId: self::TENANT_ID,
            query: 'tv',
            locale: 'de',
            page: 2,
            perPage: 12,
            sortBy: 'name',
            sortOrder: 'asc',
            filters: $filters,
        );

        self::assertSame(self::TENANT_ID, $query->tenantId);
        self::assertSame('tv', $query->query);
        self::assertSame('de', $query->locale);
        self::assertSame(2, $query->page);
        self::assertSame(12, $query->perPage);
        self::assertSame('name', $query->sortBy);
        self::assertSame('asc', $query->sortOrder);
        self::assertSame($filters, $query->filters);
    }
}
