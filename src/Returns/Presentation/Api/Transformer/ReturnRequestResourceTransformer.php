<?php

declare(strict_types=1);

namespace App\Returns\Presentation\Api\Transformer;

use App\Returns\Application\DTO\ReturnRequestDTO;
use App\Returns\Presentation\Api\Resource\ReturnRequestResource;

/**
 * Transformer to convert between DTO and API Resource.
 */
final class ReturnRequestResourceTransformer
{
    public static function fromDTO(ReturnRequestDTO $dto): ReturnRequestResource
    {
        $resource = new ReturnRequestResource();
        $resource->id = $dto->id;
        $resource->tenantId = $dto->tenantId;
        $resource->orderId = $dto->orderId;
        $resource->reason = $dto->reason;
        $resource->status = $dto->status;
        $resource->warehouseId = $dto->warehouseId;
        $resource->refundAmount = $dto->refundAmount;
        $resource->refundCurrency = $dto->refundCurrency;
        $resource->isResellable = $dto->isResellable;
        $resource->inspectionNotes = $dto->inspectionNotes;
        $resource->rejectionReason = $dto->rejectionReason;
        $resource->createdAt = $dto->createdAt;
        $resource->updatedAt = $dto->updatedAt;

        return $resource;
    }
}
