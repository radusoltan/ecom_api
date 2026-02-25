<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Doctrine\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Inventory\Domain\Model\WarehouseId;
use App\Order\Domain\Model\Fulfillment;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\ValueObject\FulfillmentId;
use App\Order\Domain\ValueObject\FulfillmentStatus;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/fulfillments',
            provider: \App\Order\Infrastructure\ApiPlatform\State\FulfillmentCollectionProvider::class
        ),
        new Get(
            uriTemplate: '/fulfillments/{id}',
            provider: \App\Order\Infrastructure\ApiPlatform\State\FulfillmentItemProvider::class
        ),
        new Patch(
            uriTemplate: '/fulfillments/{id}/start-picking',
            processor: \App\Order\Infrastructure\ApiPlatform\State\StartPickingProcessor::class
        ),
        new Patch(
            uriTemplate: '/fulfillments/{id}/start-packing',
            processor: \App\Order\Infrastructure\ApiPlatform\State\StartPackingProcessor::class
        ),
        new Patch(
            uriTemplate: '/fulfillments/{id}/ship',
            processor: \App\Order\Infrastructure\ApiPlatform\State\ShipFulfillmentProcessor::class
        ),
        new Patch(
            uriTemplate: '/fulfillments/{id}/deliver',
            processor: \App\Order\Infrastructure\ApiPlatform\State\DeliverFulfillmentProcessor::class
        ),
        new Patch(
            uriTemplate: '/fulfillments/{id}/cancel',
            processor: \App\Order\Infrastructure\ApiPlatform\State\CancelFulfillmentProcessor::class
        ),
        new Patch(
            uriTemplate: '/fulfillments/{id}/mark-failed',
            processor: \App\Order\Infrastructure\ApiPlatform\State\MarkFailedProcessor::class
        ),
    ],
    paginationEnabled: true,
    paginationItemsPerPage: 30
)]
#[ORM\Entity]
#[ORM\Table(name: 'fulfillments')]
// Performance indexes for queries
#[ORM\Index(name: 'idx_fulfillments_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_fulfillments_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_fulfillments_warehouse_id', columns: ['warehouse_id'])]
#[ORM\Index(name: 'idx_fulfillments_status', columns: ['status'])]
#[ORM\Index(name: 'idx_fulfillments_tenant_status', columns: ['tenant_id', 'status'])]
#[ORM\Index(name: 'idx_fulfillments_created_at', columns: ['created_at'])]
class FulfillmentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'order_id')]
    private string $orderId;

    #[ORM\Column(type: 'string', length: 36, name: 'warehouse_id')]
    private string $warehouseId;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'string', length: 50, nullable: true, name: 'tracking_number')]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $carrier = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'assigned_at')]
    private ?\DateTimeImmutable $assignedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'picking_started_at')]
    private ?\DateTimeImmutable $pickingStartedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'packing_started_at')]
    private ?\DateTimeImmutable $packingStartedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'shipped_at')]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'delivered_at')]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'cancelled_at')]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true, name: 'failed_at')]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(type: 'text', nullable: true, name: 'failure_reason')]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(Fulfillment $fulfillment): self
    {
        $entity = new self();
        $entity->id = $fulfillment->id()->toString();
        $entity->orderId = $fulfillment->orderId()->toString();
        $entity->warehouseId = $fulfillment->warehouseId()->toString();
        $entity->tenantId = $fulfillment->tenantId()->toString();
        $entity->status = $fulfillment->status()->value();
        $entity->trackingNumber = $fulfillment->trackingNumber();
        $entity->carrier = $fulfillment->carrier();
        $entity->assignedAt = $fulfillment->assignedAt();
        $entity->pickingStartedAt = $fulfillment->pickingStartedAt();
        $entity->packingStartedAt = $fulfillment->packingStartedAt();
        $entity->shippedAt = $fulfillment->shippedAt();
        $entity->deliveredAt = $fulfillment->deliveredAt();
        $entity->cancelledAt = $fulfillment->cancelledAt();
        $entity->failedAt = $fulfillment->failedAt();
        $entity->failureReason = $fulfillment->failureReason();
        $entity->createdAt = $fulfillment->createdAt();
        $entity->updatedAt = $fulfillment->updatedAt();

        return $entity;
    }

    /**
     * Update this entity's properties from a domain model
     * Used for updating existing entities without creating new instances.
     */
    public function updateFromDomainModel(Fulfillment $fulfillment): void
    {
        // Note: id, orderId, warehouseId, tenantId should never change
        $this->status = $fulfillment->status()->value();
        $this->trackingNumber = $fulfillment->trackingNumber();
        $this->carrier = $fulfillment->carrier();
        $this->assignedAt = $fulfillment->assignedAt();
        $this->pickingStartedAt = $fulfillment->pickingStartedAt();
        $this->packingStartedAt = $fulfillment->packingStartedAt();
        $this->shippedAt = $fulfillment->shippedAt();
        $this->deliveredAt = $fulfillment->deliveredAt();
        $this->cancelledAt = $fulfillment->cancelledAt();
        $this->failedAt = $fulfillment->failedAt();
        $this->failureReason = $fulfillment->failureReason();
        $this->updatedAt = $fulfillment->updatedAt();
    }

    public function toDomainModel(): Fulfillment
    {
        return Fulfillment::reconstituteFromPersistence(
            FulfillmentId::fromString($this->id),
            OrderId::fromString($this->orderId),
            WarehouseId::fromString($this->warehouseId),
            TenantId::fromString($this->tenantId),
            FulfillmentStatus::fromString($this->status),
            $this->trackingNumber,
            $this->carrier,
            $this->assignedAt,
            $this->pickingStartedAt,
            $this->packingStartedAt,
            $this->shippedAt,
            $this->deliveredAt,
            $this->cancelledAt,
            $this->failedAt,
            $this->failureReason,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getCarrier(): ?string
    {
        return $this->carrier;
    }

    public function getAssignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function getPickingStartedAt(): ?\DateTimeImmutable
    {
        return $this->pickingStartedAt;
    }

    public function getPackingStartedAt(): ?\DateTimeImmutable
    {
        return $this->packingStartedAt;
    }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getFailedAt(): ?\DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
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
