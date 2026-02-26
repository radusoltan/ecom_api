<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Domain\Event;

use App\Catalog\Domain\Model\ProductId;
use App\Wishlist\Domain\Event\ItemAddedToWishlist;
use App\Wishlist\Domain\Event\ItemRemovedFromWishlist;
use App\Wishlist\Domain\Event\WishlistCleared;
use App\Wishlist\Domain\ValueObject\WishlistId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItemAddedToWishlist::class)]
#[CoversClass(ItemRemovedFromWishlist::class)]
#[CoversClass(WishlistCleared::class)]
final class WishlistEventsTest extends TestCase
{
    private WishlistId $wishlistId;
    private ProductId $productId;
    private string $customerId;
    private \DateTimeImmutable $occurredAt;

    protected function setUp(): void
    {
        $this->wishlistId = WishlistId::generate();
        $this->productId = ProductId::generate();
        $this->customerId = 'customer-event-001';
        $this->occurredAt = new \DateTimeImmutable('2025-01-15 10:00:00');
    }

    // -------------------------------------------------------
    // ItemAddedToWishlist
    // -------------------------------------------------------

    #[Test]
    public function itemAddedToWishlistStoresAllProperties(): void
    {
        $event = new ItemAddedToWishlist(
            $this->wishlistId,
            $this->productId,
            $this->customerId,
            $this->occurredAt,
        );

        self::assertTrue($event->wishlistId->equals($this->wishlistId));
        self::assertTrue($event->productId->equals($this->productId));
        self::assertSame($this->customerId, $event->customerId);
        self::assertSame($this->occurredAt, $event->occurredAt);
    }

    // -------------------------------------------------------
    // ItemRemovedFromWishlist
    // -------------------------------------------------------

    #[Test]
    public function itemRemovedFromWishlistStoresAllProperties(): void
    {
        $event = new ItemRemovedFromWishlist(
            $this->wishlistId,
            $this->productId,
            $this->customerId,
            $this->occurredAt,
        );

        self::assertTrue($event->wishlistId->equals($this->wishlistId));
        self::assertTrue($event->productId->equals($this->productId));
        self::assertSame($this->customerId, $event->customerId);
        self::assertSame($this->occurredAt, $event->occurredAt);
    }

    // -------------------------------------------------------
    // WishlistCleared
    // -------------------------------------------------------

    #[Test]
    public function wishlistClearedStoresAllProperties(): void
    {
        $itemCount = 7;

        $event = new WishlistCleared(
            $this->wishlistId,
            $this->customerId,
            $itemCount,
            $this->occurredAt,
        );

        self::assertTrue($event->wishlistId->equals($this->wishlistId));
        self::assertSame($this->customerId, $event->customerId);
        self::assertSame($itemCount, $event->itemCount);
        self::assertSame($this->occurredAt, $event->occurredAt);
    }

    #[Test]
    public function wishlistClearedAllowsZeroItemCount(): void
    {
        $event = new WishlistCleared(
            $this->wishlistId,
            $this->customerId,
            0,
            $this->occurredAt,
        );

        self::assertSame(0, $event->itemCount);
    }
}
