<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;
use App\Wishlist\Domain\ValueObject\WishlistItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WishlistItem::class)]
final class WishlistItemTest extends TestCase
{
    #[Test]
    public function createSetsProductIdAndAddedAt(): void
    {
        $productId = ProductId::generate();

        $item = WishlistItem::create($productId);

        self::assertTrue($item->productId()->equals($productId));
        self::assertInstanceOf(\DateTimeImmutable::class, $item->addedAt());
    }

    #[Test]
    public function reconstitutePreservesExactTimestamp(): void
    {
        $productId = ProductId::generate();
        $addedAt = new \DateTimeImmutable('2025-06-01 10:00:00');

        $item = WishlistItem::reconstitute($productId, $addedAt);

        self::assertTrue($item->productId()->equals($productId));
        self::assertSame($addedAt, $item->addedAt());
    }

    #[Test]
    public function equalsReturnsTrueForSameProduct(): void
    {
        $productId = ProductId::generate();

        $a = WishlistItem::create($productId);
        $b = WishlistItem::create($productId);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentProduct(): void
    {
        $a = WishlistItem::create(ProductId::generate());
        $b = WishlistItem::create(ProductId::generate());

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function toArrayReturnsExpectedStructure(): void
    {
        $productId = ProductId::generate();
        $item = WishlistItem::create($productId);

        $array = $item->toArray();

        self::assertSame($productId->toString(), $array['productId']);
        self::assertArrayHasKey('addedAt', $array);
    }
}
