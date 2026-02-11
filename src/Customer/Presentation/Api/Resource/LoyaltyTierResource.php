<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\AddLoyaltyTierProcessor;
use App\Customer\Presentation\Api\Processor\RemoveLoyaltyTierProcessor;
use App\Customer\Presentation\Api\Processor\UpdateLoyaltyTierProcessor;
use App\Customer\Presentation\Api\Provider\LoyaltyTierCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Loyalty Tier API Resource.
 *
 * Represents a tier within a loyalty program (e.g., Bronze, Silver, Gold).
 */
#[ApiResource(
    shortName: 'LoyaltyTier',
    description: 'Loyalty program tier with benefits and thresholds',
    operations: [
        new GetCollection(
            uriTemplate: '/loyalty-programs/{programId}/tiers',
            uriVariables: [
                'programId' => [
                    'from_class' => LoyaltyTierResource::class,
                    'from_property' => 'programId',
                ],
            ],
            provider: LoyaltyTierCollectionProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new Model\Operation(
                summary: 'List loyalty program tiers',
                description: 'Retrieve all tiers for a specific loyalty program, ordered by threshold.',
                parameters: [
                    new Model\Parameter(
                        name: 'programId',
                        in: 'path',
                        required: true,
                        schema: ['type' => 'string', 'format' => 'uuid'],
                        description: 'Loyalty Program UUID'
                    ),
                ],
                responses: [
                    '200' => [
                        'description' => 'List of loyalty tiers',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    [
                                        'id' => '123e4567-e89b-12d3-a456-426614174000',
                                        'programId' => '550e8400-e29b-41d4-a716-446655440000',
                                        'name' => 'Bronze',
                                        'threshold' => 0,
                                        'discountPercentage' => 5,
                                        'freeShippingMinOrder' => null,
                                        'sortOrder' => 1,
                                    ],
                                    [
                                        'id' => '123e4567-e89b-12d3-a456-426614174001',
                                        'programId' => '550e8400-e29b-41d4-a716-446655440000',
                                        'name' => 'Silver',
                                        'threshold' => 1000,
                                        'discountPercentage' => 10,
                                        'freeShippingMinOrder' => ['amount' => 5000, 'currency' => 'USD'],
                                        'sortOrder' => 2,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '404' => ['description' => 'Loyalty program not found'],
                ],
            )
        ),
        new Post(
            uriTemplate: '/loyalty-programs/{programId}/tiers',
            uriVariables: [
                'programId' => [
                    'from_class' => LoyaltyTierResource::class,
                    'from_property' => 'programId',
                ],
            ],
            processor: AddLoyaltyTierProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Add a new tier',
                description: 'Add a new tier to the loyalty program. Tier threshold must be unique.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['name', 'threshold', 'discountPercentage', 'sortOrder'],
                                'properties' => [
                                    'name' => ['type' => 'string', 'maxLength' => 100, 'example' => 'Gold'],
                                    'threshold' => ['type' => 'integer', 'minimum' => 0, 'example' => 5000, 'description' => 'Minimum points required'],
                                    'discountPercentage' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100, 'example' => 15],
                                    'freeShippingMinOrder' => [
                                        'type' => 'object',
                                        'nullable' => true,
                                        'properties' => [
                                            'amount' => ['type' => 'integer', 'example' => 10000],
                                            'currency' => ['type' => 'string', 'example' => 'USD'],
                                        ],
                                    ],
                                    'sortOrder' => ['type' => 'integer', 'minimum' => 1, 'example' => 3],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '201' => ['description' => 'Tier created successfully'],
                    '400' => ['description' => 'Bad Request - Invalid input'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Requires ROLE_ADMIN'],
                    '409' => ['description' => 'Conflict - Tier threshold already exists'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
        new Put(
            uriTemplate: '/loyalty-programs/{programId}/tiers/{id}',
            uriVariables: [
                'programId' => [
                    'from_class' => LoyaltyTierResource::class,
                    'from_property' => 'programId',
                ],
                'id' => [
                    'from_class' => LoyaltyTierResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: UpdateLoyaltyTierProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Update a tier',
                description: 'Update an existing loyalty tier. Cannot change threshold if customers are already in this tier.',
                responses: [
                    '200' => ['description' => 'Tier updated successfully'],
                    '400' => ['description' => 'Bad Request'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Requires ROLE_ADMIN'],
                    '404' => ['description' => 'Tier not found'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
        new Delete(
            uriTemplate: '/loyalty-programs/{programId}/tiers/{id}',
            uriVariables: [
                'programId' => [
                    'from_class' => LoyaltyTierResource::class,
                    'from_property' => 'programId',
                ],
                'id' => [
                    'from_class' => LoyaltyTierResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: RemoveLoyaltyTierProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Remove a tier',
                description: 'Remove a tier from the loyalty program. Cannot remove tier if customers are currently assigned to it.',
                responses: [
                    '204' => ['description' => 'Tier removed successfully'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Requires ROLE_ADMIN'],
                    '404' => ['description' => 'Tier not found'],
                    '409' => ['description' => 'Conflict - Tier has active customers'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['loyalty_tier:read']],
    denormalizationContext: ['groups' => ['loyalty_tier:write']],
)]
class LoyaltyTierResource
{
    #[ApiProperty(identifier: true, readable: true, writable: false)]
    public ?string $id = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $programId = null;

    #[Assert\NotBlank(message: 'Tier name is required')]
    #[Assert\Length(max: 100, maxMessage: 'Tier name cannot be longer than {{ limit }} characters')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Threshold is required')]
    #[Assert\PositiveOrZero(message: 'Threshold must be zero or positive')]
    public ?int $threshold = null;

    #[Assert\NotBlank(message: 'Discount percentage is required')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Discount percentage must be between {{ min }} and {{ max }}')]
    public ?int $discountPercentage = null;

    /**
     * Free shipping minimum order (amount in cents, currency).
     *
     * @var array<string, mixed>|null
     */
    public ?array $freeShippingMinOrder = null;

    #[Assert\NotBlank(message: 'Sort order is required')]
    #[Assert\Positive(message: 'Sort order must be positive')]
    public ?int $sortOrder = null;
}
