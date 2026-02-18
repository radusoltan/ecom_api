<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Inventory\Infrastructure\ApiPlatform\State\CheckStockAvailabilityProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API Resource for checking stock availability.
 *
 * Used to validate if requested quantities are available before placing orders.
 * Aggregates stock across all warehouses for each product.
 *
 * REST Endpoint:
 * - POST /api/v1/stock/check - Check availability for multiple items
 *
 * Request Example:
 * {
 *   "items": [
 *     {"productId": "01234...", "variantId": null, "quantity": 2},
 *     {"productId": "56789...", "variantId": "abc", "quantity": 1}
 *   ]
 * }
 *
 * Response Example:
 * {
 *   "available": true,
 *   "items": [
 *     {
 *       "productId": "01234...",
 *       "variantId": null,
 *       "requestedQuantity": 2,
 *       "availableQuantity": 50,
 *       "available": true
 *     },
 *     {
 *       "productId": "56789...",
 *       "variantId": "abc",
 *       "requestedQuantity": 1,
 *       "availableQuantity": 0,
 *       "available": false
 *     }
 *   ]
 * }
 */
#[ApiResource(
    shortName: 'StockAvailability',
    operations: [
        new Post(
            uriTemplate: '/stock/check',
            processor: CheckStockAvailabilityProcessor::class,
            name: 'check_stock_availability',
            description: 'Check stock availability for multiple items',
        ),
    ],
    paginationEnabled: false,
)]
final class StockAvailabilityResource
{
    /**
     * @param array<StockAvailabilityItemResource>|null       $items Items to check
     * @param array<StockAvailabilityItemResultResource>|null $items Response items with availability
     */
    public function __construct(
        // Request fields
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Count(min: 1, max: 100, groups: ['create'])]
        public ?array $items = null,

        // Response fields
        public ?bool $available = null,
    ) {
    }
}
