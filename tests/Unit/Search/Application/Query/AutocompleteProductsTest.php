<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application\Query;

use App\Search\Application\Query\AutocompleteProducts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutocompleteProducts::class)]
final class AutocompleteProductsTest extends TestCase
{
    private const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    // -------------------------------------------------------
    // Constructor — explicit values
    // -------------------------------------------------------

    #[Test]
    public function itStoresTenantId(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'lap',
        );

        self::assertSame(self::TENANT_ID, $query->tenantId);
    }

    #[Test]
    public function itStoresSearchQuery(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'wire',
        );

        self::assertSame('wire', $query->query);
    }

    #[Test]
    public function itStoresLocale(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'cam',
            locale: 'ro',
        );

        self::assertSame('ro', $query->locale);
    }

    #[Test]
    public function itStoresLimit(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'pho',
            limit: 10,
        );

        self::assertSame(10, $query->limit);
    }

    // -------------------------------------------------------
    // Default values
    // -------------------------------------------------------

    #[Test]
    public function defaultLocaleIsEn(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'lap',
        );

        self::assertSame('en', $query->locale);
    }

    #[Test]
    public function defaultLimitIsFive(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'lap',
        );

        self::assertSame(5, $query->limit);
    }

    // -------------------------------------------------------
    // Limit boundary validation
    // -------------------------------------------------------

    #[Test]
    public function limitOfOneIsAccepted(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'x',
            limit: 1,
        );

        self::assertSame(1, $query->limit);
    }

    #[Test]
    public function limitOfTwentyIsAccepted(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'x',
            limit: 20,
        );

        self::assertSame(20, $query->limit);
    }

    #[Test]
    public function limitOfZeroThrowsInvalidArgumentException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Autocomplete limit must be between 1 and 20');

        new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'x',
            limit: 0,
        );
    }

    #[Test]
    public function limitOfTwentyOneThrowsInvalidArgumentException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Autocomplete limit must be between 1 and 20');

        new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'x',
            limit: 21,
        );
    }

    #[Test]
    public function negativeLimitThrowsInvalidArgumentException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Autocomplete limit must be between 1 and 20');

        new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'x',
            limit: -1,
        );
    }

    #[Test]
    #[TestWith([1])]
    #[TestWith([5])]
    #[TestWith([10])]
    #[TestWith([15])]
    #[TestWith([20])]
    public function validLimitsAreAccepted(int $limit): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'test',
            limit: $limit,
        );

        self::assertSame($limit, $query->limit);
    }

    // -------------------------------------------------------
    // Readonly — cannot mutate properties
    // -------------------------------------------------------

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'lap',
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
        $query = new AutocompleteProducts(
            tenantId: self::TENANT_ID,
            query: 'sony',
            locale: 'ja',
            limit: 8,
        );

        self::assertSame(self::TENANT_ID, $query->tenantId);
        self::assertSame('sony', $query->query);
        self::assertSame('ja', $query->locale);
        self::assertSame(8, $query->limit);
    }
}
