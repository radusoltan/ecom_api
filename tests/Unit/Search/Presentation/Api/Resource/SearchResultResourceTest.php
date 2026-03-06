<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Presentation\Api\Resource;

use App\Search\Presentation\Api\Resource\SearchResultResource;
use PHPUnit\Framework\TestCase;

final class SearchResultResourceTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $resource = new SearchResultResource();

        self::assertSame([], $resource->hits);
        self::assertSame(0, $resource->total);
        self::assertSame(1, $resource->page);
        self::assertSame(24, $resource->perPage);
        self::assertSame(0, $resource->totalPages);
        self::assertSame([], $resource->facets);
        self::assertSame(0.0, $resource->took);
    }

    public function testConstructorWithValues(): void
    {
        $hits = [['id' => 'p1', 'name' => 'Laptop'], ['id' => 'p2', 'name' => 'Phone']];
        $facets = ['categories' => ['electronics' => 5]];

        $resource = new SearchResultResource(
            hits: $hits,
            total: 100,
            page: 3,
            perPage: 10,
            totalPages: 10,
            facets: $facets,
            took: 42.5,
        );

        self::assertCount(2, $resource->hits);
        self::assertSame(100, $resource->total);
        self::assertSame(3, $resource->page);
        self::assertSame(10, $resource->perPage);
        self::assertSame(10, $resource->totalPages);
        self::assertSame($facets, $resource->facets);
        self::assertSame(42.5, $resource->took);
    }
}
