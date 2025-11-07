<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Bundle;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Catalog\Domain\ValueObject\Bundle
 */
final class BundleTest extends TestCase
{
    private function createBundleItem(int $priceAmount = 1000, int $quantity = 1): BundleItem
    {
        return BundleItem::create(
            ProductId::generate(),
            $quantity,
            Money::fromScalars($priceAmount, 'USD')
        );
    }

    public function testCanCreateBundle(): void
    {
        $items = [
            $this->createBundleItem(1000, 2),
            $this->createBundleItem(500, 1),
        ];

        $bundle = Bundle::create($items, 10.0);

        $this->assertCount(2, $bundle->items());
        $this->assertSame(10.0, $bundle->discountPercentage());
        $this->assertSame(2, $bundle->itemCount());
    }

    public function testCalculatesPriceWithoutDiscount(): void
    {
        $items = [
            $this->createBundleItem(1000, 2), // 2000
            $this->createBundleItem(500, 1),  // 500
        ];

        $bundle = Bundle::create($items, 0.0);

        $price = $bundle->calculatePrice();

        $this->assertSame(2500, $price->getAmount());
    }

    public function testCalculatesPriceWithDiscount(): void
    {
        $items = [
            $this->createBundleItem(1000, 2), // 2000
            $this->createBundleItem(500, 1),  // 500
        ];

        $bundle = Bundle::create($items, 20.0); // 20% discount

        $price = $bundle->calculatePrice();

        // 2500 * 0.8 = 2000
        $this->assertSame(2000, $price->getAmount());
    }

    public function testCalculatesSavings(): void
    {
        $items = [
            $this->createBundleItem(1000, 1),
        ];

        $bundle = Bundle::create($items, 25.0); // 25% discount

        $savings = $bundle->calculateSavings();

        // Original: 1000, After discount: 750, Savings: 250
        $this->assertSame(250, $savings->getAmount());
    }

    public function testCalculatesSavingsReturnsZeroForNoDiscount(): void
    {
        $items = [$this->createBundleItem(1000, 1)];

        $bundle = Bundle::create($items, 0.0);

        $savings = $bundle->calculateSavings();

        $this->assertSame(0, $savings->getAmount());
    }

    public function testThrowsExceptionForEmptyItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle must contain at least 1 item');

        Bundle::create([], 0.0);
    }

    public function testThrowsExceptionForTooManyItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle cannot contain more than 20 items');

        $items = [];
        for ($i = 0; $i < 21; ++$i) {
            $items[] = $this->createBundleItem(100, 1);
        }

        Bundle::create($items, 0.0);
    }

    public function testThrowsExceptionForNegativeDiscount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle discount must be between');

        $items = [$this->createBundleItem(1000, 1)];

        Bundle::create($items, -5.0);
    }

    public function testThrowsExceptionForTooHighDiscount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle discount must be between');

        $items = [$this->createBundleItem(1000, 1)];

        Bundle::create($items, 51.0);
    }

    public function testAllowsMaximumDiscount(): void
    {
        $items = [$this->createBundleItem(1000, 1)];

        $bundle = Bundle::create($items, 50.0);

        $price = $bundle->calculatePrice();

        // 1000 * 0.5 = 500
        $this->assertSame(500, $price->getAmount());
    }

    public function testContainsProductReturnsTrueWhenProductExists(): void
    {
        $productId = ProductId::generate();
        $items = [
            BundleItem::create($productId, 1, Money::fromScalars(1000, 'USD')),
        ];

        $bundle = Bundle::create($items, 0.0);

        $this->assertTrue($bundle->containsProduct($productId->toString()));
    }

    public function testContainsProductReturnsFalseWhenProductDoesNotExist(): void
    {
        $items = [$this->createBundleItem(1000, 1)];

        $bundle = Bundle::create($items, 0.0);

        $this->assertFalse($bundle->containsProduct(ProductId::generate()->toString()));
    }

    public function testEqualsReturnsTrueForSameBundles(): void
    {
        $productId = ProductId::generate();
        $items = [
            BundleItem::create($productId, 2, Money::fromScalars(1000, 'USD')),
        ];

        $bundle1 = Bundle::create($items, 10.0);
        $bundle2 = Bundle::create($items, 10.0);

        $this->assertTrue($bundle1->equals($bundle2));
    }

    public function testEqualsReturnsFalseForDifferentDiscount(): void
    {
        $items = [$this->createBundleItem(1000, 1)];

        $bundle1 = Bundle::create($items, 10.0);
        $bundle2 = Bundle::create($items, 20.0);

        $this->assertFalse($bundle1->equals($bundle2));
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $items = [
            $this->createBundleItem(1000, 2),
            $this->createBundleItem(500, 1),
        ];

        $bundle = Bundle::create($items, 15.5);

        $array = $bundle->toArray();

        $this->assertArrayHasKey('items', $array);
        $this->assertArrayHasKey('discountPercentage', $array);
        $this->assertCount(2, $array['items']);
        $this->assertSame(15.5, $array['discountPercentage']);
    }
}
