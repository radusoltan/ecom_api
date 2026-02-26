<?php

declare(strict_types=1);

namespace App\Returns\Infrastructure\Persistence\Doctrine\Entity;

use App\Order\Domain\Model\OrderId;
use App\Returns\Domain\Model\ReturnRequest;
use App\Returns\Domain\ValueObject\ReturnReason;
use App\Returns\Domain\ValueObject\ReturnRequestId;
use App\Returns\Domain\ValueObject\ReturnStatus;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine Entity for ReturnRequest aggregate.
 *
 * This is the infrastructure adapter that bridges between:
 * - Pure domain model (ReturnRequest)
 * - Database persistence (PostgreSQL)
 */
#[ORM\Entity]
#[ORM\Table(name: 'return_requests')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_returns_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_returns_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_returns_status', columns: ['status'])]
#[ORM\Index(name: 'idx_returns_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_returns_created_at', columns: ['created_at'])]
class ReturnRequestEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36, name: 'order_id')]
    private string $orderId;

    #[ORM\Column(type: 'string', length: 500)]
    private string $reason;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'string', length: 36, nullable: true, name: 'warehouse_id')]
    private ?string $warehouseId = null;

    #[ORM\Column(type: 'float', nullable: true, name: 'refund_amount')]
    private ?float $refundAmount = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'refund_currency')]
    private ?string $refundCurrency = null;

    #[ORM\Column(type: 'boolean', nullable: true, name: 'is_resellable')]
    private ?bool $isResellable = null;

    #[ORM\Column(type: 'text', nullable: true, name: 'inspection_notes')]
    private ?string $inspectionNotes = null;

    #[ORM\Column(type: 'text', nullable: true, name: 'rejection_reason')]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    /**
     * Convert domain model to Doctrine entity.
     */
    public static function fromDomainModel(ReturnRequest $returnRequest): self
    {
        $entity = new self();
        $entity->id = $returnRequest->id()->toString();
        $entity->tenantId = $returnRequest->tenantId()->toString();
        $entity->orderId = $returnRequest->orderId()->toString();
        $entity->reason = $returnRequest->reason()->value();
        $entity->status = $returnRequest->status()->value();
        $entity->warehouseId = $returnRequest->warehouseId();
        $entity->isResellable = $returnRequest->isResellable();
        $entity->inspectionNotes = $returnRequest->inspectionNotes();
        $entity->rejectionReason = $returnRequest->rejectionReason();
        $entity->createdAt = $returnRequest->createdAt();
        $entity->updatedAt = $returnRequest->updatedAt();

        if (null !== $returnRequest->refundAmount()) {
            $entity->refundAmount = $returnRequest->refundAmount()->getAmount();
            $entity->refundCurrency = $returnRequest->refundAmount()->getCurrency()->getCurrencyCode();
        }

        return $entity;
    }

    /**
     * Update entity from domain model (for updates).
     * Used instead of deprecated merge() in Doctrine 3.x.
     */
    public function updateFromDomainModel(ReturnRequest $returnRequest): void
    {
        // ID and tenantId never change
        $this->orderId = $returnRequest->orderId()->toString();
        $this->reason = $returnRequest->reason()->value();
        $this->status = $returnRequest->status()->value();
        $this->warehouseId = $returnRequest->warehouseId();
        $this->isResellable = $returnRequest->isResellable();
        $this->inspectionNotes = $returnRequest->inspectionNotes();
        $this->rejectionReason = $returnRequest->rejectionReason();
        $this->updatedAt = $returnRequest->updatedAt();

        if (null !== $returnRequest->refundAmount()) {
            $this->refundAmount = $returnRequest->refundAmount()->getAmount();
            $this->refundCurrency = $returnRequest->refundAmount()->getCurrency()->getCurrencyCode();
        } else {
            $this->refundAmount = null;
            $this->refundCurrency = null;
        }
    }

    /**
     * Convert Doctrine entity to domain model.
     */
    public function toDomainModel(): ReturnRequest
    {
        $refundAmount = null;
        if (null !== $this->refundAmount && null !== $this->refundCurrency) {
            $refundAmount = Money::fromScalars((int) $this->refundAmount, $this->refundCurrency);
        }

        return ReturnRequest::reconstituteFromPersistence(
            id: ReturnRequestId::fromString($this->id),
            tenantId: TenantId::fromString($this->tenantId),
            orderId: OrderId::fromString($this->orderId),
            reason: ReturnReason::fromString($this->reason),
            status: ReturnStatus::fromString($this->status),
            warehouseId: $this->warehouseId,
            refundAmount: $refundAmount,
            isResellable: $this->isResellable,
            inspectionNotes: $this->inspectionNotes,
            rejectionReason: $this->rejectionReason,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt
        );
    }

    // Getters

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getWarehouseId(): ?string
    {
        return $this->warehouseId;
    }

    public function getRefundAmount(): ?float
    {
        return $this->refundAmount;
    }

    public function getRefundCurrency(): ?string
    {
        return $this->refundCurrency;
    }

    public function isResellable(): ?bool
    {
        return $this->isResellable;
    }

    public function getInspectionNotes(): ?string
    {
        return $this->inspectionNotes;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
