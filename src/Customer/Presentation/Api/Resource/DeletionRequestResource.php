<?php

declare(strict_types=1);

namespace App\Customer\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\Customer\Presentation\Api\Processor\CancelDeletionProcessor;
use App\Customer\Presentation\Api\Processor\ConfirmDeletionProcessor;
use App\Customer\Presentation\Api\Processor\HoldDeletionProcessor;
use App\Customer\Presentation\Api\Processor\ReleaseDeletionHoldProcessor;
use App\Customer\Presentation\Api\Processor\RequestDeletionProcessor;
use App\Customer\Presentation\Api\Provider\DeletionRequestStatusProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Deletion Request API Resource.
 *
 * GDPR-compliant account deletion functionality.
 * Supports customer-initiated deletion, admin holds, and 30-day grace period.
 */
#[ApiResource(
    shortName: 'DeletionRequest',
    description: 'GDPR account deletion requests',
    operations: [
        new Post(
            uriTemplate: '/customers/{customerId}/deletion-request',
            uriVariables: [
                'customerId' => [
                    'from_class' => DeletionRequestResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: RequestDeletionProcessor::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Request account deletion',
                description: 'Create a GDPR account deletion request. Execution scheduled 30 days after confirmation.',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'reason' => [
                                        'type' => 'string',
                                        'maxLength' => 500,
                                        'nullable' => true,
                                        'example' => 'No longer using the service',
                                        'description' => 'Optional reason for deletion',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '201' => [
                        'description' => 'Deletion request created',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'status' => 'pending_confirmation',
                                    'statusLabel' => 'Pending Confirmation',
                                    'reason' => 'No longer using the service',
                                    'scheduledFor' => '2024-02-14T10:30:00+00:00',
                                    'canBeCancelled' => true,
                                    'createdAt' => '2024-01-15T10:30:00+00:00',
                                    'message' => 'Deletion request created. Please check your email to confirm.',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Can only request own account deletion'],
                    '409' => ['description' => 'Conflict - Active deletion request already exists'],
                ],
            )
        ),
        new Get(
            uriTemplate: '/customers/{customerId}/deletion-request/status',
            uriVariables: [
                'customerId' => [
                    'from_class' => DeletionRequestResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            provider: DeletionRequestStatusProvider::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Get deletion request status',
                description: 'Check the status of the current account deletion request.',
                responses: [
                    '200' => [
                        'description' => 'Deletion request status',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                                    'customerId' => '123e4567-e89b-12d3-a456-426614174000',
                                    'status' => 'confirmed',
                                    'statusLabel' => 'Confirmed - Scheduled for Deletion',
                                    'reason' => 'No longer using the service',
                                    'holdReason' => null,
                                    'scheduledFor' => '2024-02-14T10:30:00+00:00',
                                    'confirmedAt' => '2024-01-15T11:00:00+00:00',
                                    'completedAt' => null,
                                    'createdAt' => '2024-01-15T10:30:00+00:00',
                                    'canBeCancelled' => true,
                                    'isOnHold' => false,
                                    'canBeExecuted' => false,
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'No active deletion request found'],
                ],
            )
        ),
        new Post(
            uriTemplate: '/customers/{customerId}/deletion-request/confirm',
            uriVariables: [
                'customerId' => [
                    'from_class' => DeletionRequestResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: ConfirmDeletionProcessor::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Confirm deletion request',
                description: 'Confirm the account deletion request. Starts the 30-day countdown.',
                responses: [
                    '200' => [
                        'description' => 'Deletion request confirmed',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                                    'status' => 'confirmed',
                                    'statusLabel' => 'Confirmed - Scheduled for Deletion',
                                    'scheduledFor' => '2024-02-14T10:30:00+00:00',
                                    'confirmedAt' => '2024-01-15T11:00:00+00:00',
                                    'message' => 'Deletion confirmed. Your account will be deleted on 2024-02-14.',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'No pending deletion request found'],
                    '409' => ['description' => 'Conflict - Already confirmed or cancelled'],
                ],
            )
        ),
        new Delete(
            uriTemplate: '/customers/{customerId}/deletion-request',
            uriVariables: [
                'customerId' => [
                    'from_class' => DeletionRequestResource::class,
                    'from_property' => 'customerId',
                ],
            ],
            processor: CancelDeletionProcessor::class,
            security: "is_granted('customer.view') and user.getCustomerId() == customerId",
            openapi: new Model\Operation(
                summary: 'Cancel deletion request',
                description: 'Cancel a pending or confirmed deletion request.',
                responses: [
                    '204' => ['description' => 'Deletion request cancelled successfully'],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden'],
                    '404' => ['description' => 'No cancellable deletion request found'],
                    '409' => ['description' => 'Conflict - Cannot cancel (already completed or on hold)'],
                ],
            )
        ),
        new Patch(
            uriTemplate: '/admin/deletion-requests/{requestId}/hold',
            uriVariables: [
                'requestId' => [
                    'from_class' => DeletionRequestResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: HoldDeletionProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Put deletion request on hold',
                description: 'Admin: Put a deletion request on hold (e.g., for legal investigation).',
                requestBody: new Model\RequestBody(
                    content: new \ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['reason'],
                                'properties' => [
                                    'reason' => [
                                        'type' => 'string',
                                        'maxLength' => 500,
                                        'example' => 'Legal investigation in progress',
                                        'description' => 'Reason for placing on hold',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
                responses: [
                    '200' => [
                        'description' => 'Deletion request placed on hold',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                                    'status' => 'on_hold',
                                    'statusLabel' => 'On Hold',
                                    'holdReason' => 'Legal investigation in progress',
                                    'isOnHold' => true,
                                    'message' => 'Deletion request placed on hold',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Admin only'],
                    '404' => ['description' => 'Deletion request not found'],
                    '409' => ['description' => 'Conflict - Already on hold or completed'],
                ],
            )
        ),
        new Patch(
            uriTemplate: '/admin/deletion-requests/{requestId}/release',
            uriVariables: [
                'requestId' => [
                    'from_class' => DeletionRequestResource::class,
                    'from_property' => 'id',
                ],
            ],
            processor: ReleaseDeletionHoldProcessor::class,
            security: "is_granted('ROLE_ADMIN')",
            openapi: new Model\Operation(
                summary: 'Release deletion request hold',
                description: 'Admin: Release a deletion request from hold status.',
                responses: [
                    '200' => [
                        'description' => 'Deletion request released from hold',
                        'content' => [
                            'application/json' => [
                                'example' => [
                                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                                    'status' => 'confirmed',
                                    'statusLabel' => 'Confirmed - Scheduled for Deletion',
                                    'holdReason' => null,
                                    'isOnHold' => false,
                                    'message' => 'Deletion request released from hold',
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                    '403' => ['description' => 'Forbidden - Admin only'],
                    '404' => ['description' => 'Deletion request not found'],
                    '409' => ['description' => 'Conflict - Not on hold'],
                ],
            )
        ),
    ],
    normalizationContext: ['groups' => ['deletion_request:read']],
    denormalizationContext: ['groups' => ['deletion_request:write']],
)]
class DeletionRequestResource
{
    #[ApiProperty(identifier: true, readable: true, writable: false)]
    public ?string $id = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $customerId = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $status = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $statusLabel = null;

    #[Assert\Length(max: 500, maxMessage: 'Reason cannot exceed {{ limit }} characters')]
    public ?string $reason = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $holdReason = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $scheduledFor = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $confirmedAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $completedAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $createdAt = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?bool $canBeCancelled = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?bool $isOnHold = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?bool $canBeExecuted = null;

    #[ApiProperty(readable: true, writable: false)]
    public ?string $message = null;
}
