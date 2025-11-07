<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\AutocompleteProducts;

use App\Internationalization\Domain\Model\Locale;
use App\Shared\Domain\ValueObject\TenantId;

final readonly class AutocompleteProductsQuery
{
    public function __construct(
        public TenantId $tenantId,
        public Locale $locale,
        public string $query,
        public int $limit = 10,
    ) {
    }
}
