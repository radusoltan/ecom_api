<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Catalog\Domain\ValueObject\BundleItem
 */
final class BundleItemTest extends TestCase
{
    public function testCanCreateBundleItem(): void
    {
        $productId = ProductId::generate();
        $quantity = 2;
        $price = Money::fromScalars(1000, 'USD');

        $item = BundleItem::create($productId, $quantity, $price);

        $this->assertTrue($item->productId()->equals($productId));
        $this->assertSame(2, $item->quantity());
        $this->assertTrue($item->price()->equals($price));
    }

    public function testCalculatesTotalPrice(): void
    {
        $productId = ProductId::generate();
        $price = Money::fromScalars(1000, 'USD');

        $item = BundleItem::create($productId, 3, $price);

        $totalPrice = $item->totalPrice();

        $this->assertSame(3000, $totalPrice->getAmount());
    }

    public function testThrowsExceptionForInvalidQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle item quantity must be at least 1');

        $productId = ProductId::generate();
        $price = Money::fromScalars(1000, 'USD');

        BundleItem::create($productId, 0, $price);
    }

    public function testEqualsReturnsTrueForSameValues(): void
    {
        $productId = ProductId::generate();
        $price = Money::fromScalars(1000, 'USD');

        $item1 = BundleItem::create($productId, 2, $price);
        $item2 = BundleItem::create($productId, 2, $price);

        $this->assertTrue($item1->equals($item2));
    }

    public function testEqualsReturnsFalseForDifferentQuantity(): void
    {
        $productId = ProductId::generate();
        $price = Money::fromScalars(1000, 'USD');

        $item1 = BundleItem::create($productId, 2, $price);
        $item2 = BundleItem::create($productId, 3, $price);

        $this->assertFalse($item1->equals($item2));
    }

    public function testCanConvertToArray(): void
    {
        $productId = ProductId::generate();
        $price = Money::fromScalars(1000, 'USD');

        $item = BundleItem::create($productId, 2, $price);

        $array = $item->toArray();

        $this->assertSame($productId->toString(), $array['productId']);
        $this->assertSame(2, $array['quantity']);
        $this->assertSame(1000, $array['priceAmount']);
        $this->assertSame('USD', $array['priceCurrency']);
    }

    public function testCanReconstituteFromPersistence(): void
    {
        $productId = ProductId::generate();
        $price = Money::fromScalars(1500, 'EUR');

        $item = BundleItem::fromPersistence($productId, 5, $price);

        $this->assertTrue($item->productId()->equals($productId));
        $this->assertSame(5, $item->quantity());
        $this->assertSame(1500, $item->price()->getAmount());
        $this->assertSame('EUR', $item->price()->getCurrency()->getCurrencyCode());
    }
}
