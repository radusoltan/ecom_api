<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Event\FulfillmentCompleted;
use App\Order\Domain\Event\FulfillmentFailed;
use App\Order\Domain\Event\FulfillmentStarted;
use App\Order\Domain\Event\FulfillmentStatusChanged;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Fulfillment Aggregate Root.
 *
 * Represents the physical process of fulfilling an order from warehouse to customer.
 *
 * Business Rules:
 * - One fulfillment per order (1:1 relationship)
 * - Status transitions must follow defined workflow
 * - Cannot modify completed/cancelled/failed fulfillments
 * - Must track timestamps for each workflow step
 * - Warehouse assignment is immutable once set
 *
 * Fulfillment Workflow:
 * PENDING → ASSIGNED → PICKING → PACKING → SHIPPING → DELIVERED
 *              ↓          ↓          ↓          ↓
 *          CANCELLED  CANCELLED  CANCELLED  FAILED
 */
final class Fulfillment extends AggregateRoot
{
    private FulfillmentId $id;
    private OrderId $orderId;
    private WarehouseId $warehouseId;
    private TenantId $tenantId;
    private FulfillmentStatus $status;
    private ?string $trackingNumber = null;
    private ?string $carrier = null;
    private ?\DateTimeImmutable $assignedAt = null;
    private ?\DateTimeImmutable $pickingStartedAt = null;
    private ?\DateTimeImmutable $packingStartedAt = null;
    private ?\DateTimeImmutable $shippedAt = null;
    private ?\DateTimeImmutable $deliveredAt = null;
    private ?\DateTimeImmutable $cancelledAt = null;
    private ?\DateTimeImmutable $failedAt = null;
    private ?string $failureReason = null;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        FulfillmentId $id,
        OrderId $orderId,
        WarehouseId $warehouseId,
        TenantId $tenantId,
        FulfillmentStatus $status,
    ) {
        $this->id = $id;
        $this->orderId = $orderId;
        $this->warehouseId = $warehouseId;
        $this->tenantId = $tenantId;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Factory method to start a new fulfillment.
     */
    public static function start(
        FulfillmentId $id,
        OrderId $orderId,
        WarehouseId $warehouseId,
        TenantId $tenantId,
    ): self {
        $fulfillment = new self(
            $id,
            $orderId,
            $warehouseId,
            $tenantId,
            FulfillmentStatus::assigned(),
        );

        $fulfillment->assignedAt = new \DateTimeImmutable();

        $fulfillment->recordEvent(new FulfillmentStarted(
            $id,
            $orderId,
            $warehouseId,
            $tenantId,
            new \DateTimeImmutable(),
        ));

        return $fulfillment;
    }

    /**
     * Transition fulfillment to picking status.
     */
    public function startPicking(): void
    {
        $this->assertNotFinalState();
        $this->assertCanTransition(FulfillmentStatus::picking());

        $oldStatus = $this->status;
        $this->status = FulfillmentStatus::picking();
        $this->pickingStartedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FulfillmentStatusChanged(
            $this->id,
            $oldStatus,
            $this->status,
            $this->tenantId,
            new \DateTimeImmutable(),
        ));
    }

    /**
     * Transition fulfillment to packing status.
     */
    public function startPacking(): void
    {
        $this->assertNotFinalState();
        $this->assertCanTransition(FulfillmentStatus::packing());

        $oldStatus = $this->status;
        $this->status = FulfillmentStatus::packing();
        $this->packingStartedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FulfillmentStatusChanged(
            $this->id,
            $oldStatus,
            $this->status,
            $this->tenantId,
            new \DateTimeImmutable(),
        ));
    }

    /**
     * Mark fulfillment as shipped with tracking information.
     */
    public function ship(string $carrier, string $trackingNumber): void
    {
        $this->assertNotFinalState();
        $this->assertCanTransition(FulfillmentStatus::shipping());

        if ('' === trim($carrier)) {
            throw new \DomainException('Carrier name is required for shipping');
        }

        if ('' === trim($trackingNumber)) {
            throw new \DomainException('Tracking number is required for shipping');
        }

        $oldStatus = $this->status;
        $this->status = FulfillmentStatus::shipping();
        $this->carrier = $carrier;
        $this->trackingNumber = $trackingNumber;
        $this->shippedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FulfillmentStatusChanged(
            $this->id,
            $oldStatus,
            $this->status,
            $this->tenantId,
            new \DateTimeImmutable(),
        ));
    }

    /**
     * Mark fulfillment as delivered (final state).
     */
    public function markAsDelivered(): void
    {
        $this->assertNotFinalState();
        $this->assertCanTransition(FulfillmentStatus::delivered());

        $oldStatus = $this->status;
        $this->status = FulfillmentStatus::delivered();
        $this->deliveredAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FulfillmentStatusChanged(
            $this->id,
            $oldStatus,
            $this->status,
            $this->tenantId,
            new \DateTimeImmutable(),
        ));

        $this->recordEvent(new FulfillmentCompleted(
            $this->id,
            $this->orderId,
            $this->tenantId,
            new \DateTimeImmutable(),
        ));
    }

    /**
     * Cancel fulfillment.
     */
    public function cancel(): void
    {
        $this->assertNotFinalState();
        $this->assertCanTransition(FulfillmentStatus::cancelled());

        $oldStatus = $this->status;
        $this->status = FulfillmentStatus::cancelled();
        $this->cancelledAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FulfillmentStatusChanged(
            $this->id,
            $oldStatus,
            $this->status,
            $this->tenantId,
            new \DateTimeImmutable(),
        ));
    }

    /**
     * Mark fulfillment as failed.
     */
    public function markAsFailed(string $reason): void
    {
        $this->assertNotFinalState();
        $this->assertCanTransition(FulfillmentStatus::failed());

        if ('' === trim($reason)) {
            throw new \DomainException('Failure reason is required');
        }

        $oldStatus = $this->status;
        $this->status = FulfillmentStatus::failed();
        $this->failureReason = $reason;
        $this->failedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new FulfillmentStatusChanged(
            $this->id,
            $oldStatus,
            $this->status,
            $this->tenantId,
            new \DateTimeImmutable(),
        ));

        $this->recordEvent(new FulfillmentFailed(
            $this->id,
            $this->orderId,
            $this->tenantId,
            $reason,
            new \DateTimeImmutable(),
        ));
    }

    /**
     * Calculate fulfillment duration (from assigned to delivered).
     */
    public function calculateDuration(): ?\DateInterval
    {
        if (null === $this->assignedAt || null === $this->deliveredAt) {
            return null;
        }

        return $this->assignedAt->diff($this->deliveredAt);
    }

    /**
     * Assert fulfillment is not in final state.
     */
    private function assertNotFinalState(): void
    {
        if ($this->status->isFinalState()) {
            throw new \DomainException(sprintf('Cannot modify fulfillment in final state: %s', $this->status->value()));
        }
    }

    /**
     * Assert status transition is valid.
     */
    private function assertCanTransition(FulfillmentStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \DomainException(sprintf('Invalid status transition from %s to %s', $this->status->value(), $newStatus->value()));
        }
    }

    // Getters
    public function id(): FulfillmentId
    {
        return $this->id;
    }

    public function orderId(): OrderId
    {
        return $this->orderId;
    }

    public function warehouseId(): WarehouseId
    {
        return $this->warehouseId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function status(): FulfillmentStatus
    {
        return $this->status;
    }

    public function trackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function carrier(): ?string
    {
        return $this->carrier;
    }

    public function assignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function pickingStartedAt(): ?\DateTimeImmutable
    {
        return $this->pickingStartedAt;
    }

    public function packingStartedAt(): ?\DateTimeImmutable
    {
        return $this->packingStartedAt;
    }

    public function shippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function deliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function failedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Reconstitute aggregate from persistence.
     */
    public static function reconstituteFromPersistence(
        FulfillmentId $id,
        OrderId $orderId,
        WarehouseId $warehouseId,
        TenantId $tenantId,
        FulfillmentStatus $status,
        ?string $trackingNumber,
        ?string $carrier,
        ?\DateTimeImmutable $assignedAt,
        ?\DateTimeImmutable $pickingStartedAt,
        ?\DateTimeImmutable $packingStartedAt,
        ?\DateTimeImmutable $shippedAt,
        ?\DateTimeImmutable $deliveredAt,
        ?\DateTimeImmutable $cancelledAt,
        ?\DateTimeImmutable $failedAt,
        ?string $failureReason,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $fulfillment = new self(
            $id,
            $orderId,
            $warehouseId,
            $tenantId,
            $status,
        );

        $fulfillment->trackingNumber = $trackingNumber;
        $fulfillment->carrier = $carrier;
        $fulfillment->assignedAt = $assignedAt;
        $fulfillment->pickingStartedAt = $pickingStartedAt;
        $fulfillment->packingStartedAt = $packingStartedAt;
        $fulfillment->shippedAt = $shippedAt;
        $fulfillment->deliveredAt = $deliveredAt;
        $fulfillment->cancelledAt = $cancelledAt;
        $fulfillment->failedAt = $failedAt;
        $fulfillment->failureReason = $failureReason;
        $fulfillment->createdAt = $createdAt;
        $fulfillment->updatedAt = $updatedAt;
        $fulfillment->clearEvents(); // Don't replay events from persistence

        return $fulfillment;
    }
}
