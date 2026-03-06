<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\ValueObject\Bundle;
use App\Catalog\Domain\ValueObject\BundleItem;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Bundle::class)]
#[CoversClass(BundleItem::class)]
final class BundleExtendedTest extends TestCase
{
    private function makeItem(int $priceAmount = 1000, int $quantity = 1): BundleItem
    {
        return BundleItem::create(
            productId: ProductId::generate(),
            quantity: $quantity,
            price: Money::fromScalars($priceAmount, 'USD'),
        );
    }

    // -----------------------------------------------------------------------
    // Bundle boundary conditions
    // -----------------------------------------------------------------------

    #[Test]
    public function itAllowsExactlyOneItem(): void
    {
        $bundle = Bundle::create([$this->makeItem(500)], 0.0);

        self::assertSame(1, $bundle->itemCount());
        self::assertCount(1, $bundle->items());
    }

    #[Test]
    public function itAllowsExactlyTwentyItems(): void
    {
        $items = [];
        for ($i = 0; $i < 20; ++$i) {
            $items[] = $this->makeItem(100);
        }

        $bundle = Bundle::create($items, 0.0);

        self::assertSame(20, $bundle->itemCount());
    }

    #[Test]
    public function itRejectsZeroItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle must contain at least 1 item');

        Bundle::create([], 0.0);
    }

    #[Test]
    public function itRejectsTwentyOneItems(): void
    {
        $items = array_fill(0, 21, $this->makeItem(100));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle cannot contain more than 20 items (got 21)');

        Bundle::create($items, 0.0);
    }

    #[Test]
    public function itAllowsZeroPercentDiscount(): void
    {
        $bundle = Bundle::create([$this->makeItem(1000)], 0.0);

        self::assertSame(0.0, $bundle->discountPercentage());
        self::assertSame(1000, $bundle->calculatePrice()->getAmount());
    }

    #[Test]
    public function itAllowsExactlyFiftyPercentDiscount(): void
    {
        $bundle = Bundle::create([$this->makeItem(2000)], 50.0);

        self::assertSame(50.0, $bundle->discountPercentage());
        self::assertSame(1000, $bundle->calculatePrice()->getAmount());
    }

    #[Test]
    public function itRejectsDiscountJustAboveFiftyPercent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle discount must be between');

        Bundle::create([$this->makeItem(1000)], 50.01);
    }

    #[Test]
    public function itRejectsNegativeDiscount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle discount must be between');

        Bundle::create([$this->makeItem(1000)], -0.01);
    }

    // -----------------------------------------------------------------------
    // calculatePrice() with quantity > 1
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesPriceWithQuantityGreaterThanOne(): void
    {
        // 1 item: price=500 per unit, quantity=3 → total=1500
        $item = BundleItem::create(ProductId::generate(), 3, Money::fromScalars(500, 'USD'));
        $bundle = Bundle::create([$item], 0.0);

        self::assertSame(1500, $bundle->calculatePrice()->getAmount());
    }

    #[Test]
    public function itCalculatesPriceWithMultipleItemsAndDiscount(): void
    {
        // item1: 2×1000 = 2000; item2: 1×500 = 500 → sum 2500, 20% off → 2000
        $item1 = BundleItem::create(ProductId::generate(), 2, Money::fromScalars(1000, 'USD'));
        $item2 = BundleItem::create(ProductId::generate(), 1, Money::fromScalars(500, 'USD'));
        $bundle = Bundle::create([$item1, $item2], 20.0);

        self::assertSame(2000, $bundle->calculatePrice()->getAmount());
    }

    // -----------------------------------------------------------------------
    // calculateSavings()
    // -----------------------------------------------------------------------

    #[Test]
    public function itCalculatesSavingsIsZeroWithNoDiscount(): void
    {
        $bundle = Bundle::create([$this->makeItem(1000)], 0.0);
        self::assertSame(0, $bundle->calculateSavings()->getAmount());
    }

    #[Test]
    public function itCalculatesSavingsWithDiscount(): void
    {
        // 2000 * 25% = 500 savings
        $bundle = Bundle::create([$this->makeItem(2000)], 25.0);
        self::assertSame(500, $bundle->calculateSavings()->getAmount());
    }

    // -----------------------------------------------------------------------
    // containsProduct()
    // -----------------------------------------------------------------------

    #[Test]
    public function itFindsProductInBundle(): void
    {
        $pid = ProductId::generate();
        $bundle = Bundle::create([
            BundleItem::create($pid, 1, Money::fromScalars(500, 'USD')),
            $this->makeItem(1000),
        ], 0.0);

        self::assertTrue($bundle->containsProduct($pid->toString()));
    }

    #[Test]
    public function itReturnsFalseForMissingProduct(): void
    {
        $bundle = Bundle::create([$this->makeItem(500)], 0.0);
        self::assertFalse($bundle->containsProduct(ProductId::generate()->toString()));
    }

    // -----------------------------------------------------------------------
    // equals()
    // -----------------------------------------------------------------------

    #[Test]
    public function itEqualsWithSameItemsAndDiscount(): void
    {
        $pid = ProductId::generate();
        $item = BundleItem::create($pid, 2, Money::fromScalars(1000, 'USD'));

        $a = Bundle::create([$item], 10.0);
        $b = Bundle::create([$item], 10.0);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itNotEqualsWithDifferentItemCount(): void
    {
        $a = Bundle::create([$this->makeItem(1000)], 0.0);
        $b = Bundle::create([$this->makeItem(1000), $this->makeItem(500)], 0.0);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itNotEqualsWithDifferentDiscount(): void
    {
        $pid = ProductId::generate();
        $item = BundleItem::create($pid, 1, Money::fromScalars(1000, 'USD'));

        $a = Bundle::create([$item], 10.0);
        $b = Bundle::create([$item], 20.0);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itNotEqualsWithDifferentItems(): void
    {
        $item1 = BundleItem::create(ProductId::generate(), 1, Money::fromScalars(1000, 'USD'));
        $item2 = BundleItem::create(ProductId::generate(), 1, Money::fromScalars(1000, 'USD'));

        $a = Bundle::create([$item1], 0.0);
        $b = Bundle::create([$item2], 0.0);

        self::assertFalse($a->equals($b));
    }

    // -----------------------------------------------------------------------
    // toArray()
    // -----------------------------------------------------------------------

    #[Test]
    public function itConvertsToArrayWithCorrectStructure(): void
    {
        $pid = ProductId::generate();
        $item = BundleItem::create($pid, 2, Money::fromScalars(999, 'USD'));
        $bundle = Bundle::create([$item], 15.0);

        $array = $bundle->toArray();

        self::assertArrayHasKey('items', $array);
        self::assertArrayHasKey('discountPercentage', $array);
        self::assertSame(15.0, $array['discountPercentage']);
        self::assertCount(1, $array['items']);

        $itemArray = $array['items'][0];
        self::assertSame($pid->toString(), $itemArray['productId']);
        self::assertSame(2, $itemArray['quantity']);
        self::assertSame(999, $itemArray['priceAmount']);
        self::assertSame('USD', $itemArray['priceCurrency']);
    }

    // -----------------------------------------------------------------------
    // BundleItem tests
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesBundleItemWithCorrectFields(): void
    {
        $pid = ProductId::generate();
        $price = Money::fromScalars(1500, 'USD');
        $item = BundleItem::create($pid, 3, $price);

        self::assertTrue($item->productId()->equals($pid));
        self::assertSame(3, $item->quantity());
        self::assertSame(1500, $item->price()->getAmount());
    }

    #[Test]
    public function itCalculatesTotalPriceForItem(): void
    {
        $item = BundleItem::create(ProductId::generate(), 4, Money::fromScalars(250, 'USD'));

        // 4 × 250 = 1000
        self::assertSame(1000, $item->totalPrice()->getAmount());
    }

    #[Test]
    public function itThrowsWhenBundleItemQuantityIsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle item quantity must be at least 1');

        BundleItem::create(ProductId::generate(), 0, Money::fromScalars(1000, 'USD'));
    }

    #[Test]
    public function itThrowsWhenBundleItemQuantityIsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bundle item quantity must be at least 1');

        BundleItem::create(ProductId::generate(), -1, Money::fromScalars(1000, 'USD'));
    }

    #[Test]
    public function itEqualsBundleItemsWithSameData(): void
    {
        $pid = ProductId::generate();
        $price = Money::fromScalars(500, 'USD');

        $a = BundleItem::create($pid, 2, $price);
        $b = BundleItem::create($pid, 2, $price);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itNotEqualsBundleItemsWithDifferentQuantity(): void
    {
        $pid = ProductId::generate();
        $price = Money::fromScalars(500, 'USD');

        $a = BundleItem::create($pid, 1, $price);
        $b = BundleItem::create($pid, 2, $price);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itNotEqualsBundleItemsWithDifferentProduct(): void
    {
        $price = Money::fromScalars(500, 'USD');

        $a = BundleItem::create(ProductId::generate(), 1, $price);
        $b = BundleItem::create(ProductId::generate(), 1, $price);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itConvertsBundleItemToArray(): void
    {
        $pid = ProductId::generate();
        $item = BundleItem::create($pid, 3, Money::fromScalars(750, 'USD'));

        $array = $item->toArray();

        self::assertSame($pid->toString(), $array['productId']);
        self::assertSame(3, $array['quantity']);
        self::assertSame(750, $array['priceAmount']);
        self::assertSame('USD', $array['priceCurrency']);
    }

    #[Test]
    public function itCreatesBundleItemFromPersistence(): void
    {
        $pid = ProductId::generate();
        $price = Money::fromScalars(1000, 'USD');

        $item = BundleItem::fromPersistence($pid, 2, $price);

        self::assertTrue($item->productId()->equals($pid));
        self::assertSame(2, $item->quantity());
        self::assertSame(1000, $item->price()->getAmount());
    }
}
