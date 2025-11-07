<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BundleItemEntity.
 *
 * Doctrine entity for bundle items (infrastructure layer).
 */
#[ORM\Entity]
#[ORM\Table(name: 'bundle_items')]
#[ORM\Index(name: 'idx_bundle_items_bundle_product', columns: ['bundle_product_id'])]
#[ORM\Index(name: 'idx_bundle_items_product', columns: ['product_id'])]
#[ORM\Index(name: 'idx_bundle_items_created_at', columns: ['created_at'])]
class BundleItemEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, name: 'bundle_product_id')]
    private string $bundleProductId;

    #[ORM\Column(type: 'string', length: 36, name: 'product_id')]
    private string $productId;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer', name: 'price_amount')]
    private int $priceAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'price_currency')]
    private string $priceCurrency;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getBundleProductId(): string
    {
        return $this->bundleProductId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPriceAmount(): int
    {
        return $this->priceAmount;
    }

    public function getPriceCurrency(): string
    {
        return $this->priceCurrency;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Setters
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setBundleProductId(string $bundleProductId): void
    {
        $this->bundleProductId = $bundleProductId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function setPriceAmount(int $priceAmount): void
    {
        $this->priceAmount = $priceAmount;
    }

    public function setPriceCurrency(string $priceCurrency): void
    {
        $this->priceCurrency = $priceCurrency;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
