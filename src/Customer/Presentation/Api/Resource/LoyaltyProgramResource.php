<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\ActivateLoyaltyProgramProcessor;
use App\Customer\Presentation\Api\Processor\CreateLoyaltyProgramProcessor;
use App\Customer\Presentation\Api\Processor\DeactivateLoyaltyProgramProcessor;
use App\Customer\Presentation\Api\Processor\UpdateLoyaltyProgramProcessor;
use App\Customer\Presentation\Api\Provider\LoyaltyProgramProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Loyalty Program API Resource.
 *
 * Represents a tenant's loyalty/rewards program configuration.
 * Each tenant can have one active loyalty program.
 */
#[ApiResource(
    shortName: 'LoyaltyProgram',
    description: 'Loyalty/rewards program configuration for a tenant',
    operations: [
        new Get(
            uriTemplate: '/loyalty-programs',
            provider: LoyaltyProgramProvider::class,
            security: "is_granted('ROLE_USER')",
            openapi: new Model\Operation(
                summary: 'Get tenant loyalty program',
                description: 'Retrieve the loyalty program configuration for the current tenant. Returns 404 if no program exists.',
                responses: [
                    '200' => [
                        'description' => 'Loyalty program details',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                                    'tenantId' => '00000000-0000-4000-8000-000000000001',
                                    'name' => 'Gold Rewards Program',
                                    'description' => 'Earn points on every purchase and unlock exclusive benefits',
                                    'earningRate' => 1.5,
                                    'minOrderValue' => ['amount' => 1000, 'currency' => 'USD'],
                                    'redemptionRule' => ['rate' => 100, 'currency' => 'USD'],
                                    'validityDays' => 365,
                                    'isActive' => true,
                                    'tiers' => [
                                        [
                                            'id' => '123e4567-e89b-12d3-a456-426614174000',
                                            'name' => 'Bronze',
                                            'threshold' => 0,
                                            'discountPercentage' => 5,
                                            'freeShippingMinOrder' => null,
                                            'sortOrder' => 1,
                                        ],
                                        [
                                            'id' => '123e4567-e89b-12d3-a456-426614174001',
                                            'name' => 'Silver',
                                            'threshold' => 1000,
                                            'discountPercentage' => 10,
                                            'freeShippingMinOrder' => ['amount' => 5000, 'currency' => 'USD'],
                                            'sortOrder' => 2,
                                        ],
                                    ],
                                    'createdAt' => '2024-01-15T10:30:00+00:00',
                                    'updatedAt' => '2024-01-15T10:30:00+00:00',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized - Missing or invalid authentication'],
                    '404' => ['description' => 'No loyalty program found for this tenant'],
                ],
            )
        ),
        new Post(
            uriTemplate: '/loyalty-programs',
            processor: CreateLoyaltyProgramProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Create loyalty program',
                description: 'Create a new loyalty program for the current tenant. Only one program allowed per tenant.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['name', 'earningRate', 'minOrderValue', 'minOrderCurrency', 'redemptionRate', 'redemptionCurrency'],
                                'properties' => [
                                    'name' => ['type' => 'string', 'maxLength' => 255, 'example' => 'Gold Rewards Program'],
                                    'description' => ['type' => 'string', 'nullable' => true, 'example' => 'Earn points on every purchase'],
                                    'earningRate' => ['type' => 'number', 'format' => 'float', 'minimum' => 0.01, 'example' => 1.5, 'description' => 'Points earned per currency unit spent'],
                                    'minOrderValue' => ['type' => 'integer', 'minimum' => 0, 'example' => 1000, 'description' => 'Minimum order value in cents to earn points'],
                                    'minOrderCurrency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3, 'example' => 'USD'],
                                    'redemptionRate' => ['type' => 'integer', 'minimum' => 1, 'example' => 100, 'description' => 'Points required for 1 currency unit discount'],
                                    'redemptionCurrency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3, 'example' => 'USD'],
                                    'validityDays' => ['type' => 'integer', 'minimum' => 1, 'nullable' => true, 'example' => 365, 'description' => 'Point expiration in days (null = never)'],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '201' => ['description' => 'Loyalty program created successfully'],
                    '400' => ['description' => 'Bad Request - Invalid input'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Requires ROLE_ADMIN'],
                    '409' => ['description' => 'Conflict - Loyalty program already exists for this tenant'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
        new Put(
            uriTemplate: '/loyalty-programs/{id}',
            uriVariables: [
                'id' => [
                    'from_class' => LoyaltyProgramResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: UpdateLoyaltyProgramProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Update loyalty program',
                description: 'Update the loyalty program configuration. Cannot change tiers through this endpoint (use tier-specific endpoints).',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'maxLength' => 255],
                                    'description' => ['type' => 'string', 'nullable' => true],
                                    'earningRate' => ['type' => 'number', 'format' => 'float', 'minimum' => 0.01],
                                    'minOrderValue' => ['type' => 'integer', 'minimum' => 0],
                                    'minOrderCurrency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3],
                                    'redemptionRate' => ['type' => 'integer', 'minimum' => 1],
                                    'redemptionCurrency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3],
                                    'validityDays' => ['type' => 'integer', 'minimum' => 1, 'nullable' => true],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '200' => ['description' => 'Loyalty program updated successfully'],
                    '400' => ['description' => 'Bad Request'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Requires ROLE_ADMIN'],
                    '404' => ['description' => 'Loyalty program not found'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
        new Patch(
            uriTemplate: '/loyalty-programs/{id}/activate',
            uriVariables: [
                'id' => [
                    'from_class' => LoyaltyProgramResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: ActivateLoyaltyProgramProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Activate loyalty program',
                description: 'Activate the loyalty program, allowing customers to earn and redeem points.',
                responses: [
                    '200' => ['description' => 'Loyalty program activated successfully'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Requires ROLE_ADMIN'],
                    '404' => ['description' => 'Loyalty program not found'],
                ],
            )
        ),
        new Patch(
            uriTemplate: '/loyalty-programs/{id}/deactivate',
            uriVariables: [
                'id' => [
                    'from_class' => LoyaltyProgramResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: DeactivateLoyaltyProgramProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Deactivate loyalty program',
                description: 'Deactivate the loyalty program. Customers retain their points but cannot earn or redeem while inactive.',
                responses: [
                    '200' => ['description' => 'Loyalty program deactivated successfully'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Requires ROLE_ADMIN'],
                    '404' => ['description' => 'Loyalty program not found'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['loyalty_program:read']],
    denormalizationContext: ['groups' => ['loyalty_program:write']],
)]
class LoyaltyProgramResource
{
    #[ApiProperty(identifier: true, readable: true, writable: false)]
    public ?string $id = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $tenantId = null;

    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(max: 255, maxMessage: 'Name cannot be longer than {{ limit }} characters')]
    public ?string $name = null;

    #[Assert\Length(max: 1000, maxMessage: 'Description cannot be longer than {{ limit }} characters')]
    public ?string $description = null;

    #[Assert\NotBlank(message: 'Earning rate is required')]
    #[Assert\Positive(message: 'Earning rate must be positive')]
    public ?float $earningRate = null;

    /**
     * Minimum order value configuration (amount in cents, currency).
     *
     * @var array<string, mixed>|null
     */
    #[Assert\NotBlank(message: 'Minimum order value is required')]
    public ?array $minOrderValue = null;

    /**
     * Redemption rule configuration (rate, currency).
     *
     * @var array<string, mixed>|null
     */
    #[Assert\NotBlank(message: 'Redemption rule is required')]
    public ?array $redemptionRule = null;

    #[Assert\Positive(message: 'Validity days must be positive')]
    public ?int $validityDays = null;

    #[ApiProperty(readable: true, writable: false)]
    public bool $isActive = false;

    /**
     * Loyalty tiers.
     *
     * @var array<int, array<string, mixed>>
     */
    #[ApiProperty(readable: true, writable: false)]
    public array $tiers = [];

    #[ApiProperty(readable: true, writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $updatedAt = null;
}
