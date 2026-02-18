<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to add a value to an existing option.
 */
final readonly class DefineOptionValue
{
    /**
     * @param array<string, string> $nameTranslations - Value name in different locales (e.g., ['en' => 'Red', 'fr' => 'Rouge'])
     */
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public string $optionCode,
        public string $valueCode,
        public array $nameTranslations,
        public int $position = 0,
    ) {
    }
}
