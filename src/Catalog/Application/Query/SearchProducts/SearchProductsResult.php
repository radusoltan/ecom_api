<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

final readonly class SearchProductsResult
{
    /**
     * @param array<ProductSearchDTO> $products
     * @param array<string, mixed> $facets
     */
    public function __construct(
        public array $products,
        public int $total,
        public array $facets,
        public int $page,
        public int $limit,
    ) {}

    public function getTotalPages(): int
    {
        return (int) ceil($this->total / $this->limit);
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->getTotalPages();
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }
}
