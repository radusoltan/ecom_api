<?php

declare(strict_types=1);

namespace App\Shipping\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Event\ShipmentCancelled;
use App\Shipping\Domain\Event\ShipmentCreated;
use App\Shipping\Domain\Event\ShipmentDelivered;
use App\Shipping\Domain\Event\ShipmentDispatched;
use App\Shipping\Domain\Event\ShipmentInTransit;
use App\Shipping\Domain\Exception\InvalidShipmentTransitionException;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;

final class Shipment extends AggregateRoot
{
    private ShipmentId $id;
    private TenantId $tenantId;
    private string $orderId;
    private ShipmentStatus $status;
    private ?CarrierCode $carrier = null;
    private ?TrackingNumber $trackingNumber = null;
    private ?ShippingRate $rate = null;
    private string $recipientName;
    /** @var array<string, mixed> */
    private array $recipientAddress;
    private ?\DateTimeImmutable $shippedAt = null;
    private ?\DateTimeImmutable $deliveredAt = null;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt = null;

    private function __construct()
    {
    }

    /** @param array<string, mixed> $recipientAddress */
    public static function create(
        ShipmentId $id,
        TenantId $tenantId,
        string $orderId,
        string $recipientName,
        array $recipientAddress,
        ?ShippingRate $rate = null,
    ): self {
        if ('' === trim($orderId)) {
            throw new \InvalidArgumentException('Order ID cannot be empty');
        }
        if ('' === trim($recipientName)) {
            throw new \InvalidArgumentException('Recipient name cannot be empty');
        }

        $shipment = new self();
        $shipment->id = $id;
        $shipment->tenantId = $tenantId;
        $shipment->orderId = $orderId;
        $shipment->status = ShipmentStatus::pending();
        $shipment->recipientName = $recipientName;
        $shipment->recipientAddress = $recipientAddress;
        $shipment->rate = $rate;
        $shipment->createdAt = new \DateTimeImmutable();

        $shipment->recordEvent(new ShipmentCreated(
            $shipment->id, $shipment->tenantId, $shipment->orderId, $shipment->recipientName, $shipment->createdAt,
        ));

        return $shipment;
    }

    /** @param array<string, mixed> $recipientAddress */
    public static function reconstituteFromPersistence(
        ShipmentId $id,
        TenantId $tenantId,
        string $orderId,
        ShipmentStatus $status,
        string $recipientName,
        array $recipientAddress,
        ?CarrierCode $carrier,
        ?TrackingNumber $trackingNumber,
        ?ShippingRate $rate,
        ?\DateTimeImmutable $shippedAt,
        ?\DateTimeImmutable $deliveredAt,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $updatedAt,
    ): self {
        $shipment = new self();
        $shipment->id = $id;
        $shipment->tenantId = $tenantId;
        $shipment->orderId = $orderId;
        $shipment->status = $status;
        $shipment->recipientName = $recipientName;
        $shipment->recipientAddress = $recipientAddress;
        $shipment->carrier = $carrier;
        $shipment->trackingNumber = $trackingNumber;
        $shipment->rate = $rate;
        $shipment->shippedAt = $shippedAt;
        $shipment->deliveredAt = $deliveredAt;
        $shipment->createdAt = $createdAt;
        $shipment->updatedAt = $updatedAt;

        return $shipment;
    }

    public function dispatch(CarrierCode $carrier, TrackingNumber $trackingNumber): void
    {
        if (!$this->status->canTransitionTo(ShipmentStatus::dispatched())) {
            throw InvalidShipmentTransitionException::fromTransition($this->status->value(), ShipmentStatus::DISPATCHED);
        }

        $this->carrier = $carrier;
        $this->trackingNumber = $trackingNumber;
        $this->shippedAt = new \DateTimeImmutable();
        $this->status = ShipmentStatus::dispatched();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ShipmentDispatched(
            $this->id, $this->tenantId, $carrier, $trackingNumber, $this->shippedAt, new \DateTimeImmutable(),
        ));
    }

    public function markInTransit(): void
    {
        if (!$this->status->canTransitionTo(ShipmentStatus::inTransit())) {
            throw InvalidShipmentTransitionException::fromTransition($this->status->value(), ShipmentStatus::IN_TRANSIT);
        }

        $this->status = ShipmentStatus::inTransit();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ShipmentInTransit($this->id, $this->tenantId, '', 'Shipment is in transit', new \DateTimeImmutable()));
    }

    public function updateTracking(string $location, string $statusNote): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordEvent(new ShipmentInTransit($this->id, $this->tenantId, $location, $statusNote, new \DateTimeImmutable()));
    }

    public function markDelivered(): void
    {
        if (!$this->status->canTransitionTo(ShipmentStatus::delivered())) {
            throw InvalidShipmentTransitionException::fromTransition($this->status->value(), ShipmentStatus::DELIVERED);
        }

        $this->deliveredAt = new \DateTimeImmutable();
        $this->status = ShipmentStatus::delivered();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ShipmentDelivered($this->id, $this->tenantId, $this->deliveredAt, new \DateTimeImmutable()));
    }

    public function cancel(): void
    {
        if (!$this->status->canTransitionTo(ShipmentStatus::cancelled())) {
            throw InvalidShipmentTransitionException::fromTransition($this->status->value(), ShipmentStatus::CANCELLED);
        }

        $this->status = ShipmentStatus::cancelled();
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new ShipmentCancelled($this->id, $this->tenantId, $this->updatedAt, new \DateTimeImmutable()));
    }

    public function markFailed(string $reason): void
    {
        if (!$this->status->canTransitionTo(ShipmentStatus::failed())) {
            throw InvalidShipmentTransitionException::fromTransition($this->status->value(), ShipmentStatus::FAILED);
        }

        $this->status = ShipmentStatus::failed();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): ShipmentId { return $this->id; }
    public function tenantId(): TenantId { return $this->tenantId; }
    public function orderId(): string { return $this->orderId; }
    public function status(): ShipmentStatus { return $this->status; }
    public function carrier(): ?CarrierCode { return $this->carrier; }
    public function trackingNumber(): ?TrackingNumber { return $this->trackingNumber; }
    public function rate(): ?ShippingRate { return $this->rate; }
    public function recipientName(): string { return $this->recipientName; }
    /** @return array<string, mixed> */
    public function recipientAddress(): array { return $this->recipientAddress; }
    public function shippedAt(): ?\DateTimeImmutable { return $this->shippedAt; }
    public function deliveredAt(): ?\DateTimeImmutable { return $this->deliveredAt; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
}
