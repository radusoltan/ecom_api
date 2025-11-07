<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * StockReservation Aggregate Root.
 *
 * Tracks individual stock reservations with expiry times.
 * Separate from StockItem to allow independent tracking of reservation timeouts.
 *
 * Business Rules:
 * - Each reservation expires after 15 minutes
 * - Expired reservations should be automatically released
 * - Reservations can be extended if user is still active
 * - Each reservation is tied to a specific warehouse for order routing
 */
final class StockReservation extends AggregateRoot
{
    private const TIMEOUT_MINUTES = 15;

    private string $reservationId;
    private StockItemId $stockItemId;
    private WarehouseId $warehouseId;
    private TenantId $tenantId;
    private Quantity $quantity;
    private \DateTimeImmutable $reservedAt;
    private \DateTimeImmutable $expiresAt;
    private bool $isReleased;
    private ?\DateTimeImmutable $releasedAt;

    private function __construct(
        string $reservationId,
        StockItemId $stockItemId,
        WarehouseId $warehouseId,
        TenantId $tenantId,
        Quantity $quantity,
        \DateTimeImmutable $reservedAt,
        \DateTimeImmutable $expiresAt,
    ) {
        $this->reservationId = $reservationId;
        $this->stockItemId = $stockItemId;
        $this->warehouseId = $warehouseId;
        $this->tenantId = $tenantId;
        $this->quantity = $quantity;
        $this->reservedAt = $reservedAt;
        $this->expiresAt = $expiresAt;
        $this->isReleased = false;
        $this->releasedAt = null;
    }

    public static function create(
        string $reservationId,
        StockItemId $stockItemId,
        WarehouseId $warehouseId,
        TenantId $tenantId,
        Quantity $quantity,
    ): self {
        $reservedAt = new \DateTimeImmutable();
        $expiresAt = $reservedAt->modify(sprintf('+%d minutes', self::TIMEOUT_MINUTES));

        return new self(
            $reservationId,
            $stockItemId,
            $warehouseId,
            $tenantId,
            $quantity,
            $reservedAt,
            $expiresAt
        );
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable();

        return !$this->isReleased && $now >= $this->expiresAt;
    }

    public function extend(?\DateTimeImmutable $now = null): void
    {
        if ($this->isReleased) {
            throw new \DomainException('Cannot extend released reservation');
        }

        $now = $now ?? new \DateTimeImmutable();
        $this->expiresAt = $now->modify(sprintf('+%d minutes', self::TIMEOUT_MINUTES));
    }

    public function release(): void
    {
        if ($this->isReleased) {
            throw new \DomainException('Reservation already released');
        }

        $this->isReleased = true;
        $this->releasedAt = new \DateTimeImmutable();
    }

    public function reservationId(): string
    {
        return $this->reservationId;
    }

    public function stockItemId(): StockItemId
    {
        return $this->stockItemId;
    }

    public function warehouseId(): WarehouseId
    {
        return $this->warehouseId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function reservedAt(): \DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isReleased(): bool
    {
        return $this->isReleased;
    }

    public function releasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedAt;
    }

    public static function reconstituteFromPersistence(
        string $reservationId,
        StockItemId $stockItemId,
        WarehouseId $warehouseId,
        TenantId $tenantId,
        Quantity $quantity,
        \DateTimeImmutable $reservedAt,
        \DateTimeImmutable $expiresAt,
        bool $isReleased,
        ?\DateTimeImmutable $releasedAt,
    ): self {
        $reservation = new self(
            $reservationId,
            $stockItemId,
            $warehouseId,
            $tenantId,
            $quantity,
            $reservedAt,
            $expiresAt
        );

        $reservation->isReleased = $isReleased;
        $reservation->releasedAt = $releasedAt;

        return $reservation;
    }
}
