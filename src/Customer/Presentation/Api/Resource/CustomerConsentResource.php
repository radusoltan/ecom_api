<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\UpdateAllConsentsProcessor;
use App\Customer\Presentation\Api\Processor\UpdateSingleConsentProcessor;
use App\Customer\Presentation\Api\Provider\ConsentHistoryProvider;
use App\Customer\Presentation\Api\Provider\CustomerConsentsProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Customer Consent API Resource.
 *
 * GDPR consent management.
 * Tracks customer consent for marketing, analytics, and data sharing.
 */
#[ApiResource(
    shortName: 'CustomerConsent',
    description: 'GDPR customer consent management',
    operations: [
        new Get(
            uriTemplate: '/customers/{customerId}/consents',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerConsentResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            provider: CustomerConsentsProvider::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Get customer consents',
                description: 'Retrieve current GDPR consent status for all consent types.',
                responses: [
                    '200' => [
                        'description' => 'Current consent status',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'marketingEmail' => true,
                                    'marketingSms' => false,
                                    'thirdPartySharing' => false,
                                    'analyticsTracking' => true,
                                    'lastUpdatedAt' => '2024-01-15T10:30:00+00:00',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only view own consents'],
                    '404' => ['description' => 'Customer not found'],
                ],
            )
        ),
        new Put(
            uriTemplate: '/customers/{customerId}/consents',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerConsentResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: UpdateAllConsentsProcessor::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Update all consents',
                description: 'Update all GDPR consents at once (e.g., from consent banner). Captures IP and user agent for audit.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['marketingEmail', 'marketingSms', 'thirdPartySharing', 'analyticsTracking'],
                                'properties' => [
                                    'marketingEmail' => ['type' => 'boolean', 'example' => true],
                                    'marketingSms' => ['type' => 'boolean', 'example' => false],
                                    'thirdPartySharing' => ['type' => 'boolean', 'example' => false],
                                    'analyticsTracking' => ['type' => 'boolean', 'example' => true],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '200' => [
                        'description' => 'Consents updated successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'marketingEmail' => true,
                                    'marketingSms' => false,
                                    'thirdPartySharing' => false,
                                    'analyticsTracking' => true,
                                    'lastUpdatedAt' => '2024-01-15T10:30:00+00:00',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only update own consents'],
                    '422' => ['description' => 'Validation error'],
                ],
            )
        ),
        new Patch(
            uriTemplate: '/customers/{customerId}/consents/{type}',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerConsentResource::class,
                    'from_property' => 'customerId',
                ],
                'type' => [
                    'from_class' => CustomerConsentResource::class,
                    'from_property' => 'consentType',
                ],
            ],
            processor: UpdateSingleConsentProcessor::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Update single consent',
                description: 'Update a specific consent type. Captures IP and user agent for audit.',
                parameters: [
                    new Model\Parameter(
                        name: 'type',
                        in: 'path',
                        required: true,
                        schema: ['type' => 'string', 'enum' => ['marketing_email', 'marketing_sms', 'third_party_sharing', 'analytics_tracking']],
                        description: 'Consent type to update'
                    ),
                ],
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['granted'],
                                'properties' => [
                                    'granted' => ['type' => 'boolean', 'example' => true],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '200' => [
                        'description' => 'Consent updated successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'consentType' => 'marketing_email',
                                    'granted' => true,
                                    'updatedAt' => '2024-01-15T10:30:00+00:00',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '422' => ['description' => 'Validation error - Invalid consent type'],
                ],
            )
        ),
        new GetCollection(
            uriTemplate: '/customers/{customerId}/consents/history',
            uriVariables: [
                'customerId' => [
                    'from_class' => CustomerConsentResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            provider: ConsentHistoryProvider::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Get consent history',
                description: 'Retrieve paginated consent change history for audit purposes.',
                parameters: [
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
                        'description' => 'Consent history',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    [
                                        'id' => '550e8400-e29b-41d4-a716-446655440000',
                                        'consentType' => 'marketing_email',
                                        'consentTypeLabel' => 'Marketing Email',
                                        'granted' => true,
                                        'ipAddress' => '192.168.1.1',
                                        'userAgent' => 'Mozilla/5.0...',
                                        'createdAt' => '2024-01-15T10:30:00+00:00',
                                    ],
                                    [
                                        'id' => '660e8400-e29b-41d4-a716-446655440001',
                                        'consentType' => 'marketing_sms',
                                        'consentTypeLabel' => 'Marketing SMS',
                                        'granted' => false,
                                        'ipAddress' => '192.168.1.1',
                                        'userAgent' => 'Mozilla/5.0...',
                                        'createdAt' => '2024-01-15T10:30:00+00:00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['consent:read']],
    denormalizationContext: ['groups' => ['consent:write']],
)]
class CustomerConsentResource
{
    #[ApiProperty(identifier: false, readable: true, writable: false)]
    public ?string $customerId = null;

    #[ApiProperty(identifier: false, readable: true, writable: false)]
    public ?string $consentType = null;

    #[Assert\NotNull(message: 'Marketing email consent is required')]
    #[Assert\Type(type: 'bool', message: 'Marketing email consent must be a boolean')]
    public ?bool $marketingEmail = null;

    #[Assert\NotNull(message: 'Marketing SMS consent is required')]
    #[Assert\Type(type: 'bool', message: 'Marketing SMS consent must be a boolean')]
    public ?bool $marketingSms = null;

    #[Assert\NotNull(message: 'Third party sharing consent is required')]
    #[Assert\Type(type: 'bool', message: 'Third party sharing consent must be a boolean')]
    public ?bool $thirdPartySharing = null;

    #[Assert\NotNull(message: 'Analytics tracking consent is required')]
    #[Assert\Type(type: 'bool', message: 'Analytics tracking consent must be a boolean')]
    public ?bool $analyticsTracking = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $lastUpdatedAt = null;

    // For single consent update
    #[Assert\Type(type: 'bool', message: 'Granted must be a boolean')]
    public ?bool $granted = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $updatedAt = null;

    // For history entries
    #[ApiProperty(readable: true, writable: false)]
    public ?string $id = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $consentTypeLabel = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $ipAddress = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $userAgent = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $createdAt = null;
}
