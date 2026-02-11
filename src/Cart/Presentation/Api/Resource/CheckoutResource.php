<?php

declare(strict_types=1);

namespace App\Cart\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Cart\Presentation\Api\Processor\CheckoutProcessor;

/**
 * CheckoutResource - API Resource for cart checkout.
 *
 * Converts a cart into an order and processes the checkout.
 * This is the main entry point for converting shopping carts to orders.
 *
 * Flow:
 * 1. Client sends cart ID + customer info + addresses
 * 2. Cart is validated (active, has items)
 * 3. Cart items are converted to order lines
 * 4. Order is placed via PlaceOrderCommand
 * 5. Cart is marked as converted
 * 6. Order details are returned
 */
#[ApiResource(
    shortName: 'Checkout',
    operations: [
        new Post(
            uriTemplate: '/checkout',
            processor: CheckoutProcessor::class,
            openapi: new Model\Operation(
                summary: 'Process cart checkout',
                description: 'Converts the cart to an order and processes checkout. Requires cart ID, customer email, and shipping/billing addresses.',
                requestBody: new Model\RequestBody(
                    description: 'Checkout request with cart and customer information',
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['cartId', 'customerEmail', 'shippingAddress', 'billingAddress'],
                                'properties' => [
                                    'cartId' => [
                                        'type' => 'string',
                                        'description' => 'Cart ID to checkout',
                                        'example' => '01HQVZP3X8EXAMPLEULID123',
                                    ],
                                    'customerEmail' => [
                                        'type' => 'string',
                                        'format' => 'email',
                                        'description' => 'Customer email for order confirmation',
                                        'example' => 'customer@example.com',
                                    ],
                                    'shippingAddress' => [
                                        'type' => 'object',
                                        'required' => ['street', 'city', 'state', 'postalCode', 'country'],
                                        'properties' => [
                                            'street' => ['type' => 'string', 'example' => '123 Main St'],
                                            'city' => ['type' => 'string', 'example' => 'New York'],
                                            'state' => ['type' => 'string', 'example' => 'NY'],
                                            'postalCode' => ['type' => 'string', 'example' => '10001'],
                                            'country' => ['type' => 'string', 'example' => 'US'],
                                        ],
                                    ],
                                    'billingAddress' => [
                                        'type' => 'object',
                                        'required' => ['street', 'city', 'state', 'postalCode', 'country'],
                                        'properties' => [
                                            'street' => ['type' => 'string', 'example' => '123 Main St'],
                                            'city' => ['type' => 'string', 'example' => 'New York'],
                                            'state' => ['type' => 'string', 'example' => 'NY'],
                                            'postalCode' => ['type' => 'string', 'example' => '10001'],
                                            'country' => ['type' => 'string', 'example' => 'US'],
                                        ],
                                    ],
                                    'couponCode' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                        'description' => 'Optional coupon code for discount',
                                        'example' => 'SAVE10',
                                    ],
                                    'useBillingAsShipping' => [
                                        'type' => 'boolean',
                                        'description' => 'If true, billing address is used as shipping address',
                                        'default' => false,
                                    ],
                                ],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '201' => new Model\Response(
                        description: 'Checkout successful, order created',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'orderId' => ['type' => 'string', 'example' => '01HQVZP3X8ORDER12345'],
                                        'status' => ['type' => 'string', 'example' => 'pending'],
                                        'totalAmount' => ['type' => 'integer', 'example' => 3998],
                                        'totalCurrency' => ['type' => 'string', 'example' => 'USD'],
                                        'itemCount' => ['type' => 'integer', 'example' => 2],
                                        'customerEmail' => ['type' => 'string', 'example' => 'customer@example.com'],
                                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                                    ],
                                ],
                            ],
                        ])
                    ),
                    '400' => new Model\Response(description: 'Invalid request (missing required fields, empty cart, invalid address)'),
                    '404' => new Model\Response(description: 'Cart not found'),
                    '409' => new Model\Response(description: 'Cart already converted or inactive'),
                ]
            )
        ),
    ]
)]
class CheckoutResource
{
    // Input fields
    public ?string $cartId = null;
    public ?string $customerEmail = null;
    /** @var array<string, string>|null */
    public ?array $shippingAddress = null;
    /** @var array<string, string>|null */
    public ?array $billingAddress = null;
    public ?string $couponCode = null;
    public bool $useBillingAsShipping = false;

    // Output fields (returned after successful checkout)
    public ?string $orderId = null;
    public ?string $status = null;
    public ?int $totalAmount = null;
    public ?string $totalCurrency = null;
    public ?int $itemCount = null;
    public ?string $createdAt = null;
    public ?string $tenantId = null;
    /** @var array<int, mixed> */
    public array $lines = [];
    /** @var array<string, mixed>|array<int, mixed> */
    public array $appliedPromotions = [];
    public ?int $discountAmount = null;
    public ?string $discountCurrency = null;
}
