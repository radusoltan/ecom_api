<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

/**
 * API Resource for Product Option Values.
 *
 * Represents a single value for a product option (e.g., "Red" for Color option)
 * This is a nested resource within ProductOptionsResource
 */
final readonly class ProductOptionValueResource
{
    /**
     * @param string                $code             Value code (e.g., 'red', 's', 'xl')
     * @param string                $name             Localized value name for current locale
     * @param array<string, string> $nameTranslations All translations for value name
     */
    public function __construct(
        public string $code,
        public string $name,
        public array $nameTranslations,
    ) {
    }
}
