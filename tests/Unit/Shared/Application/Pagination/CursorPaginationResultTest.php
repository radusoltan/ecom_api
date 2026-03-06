<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Pagination;

use App\Shared\Application\Pagination\CursorPaginationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CursorPaginationResult::class)]
final class CursorPaginationResultTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Constructor & properties
    // -----------------------------------------------------------------------

    #[Test]
    public function itStoresAllProperties(): void
    {
        $items = ['a', 'b', 'c'];
        $result = new CursorPaginationResult(
            items: $items,
            hasNextPage: true,
            hasPreviousPage: false,
            startCursor: 'cursor-start',
            endCursor: 'cursor-end',
            totalCount: 42,
        );

        self::assertSame($items, $result->items);
        self::assertTrue($result->hasNextPage);
        self::assertFalse($result->hasPreviousPage);
        self::assertSame('cursor-start', $result->startCursor);
        self::assertSame('cursor-end', $result->endCursor);
        self::assertSame(42, $result->totalCount);
    }

    #[Test]
    public function itDefaultsTotalCountToNull(): void
    {
        $result = new CursorPaginationResult(
            items: [],
            hasNextPage: false,
            hasPreviousPage: false,
            startCursor: null,
            endCursor: null,
        );

        self::assertNull($result->totalCount);
    }

    #[Test]
    public function itAcceptsNullCursors(): void
    {
        $result = new CursorPaginationResult(
            items: [],
            hasNextPage: false,
            hasPreviousPage: false,
            startCursor: null,
            endCursor: null,
        );

        self::assertNull($result->startCursor);
        self::assertNull($result->endCursor);
    }

    // -----------------------------------------------------------------------
    // toArray
    // -----------------------------------------------------------------------

    #[Test]
    public function itToArrayReturnsCorrectStructure(): void
    {
        $result = new CursorPaginationResult(
            items: [1, 2],
            hasNextPage: true,
            hasPreviousPage: true,
            startCursor: 'start',
            endCursor: 'end',
            totalCount: 100,
        );

        $array = $result->toArray();

        self::assertArrayHasKey('items', $array);
        self::assertArrayHasKey('pageInfo', $array);
        self::assertArrayHasKey('totalCount', $array);
    }

    #[Test]
    public function itToArrayContainsCorrectPageInfo(): void
    {
        $result = new CursorPaginationResult(
            items: [],
            hasNextPage: true,
            hasPreviousPage: false,
            startCursor: 'c1',
            endCursor: 'c2',
            totalCount: 5,
        );

        $pageInfo = $result->toArray()['pageInfo'];

        self::assertTrue($pageInfo['hasNextPage']);
        self::assertFalse($pageInfo['hasPreviousPage']);
        self::assertSame('c1', $pageInfo['startCursor']);
        self::assertSame('c2', $pageInfo['endCursor']);
    }

    #[Test]
    public function itToArrayPreservesItems(): void
    {
        $items = ['product-1', 'product-2'];
        $result = new CursorPaginationResult(
            items: $items,
            hasNextPage: false,
            hasPreviousPage: false,
            startCursor: null,
            endCursor: null,
        );

        self::assertSame($items, $result->toArray()['items']);
    }

    #[Test]
    public function itToArrayWithNullTotalCount(): void
    {
        $result = new CursorPaginationResult(
            items: [],
            hasNextPage: false,
            hasPreviousPage: false,
            startCursor: null,
            endCursor: null,
        );

        self::assertNull($result->toArray()['totalCount']);
    }
}
