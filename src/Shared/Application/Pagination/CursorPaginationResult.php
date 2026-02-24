<?php

declare(strict_types=1);

namespace App\Shared\Application\Pagination;

/**
 * Result DTO for cursor-based pagination.
 *
 * Contains the paginated items along with navigation metadata
 * following the Relay connection specification pattern.
 *
 * @template T
 */
final readonly class CursorPaginationResult
{
    /**
     * @param list<T>     $items           The items in the current page
     * @param bool        $hasNextPage     Whether more items exist after endCursor
     * @param bool        $hasPreviousPage Whether items exist before startCursor
     * @param string|null $startCursor     Cursor of the first item (null if empty)
     * @param string|null $endCursor       Cursor of the last item (null if empty)
     * @param int|null    $totalCount      Total count of items (optional, expensive)
     */
    public function __construct(
        public array $items,
        public bool $hasNextPage,
        public bool $hasPreviousPage,
        public ?string $startCursor,
        public ?string $endCursor,
        public ?int $totalCount = null,
    ) {
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array{items: list<T>, pageInfo: array{hasNextPage: bool, hasPreviousPage: bool, startCursor: string|null, endCursor: string|null}, totalCount: int|null}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'pageInfo' => [
                'hasNextPage' => $this->hasNextPage,
                'hasPreviousPage' => $this->hasPreviousPage,
                'startCursor' => $this->startCursor,
                'endCursor' => $this->endCursor,
            ],
            'totalCount' => $this->totalCount,
        ];
    }
}
