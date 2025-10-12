<?php

declare(strict_types=1);

namespace App\Returns\Application\DTO;

use App\Returns\Domain\Model\ReturnRequest;

/**
 * Data Transfer Object for ReturnRequest.
 *
 * Used for API responses and query results.
 */
final readonly class ReturnRequestDTO
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $orderId,
        public string $reason,
        public string $status,
        public ?string $warehouseId,
        public ?int $refundAmount,
        public ?string $refundCurrency,
        public ?bool $isResellable,
        public ?string $inspectionNotes,
        public ?string $rejectionReason,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromDomain(ReturnRequest $returnRequest): self
    {
        return new self(
            id: $returnRequest->id()->toString(),
            tenantId: $returnRequest->tenantId()->toString(),
            orderId: $returnRequest->orderId()->toString(),
            reason: $returnRequest->reason()->value(),
            status: $returnRequest->status()->value(),
            warehouseId: $returnRequest->warehouseId(),
            refundAmount: $returnRequest->refundAmount()?->getAmount(),
            refundCurrency: $returnRequest->refundAmount()?->getCurrency()->getCurrencyCode(),
            isResellable: $returnRequest->isResellable(),
            inspectionNotes: $returnRequest->inspectionNotes(),
            rejectionReason: $returnRequest->rejectionReason(),
            createdAt: $returnRequest->createdAt()->format('Y-m-d H:i:s'),
            updatedAt: $returnRequest->updatedAt()->format('Y-m-d H:i:s')
        );
    }
}
