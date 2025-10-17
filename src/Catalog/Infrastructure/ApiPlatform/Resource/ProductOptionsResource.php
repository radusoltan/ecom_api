<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Catalog\Infrastructure\ApiPlatform\State\ProductOptionsProvider;

/**
 * API Resource for Product Configuration Options
 *
 * Exposes all unique product options (color, size, etc.) across the catalog
 * for use in product filtering and search interfaces.
 *
 * Example response:
 * [
 *   {
 *     "code": "color",
 *     "name": "Color",
 *     "nameTranslations": {"en": "Color", "fr": "Couleur", "de": "Farbe"},
 *     "values": [
 *       {
 *         "code": "red",
 *         "name": "Red",
 *         "nameTranslations": {"en": "Red", "fr": "Rouge", "de": "Rot"}
 *       },
 *       {
 *         "code": "blue",
 *         "name": "Blue",
 *         "nameTranslations": {"en": "Blue", "fr": "Bleu", "de": "Blau"}
 *       }
 *     ]
 *   },
 *   {
 *     "code": "size",
 *     "name": "Size",
 *     "nameTranslations": {"en": "Size", "fr": "Taille", "de": "Größe"},
 *     "values": [
 *       {
 *         "code": "s",
 *         "name": "Small",
 *         "nameTranslations": {"en": "Small", "fr": "Petit", "de": "Klein"}
 *       }
 *     ]
 *   }
 * ]
 *
 * Query parameters:
 * - locale: Language code for localized names (en, fr, de) - defaults to Accept-Language header
 */
#[ApiResource(
    shortName: 'ProductOptions',
    operations: [
        new GetCollection(
            uriTemplate: '/product-options',
            provider: ProductOptionsProvider::class,
        ),
    ],
    paginationEnabled: false,
)]
final readonly class ProductOptionsResource
{
    /**
     * @param string $code Option code (e.g., 'color', 'size')
     * @param string $name Localized option name for current locale
     * @param array<string, string> $nameTranslations All translations for option name
     * @param ProductOptionValueResource[] $values Available values for this option
     */
    public function __construct(
        public string $code,
        public string $name,
        public array $nameTranslations,
        public array $values,
    ) {}
}
