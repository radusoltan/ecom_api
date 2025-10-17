<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\SearchProducts;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class SearchProductsQuery
{
    /**
     * @param array<string>|null $categoryIds
     * @param array<string, array<string>>|null $options Product variant options (e.g., ['color' => ['red', 'blue'], 'size' => ['m', 'l']])
     */
    public function __construct(
        public TenantId $tenantId,
        public Locale $locale,
        public ?string $query = null,
        public ?array $categoryIds = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?string $status = 'active',
        public string $sortBy = 'relevance',
        public int $page = 1,
        public int $limit = 20,
        public ?array $options = null,
        public ?int $minRating = null,
        public ?bool $featured = null,
    ) {}
}
