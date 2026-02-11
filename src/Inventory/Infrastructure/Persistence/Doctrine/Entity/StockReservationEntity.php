<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\StockReservation;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stock_reservations')]
#[ORM\Index(columns: ['tenant_id', 'expires_at', 'is_released'], name: 'idx_reservation_expiry')]
#[ORM\Index(columns: ['stock_item_id'], name: 'idx_reservation_stock_item')]
#[ORM\Index(columns: ['warehouse_id'], name: 'idx_reservation_warehouse')]
class StockReservationEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'stock_item_id')]
    private string $stockItemId;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'warehouse_id')]
    private string $warehouseId;

    #[ORM\Column(type: 'string', length: 36, nullable: false, name: 'tenant_id')]
    private string $tenantId;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $quantity;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false, name: 'reserved_at')]
    private \DateTimeImmutable $reservedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false, name: 'expires_at')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'boolean', nullable: false, name: 'is_released')]
    private bool $isReleased;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'released_at')]
    private ?\DateTimeImmutable $releasedAt;

    public static function fromDomainModel(StockReservation $reservation): self
    {
        $entity = new self();
        $entity->id = $reservation->reservationId();
        $entity->stockItemId = $reservation->stockItemId()->toString();
        $entity->warehouseId = $reservation->warehouseId()->toString();
        $entity->tenantId = $reservation->tenantId()->toString();
        $entity->quantity = $reservation->quantity()->value();
        $entity->reservedAt = $reservation->reservedAt();
        $entity->expiresAt = $reservation->expiresAt();
        $entity->isReleased = $reservation->isReleased();
        $entity->releasedAt = $reservation->releasedAt();

        return $entity;
    }

    public function updateFromDomainModel(StockReservation $reservation): void
    {
        $this->expiresAt = $reservation->expiresAt();
        $this->isReleased = $reservation->isReleased();
        $this->releasedAt = $reservation->releasedAt();
    }

    public function toDomainModel(): StockReservation
    {
        return StockReservation::reconstituteFromPersistence(
            $this->id,
            StockItemId::fromString($this->stockItemId),
            WarehouseId::fromString($this->warehouseId),
            TenantId::fromString($this->tenantId),
            Quantity::fromInt($this->quantity),
            $this->reservedAt,
            $this->expiresAt,
            $this->isReleased,
            $this->releasedAt
        );
    }

    public function getId(): string
    {
        return $this->id;
    }
}
