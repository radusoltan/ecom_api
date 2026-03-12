<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Event\StockAdjusted;
use App\Inventory\Domain\Event\StockAllocated;
use App\Inventory\Domain\Event\StockDepleted;
use App\Inventory\Domain\Event\StockReleased;
use App\Inventory\Domain\Event\StockReserved;
use App\Inventory\Domain\Event\StockTransferred;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * StockItem Aggregate Root.
 *
 * Represents inventory for a specific product at a specific warehouse.
 *
 * Business Rules:
 * - onHand >= 0 (cannot have negative stock)
 * - reserved <= onHand (cannot reserve more than available)
 * - allocated <= onHand (cannot allocate more than available)
 * - available = onHand - reserved - allocated
 * - Reservation timeout: 15 minutes
 * - Low stock alert when onHand <= lowStockThreshold
 */
final class StockItem extends AggregateRoot
{
    private const DEFAULT_LOW_STOCK_THRESHOLD = 10;
    private const RESERVATION_TIMEOUT_MINUTES = 15;

    private StockItemId $id;
    private TenantId $tenantId;
    private ProductId $productId;
    private WarehouseId $warehouseId;
    private Quantity $onHand;
    private Quantity $reserved;
    private Quantity $allocated;
    private Quantity $lowStockThreshold;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        StockItemId $id,
        TenantId $tenantId,
        ProductId $productId,
        WarehouseId $warehouseId,
        Quantity $onHand,
        Quantity $lowStockThreshold,
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->productId = $productId;
        $this->warehouseId = $warehouseId;
        $this->onHand = $onHand;
        $this->reserved = Quantity::zero();
        $this->allocated = Quantity::zero();
        $this->lowStockThreshold = $lowStockThreshold;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(
        StockItemId $id,
        TenantId $tenantId,
        ProductId $productId,
        WarehouseId $warehouseId,
        Quantity $initialQuantity,
        ?Quantity $lowStockThreshold = null,
    ): self {
        return new self(
            $id,
            $tenantId,
            $productId,
            $warehouseId,
            $initialQuantity,
            $lowStockThreshold ?? Quantity::fromInt(self::DEFAULT_LOW_STOCK_THRESHOLD),
        );
    }

    /**
     * Reserve stock (soft hold) for a cart/checkout session
     * Typically used when adding to cart or during checkout.
     */
    public function reserve(Quantity $quantity, string $reservationId): void
    {
        $available = $this->calculateAvailable();

        if ($available->isLessThan($quantity)) {
            throw new \DomainException(sprintf('Insufficient stock to reserve. Available: %d, Requested: %d', $available->value(), $quantity->value()));
        }

        $this->reserved = $this->reserved->add($quantity);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new StockReserved(
            $this->id,
            $this->tenantId,
            $this->productId,
            $quantity,
            $reservationId,
            new \DateTimeImmutable()
        ));

