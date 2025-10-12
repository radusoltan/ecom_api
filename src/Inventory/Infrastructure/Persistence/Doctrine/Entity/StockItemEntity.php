<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Inventory\Domain\Model\Quantity;
use App\Inventory\Domain\Model\StockItem;
use App\Inventory\Domain\Model\StockItemId;
use App\Inventory\Domain\Model\WarehouseId;
use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stock_items')]
#[ORM\Index(columns: ['tenant_id', 'product_id'], name: 'idx_stock_tenant_product')]
#[ORM\Index(columns: ['tenant_id', 'warehouse_id'], name: 'idx_stock_tenant_warehouse')]
#[ORM\UniqueConstraint(name: 'uniq_stock_product_warehouse', columns: ['tenant_id', 'product_id', 'warehouse_id'])]
class StockItemEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $productId;

    #[ORM\Column(type: 'string', length: 36, nullable: false)]
    private string $warehouseId;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $onHand;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $reserved;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $allocated;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $lowStockThreshold;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false)]
    private \DateTimeImmutable $updatedAt;

    public static function fromDomainModel(StockItem $stockItem): self
    {
        $entity = new self();
        $entity->id = $stockItem->id()->toString();
        $entity->tenantId = $stockItem->tenantId()->toString();
        $entity->productId = $stockItem->productId()->toString();
        $entity->warehouseId = $stockItem->warehouseId()->toString();
        $entity->onHand = $stockItem->onHand()->value();
        $entity->reserved = $stockItem->reserved()->value();
        $entity->allocated = $stockItem->allocated()->value();
        $entity->lowStockThreshold = $stockItem->lowStockThreshold()->value();
        $entity->createdAt = $stockItem->createdAt();
        $entity->updatedAt = $stockItem->updatedAt();

        return $entity;
    }

    public function updateFromDomainModel(StockItem $stockItem): void
    {
        $this->onHand = $stockItem->onHand()->value();
        $this->reserved = $stockItem->reserved()->value();
        $this->allocated = $stockItem->allocated()->value();
        $this->lowStockThreshold = $stockItem->lowStockThreshold()->value();
        $this->updatedAt = $stockItem->updatedAt();
    }

    public function toDomainModel(): StockItem
    {
        return StockItem::reconstituteFromPersistence(
            StockItemId::fromString($this->id),
            TenantId::fromString($this->tenantId),
            ProductId::fromString($this->productId),
            WarehouseId::fromString($this->warehouseId),
            Quantity::fromInt($this->onHand),
            Quantity::fromInt($this->reserved),
            Quantity::fromInt($this->allocated),
            Quantity::fromInt($this->lowStockThreshold),
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getWarehouseId(): string
    {
        return $this->warehouseId;
    }
}
