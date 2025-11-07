<?php

declare(strict_types=1);

namespace App\Returns\Domain\Model;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Event\ReturnRequestApproved;
use App\Returns\Domain\Event\ReturnRequestCompleted;
use App\Returns\Domain\Event\ReturnRequestCreated;
use App\Returns\Domain\Event\ReturnRequestInspected;
use App\Returns\Domain\Event\ReturnRequestReceived;
use App\Returns\Domain\Event\ReturnRequestRejected;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Returns\Domain\ValueObject\ReturnStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * ReturnRequest Aggregate Root.
 *
 * Represents a customer's request to return an order item.
 *
 * Business Rules (from PRD Appendix B):
 * - Can only create return from Delivered orders
 * - Refund amount ≤ amount originally paid
 * - State machine enforces valid transitions
 * - Inspection required before refund
 * - Return reason required (min 10 chars)
 *
 * State Machine:
 * requested → approved → received → inspected → completed / rejected
 */
final class ReturnRequest
{
    /** @var array<object> */
    private array $recordedEvents = [];

    private function __construct(
        private ReturnRequestId $id,
        private TenantId $tenantId,
        private OrderId $orderId,
        private ReturnReason $reason,
        private ReturnStatus $status,
        private ?string $warehouseId,
        private ?Money $refundAmount,
        private ?bool $isResellable,
        private ?string $inspectionNotes,
        private ?string $rejectionReason,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * Create a new return request (RMA).
     *
     * @param OrderId      $orderId The order for which return is requested
     * @param ReturnReason $reason  Customer's reason for return
     */
    public static function create(
        ReturnRequestId $id,
        TenantId $tenantId,
        OrderId $orderId,
        ReturnReason $reason
    ): self {
        $now = new \DateTimeImmutable();

        $returnRequest = new self(
            id: $id,
            tenantId: $tenantId,
            orderId: $orderId,
            reason: $reason,
            status: ReturnStatus::requested(),
            warehouseId: null,
            refundAmount: null,
            isResellable: null,
            inspectionNotes: null,
            rejectionReason: null,
            createdAt: $now,
            updatedAt: $now
        );

        $returnRequest->recordEvent(
            ReturnRequestCreated::create($id, $tenantId, $orderId->toString(), $reason->value())
        );

        return $returnRequest;
    }

    /**
     * Approve the return request.
     *
     * Business Rules:
     * - Can only approve from 'requested' status
     * - Triggers return label generation
     */
    public function approve(): void
    {
        if (!$this->status->isRequested()) {
            throw new \DomainException(sprintf('Cannot approve return request in status "%s". Must be "requested".', $this->status->value()));
        }

        $this->status = ReturnStatus::approved();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(
            ReturnRequestApproved::create($this->id, $this->tenantId)
        );
    }

    /**
     * Mark return as received at warehouse.
     *
     * Business Rules:
     * - Can only mark received from 'approved' status
     * - Warehouse ID required
     */
    public function markAsReceived(string $warehouseId): void
    {
        if (!$this->status->isApproved()) {
            throw new \DomainException(sprintf('Cannot mark return as received in status "%s". Must be "approved".', $this->status->value()));
        }

        if ('' === trim($warehouseId)) {
            throw new \InvalidArgumentException('Warehouse ID is required when marking return as received.');
        }

        $this->status = ReturnStatus::received();
        $this->warehouseId = $warehouseId;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(
            ReturnRequestReceived::create($this->id, $this->tenantId, $warehouseId)
        );
    }

    /**
     * Record inspection results.
     *
     * Business Rules:
     * - Can only inspect from 'received' status
     * - Inspection notes required
     * - Determines if item is resellable
     */
    public function inspect(bool $isResellable, string $inspectionNotes): void
    {
        if (!$this->status->isReceived()) {
            throw new \DomainException(sprintf('Cannot inspect return in status "%s". Must be "received".', $this->status->value()));
        }

        if ('' === trim($inspectionNotes)) {
            throw new \InvalidArgumentException('Inspection notes are required.');
        }

        $this->status = ReturnStatus::inspected();
        $this->isResellable = $isResellable;
        $this->inspectionNotes = $inspectionNotes;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(
            ReturnRequestInspected::create($this->id, $this->tenantId, $isResellable, $inspectionNotes)
        );
    }

    /**
     * Complete the return (issue refund).
     *
     * Business Rules:
     * - Can only complete from 'inspected' status
     * - Refund amount required
     * - Refund amount must be ≤ original order amount (validated in application layer)
     */
    public function complete(Money $refundAmount): void
    {
        if (!$this->status->isInspected()) {
            throw new \DomainException(sprintf('Cannot complete return in status "%s". Must be "inspected".', $this->status->value()));
        }

        if ($refundAmount->isNegative()) {
            throw new \InvalidArgumentException('Refund amount must be positive.');
        }

        $this->status = ReturnStatus::completed();
        $this->refundAmount = $refundAmount;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(
            ReturnRequestCompleted::create(
                $this->id,
                $this->tenantId,
                $refundAmount->getAmount(),
                $refundAmount->getCurrency()->getCurrencyCode()
            )
        );
    }

    /**
     * Reject the return request.
     *
     * Business Rules:
     * - Can reject from 'requested' or 'inspected' status
     * - Rejection reason required
     */
    public function reject(string $rejectionReason): void
    {
        if (!$this->status->isRequested() && !$this->status->isInspected()) {
            throw new \DomainException(sprintf('Cannot reject return in status "%s". Must be "requested" or "inspected".', $this->status->value()));
        }

        if ('' === trim($rejectionReason)) {
            throw new \InvalidArgumentException('Rejection reason is required.');
        }

        $this->status = ReturnStatus::rejected();
        $this->rejectionReason = $rejectionReason;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(
            ReturnRequestRejected::create($this->id, $this->tenantId, $rejectionReason)
        );
    }

    /**
     * Reconstitute aggregate from persistence.
     * Used by repository to rebuild domain object from database.
     */
    public static function reconstituteFromPersistence(
        ReturnRequestId $id,
        TenantId $tenantId,
        OrderId $orderId,
        ReturnReason $reason,
        ReturnStatus $status,
        ?string $warehouseId,
        ?Money $refundAmount,
        ?bool $isResellable,
        ?string $inspectionNotes,
        ?string $rejectionReason,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ): self {
        return new self(
            $id,
            $tenantId,
            $orderId,
            $reason,
            $status,
            $warehouseId,
            $refundAmount,
            $isResellable,
            $inspectionNotes,
            $rejectionReason,
            $createdAt,
            $updatedAt
        );
    }

    // Getters

    public function id(): ReturnRequestId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function reason(): ReturnReason
    {
        return $this->reason;
    }

    public function status(): ReturnStatus
    {
        return $this->status;
    }

    public function warehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function refundAmount(): ?Money
    {
        return $this->refundAmount;
    }

    public function isResellable(): ?bool
    {
        return $this->isResellable;
    }

    public function inspectionNotes(): ?string
    {
        return $this->inspectionNotes;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Event management

    private function recordEvent(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @return array<object>
     */
    public function getRecordedEvents(): array
    {
        return $this->recordedEvents;
    }

    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }
}
