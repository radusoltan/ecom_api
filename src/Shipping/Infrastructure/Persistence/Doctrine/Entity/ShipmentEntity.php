<?php

declare(strict_types=1);

namespace App\Shipping\Infrastructure\Persistence\Doctrine\Entity;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shipping\Domain\Model\Shipment;
use App\Shipping\Domain\Model\ShipmentId;
use App\Shipping\Domain\Model\ShipmentStatus;
use App\Shipping\Domain\ValueObject\CarrierCode;
use App\Shipping\Domain\ValueObject\ShippingRate;
use App\Shipping\Domain\ValueObject\TrackingNumber;
use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'shipments')]
#[ORM\Index(name: 'idx_shipments_tenant_id', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_shipments_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_shipments_status', columns: ['status'])]
class ShipmentEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'uuid', name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'uuid', name: 'order_id')]
    private string $orderId;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'string', length: 10, nullable: true, name: 'carrier_code')]
    private ?string $carrierCode = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true, name: 'tracking_number')]
    private ?string $trackingNumber = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'rate_amount')]
    private ?int $rateAmount = null;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'rate_currency')]
    private ?string $rateCurrency = null;

    #[ORM\Column(type: 'integer', nullable: true, name: 'rate_estimated_days')]
    private ?int $rateEstimatedDays = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true, name: 'rate_service_name')]
    private ?string $rateServiceName = null;

    #[ORM\Column(type: 'string', length: 255, name: 'recipient_name')]
    private string $recipientName;

    /** @var array<string, string> */
    #[ORM\Column(type: 'json', name: 'recipient_address')]
    private array $recipientAddress;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'shipped_at')]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'delivered_at')]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(type: 'datetimetz_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true, name: 'updated_at')]
    private ?\DateTimeImmutable $updatedAt = null;

    public static function fromDomainModel(Shipment $shipment): self
    {
        $entity = new self();
        $entity->id = $shipment->id()->toString();
        $entity->tenantId = $shipment->tenantId()->toString();
        $entity->orderId = $shipment->orderId();
        $entity->status = $shipment->status()->value();
        $entity->carrierCode = $shipment->carrier()?->toString();
        $entity->trackingNumber = $shipment->trackingNumber()?->toString();
        $entity->recipientName = $shipment->recipientName();
        $entity->recipientAddress = $shipment->recipientAddress();
        $entity->shippedAt = $shipment->shippedAt();
        $entity->deliveredAt = $shipment->deliveredAt();
        $entity->createdAt = $shipment->createdAt();
        $entity->updatedAt = $shipment->updatedAt();

        if (null !== $shipment->rate()) {
            $entity->rateAmount = $shipment->rate()->amount()->getMinorAmount()->toInt();
            $entity->rateCurrency = $shipment->rate()->amount()->getCurrency()->getCurrencyCode();
            $entity->rateEstimatedDays = $shipment->rate()->estimatedDays();
            $entity->rateServiceName = $shipment->rate()->serviceName();
        }

        return $entity;
    }

    public function toDomainModel(): Shipment
    {
        $rate = null;
        if (null !== $this->rateAmount && null !== $this->rateCurrency && null !== $this->rateServiceName) {
            $rate = ShippingRate::create(
                Money::ofMinor($this->rateAmount, $this->rateCurrency),
                CarrierCode::fromString($this->carrierCode ?? CarrierCode::MOCK),
                $this->rateEstimatedDays ?? 0,
                $this->rateServiceName,
            );
        }

        return Shipment::reconstituteFromPersistence(
            ShipmentId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            $this->orderId,
            ShipmentStatus::fromString($this->status),
            $this->recipientName,
            $this->recipientAddress,
            null !== $this->carrierCode ? CarrierCode::fromString($this->carrierCode) : null,
            null !== $this->trackingNumber ? TrackingNumber::fromString($this->trackingNumber) : null,
            $rate,
            $this->shippedAt,
            $this->deliveredAt,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public function updateFromDomainModel(Shipment $shipment): void
    {
        $this->status = $shipment->status()->value();
        $this->carrierCode = $shipment->carrier()?->toString();
        $this->trackingNumber = $shipment->trackingNumber()?->toString();
        $this->recipientName = $shipment->recipientName();
        $this->recipientAddress = $shipment->recipientAddress();
        $this->shippedAt = $shipment->shippedAt();
        $this->deliveredAt = $shipment->deliveredAt();
        $this->updatedAt = $shipment->updatedAt();

        if (null !== $shipment->rate()) {
            $this->rateAmount = $shipment->rate()->amount()->getMinorAmount()->toInt();
            $this->rateCurrency = $shipment->rate()->amount()->getCurrency()->getCurrencyCode();
            $this->rateEstimatedDays = $shipment->rate()->estimatedDays();
            $this->rateServiceName = $shipment->rate()->serviceName();
        }
    }
}
