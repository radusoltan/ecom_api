<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use App\Inventory\Domain\Model\Warehouse;
use App\Inventory\Domain\Model\WarehouseCode;
use App\Inventory\Domain\Model\WarehouseId;
use App\Inventory\Domain\Model\WarehouseName;
use App\Shared\Domain\ValueObject\Address;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'warehouses')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_warehouses_tenant_id')]
#[ORM\Index(columns: ['code', 'tenant_id'], name: 'idx_warehouses_code_tenant')]
#[ORM\Index(columns: ['is_active'], name: 'idx_warehouses_is_active')]
#[ORM\Index(columns: ['priority'], name: 'idx_warehouses_priority')]
#[ORM\UniqueConstraint(name: 'uniq_warehouse_code_tenant', columns: ['code', 'tenant_id'])]
#[ORM\Index(name: 'idx_warehouses_tenant_text_active_pri', columns: ['is_active', 'priority', 'name'])]
class WarehouseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'uuid')]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 10)]
    private string $code;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'json')]
    /** @var array<string, mixed> */
    private array $address;

    #[ORM\Column(type: 'integer')]
    private int $priority;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $tenantId,
        string $code,
        string $name,
        array $address,
        int $priority,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->code = $code;
        $this->name = $name;
        $this->address = $address;
        $this->priority = $priority;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): array
    {
        return $this->address;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters (for updates)
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setAddress(array $address): void
    {
        $this->address = $address;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Convert Doctrine entity to Domain aggregate.
     */
    public function toDomainModel(): Warehouse
    {
        return Warehouse::reconstituteFromPersistence(
            WarehouseId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            WarehouseCode::fromString($this->code),
            WarehouseName::fromString($this->name),
            Address::fromArray($this->address),
            $this->priority,
            $this->isActive,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    /**
     * Create Doctrine entity from Domain aggregate.
     */
    public static function fromDomainModel(Warehouse $warehouse): self
    {
        return new self(
            $warehouse->id()->toString(),
            $warehouse->tenantId()->toString(),
            $warehouse->code()->value(),
            $warehouse->name()->value(),
            [
                'street' => $warehouse->address()->street(),
                'city' => $warehouse->address()->city(),
                'state' => $warehouse->address()->state(),
                'postalCode' => $warehouse->address()->postalCode(),
                'country' => $warehouse->address()->country(),
            ],
            $warehouse->priority(),
            $warehouse->isActive(),
            $warehouse->createdAt(),
            $warehouse->updatedAt(),
        );
    }

    /**
     * Update entity from Domain aggregate (Doctrine 3.x compatible - no merge()).
     */
    public function updateFromDomainModel(Warehouse $warehouse): void
    {
        $this->name = $warehouse->name()->value();
        $this->address = [
            'street' => $warehouse->address()->street(),
            'city' => $warehouse->address()->city(),
            'state' => $warehouse->address()->state(),
            'postalCode' => $warehouse->address()->postalCode(),
            'country' => $warehouse->address()->country(),
        ];
        $this->priority = $warehouse->priority();
        $this->isActive = $warehouse->isActive();
        $this->updatedAt = $warehouse->updatedAt();
    }
}