        $this->checkLowStock();
    }

    /**
     * Allocate stock (hard allocation) when order is confirmed
     * Moves stock from reserved to allocated.
     */
    public function allocate(Quantity $quantity, string $orderId): void
    {
        $available = $this->calculateAvailable();

        if ($available->isLessThan($quantity)) {
            throw new \DomainException(sprintf('Insufficient stock to allocate. Available: %d, Requested: %d', $available->value(), $quantity->value()));
        }

        // If stock was previously reserved, reduce reserved amount
        if ($this->reserved->isGreaterThanOrEqual($quantity)) {
            $this->reserved = $this->reserved->subtract($quantity);
        }

        $this->allocated = $this->allocated->add($quantity);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new StockAllocated(
            $this->id,
            $this->tenantId,
            $this->productId,
            $quantity,
            $orderId,
            new \DateTimeImmutable()
        ));

        $this->checkLowStock();
    }

    /**
     * Release reserved or allocated stock
     * Used when cart expires, order is cancelled, or items are returned.
     */
    public function release(Quantity $quantity, string $referenceId, string $reason): void
    {
        // Try to release from allocated first, then from reserved
        if ($this->allocated->isGreaterThanOrEqual($quantity)) {
            $this->allocated = $this->allocated->subtract($quantity);
        } elseif ($this->reserved->isGreaterThanOrEqual($quantity)) {
            $this->reserved = $this->reserved->subtract($quantity);
        } else {
            $totalReservedAndAllocated = $this->reserved->add($this->allocated);
            if ($totalReservedAndAllocated->isGreaterThanOrEqual($quantity)) {
                // Release what we can from both
                $remainingToRelease = $quantity;

                if ($this->allocated->isPositive()) {
                    $fromAllocated = $this->allocated->isGreaterThan($remainingToRelease)
                        ? $remainingToRelease
                        : $this->allocated;
                    $this->allocated = $this->allocated->subtract($fromAllocated);
                    $remainingToRelease = $remainingToRelease->subtract($fromAllocated);
                }

                if ($remainingToRelease->isPositive() && $this->reserved->isPositive()) {
                    $this->reserved = $this->reserved->subtract($remainingToRelease);
                }
            } else {
                throw new \DomainException(sprintf('Cannot release more than reserved/allocated. Reserved: %d, Allocated: %d, Requested: %d', $this->reserved->value(), $this->allocated->value(), $quantity->value()));
            }
        }

        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new StockReleased(
            $this->id,
            $this->tenantId,
            $this->productId,
            $quantity,
            $referenceId,
            $reason,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Adjust on-hand quantity (manual correction, restocking, inventory count).
     */
    public function adjust(Quantity $newQuantity, string $reason): void
    {
        $previousQuantity = $this->onHand;

        // Ensure new quantity can accommodate reserved + allocated
        $totalCommitted = $this->reserved->add($this->allocated);
        if ($newQuantity->isLessThan($totalCommitted)) {
            throw new \DomainException(sprintf('Cannot adjust to %d. Reserved + Allocated = %d', $newQuantity->value(), $totalCommitted->value()));
        }

        $this->onHand = $newQuantity;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new StockAdjusted(
            $this->id,
            $this->tenantId,
            $this->productId,
            $previousQuantity,
            $newQuantity,
            $reason,
            new \DateTimeImmutable()
        ));

        $this->checkLowStock();
    }

    /**
     * Transfer stock out to another warehouse.
     * Deducts quantity from on-hand available stock and records a transfer event.
     * The caller is responsible for adding the quantity to the destination StockItem.
     */
    public function transferOut(Quantity $quantity, WarehouseId $destinationWarehouseId): void
    {
        if (!$quantity->isPositive()) {
            throw new \DomainException('Transfer quantity must be greater than zero.');
        }

        $available = $this->calculateAvailable();

        if ($available->isLessThan($quantity)) {
            throw new \DomainException(sprintf('Insufficient available stock to transfer. Available: %d, Requested: %d', $available->value(), $quantity->value()));
        }

        $this->onHand = $this->onHand->subtract($quantity);
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new StockTransferred(
            $this->id,
            $this->productId,
            $this->warehouseId,
            $destinationWarehouseId,
            $quantity,
            $this->tenantId,
            new \DateTimeImmutable()
        ));

        $this->checkLowStock();
    }

    /**
     * Receive stock transferred in from another warehouse.
     * Increases on-hand quantity. Called on the destination StockItem.
     */
    public function transferIn(Quantity $quantity): void
    {
        $this->onHand = $this->onHand->add($quantity);
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Decrement on-hand when items are shipped.
     */
    public function decrementOnShipment(Quantity $quantity): void
    {
        if ($this->allocated->isLessThan($quantity)) {
            throw new \DomainException(sprintf('Cannot ship more than allocated. Allocated: %d, Requested: %d', $this->allocated->value(), $quantity->value()));
        }

        $this->onHand = $this->onHand->subtract($quantity);
        $this->allocated = $this->allocated->subtract($quantity);
        $this->updatedAt = new \DateTimeImmutable();

        $this->checkLowStock();
    }

    /**
     * Update low stock threshold.
     */
    public function updateLowStockThreshold(Quantity $threshold): void
    {
        $this->lowStockThreshold = $threshold;
        $this->updatedAt = new \DateTimeImmutable();

        $this->checkLowStock();
    }

    /**
     * Calculate available quantity for reservation/allocation.
     */
    public function calculateAvailable(): Quantity
    {
        return $this->onHand
            ->subtract($this->reserved)
            ->subtract($this->allocated);
    }

    /**
     * Check if stock is below threshold and record event.
     */
    private function checkLowStock(): void
    {
        $available = $this->calculateAvailable();

        if ($available->isLessThanOrEqual($this->lowStockThreshold)) {
            $this->recordEvent(new StockDepleted(
                $this->id,
                $this->productId,
                $this->warehouseId,
                $this->tenantId,
                $available,
                $this->lowStockThreshold,
                new \DateTimeImmutable()
            ));
        }
    }

    // Getters
    public function id(): StockItemId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function productId(): ProductId
    {
        return $this->productId;
    }

    public function warehouseId(): WarehouseId
    {
        return $this->warehouseId;
    }

    public function onHand(): Quantity
    {
        return $this->onHand;
    }

    public function reserved(): Quantity
    {
        return $this->reserved;
    }

    public function allocated(): Quantity
    {
        return $this->allocated;
    }

    public function lowStockThreshold(): Quantity
    {
        return $this->lowStockThreshold;
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
        StockItemId $id,
        TenantId $tenantId,
        ProductId $productId,
        WarehouseId $warehouseId,
        Quantity $onHand,
        Quantity $reserved,
        Quantity $allocated,
        Quantity $lowStockThreshold,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $stockItem = new self(
            $id,
            $tenantId,
            $productId,
            $warehouseId,
            $onHand,
            $lowStockThreshold,
        );

        $stockItem->reserved = $reserved;
        $stockItem->allocated = $allocated;
        $stockItem->createdAt = $createdAt;
        $stockItem->updatedAt = $updatedAt;

        return $stockItem;
    }
}
