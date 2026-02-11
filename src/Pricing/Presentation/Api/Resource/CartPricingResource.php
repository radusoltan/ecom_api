<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Pricing\Presentation\Api\Processor\ApplyCouponProcessor;
use App\Pricing\Presentation\Api\Provider\GetCartPricingProvider;

/**
 * Cart Pricing API Resource.
 *
 * Provides endpoints for cart pricing calculations and coupon application
 */
#[ApiResource(
    shortName: 'CartPricing',
    description: 'Cart pricing calculations with discounts, promotions, and coupons',
    operations: [
        new Get(
            uriTemplate: '/cart/pricing',
            provider: GetCartPricingProvider::class,
            openapi: new Model\Operation(
                summary: 'Get cart pricing breakdown',
                description: 'Calculate complete pricing for cart including all discounts, promotions, and applied coupons. Returns detailed breakdown per item and cart-level totals.',
                parameters: [
                    new Model\Parameter(
                        name: 'X-Cart-ID',
                        in: 'header',
                        description: 'Cart identifier (UUID/ULID)',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                    new Model\Parameter(
                        name: 'X-Tenant-ID',
                        in: 'header',
                        description: 'Tenant identifier for multi-tenancy',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                    new Model\Parameter(
                        name: 'coupons',
                        in: 'query',
                        description: 'Comma-separated coupon codes to apply',
                        required: false,
                        schema: ['type' => 'string'],
                        example: 'SUMMER2024,WELCOME10'
                    ),
                ],
                responses: [
                    '200' => new Model\Response(
                        description: 'Pricing breakdown retrieved successfully',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'cart_id' => ['type' => 'string', 'format' => 'uuid'],
                                        'items' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'cart_item_id' => ['type' => 'string'],
                                                    'product_id' => ['type' => 'string'],
                                                    'quantity' => ['type' => 'integer'],
                                                    'base_unit_price' => ['type' => 'object'],
                                                    'final_unit_price' => ['type' => 'object'],
                                                    'row_total' => ['type' => 'object'],
                                                    'applied_discounts' => ['type' => 'array'],
                                                ],
                                            ],
                                        ],
                                        'subtotal' => ['type' => 'object'],
                                        'total_discounts' => ['type' => 'object'],
                                        'grand_total' => ['type' => 'object'],
                                        'cart_level_discounts' => ['type' => 'array'],
                                        'total_items_count' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '404' => new Model\Response(description: 'Cart not found'),
                ]
            )
        ),
        new Post(
            uriTemplate: '/cart/apply-coupon',
            processor: ApplyCouponProcessor::class,
            openapi: new Model\Operation(
                summary: 'Apply coupon to cart',
                description: 'Validate and apply a coupon code to the cart. Returns discount details if successful.',
                requestBody: new Model\RequestBody(
                    description: 'Coupon code to apply',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['coupon_code'],
                                'properties' => [
                                    'coupon_code' => [
                                        'type' => 'string',
                                        'description' => 'Coupon code to validate and apply',
                                        'example' => 'SUMMER2024',
                                        'minLength' => 3,
                                        'maxLength' => 50,
                                    ],
                                ],
                            ],
                        ],
                    ])
                ),
                parameters: [
                    new Model\Parameter(
                        name: 'X-Cart-ID',
                        in: 'header',
                        description: 'Cart identifier (UUID/ULID)',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                    new Model\Parameter(
                        name: 'X-Tenant-ID',
                        in: 'header',
                        description: 'Tenant identifier for multi-tenancy',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ],
                responses: [
                    '200' => new Model\Response(
                        description: 'Coupon applied successfully',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'string'],
                                        'type' => ['type' => 'string'],
                                        'name' => ['type' => 'string'],
                                        'amount' => ['type' => 'object'],
                                        'discount_type' => ['type' => 'string'],
                                        'discount_value' => ['type' => 'number'],
                                        'scope' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '400' => new Model\Response(description: 'Invalid coupon code or cannot be applied'),
                    '404' => new Model\Response(description: 'Cart not found'),
                ]
            )
        ),
    ]
)]
final class CartPricingResource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    public ?string $cartId = null;
    public ?array $items = null;
    public ?array $subtotal = null;
    public ?array $totalDiscounts = null;
    public ?array $grandTotal = null;
    public ?array $cartLevelDiscounts = null;
    public ?int $totalItemsCount = null;
}
