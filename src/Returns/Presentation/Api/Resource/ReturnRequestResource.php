<?php

declare(strict_types=1);

namespace App\Returns\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Returns\Presentation\Api\Processor\ApproveReturnRequestProcessor;
use App\Returns\Presentation\Api\Processor\CompleteReturnRequestProcessor;
use App\Returns\Presentation\Api\Processor\CreateReturnRequestProcessor;
use App\Returns\Presentation\Api\Processor\InspectReturnRequestProcessor;
use App\Returns\Presentation\Api\Processor\MarkReturnAsReceivedProcessor;
use App\Returns\Presentation\Api\Processor\RejectReturnRequestProcessor;
use App\Returns\Presentation\Api\Provider\ReturnRequestCollectionProvider;
use App\Returns\Presentation\Api\Provider\ReturnRequestItemProvider;

/**
 * API Platform Resource for ReturnRequest.
 *
 * Exposes REST endpoints for return management.
 */
#[ApiResource(
    shortName: 'ReturnRequest',
    operations: [
        new Get(
            uriTemplate: '/return-requests/{id}',
            provider: ReturnRequestItemProvider::class
        ),
        new GetCollection(
            uriTemplate: '/return-requests',
            provider: ReturnRequestCollectionProvider::class
        ),
        new Post(
            uriTemplate: '/return-requests',
            processor: CreateReturnRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/return-requests/{id}/approve',
            processor: ApproveReturnRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/return-requests/{id}/receive',
            processor: MarkReturnAsReceivedProcessor::class
        ),
        new Patch(
            uriTemplate: '/return-requests/{id}/inspect',
            processor: InspectReturnRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/return-requests/{id}/complete',
            processor: CompleteReturnRequestProcessor::class
        ),
        new Patch(
            uriTemplate: '/return-requests/{id}/reject',
            processor: RejectReturnRequestProcessor::class
        ),
    ]
)]
final class ReturnRequestResource
{
    #[ApiProperty(identifier: true)]
    public ?string $id = null;

    public ?string $tenantId = null;
    public ?string $orderId = null;
    public ?string $reason = null;
    public ?string $status = null;
    public ?string $warehouseId = null;
    public ?int $refundAmount = null;
    public ?string $refundCurrency = null;
    public ?bool $isResellable = null;
    public ?string $inspectionNotes = null;
    public ?string $rejectionReason = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
}
