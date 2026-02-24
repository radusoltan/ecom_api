<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/**
 * Cursor-based (keyset) paginator for high-volume endpoints.
 *
 * Provides efficient seek-based pagination that avoids the O(n) offset
 * scanning problem of traditional page/limit pagination. Works by encoding
 * the last seen row's sort value + ID as an opaque cursor.
 *
 * Usage in a repository:
 * 1. Decode the cursor to get sort value and ID
 * 2. Build WHERE clause: (sort_field > :sv) OR (sort_field = :sv AND id > :id)
 * 3. Fetch limit + 1 rows to detect hasNextPage
 * 4. Pass results to CursorPaginator::paginate()
 *
 * @see https://use-the-index-luke.com/no-offset
 */
final readonly class CursorPaginator
{
    public const int DEFAULT_LIMIT = 20;
    public const int MAX_LIMIT = 100;

    /**
     * Build a CursorPaginationResult from a fetched result set.
     *
     * The caller must fetch limit + 1 items from the database.
     * If more than limit items are returned, hasNextPage is true.
     *
     * @param list<mixed>  $items           Items fetched (limit + 1 for next page detection)
     * @param int          $limit           Requested page size
     * @param string       $sortField       Name of the sort field
     * @param \Closure     $getId           Extracts the ID from an item
     * @param \Closure     $getSortValue    Extracts the sort value from an item
     * @param bool         $hasPreviousPage Whether a previous page exists (cursor was provided)
     * @param int|null     $totalCount      Optional total count
     *
     * @return CursorPaginationResult<mixed>
     */
    public static function paginate(
        array $items,
        int $limit,
        string $sortField,
        \Closure $getId,
        \Closure $getSortValue,
        bool $hasPreviousPage = false,
        ?int $totalCount = null,
    ): CursorPaginationResult {
        $hasNextPage = count($items) > $limit;

        // Trim the extra detection item
        if ($hasNextPage) {
            $items = array_slice($items, 0, $limit);
        }

        if ([] === $items) {
            return new CursorPaginationResult(
                items: [],
                hasNextPage: false,
                hasPreviousPage: $hasPreviousPage,
                startCursor: null,
                endCursor: null,
                totalCount: $totalCount,
            );
        }

        $firstItem = $items[0];
        $lastItem = $items[count($items) - 1];

        return new CursorPaginationResult(
            items: $items,
            hasNextPage: $hasNextPage,
            hasPreviousPage: $hasPreviousPage,
            startCursor: Cursor::encode($getId($firstItem), $getSortValue($firstItem), $sortField),
            endCursor: Cursor::encode($getId($lastItem), $getSortValue($lastItem), $sortField),
            totalCount: $totalCount,
        );
    }

    /**
     * Normalize the limit parameter, capping at MAX_LIMIT.
     */
    public static function normalizeLimit(?int $limit): int
    {
        if (null === $limit || $limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * Decode a cursor string, returning null if not provided or invalid.
     */
    public static function decodeCursor(?string $cursor): ?Cursor
    {
        if (null === $cursor || '' === $cursor) {
            return null;
        }

        try {
            return Cursor::decode($cursor);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
