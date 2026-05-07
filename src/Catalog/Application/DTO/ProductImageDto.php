<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Wire-shape value object for product image URL sets on storefront read DTOs.
 *
 * Same JSON-LD-normalization concern as MoneyDto: an `array<string,mixed>`
 * field gets wrapped as a Hydra Collection on a single Get response.
 *
 * Wire format: `{ "urlSm": string, "urlMd": string, "urlLg": string }`.
 */
final readonly class ProductImageDto
{
    public function __construct(
        #[Groups(['storefront:read'])]
        public string $urlSm,
        #[Groups(['storefront:read'])]
        public string $urlMd,
        #[Groups(['storefront:read'])]
        public string $urlLg,
    ) {
    }
}
