<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Domain\Event\ItemAddedToWishlist;
use App\Wishlist\Domain\Event\ItemRemovedFromWishlist;
use App\Wishlist\Domain\Event\WishlistCleared;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\ValueObject\WishlistId;
use App\Wishlist\Domain\ValueObject\WishlistItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Wishlist::class)]
final class WishlistTest extends TestCase
{
    private WishlistId $wishlistId;
    private string $customerId;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->wishlistId = WishlistId::generate();
        $this->customerId = 'customer-uuid-001';
        $this->tenantId = TenantId::generate();
    }

    // --- create() ---

    #[Test]
    public function createReturnsWishlistWithCorrectState(): void
    {
        $wishlist = $this->createEmptyWishlist();

        self::assertTrue($wishlist->id()->equals($this->wishlistId));
        self::assertSame($this->customerId, $wishlist->customerId());
        self::assertTrue($wishlist->tenantId()->equals($this->tenantId));
        self::assertSame(0, $wishlist->itemCount());
        self::assertTrue($wishlist->isEmpty());
        self::assertSame([], $wishlist->items());
    }

    #[Test]
    public function createDoesNotRecordEvents(): void
    {
        $wishlist = $this->createEmptyWishlist();

        self::assertEmpty($wishlist->popEvents());
    }

    // --- addItem() ---

    #[Test]
    public function addItemIncreasesItemCount(): void
    {
        $wishlist = $this->createEmptyWishlist();

        $wishlist->addItem(ProductId::generate());

        self::assertSame(1, $wishlist->itemCount());
        self::assertFalse($wishlist->isEmpty());
    }

    #[Test]
    public function addItemRecordsEvent(): void
    {
        $wishlist = $this->createEmptyWishlist();
        $productId = ProductId::generate();

        $wishlist->addItem($productId);

        $events = $wishlist->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ItemAddedToWishlist::class, $events[0]);
        self::assertTrue($events[0]->wishlistId->equals($this->wishlistId));
        self::assertTrue($events[0]->productId->equals($productId));
    }

    #[Test]
    public function addItemMakesHasItemReturnTrue(): void
    {
        $wishlist = $this->createEmptyWishlist();
        $productId = ProductId::generate();

        self::assertFalse($wishlist->hasItem($productId));

        $wishlist->addItem($productId);

        self::assertTrue($wishlist->hasItem($productId));
    }

    #[Test]
    public function addDuplicateItemThrowsException(): void
    {
        $wishlist = $this->createEmptyWishlist();
        $productId = ProductId::generate();
        $wishlist->addItem($productId);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product is already in the wishlist');

        $wishlist->addItem($productId);
    }

    #[Test]
    public function addItemBeyondMaxLimitThrowsException(): void
    {
        $wishlist = $this->createWishlistWithItems(100);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Wishlist cannot contain more than 100 items');

        $wishlist->addItem(ProductId::generate());
    }

    #[Test]
    public function addItemAtMaxBoundaryIsAllowed(): void
    {
        $wishlist = $this->createWishlistWithItems(99);

        $wishlist->addItem(ProductId::generate());

        self::assertSame(100, $wishlist->itemCount());
    }

    #[Test]
    public function itemsReturnsWishlistItemArray(): void
    {
        $wishlist = $this->createEmptyWishlist();
        $productId = ProductId::generate();

        $wishlist->addItem($productId);

        $items = $wishlist->items();
        self::assertCount(1, $items);
        self::assertInstanceOf(WishlistItem::class, $items[0]);
        self::assertTrue($items[0]->productId()->equals($productId));
    }

    // --- removeItem() ---

    #[Test]
    public function removeItemDecreasesItemCount(): void
    {
        $wishlist = $this->createEmptyWishlist();
        $productId = ProductId::generate();
        $wishlist->addItem($productId);
        $wishlist->popEvents();

        $wishlist->removeItem($productId);

        self::assertSame(0, $wishlist->itemCount());
        self::assertTrue($wishlist->isEmpty());
    }

    #[Test]
    public function removeItemRecordsEvent(): void
    {
        $wishlist = $this->createEmptyWishlist();
        $productId = ProductId::generate();
        $wishlist->addItem($productId);
        $wishlist->popEvents();

        $wishlist->removeItem($productId);

        $events = $wishlist->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ItemRemovedFromWishlist::class, $events[0]);
    }

    #[Test]
    public function removeNonExistentItemThrowsException(): void
    {
        $wishlist = $this->createEmptyWishlist();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Product is not in the wishlist');

        $wishlist->removeItem(ProductId::generate());
    }

    #[Test]
    public function removeItemOnlyRemovesTarget(): void
    {
        $wishlist = $this->createEmptyWishlist();
        $p1 = ProductId::generate();
        $p2 = ProductId::generate();
        $wishlist->addItem($p1);
        $wishlist->addItem($p2);
        $wishlist->popEvents();

        $wishlist->removeItem($p1);

        self::assertSame(1, $wishlist->itemCount());
        self::assertFalse($wishlist->hasItem($p1));
        self::assertTrue($wishlist->hasItem($p2));
    }

    // --- clear() ---

    #[Test]
    public function clearRemovesAllItems(): void
    {
        $wishlist = $this->createWishlistWithItems(3);
        $wishlist->popEvents();

        $wishlist->clear();

        self::assertSame(0, $wishlist->itemCount());
        self::assertTrue($wishlist->isEmpty());
    }

    #[Test]
    public function clearRecordsWishlistClearedEvent(): void
    {
        $wishlist = $this->createWishlistWithItems(3);
        $wishlist->popEvents();

        $wishlist->clear();

        $events = $wishlist->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(WishlistCleared::class, $events[0]);
        self::assertSame(3, $events[0]->itemCount);
    }

    #[Test]
    public function clearOnEmptyWishlistDoesNotRecordEvent(): void
    {
        $wishlist = $this->createEmptyWishlist();

        $wishlist->clear();

        self::assertEmpty($wishlist->popEvents());
    }

    // --- reconstituteFromPersistence() ---

    #[Test]
    public function reconstituteFromPersistenceRestoresFields(): void
    {
        $productId = ProductId::generate();
        $addedAt = new \DateTimeImmutable('2025-06-01 10:00:00');
        $item = WishlistItem::reconstitute($productId, $addedAt);
        $createdAt = new \DateTimeImmutable('2025-01-01');
        $updatedAt = new \DateTimeImmutable('2025-06-01');

        $wishlist = Wishlist::reconstituteFromPersistence(
            $this->wishlistId,
            $this->customerId,
            $this->tenantId,
            [$productId->toString() => $item],
            $createdAt,
            $updatedAt,
        );

        self::assertTrue($wishlist->id()->equals($this->wishlistId));
        self::assertSame($this->customerId, $wishlist->customerId());
        self::assertSame(1, $wishlist->itemCount());
        self::assertSame($createdAt, $wishlist->createdAt());
        self::assertSame($updatedAt, $wishlist->updatedAt());
    }

    #[Test]
    public function reconstituteDoesNotRecordEvents(): void
    {
        $wishlist = Wishlist::reconstituteFromPersistence(
            $this->wishlistId,
            $this->customerId,
            $this->tenantId,
            [],
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );

        self::assertEmpty($wishlist->popEvents());
    }

    // --- helpers ---

    private function createEmptyWishlist(): Wishlist
    {
        return Wishlist::create($this->wishlistId, $this->customerId, $this->tenantId);
    }

    private function createWishlistWithItems(int $count): Wishlist
    {
        $wishlist = $this->createEmptyWishlist();

        for ($i = 0; $i < $count; ++$i) {
            $wishlist->addItem(ProductId::generate());
        }

        return $wishlist;
    }
}
