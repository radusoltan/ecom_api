<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\RedeemPointsProcessor;
use App\Customer\Presentation\Api\Provider\CustomerLoyaltyProvider;
use App\Customer\Presentation\Api\Provider\LoyaltyTransactionCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Customer Loyalty API Resource.
 *
 * Represents a customer's loyalty status and point balance.
 */
#[ApiResource(
    shortName: 'CustomerLoyalty',
    description: 'Customer loyalty status and points management',
    operations: [
        new Get(
            uriTemplate: '/customers/{customerId}/loyalty',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerLoyaltyResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            provider: CustomerLoyaltyProvider::class,
            security: "is_granted('customer.view') or (is_granted('ROLE_CUSTOMER') and object.customerId == user.getId())",
            openapi: new Model\Operation(
                summary: 'Get customer loyalty status',
                description: 'Retrieve comprehensive loyalty status for a customer including current points, tier, and progress to next tier.',
                parameters: [
                    new Model\Parameter(
                        name: 'customerId',
                        in: 'path',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                        description: 'Customer UUID'
                    ),
                ],
                responses: [
                    '200' => [
                        'description' => 'Customer loyalty status',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'currentPoints' => 2500,
                                    'currentTier' => 'Silver',
                                    'nextTier' => 'Gold',
                                    'pointsToNextTier' => 2500,
                                    'lifetimePointsEarned' => 10000,
                                    'lifetimePointsRedeemed' => 7500,
                                    'currentTierDiscount' => 10,
                                    'programName' => 'Gold Rewards Program',
                                    'programIsActive' => true,
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only view own loyalty status'],
                    '404' => ['description' => 'Customer not found or no loyalty program exists'],
                ],
            )
        ),
        new GetCollection(
            uriTemplate: '/customers/{customerId}/loyalty/transactions',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerLoyaltyResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            provider: LoyaltyTransactionCollectionProvider::class,
            security: "is_granted('customer.view') or (is_granted('ROLE_CUSTOMER') and object.customerId == user.getId())",
            openapi: new Model\Operation(
                summary: 'Get customer transaction history',
                description: 'Retrieve paginated loyalty point transaction history for a customer.',
                parameters: [
                    new Model\Parameter(
                        name: 'customerId',
                        in: 'path',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                        description: 'Customer UUID'
                    ),
                    new Model\Parameter(
                        name: 'page',
                        in: 'query',
                        required: false,
                        schema: ['type' => 'integer', 'default' => 1],
                        description: 'Page number'
                    ),
                    new Model\Parameter(
                        name: 'limit',
                        in: 'query',
                        required: false,
                        schema: ['type' => 'integer', 'default' => 20, 'maximum' => 100],
                        description: 'Items per page'
                    ),
                ],
                responses: [
                    '200' => [
                        'description' => 'List of loyalty point transactions',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    [
                                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                                        'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                        'tenantId' => '00000000-0000-4000-8000-000000000001',
                                        'type' => 'earned',
                                        'typeLabel' => 'Earned',
                                        'points' => 150,
                                        'balanceAfter' => 2650,
                                        'reason' => 'Order purchase',
                                        'orderId' => '789e4567-e89b-12d3-a456-426614174000',
                                        'expiresAt' => '2025-01-15T10:30:00+00:00',
                                        'createdAt' => '2024-01-15T10:30:00+00:00',
                                        'isDebit' => false,
                                        'isCredit' => true,
                                    ],
                                    [
                                        'id' => '550e8400-e29b-41d4-a716-446655440001',
                                        'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                        'tenantId' => '00000000-0000-4000-8000-000000000001',
                                        'type' => 'redeemed',
                                        'typeLabel' => 'Redeemed',
                                        'points' => 100,
                                        'balanceAfter' => 2500,
                                        'reason' => 'Points redemption',
                                        'orderId' => null,
                                        'expiresAt' => null,
                                        'createdAt' => '2024-01-14T15:20:00+00:00',
                                        'isDebit' => true,
                                        'isCredit' => false,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'Customer not found'],
                ],
            )
        ),
        new Post(
            uriTemplate: '/customers/{customerId}/loyalty/redeem',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerLoyaltyResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: RedeemPointsProcessor::class,
            security: "is_granted('customer.edit') or (is_granted('ROLE_CUSTOMER') and object.customerId == user.getId())",
            openapi: new Model\Operation(
                summary: 'Redeem loyalty points',
                description: 'Redeem loyalty points for a discount. Returns the discount amount and remaining balance.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['pointsToRedeem'],
                                'properties' => [
                                    'pointsToRedeem' => [
                                        'type' => 'integer',
                                        'minimum' => 1,
                                        'example' => 500,
                                        'description' => 'Number of points to redeem',
                                    ],
                                    'orderId' => [
                                        'type' => 'string',
                                        'format' => 'uuid',
                                        'nullable' => true,
                                        'example' => '789e4567-e89b-12d3-a456-426614174000',
                                        'description' => 'Optional order ID to associate with redemption',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '200' => [
                        'description' => 'Points redeemed successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'pointsRedeemed' => 500,
                                    'discountAmount' => 5.0,
                                    'discountCurrency' => 'USD',
                                    'remainingBalance' => 2000,
                                ],
                            ],
                        ],
                    ],
                    '400' => ['description' => 'Bad Request - Invalid input or insufficient points'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only redeem own points'],
                    '404' => ['description' => 'Customer not found'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['customer_loyalty:read']],
    denormalizationContext: ['groups' => ['customer_loyalty:write']],
)]
class CustomerLoyaltyResource
{
    #[ApiProperty(identifier: true, readable: true, writable: false)]
    public ?string $customerId = null;

    #[ApiProperty(readable: true, writable: false)]
    public int $currentPoints = 0;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $currentTier = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $nextTier = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?int $pointsToNextTier = null;

    #[ApiProperty(readable: true, writable: false)]
    public int $lifetimePointsEarned = 0;

    #[ApiProperty(readable: true, writable: false)]
    public int $lifetimePointsRedeemed = 0;

    #[ApiProperty(readable: true, writable: false)]
    public ?int $currentTierDiscount = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $programName = null;

    #[ApiProperty(readable: true, writable: false)]
    public bool $programIsActive = false;

    // For redeem operation
    #[Assert\NotBlank(message: 'Points to redeem is required', groups: ['redeem'])]
    #[Assert\Positive(message: 'Points to redeem must be positive', groups: ['redeem'])]
    public ?int $pointsToRedeem = null;

    public ?string $orderId = null;

    // For redeem result
    #[ApiProperty(readable: true, writable: false)]
    public ?int $pointsRedeemed = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?float $discountAmount = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $discountCurrency = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?int $remainingBalance = null;
}
