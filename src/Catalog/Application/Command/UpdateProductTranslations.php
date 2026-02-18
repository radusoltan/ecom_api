<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to update product translations for a specific locale.
 */
final readonly class UpdateProductTranslations
{
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public Locale $locale,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $shortDescription = null,
    ) {
    }
}
