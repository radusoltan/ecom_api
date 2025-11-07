<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Model;

use App\Inventory\Domain\Event\WarehouseActivated;
use App\Inventory\Domain\Event\WarehouseCreated;
use App\Inventory\Domain\Event\WarehouseDeactivated;
use App\Inventory\Domain\Event\WarehouseUpdated;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Warehouse Aggregate Root.
 *
 * Represents a physical location where inventory is stored and managed.
 *
 * Business Rules:
 * - Warehouse code must be unique within a tenant
 * - Active warehouses can fulfill orders
 * - Inactive warehouses cannot be used for new allocations
 * - Each warehouse has a priority for order routing
 * - Warehouses can have shipping zones for routing logic
 */
final class Warehouse extends AggregateRoot
{
    private const DEFAULT_PRIORITY = 100;

    private WarehouseId $id;
    private TenantId $tenantId;
    private WarehouseCode $code;
    private WarehouseName $name;
    private Address $address;
    private int $priority;
    private bool $isActive;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        WarehouseId $id,
        TenantId $tenantId,
        WarehouseCode $code,
        WarehouseName $name,
        Address $address,
        int $priority,
        bool $isActive,
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->code = $code;
        $this->name = $name;
        $this->address = $address;
        $this->priority = $priority;
        $this->isActive = $isActive;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Factory method to create a new warehouse.
     */
    public static function create(
        WarehouseId $id,
        TenantId $tenantId,
        WarehouseCode $code,
        WarehouseName $name,
        Address $address,
        ?int $priority = null,
    ): self {
        $warehouse = new self(
            $id,
            $tenantId,
            $code,
            $name,
            $address,
            $priority ?? self::DEFAULT_PRIORITY,
            true, // Active by default
        );

        $warehouse->recordEvent(new WarehouseCreated(
            $id,
            $tenantId,
            $code,
            $name,
            new \DateTimeImmutable()
        ));

        return $warehouse;
    }

    /**
     * Update warehouse details.
     */
    public function update(
        WarehouseName $name,
        Address $address,
        int $priority,
    ): void {
        if ($priority < 0) {
            throw new \DomainException('Warehouse priority must be non-negative');
        }

        $this->name = $name;
        $this->address = $address;
        $this->priority = $priority;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new WarehouseUpdated(
            $this->id,
            $this->name,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Activate warehouse - allows it to be used for order fulfillment.
     */
    public function activate(): void
    {
        if ($this->isActive) {
            throw new \DomainException('Warehouse is already active');
        }

        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new WarehouseActivated(
            $this->id,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Deactivate warehouse - prevents new allocations
     * Note: Existing allocations remain until fulfilled.
     */
    public function deactivate(): void
    {
        if (!$this->isActive) {
            throw new \DomainException('Warehouse is already inactive');
        }

        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->recordEvent(new WarehouseDeactivated(
            $this->id,
            new \DateTimeImmutable()
        ));
    }

    /**
     * Set warehouse priority for routing
     * Higher priority warehouses are preferred for order fulfillment.
     *
     * @param int $priority Priority value (1-10, higher = more preferred)
     *
     * @throws \DomainException if priority is invalid
     */
    public function setPriority(int $priority): void
    {
        if ($priority < 1 || $priority > 10) {
            throw new \DomainException('Warehouse priority must be between 1 and 10');
        }

        $this->priority = $priority;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Check if warehouse can fulfill orders.
     */
    public function canFulfillOrders(): bool
    {
        return $this->isActive;
    }

    // Getters
    public function id(): WarehouseId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function code(): WarehouseCode
    {
        return $this->code;
    }

    public function name(): WarehouseName
    {
        return $this->name;
    }

    public function address(): Address
    {
        return $this->address;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function isActive(): bool
    {
        return $this->isActive;
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
        WarehouseId $id,
        TenantId $tenantId,
        WarehouseCode $code,
        WarehouseName $name,
        Address $address,
        int $priority,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $warehouse = new self(
            $id,
            $tenantId,
            $code,
            $name,
            $address,
            $priority,
            $isActive,
        );

        $warehouse->createdAt = $createdAt;
        $warehouse->updatedAt = $updatedAt;
        $warehouse->clearEvents(); // Don't replay events from persistence

        return $warehouse;
    }
}
