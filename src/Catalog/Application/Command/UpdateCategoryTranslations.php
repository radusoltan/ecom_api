<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\CategoryId;
use App\Catalog\Domain\ValueObject\Locale;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to update category translations for a specific locale.
 */
final readonly class UpdateCategoryTranslations
{
    public function __construct(
        public CategoryId $categoryId,
        public TenantId $tenantId,
        public Locale $locale,
        public ?string $name = null,
        public ?string $description = null
    ) {
    }
}
