<?php

declare(strict_types=1);

namespace App\Customer\Application\Query\SearchCustomers;

use App\Customer\Application\DTO\CustomerSummaryDTO;

/**
 * Search Customers Result.
 *
 * Contains paginated search results with metadata.
 */
final readonly class SearchCustomersResult
{
    /**
     * @param CustomerSummaryDTO[] $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
        public int $totalPages
    ) {
    }

    /**
     * @param CustomerSummaryDTO[] $items
     */
    public static function create(
        array $items,
        int $total,
        int $page,
        int $limit
    ): self {
        $totalPages = (int) ceil($total / $limit);

        return new self(
            items: $items,
            total: $total,
            page: $page,
            limit: $limit,
            totalPages: $totalPages
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (CustomerSummaryDTO $item) => $item->toArray(), $this->items),
            'total' => $this->total,
            'page' => $this->page,
            'limit' => $this->limit,
            'totalPages' => $this->totalPages,
        ];
    }
}
