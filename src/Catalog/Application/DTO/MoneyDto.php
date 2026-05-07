<?php

declare(strict_types=1);

namespace App\Catalog\Application\DTO;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Wire-shape value object for monetary amounts on storefront read DTOs.
 *
 * Distinct from the domain `Money` VO (brick/money based) — this exists
 * purely to give API Platform's JSON-LD normalizer a typed object to work
 * with. An untyped `array<string,mixed>` field gets wrapped as a Hydra
 * Collection at the top level of a single Get response, breaking the
 * `{amount, currency}` shape the storefront client expects.
 *
 * Wire format is intentionally identical to the listing endpoint:
 * `{ "amount": int, "currency": string }`.
 */
final readonly class MoneyDto
{
    public function __construct(
        #[Groups(['storefront:read'])]
        public int $amount,
        #[Groups(['storefront:read'])]
        public string $currency,
    ) {
    }
}
