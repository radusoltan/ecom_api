<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Infrastructure\Persistence\Doctrine\Entity;

use App\Catalog\Domain\Model\ProductId;
use App\Shared\Domain\ValueObject\TenantId;
use App\Wishlist\Domain\Model\Wishlist;
use App\Wishlist\Domain\ValueObject\WishlistId;
use App\Wishlist\Domain\ValueObject\WishlistItem;
use App\Wishlist\Infrastructure\Persistence\Doctrine\Entity\WishlistEntity;
use PHPUnit\Framework\TestCase;

final class WishlistEntityTest extends TestCase
{
    public function testFromDomainModelRoundtripEmptyWishlist(): void
    {
        $id = WishlistId::generate();
        $customerId = 'customer-00000000-0000-4000-8000-000000000001';
        $tenantId = TenantId::generate();
        $createdAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-10T12:00:00+00:00');

        $wishlist = Wishlist::reconstituteFromPersistence(
            $id,
            $customerId,
            $tenantId,
            [],
            $createdAt,
            $updatedAt,
        );

        $entity = WishlistEntity::fromDomainModel($wishlist);

        self::assertSame($id->toString(), $entity->getId());
        self::assertSame($customerId, $entity->getCustomerId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame([], $entity->getItems());
        self::assertSame($createdAt, $entity->getCreatedAt());
        self::assertSame($updatedAt, $entity->getUpdatedAt());

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertSame($id->toString(), $restored->id()->toString());
        self::assertSame($customerId, $restored->customerId());
        self::assertSame($tenantId->toString(), $restored->tenantId()->toString());
        self::assertCount(0, $restored->items());
        self::assertSame($createdAt, $restored->createdAt());
        self::assertSame($updatedAt, $restored->updatedAt());
    }

    public function testFromDomainModelRoundtripWithItems(): void
    {
        $id = WishlistId::generate();
        $customerId = 'customer-00000000-0000-4000-8000-000000000002';
        $tenantId = TenantId::generate();
        $createdAt = new \DateTimeImmutable('2026-02-01T00:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-02-15T08:30:00+00:00');

        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();
        $addedAt1 = new \DateTimeImmutable('2026-02-10T10:00:00');
        $addedAt2 = new \DateTimeImmutable('2026-02-15T08:30:00');

        $items = [
            $productId1->toString() => WishlistItem::reconstitute($productId1, $addedAt1),
            $productId2->toString() => WishlistItem::reconstitute($productId2, $addedAt2),
        ];

        $wishlist = Wishlist::reconstituteFromPersistence(
            $id,
            $customerId,
            $tenantId,
            $items,
            $createdAt,
            $updatedAt,
        );

        $entity = WishlistEntity::fromDomainModel($wishlist);

        $entityItems = $entity->getItems();
        self::assertCount(2, $entityItems);
        self::assertSame($productId1->toString(), $entityItems[0]['productId']);
        self::assertSame($productId2->toString(), $entityItems[1]['productId']);

        // Roundtrip
        $restored = $entity->toDomainModel();
        self::assertCount(2, $restored->items());
        self::assertTrue($restored->hasItem($productId1));
        self::assertTrue($restored->hasItem($productId2));
        self::assertSame($createdAt, $restored->createdAt());
        self::assertSame($updatedAt, $restored->updatedAt());
    }

    public function testUpdateFromDomainModelReplacesItemsAndUpdatedAt(): void
    {
        $id = WishlistId::generate();
        $customerId = 'customer-00000000-0000-4000-8000-000000000003';
        $tenantId = TenantId::generate();
        $createdAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $updatedAt1 = new \DateTimeImmutable('2026-01-05T00:00:00+00:00');

        $productId1 = ProductId::generate();
        $initialItems = [
            $productId1->toString() => WishlistItem::reconstitute($productId1, new \DateTimeImmutable('2026-01-02T10:00:00')),
        ];

        $wishlist = Wishlist::reconstituteFromPersistence(
            $id, $customerId, $tenantId, $initialItems, $createdAt, $updatedAt1,
        );
        $entity = WishlistEntity::fromDomainModel($wishlist);
        self::assertCount(1, $entity->getItems());

        // Now update with a wishlist that has 2 items
        $productId2 = ProductId::generate();
        $updatedAt2 = new \DateTimeImmutable('2026-01-20T00:00:00+00:00');
        $newItems = [
            $productId1->toString() => WishlistItem::reconstitute($productId1, new \DateTimeImmutable('2026-01-02T10:00:00')),
            $productId2->toString() => WishlistItem::reconstitute($productId2, new \DateTimeImmutable('2026-01-18T14:00:00')),
        ];
        $updatedWishlist = Wishlist::reconstituteFromPersistence(
            $id, $customerId, $tenantId, $newItems, $createdAt, $updatedAt2,
        );

        $entity->updateFromDomainModel($updatedWishlist);

        self::assertCount(2, $entity->getItems());
        self::assertSame($updatedAt2, $entity->getUpdatedAt());
        // id, customerId, tenantId, createdAt should be preserved
        self::assertSame($id->toString(), $entity->getId());
        self::assertSame($customerId, $entity->getCustomerId());
        self::assertSame($tenantId->toString(), $entity->getTenantId());
        self::assertSame($createdAt, $entity->getCreatedAt());
    }

    public function testConstructorInitialisesTimestamps(): void
    {
        $entity = new WishlistEntity();
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $entity->getUpdatedAt());
    }
}
