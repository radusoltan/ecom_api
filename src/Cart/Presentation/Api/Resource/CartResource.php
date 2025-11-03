<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Cart\Presentation\Api\Processor\AddItemToCartProcessor;
use App\Cart\Presentation\Api\Processor\ClearCartProcessor;
use App\Cart\Presentation\Api\Processor\RemoveItemFromCartProcessor;
use App\Cart\Presentation\Api\Processor\UpdateCartItemProcessor;
use App\Cart\Presentation\Api\Provider\GetCartProvider;

#[ApiResource(
    shortName: 'Cart',
    description: 'Shopping cart management for e-commerce platform. Supports guest and authenticated users with automatic cart creation and merging.',
    operations: [
        new Get(
            uriTemplate: '/cart',
            provider: GetCartProvider::class,
            openapi: new Model\Operation(
                summary: 'Retrieve current cart',
                description: 'Get the full cart with all items, prices, and totals. Requires X-Cart-ID header or authenticated session.',
                parameters: [
                    new Model\Parameter(
                        name: 'X-Cart-ID',
                        in: 'header',
                        description: 'Cart identifier (UUID/ULID)',
                        required: false,
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
                        description: 'Cart retrieved successfully',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'string', 'example' => '01HQVZP3X8EXAMPLEULID123'],
                                        'tenantId' => ['type' => 'string', 'example' => '550e8400-e29b-41d4-a716-446655440000'],
                                        'customerId' => ['type' => 'string', 'nullable' => true],
                                        'sessionId' => ['type' => 'string', 'nullable' => true],
                                        'status' => ['type' => 'string', 'example' => 'active'],
                                        'items' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'id' => ['type' => 'string'],
                                                    'productId' => ['type' => 'string'],
                                                    'variantId' => ['type' => 'string', 'nullable' => true],
                                                    'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999],
                                                    'unitPrice' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'amount' => ['type' => 'integer', 'example' => 1999],
                                                            'currency' => ['type' => 'string', 'example' => 'USD'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        'totalAmount' => ['type' => 'integer', 'example' => 3998],
                                        'totalCurrency' => ['type' => 'string', 'example' => 'USD'],
                                        'itemCount' => ['type' => 'integer', 'example' => 2],
                                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                                        'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
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
            uriTemplate: '/cart/items',
            processor: AddItemToCartProcessor::class,
            openapi: new Model\Operation(
                summary: 'Add item to cart',
                description: 'Add a product to the cart. If the cart doesn\'t exist, it will be created automatically. If the same product already exists, quantities will be merged.',
                requestBody: new Model\RequestBody(
                    description: 'Item details to add',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['tenantId', 'productId', 'quantity'],
                                'properties' => [
                                    'tenantId' => ['type' => 'string', 'format' => 'uuid', 'example' => '550e8400-e29b-41d4-a716-446655440000'],
                                    'productId' => ['type' => 'string', 'example' => '01HQVZP3X8PRODUCT123'],
                                    'variantId' => ['type' => 'string', 'nullable' => true, 'example' => 'size-L'],
                                    'quantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999, 'example' => 2],
                                    'unitPriceAmount' => ['type' => 'integer', 'description' => 'Price in cents (optional, fetched from catalog if not provided)', 'example' => 1999],
                                    'unitPriceCurrency' => ['type' => 'string', 'example' => 'USD'],
                                    'customerId' => ['type' => 'string', 'nullable' => true, 'description' => 'Customer ID for authenticated users'],
                                ],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '201' => new Model\Response(description: 'Item added to cart successfully'),
                    '400' => new Model\Response(description: 'Invalid request (missing required fields, invalid quantity)'),
                    '409' => new Model\Response(description: 'Insufficient stock available'),
                ]
            )
        ),
        new Patch(
            uriTemplate: '/cart/items/{itemId}',
            processor: UpdateCartItemProcessor::class,
            openapi: new Model\Operation(
                summary: 'Update cart item quantity',
                description: 'Update the quantity of an existing cart item. Stock availability is validated before update.',
                parameters: [
                    new Model\Parameter(
                        name: 'itemId',
                        in: 'path',
                        description: 'Cart item identifier',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                    new Model\Parameter(
                        name: 'X-Cart-ID',
                        in: 'header',
                        description: 'Cart identifier',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ],
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['tenantId', 'newQuantity'],
                                'properties' => [
                                    'tenantId' => ['type' => 'string', 'format' => 'uuid'],
                                    'newQuantity' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999, 'example' => 5],
                                ],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => new Model\Response(description: 'Quantity updated successfully'),
                    '400' => new Model\Response(description: 'Invalid quantity'),
                    '404' => new Model\Response(description: 'Cart item not found'),
                    '409' => new Model\Response(description: 'Insufficient stock for requested quantity'),
                ]
            )
        ),
        new Delete(
            uriTemplate: '/cart/items/{itemId}',
            processor: RemoveItemFromCartProcessor::class,
            openapi: new Model\Operation(
                summary: 'Remove item from cart',
                description: 'Remove a specific item from the cart',
                parameters: [
                    new Model\Parameter(
                        name: 'itemId',
                        in: 'path',
                        description: 'Cart item identifier to remove',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                    new Model\Parameter(
                        name: 'X-Cart-ID',
                        in: 'header',
                        description: 'Cart identifier',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ],
                responses: [
                    '200' => new Model\Response(description: 'Item removed successfully'),
                    '404' => new Model\Response(description: 'Cart item not found'),
                ]
            )
        ),
        new Delete(
            uriTemplate: '/cart',
            processor: ClearCartProcessor::class,
            openapi: new Model\Operation(
                summary: 'Clear entire cart',
                description: 'Remove all items from the cart',
                parameters: [
                    new Model\Parameter(
                        name: 'X-Cart-ID',
                        in: 'header',
                        description: 'Cart identifier',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid']
                    ),
                ],
                responses: [
                    '200' => new Model\Response(description: 'Cart cleared successfully'),
                    '404' => new Model\Response(description: 'Cart not found'),
                ]
            )
        ),
    ]
)]
class CartResource
{
    public ?string $id = null;
    public ?string $tenantId = null;
    public ?string $customerId = null;
    public ?string $sessionId = null;
    public ?string $status = null;
    /** @var array<int, mixed> */
    public array $items = [];
    public ?int $totalAmount = null;
    public ?string $totalCurrency = null;
    public ?int $itemCount = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    // For adding items
    public ?string $productId = null;
    public ?string $variantId = null;
    public ?int $quantity = null;
    public ?int $unitPriceAmount = null;
    public ?string $unitPriceCurrency = null;

    // For updating items
    public ?int $newQuantity = null;
}
