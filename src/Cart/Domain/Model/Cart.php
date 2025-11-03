<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

use App\Cart\Domain\Event\CartCleared;
use App\Cart\Domain\Event\CartCreated;
use App\Cart\Domain\Event\CartQuantityUpdated;
use App\Cart\Domain\Event\ItemAddedToCart;
use App\Cart\Domain\Event\ItemRemovedFromCart;
use App\Cart\Domain\Exception\CartItemNotFoundException;
use App\Cart\Domain\Exception\InvalidQuantityException;
use App\Catalog\Domain\Model\ProductId;
use App\Customer\Domain\ValueObject\CustomerId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Cart Aggregate Root
 *
 * Business Rules:
 * - Max 100 items per cart
 * - Max 999 quantity per item
 * - Duplicate prevention (same product+variant merges quantities)
 * - Cart expires after 7 days of inactivity
 * - Stock availability check via events
 * - Price snapshot at add-to-cart time
 */
final class Cart extends AggregateRoot
{
    private const MAX_ITEMS = 100;
    private const EXPIRY_DAYS = 7;

    private CartId $id;
    private TenantId $tenantId;
    private ?CustomerId $customerId;
    private ?SessionId $sessionId;
    private CartStatus $status;
    /** @var CartItem[] */
    private array $items = [];
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function create(
        CartId $id,
        TenantId $tenantId,
        ?CustomerId $customerId,
        SessionId $sessionId
    ): self {
        $cart = new self();
        $cart->id = $id;
        $cart->tenantId = $tenantId;
        $cart->customerId = $customerId;
        $cart->sessionId = $sessionId;
        $cart->status = CartStatus::active();
        $cart->items = [];
        $cart->createdAt = new DateTimeImmutable();
        $cart->updatedAt = new DateTimeImmutable();

        $cart->recordEvent(new CartCreated(
            $cart->id,
            $cart->tenantId,
            $cart->customerId,
            $cart->sessionId
        ));

        return $cart;
    }

    /**
     * @param CartItem[] $items
     */
    public static function reconstituteFromPersistence(
        CartId $id,
        TenantId $tenantId,
        ?CustomerId $customerId,
        ?SessionId $sessionId,
        CartStatus $status,
        array $items,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        $cart = new self();
        $cart->id = $id;
        $cart->tenantId = $tenantId;
        $cart->customerId = $customerId;
        $cart->sessionId = $sessionId;
        $cart->status = $status;
        $cart->items = $items;
        $cart->createdAt = $createdAt;
        $cart->updatedAt = $updatedAt;

        return $cart;
    }

    public function addItem(
        ProductId $productId,
        ?string $variantId,
        Quantity $quantity,
        Money $unitPrice
    ): void {
        if ($this->status !== CartStatus::ACTIVE) {
            throw new InvalidArgumentException('Cannot add items to a non-active cart');
        }

        // Check for duplicate item (same product+variant)
        foreach ($this->items as $existingItem) {
            if ($existingItem->isSameAs($productId, $variantId)) {
                // Merge by increasing quantity
                $existingItem->increaseQuantity($quantity);
                $this->updatedAt = new DateTimeImmutable();

                $this->recordEvent(new CartQuantityUpdated(
                    $this->id,
                    $this->tenantId,
                    $existingItem->id(),
                    $productId,
                    $variantId,
                    $existingItem->quantity()
                ));

                return;
            }
        }

        // Check max items limit
        if (count($this->items) >= self::MAX_ITEMS) {
            throw new InvalidArgumentException(
                sprintf('Cannot add more than %d items to cart', self::MAX_ITEMS)
            );
        }

        // Create new item
        $item = CartItem::create(
            CartItemId::generate(),
            $productId,
            $variantId,
            $quantity,
            $unitPrice
        );

        $this->items[] = $item;
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new ItemAddedToCart(
            $this->id,
            $this->tenantId,
            $item->id(),
            $productId,
            $variantId,
            $quantity,
            $unitPrice
        ));
    }

    public function removeItem(CartItemId $cartItemId): void
    {
        if ($this->status !== CartStatus::ACTIVE) {
            throw new InvalidArgumentException('Cannot remove items from a non-active cart');
        }

        $itemIndex = null;
        $removedItem = null;

        foreach ($this->items as $index => $item) {
            if ($item->id()->equals($cartItemId)) {
                $itemIndex = $index;
                $removedItem = $item;
                break;
            }
        }

        if ($itemIndex === null || $removedItem === null) {
            throw new CartItemNotFoundException(
                sprintf('Cart item with ID "%s" not found', $cartItemId->toString())
            );
        }

        array_splice($this->items, (int) $itemIndex, 1);
        $this->items = array_values($this->items); // Reindex array
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new ItemRemovedFromCart(
            $this->id,
            $this->tenantId,
            $cartItemId,
            $removedItem->productId(),
            $removedItem->variantId()
        ));
    }

    public function updateQuantity(CartItemId $cartItemId, Quantity $newQuantity): void
    {
        if ($this->status !== CartStatus::ACTIVE) {
            throw new InvalidArgumentException('Cannot update quantities in a non-active cart');
        }

        foreach ($this->items as $item) {
            if ($item->id()->equals($cartItemId)) {
                $item->updateQuantity($newQuantity);
                $this->updatedAt = new DateTimeImmutable();

                $this->recordEvent(new CartQuantityUpdated(
                    $this->id,
                    $this->tenantId,
                    $cartItemId,
                    $item->productId(),
                    $item->variantId(),
                    $newQuantity
                ));

                return;
            }
        }

        throw new CartItemNotFoundException(
            sprintf('Cart item with ID "%s" not found', $cartItemId->toString())
        );
    }

    public function clear(): void
    {
        if (empty($this->items)) {
            return; // Already empty, nothing to do
        }

        $this->items = [];
        $this->updatedAt = new DateTimeImmutable();

        $this->recordEvent(new CartCleared($this->id, $this->tenantId));
    }

    public function calculateTotal(): Money
    {
        if (empty($this->items)) {
            return Money::fromScalars(0, 'USD');
        }

        $total = Money::fromScalars(0, $this->items[0]->unitPrice()->getCurrency()->getCurrencyCode());

        foreach ($this->items as $item) {
            $total = $total->add($item->rowTotal());
        }

        return $total;
    }

    public function isExpired(): bool
    {
        if ($this->status === CartStatus::EXPIRED) {
            return true;
        }

        $expiryDate = $this->updatedAt->modify(sprintf('+%d days', self::EXPIRY_DAYS));
        $now = new DateTimeImmutable();

        return $now >= $expiryDate;
    }

    public function markAsExpired(): void
    {
        if ($this->status !== CartStatus::ACTIVE) {
            return;
        }

        $this->status = CartStatus::expired();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markAsConverted(): void
    {
        if ($this->status !== CartStatus::ACTIVE) {
            throw new InvalidArgumentException('Only active carts can be converted to orders');
        }

        $this->status = CartStatus::converted();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function assignToCustomer(CustomerId $customerId): void
    {
        $this->customerId = $customerId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->quantity()->toInt();
        }
        return $count;
    }

    // Getters

    public function id(): CartId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customerId(): ?CustomerId
    {
        return $this->customerId;
    }

    public function sessionId(): ?SessionId
    {
        return $this->sessionId;
    }

    public function status(): CartStatus
    {
        return $this->status;
    }

    /**
     * @return CartItem[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Check if cart is active (can be modified)
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if cart is empty (has no items)
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}