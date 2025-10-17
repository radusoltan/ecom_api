<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Command to define an option for a configurable product
 */
final readonly class DefineOption
{
    /**
     * @param array<string, string> $nameTranslations - Option name in different locales (e.g., ['en' => 'Color', 'fr' => 'Couleur'])
     * @param array<array{code: string, nameTranslations: array<string, string>, position: int}> $values - Option values
     */
    public function __construct(
        public ProductId $productId,
        public TenantId $tenantId,
        public string $code,
        public array $nameTranslations,
        public int $position = 0,
        public array $values = []
    ) {}
}