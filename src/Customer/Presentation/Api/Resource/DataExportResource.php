<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\RequestDataExportProcessor;
use App\Customer\Presentation\Api\Provider\DataExportDownloadProvider;
use App\Customer\Presentation\Api\Provider\DataExportStatusProvider;

/**
 * Data Export API Resource.
 *
 * GDPR-compliant data export functionality.
 * Allows customers to request, track, and download their personal data.
 */
#[ApiResource(
    shortName: 'DataExport',
    description: 'GDPR data export requests',
    operations: [
        new Post(
            uriTemplate: '/customers/{customerId}/data-export/request',
            uriVariables: [
                'customerId' => [
                    'from_class' => DataExportResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: RequestDataExportProcessor::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Request data export',
                description: 'Create a new GDPR data export request. Rate limited to 1 request per 24 hours.',
                responses: [
                    '201' => [
                        'description' => 'Export request created successfully',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'requestId' => '550e8400-e29b-41d4-a716-446655440000',
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'status' => 'pending',
                                    'message' => 'Export request created. Processing will begin shortly.',
                                    'estimatedCompletionMinutes' => 30,
                                    'createdAt' => '2024-01-15T10:30:00+00:00',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized - Missing authentication'],
                    '403' => ['description' => 'Forbidden - Can only request own data'],
                    '429' => ['description' => 'Too Many Requests - Rate limit exceeded (1 per 24h)'],
                ],
            )
        ),
        new Get(
            uriTemplate: '/customers/{customerId}/data-export/{requestId}/status',
            uriVariables: [
                'customerId' => [
                    'from_class' => DataExportResource::class,
                    'from_property' => 'customerId',
                ],
                'requestId' => [
                    'from_class' => DataExportResource::class,
                    'from_property' => 'requestId',
                ],
            ],
            provider: DataExportStatusProvider::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Get export status',
                description: 'Check the status of a data export request. Includes download availability and expiration.',
                responses: [
                    '200' => [
                        'description' => 'Export request status',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                                    'status' => 'completed',
                                    'isDownloadable' => true,
                                    'expiresAt' => '2024-01-22T10:30:00+00:00',
                                    'createdAt' => '2024-01-15T10:30:00+00:00',
                                    'completedAt' => '2024-01-15T10:45:00+00:00',
                                    'downloadUrl' => '/api/customers/123e4567-e89b-12d3-a456-426614174000/data-export/550e8400-e29b-41d4-a716-446655440000/download',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only view own requests'],
                    '404' => ['description' => 'Export request not found'],
                ],
            )
        ),
        new Get(
            uriTemplate: '/customers/{customerId}/data-export/{requestId}/download',
            uriVariables: [
                'customerId' => [
                    'from_class' => DataExportResource::class,
                    'from_property' => 'customerId',
                ],
                'requestId' => [
                    'from_class' => DataExportResource::class,
                    'from_property' => 'requestId',
                ],
            ],
            provider: DataExportDownloadProvider::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Download export file',
                description: 'Download the exported data as a JSON file. Available for 7 days after completion.',
                responses: [
                    '200' => [
                        'description' => 'Export file download',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'description' => 'Complete customer data export',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only download own data'],
                    '404' => ['description' => 'Export not found or expired'],
                    '410' => ['description' => 'Gone - Export has expired'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['data_export:read']],
    denormalizationContext: ['groups' => ['data_export:write']],
)]
class DataExportResource
{
    #[ApiProperty(identifier: true, readable: true, writable: false)]
    public ?string $requestId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $customerId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $status = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?bool $isDownloadable = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $expiresAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $completedAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $errorMessage = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $downloadUrl = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $message = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?int $estimatedCompletionMinutes = null;
}
