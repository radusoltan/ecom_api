<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Persistence\Doctrine\Entity;

use App\Cart\Domain\Model\CartItem;
use App\Cart\Domain\Model\CartItemId;
use App\Cart\Domain\Model\Quantity;
use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cart_items')]
#[ORM\Index(name: 'idx_cart_items_cart_id', columns: ['cart_id'])]
#[ORM\Index(name: 'idx_cart_items_product', columns: ['product_id', 'variant_id'])]
#[ORM\Index(name: 'idx_cart_items_tenant_id', columns: ['tenant_id'])]
class CartItemEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 26)]
    private string $id;

    #[ORM\Column(type: 'guid', name: 'tenant_id')]
    private string $tenantId;

    #[ORM\ManyToOne(targetEntity: CartEntity::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'cart_id', referencedColumnName: 'id', nullable: false)]
    private ?CartEntity $cart = null;

    #[ORM\Column(type: 'uuid', name: 'product_id')]
    private string $productId;

    #[ORM\Column(type: 'uuid', nullable: true, name: 'variant_id')]
    private ?string $variantId = null;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'float', name: 'unit_price_amount')]
    private float $unitPriceAmount;

    #[ORM\Column(type: 'string', length: 3, name: 'unit_price_currency')]
    private string $unitPriceCurrency;

    public static function fromDomainModel(CartItem $item, CartEntity $cartEntity, string $tenantId = ''): self
    {
        $entity = new self();
        $entity->id = $item->id()->toString();
        $entity->cart = $cartEntity;
        $entity->tenantId = $tenantId ?: $cartEntity->getTenantId();
        $entity->productId = $item->productId()->toString();
        $entity->variantId = $item->variantId();
        $entity->quantity = $item->quantity()->toInt();
        $entity->unitPriceAmount = $item->unitPrice()->getAmount();
        $entity->unitPriceCurrency = $item->unitPrice()->getCurrency()->getCurrencyCode();

        return $entity;
    }

    public function toDomainModel(): CartItem
    {
        return CartItem::create(
            CartItemId::fromString($this->id),
            ProductId::fromString($this->productId),
            $this->variantId,
            Quantity::fromInt($this->quantity),
            // Cast to int as Money expects integer minor units (cents)
            Money::fromScalars((int) $this->unitPriceAmount, $this->unitPriceCurrency)
        );
    }

    public function updateFromDomainModel(CartItem $item): void
    {
        // ID and cartId should never change
        $this->productId = $item->productId()->toString();
        $this->variantId = $item->variantId();
        $this->quantity = $item->quantity()->toInt();
        $this->unitPriceAmount = $item->unitPrice()->getAmount();
        $this->unitPriceCurrency = $item->unitPrice()->getCurrency()->getCurrencyCode();
    }

    // Getters for Doctrine

    public function getId(): string
    {
        return $this->id;
    }

    public function getCartId(): string
    {
        return $this->cart?->getId() ?? '';
    }

    public function getCart(): ?CartEntity
    {
        return $this->cart;
    }

    public function setCart(CartEntity $cart): void
    {
        $this->cart = $cart;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getVariantId(): ?string
    {
        return $this->variantId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceAmount(): float
    {
        return $this->unitPriceAmount;
    }

    public function getUnitPriceCurrency(): string
    {
        return $this->unitPriceCurrency;
    }
}
