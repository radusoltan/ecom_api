<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Pagination;

use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPaginationResult;
use App\Shared\Application\Pagination\CursorPaginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CursorPaginator::class)]
#[CoversClass(Cursor::class)]
#[CoversClass(CursorPaginationResult::class)]
final class CursorPaginatorTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Cursor encode/decode
    // -----------------------------------------------------------------------

    #[Test]
    public function cursorEncodeDecodeRoundTrip(): void
    {
        $encoded = Cursor::encode('abc-123', '2026-01-15', 'created_at');

        $decoded = Cursor::decode($encoded);

        self::assertSame('abc-123', $decoded->id);
        self::assertSame('2026-01-15', $decoded->sortValue);
        self::assertSame('created_at', $decoded->sortField);
    }

    #[Test]
    public function cursorHandlesNumericSortValue(): void
    {
        $encoded = Cursor::encode('id-1', 42, 'position');

        $decoded = Cursor::decode($encoded);

        self::assertSame('id-1', $decoded->id);
        self::assertSame(42, $decoded->sortValue);
        self::assertSame('position', $decoded->sortField);
    }

    #[Test]
    public function cursorHandlesNullSortValue(): void
    {
        $encoded = Cursor::encode('id-1', null, 'id');

        $decoded = Cursor::decode($encoded);

        self::assertSame('id-1', $decoded->id);
        self::assertNull($decoded->sortValue);
        self::assertSame('id', $decoded->sortField);
    }

    #[Test]
    public function cursorDecodeThrowsOnInvalidBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid base64');

        // Invalid base64 with special chars
        Cursor::decode('!!!not-base64!!!');
    }

    #[Test]
    public function cursorDecodeThrowsOnInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        Cursor::decode(base64_encode('not json'));
    }

    #[Test]
    public function cursorDecodeThrowsOnMissingFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('missing required fields');

        Cursor::decode(base64_encode(json_encode(['foo' => 'bar'])));
    }

    // -----------------------------------------------------------------------
    // Forward pagination
    // -----------------------------------------------------------------------

    #[Test]
    public function paginateForwardWithMoreResults(): void
    {
        // Simulate fetching limit+1 items (3 items for limit=2)
        $items = [
            ['id' => 'a', 'name' => 'Alpha', 'created_at' => '2026-01-01'],
            ['id' => 'b', 'name' => 'Beta', 'created_at' => '2026-01-02'],
            ['id' => 'c', 'name' => 'Charlie', 'created_at' => '2026-01-03'], // extra
        ];

        $result = CursorPaginator::paginate(
            items: $items,
            limit: 2,
            sortField: 'created_at',
            getId: fn (array $item) => $item['id'],
            getSortValue: fn (array $item) => $item['created_at'],
        );

        // Should trim to 2 items
        self::assertCount(2, $result->items);
        self::assertSame('a', $result->items[0]['id']);
        self::assertSame('b', $result->items[1]['id']);

        // Navigation
        self::assertTrue($result->hasNextPage);
        self::assertFalse($result->hasPreviousPage);
        self::assertNotNull($result->startCursor);
        self::assertNotNull($result->endCursor);

        // Verify end cursor points to last visible item
        $endCursor = Cursor::decode($result->endCursor);
        self::assertSame('b', $endCursor->id);
        self::assertSame('2026-01-02', $endCursor->sortValue);
    }

    #[Test]
    public function paginateForwardLastPage(): void
    {
        // Exactly limit items (no extra = last page)
        $items = [
            ['id' => 'd', 'name' => 'Delta', 'created_at' => '2026-01-04'],
            ['id' => 'e', 'name' => 'Echo', 'created_at' => '2026-01-05'],
        ];

        $result = CursorPaginator::paginate(
            items: $items,
            limit: 2,
            sortField: 'created_at',
            getId: fn (array $item) => $item['id'],
            getSortValue: fn (array $item) => $item['created_at'],
            hasPreviousPage: true,
        );

        self::assertCount(2, $result->items);
        self::assertFalse($result->hasNextPage);
        self::assertTrue($result->hasPreviousPage);
    }

    // -----------------------------------------------------------------------
    // Backward pagination
    // -----------------------------------------------------------------------

    #[Test]
    public function paginateBackwardWithCursor(): void
    {
        // Backward pagination: cursor was provided, results exist
        $items = [
            ['id' => 'x', 'name' => 'X-ray', 'ts' => 100],
            ['id' => 'y', 'name' => 'Yankee', 'ts' => 200],
            ['id' => 'z', 'name' => 'Zulu', 'ts' => 300], // extra
        ];

        $result = CursorPaginator::paginate(
            items: $items,
            limit: 2,
            sortField: 'ts',
            getId: fn (array $item) => $item['id'],
            getSortValue: fn (array $item) => $item['ts'],
            hasPreviousPage: true,
            totalCount: 50,
        );

        self::assertCount(2, $result->items);
        self::assertTrue($result->hasNextPage);
        self::assertTrue($result->hasPreviousPage);
        self::assertSame(50, $result->totalCount);
    }

    // -----------------------------------------------------------------------
    // Edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function paginateEmptyResults(): void
    {
        $result = CursorPaginator::paginate(
            items: [],
            limit: 20,
            sortField: 'id',
            getId: fn (array $item) => $item['id'],
            getSortValue: fn (array $item) => $item['id'],
        );

        self::assertCount(0, $result->items);
        self::assertFalse($result->hasNextPage);
        self::assertFalse($result->hasPreviousPage);
        self::assertNull($result->startCursor);
        self::assertNull($result->endCursor);
    }

    #[Test]
    public function paginateSingleItem(): void
    {
        $items = [
            ['id' => 'only', 'value' => 'one'],
        ];

        $result = CursorPaginator::paginate(
            items: $items,
            limit: 20,
            sortField: 'id',
            getId: fn (array $item) => $item['id'],
            getSortValue: fn (array $item) => $item['id'],
        );

        self::assertCount(1, $result->items);
        self::assertFalse($result->hasNextPage);
        self::assertFalse($result->hasPreviousPage);
        self::assertNotNull($result->startCursor);
        self::assertSame($result->startCursor, $result->endCursor);
    }

    // -----------------------------------------------------------------------
    // Limit normalization
    // -----------------------------------------------------------------------

    #[Test]
    public function normalizeLimitCapsAtMax(): void
    {
        self::assertSame(100, CursorPaginator::normalizeLimit(500));
        self::assertSame(100, CursorPaginator::normalizeLimit(100));
        self::assertSame(50, CursorPaginator::normalizeLimit(50));
    }

    #[Test]
    public function normalizeLimitDefaultsForInvalid(): void
    {
        self::assertSame(20, CursorPaginator::normalizeLimit(null));
        self::assertSame(20, CursorPaginator::normalizeLimit(0));
        self::assertSame(20, CursorPaginator::normalizeLimit(-1));
    }

    // -----------------------------------------------------------------------
    // decodeCursor helper
    // -----------------------------------------------------------------------

    #[Test]
    public function decodeCursorReturnsNullForEmpty(): void
    {
        self::assertNull(CursorPaginator::decodeCursor(null));
        self::assertNull(CursorPaginator::decodeCursor(''));
    }

    #[Test]
    public function decodeCursorReturnsNullForInvalid(): void
    {
        self::assertNull(CursorPaginator::decodeCursor('not-a-cursor'));
    }

    #[Test]
    public function decodeCursorReturnsObjectForValid(): void
    {
        $encoded = Cursor::encode('uuid-1', '2026-02-01', 'created_at');
        $cursor = CursorPaginator::decodeCursor($encoded);

        self::assertNotNull($cursor);
        self::assertSame('uuid-1', $cursor->id);
    }

    // -----------------------------------------------------------------------
    // Result serialization
    // -----------------------------------------------------------------------

    #[Test]
    public function resultToArrayFormat(): void
    {
        $result = new CursorPaginationResult(
            items: [['id' => '1']],
            hasNextPage: true,
            hasPreviousPage: false,
            startCursor: 'abc',
            endCursor: 'xyz',
            totalCount: 42,
        );

        $array = $result->toArray();

        self::assertArrayHasKey('items', $array);
        self::assertArrayHasKey('pageInfo', $array);
        self::assertTrue($array['pageInfo']['hasNextPage']);
        self::assertFalse($array['pageInfo']['hasPreviousPage']);
        self::assertSame('abc', $array['pageInfo']['startCursor']);
        self::assertSame('xyz', $array['pageInfo']['endCursor']);
        self::assertSame(42, $array['totalCount']);
    }
}
