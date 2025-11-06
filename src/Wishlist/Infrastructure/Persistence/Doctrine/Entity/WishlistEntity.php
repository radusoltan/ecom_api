<?php

declare(strict_types=1);

namespace App\Wishlist\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\ValueObject\WishlistId;
use App\Wishlist\Domain\ValueObject\WishlistItem;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for Wishlist
 */
#[ORM\Entity]
#[ORM\Table(name: 'wishlists')]
#[ORM\Index(name: 'idx_wishlists_customer', columns: ['customer_id', 'tenant_id'])]
#[ORM\Index(name: 'idx_wishlists_tenant', columns: ['tenant_id'])]
#[ORM\UniqueConstraint(name: 'uniq_customer_tenant', columns: ['customer_id', 'tenant_id'])]
class WishlistEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255, name: 'customer_id')]
    private string $customerId;

    #[ORM\Column(type: 'string', length: 36, name: 'tenant_id')]
    private string $tenantId;

    /**
     * JSON array of wishlist items
     * Format: [{"productId": "uuid", "addedAt": "2024-01-01 10:00:00"}, ...]
     */
    #[ORM\Column(type: 'json')]
    private array $items = [];

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Create entity from domain model
     */
    public static function fromDomainModel(Wishlist $wishlist): self
    {
        $entity = new self();
        $entity->id = $wishlist->id()->toString();
        $entity->customerId = $wishlist->customerId();
        $entity->tenantId = $wishlist->tenantId()->toString();
        $entity->items = array_map(
            fn(WishlistItem $item) => $item->toArray(),
            $wishlist->items()
        );
        $entity->createdAt = $wishlist->createdAt();
        $entity->updatedAt = $wishlist->updatedAt();

        return $entity;
    }

    /**
     * Convert to domain model
     */
    public function toDomainModel(): Wishlist
    {
        $items = [];
        foreach ($this->items as $itemData) {
            $productId = ProductId::fromString($itemData['productId']);
            $addedAt = new \DateTimeImmutable($itemData['addedAt']);
            $items[$productId->toString()] = WishlistItem::reconstitute($productId, $addedAt);
        }

        return Wishlist::reconstituteFromPersistence(
            WishlistId::fromString($this->id),
            $this->customerId,
            TenantId::fromString($this->tenantId),
            $items,
            $this->createdAt,
            $this->updatedAt
        );
    }

    /**
     * Update entity from domain model
     */
    public function updateFromDomainModel(Wishlist $wishlist): void
    {
        $this->items = array_map(
            fn(WishlistItem $item) => $item->toArray(),
            $wishlist->items()
        );
        $this->updatedAt = $wishlist->updatedAt();
    }

    // Getters

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getItems(): array
    {
        return $this->items;
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
