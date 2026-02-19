<?php

declare(strict_types=1);

namespace App\Cart\Application\Query\GetCartTaxEstimate;

/**
 * Query to estimate tax for a cart.
 *
 * Country/region codes are optional — if not provided, the handler
 * will attempt to resolve the jurisdiction from the customer's
 * default shipping address.
 */
final readonly class GetCartTaxEstimateQuery
{
    public function __construct(
        public string $cartId,
        public string $tenantId,
        public ?string $countryCode = null,
        public ?string $regionCode = null,
    ) {
    }
}
