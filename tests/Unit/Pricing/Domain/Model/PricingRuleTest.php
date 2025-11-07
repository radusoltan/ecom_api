<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\Model\Discount;
use App\Pricing\Domain\Model\PricingRule;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PricingRuleTest extends TestCase
{
    // ============================================
    // Factory Method Tests
    // ============================================

    public function testForProductCreatesValidRule(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);

        $rule = PricingRule::forProduct($productId, $discount, 1, null);

        $this->assertInstanceOf(PricingRule::class, $rule);
        $this->assertSame('product', $rule->getScope());
        $this->assertTrue($rule->getProductId()->equals($productId));
        $this->assertNull($rule->getCategoryId());
        $this->assertSame(1, $rule->getMinQuantity());
        $this->assertNull($rule->getMinPurchaseAmount());
    }

    public function testForProductWithMinimumQuantity(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(15.0);

        $rule = PricingRule::forProduct($productId, $discount, 5, null);

        $this->assertSame(5, $rule->getMinQuantity());
    }

    public function testForProductWithMinimumPurchaseAmount(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(20.0);
        $minAmount = Money::of('100.00', 'USD');

        $rule = PricingRule::forProduct($productId, $discount, 1, $minAmount);

        $this->assertTrue($rule->getMinPurchaseAmount()->equals($minAmount));
    }

    public function testForCategoryCreatesValidRule(): void
    {
        $categoryId = 'electronics';
        $discount = Discount::percentage(12.0);

        $rule = PricingRule::forCategory($categoryId, $discount, 1, null);

        $this->assertInstanceOf(PricingRule::class, $rule);
        $this->assertSame('category', $rule->getScope());
        $this->assertSame($categoryId, $rule->getCategoryId());
        $this->assertNull($rule->getProductId());
        $this->assertSame(1, $rule->getMinQuantity());
    }

    public function testForCategoryWithConditions(): void
    {
        $categoryId = 'clothing';
        $discount = Discount::fixed(Money::of('20.00', 'USD'));
        $minAmount = Money::of('200.00', 'USD');

        $rule = PricingRule::forCategory($categoryId, $discount, 3, $minAmount);

        $this->assertSame(3, $rule->getMinQuantity());
        $this->assertTrue($rule->getMinPurchaseAmount()->equals($minAmount));
    }

    public function testForAllCreatesValidRule(): void
    {
        $discount = Discount::percentage(5.0);

        $rule = PricingRule::forAll($discount, 1, null);

        $this->assertInstanceOf(PricingRule::class, $rule);
        $this->assertSame('all', $rule->getScope());
        $this->assertNull($rule->getProductId());
        $this->assertNull($rule->getCategoryId());
        $this->assertSame(1, $rule->getMinQuantity());
    }

    public function testForAllWithConditions(): void
    {
        $discount = Discount::percentage(10.0);
        $minAmount = Money::of('500.00', 'USD');

        $rule = PricingRule::forAll($discount, 10, $minAmount);

        $this->assertSame(10, $rule->getMinQuantity());
        $this->assertTrue($rule->getMinPurchaseAmount()->equals($minAmount));
    }

    // ============================================
    // Validation Tests
    // ============================================

    public function testForProductThrowsExceptionForZeroQuantity(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum quantity must be at least 1');

        PricingRule::forProduct($productId, $discount, 0, null);
    }

    public function testForProductThrowsExceptionForNegativeQuantity(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum quantity must be at least 1');

        PricingRule::forProduct($productId, $discount, -5, null);
    }

    public function testForCategoryThrowsExceptionForInvalidQuantity(): void
    {
        $discount = Discount::percentage(10.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum quantity must be at least 1');

        PricingRule::forCategory('electronics', $discount, 0, null);
    }

    public function testForAllThrowsExceptionForInvalidQuantity(): void
    {
        $discount = Discount::percentage(10.0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum quantity must be at least 1');

        PricingRule::forAll($discount, -1, null);
    }

    // ============================================
    // appliesTo() Logic Tests - Product Scope
    // ============================================

    public function testAppliesToReturnsTrueForMatchingProduct(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forProduct($productId, $discount);

        $result = $rule->appliesTo($productId, null, 1, Money::of('50.00', 'USD'));

        $this->assertTrue($result);
    }

    public function testAppliesToReturnsFalseForDifferentProduct(): void
    {
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forProduct($productId1, $discount);

        $result = $rule->appliesTo($productId2, null, 1, Money::of('50.00', 'USD'));

        $this->assertFalse($result);
    }

    public function testAppliesToReturnsTrueWhenQuantityMeetsThreshold(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forProduct($productId, $discount, 5, null);

        $this->assertTrue($rule->appliesTo($productId, null, 5, Money::of('100.00', 'USD')));
        $this->assertTrue($rule->appliesTo($productId, null, 10, Money::of('100.00', 'USD')));
    }

    public function testAppliesToReturnsFalseWhenQuantityBelowThreshold(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forProduct($productId, $discount, 5, null);

        $this->assertFalse($rule->appliesTo($productId, null, 4, Money::of('100.00', 'USD')));
        $this->assertFalse($rule->appliesTo($productId, null, 1, Money::of('100.00', 'USD')));
    }

    public function testAppliesToReturnsTrueWhenAmountMeetsMinimum(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $minAmount = Money::of('100.00', 'USD');
        $rule = PricingRule::forProduct($productId, $discount, 1, $minAmount);

        $this->assertTrue($rule->appliesTo($productId, null, 1, Money::of('100.00', 'USD')));
        $this->assertTrue($rule->appliesTo($productId, null, 1, Money::of('150.00', 'USD')));
    }

    public function testAppliesToReturnsFalseWhenAmountBelowMinimum(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $minAmount = Money::of('100.00', 'USD');
        $rule = PricingRule::forProduct($productId, $discount, 1, $minAmount);

        $this->assertFalse($rule->appliesTo($productId, null, 1, Money::of('99.99', 'USD')));
        $this->assertFalse($rule->appliesTo($productId, null, 1, Money::of('50.00', 'USD')));
    }

    public function testAppliesToWithBothQuantityAndAmountConditions(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(15.0);
        $minAmount = Money::of('200.00', 'USD');
        $rule = PricingRule::forProduct($productId, $discount, 5, $minAmount);

        // Both conditions met
        $this->assertTrue($rule->appliesTo($productId, null, 5, Money::of('200.00', 'USD')));
        $this->assertTrue($rule->appliesTo($productId, null, 10, Money::of('500.00', 'USD')));

        // Quantity met but not amount
        $this->assertFalse($rule->appliesTo($productId, null, 5, Money::of('150.00', 'USD')));

        // Amount met but not quantity
        $this->assertFalse($rule->appliesTo($productId, null, 3, Money::of('250.00', 'USD')));

        // Neither met
        $this->assertFalse($rule->appliesTo($productId, null, 2, Money::of('100.00', 'USD')));
    }

    // ============================================
    // appliesTo() Logic Tests - Category Scope
    // ============================================

    public function testAppliesToReturnsTrueForMatchingCategory(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forCategory('electronics', $discount);

        $result = $rule->appliesTo($productId, 'electronics', 1, Money::of('50.00', 'USD'));

        $this->assertTrue($result);
    }

    public function testAppliesToReturnsFalseForDifferentCategory(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forCategory('electronics', $discount);

        $result = $rule->appliesTo($productId, 'clothing', 1, Money::of('50.00', 'USD'));

        $this->assertFalse($result);
    }

    public function testAppliesToReturnsFalseForNullCategory(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forCategory('electronics', $discount);

        $result = $rule->appliesTo($productId, null, 1, Money::of('50.00', 'USD'));

        $this->assertFalse($result);
    }

    // ============================================
    // appliesTo() Logic Tests - All Scope
    // ============================================

    public function testAppliesToAlwaysReturnsTrueForAllScope(): void
    {
        $productId1 = ProductId::generate();
        $productId2 = ProductId::generate();
        $discount = Discount::percentage(5.0);
        $rule = PricingRule::forAll($discount);

        $this->assertTrue($rule->appliesTo($productId1, null, 1, Money::of('10.00', 'USD')));
        $this->assertTrue($rule->appliesTo($productId2, 'electronics', 1, Money::of('20.00', 'USD')));
        $this->assertTrue($rule->appliesTo($productId1, 'clothing', 1, Money::of('30.00', 'USD')));
    }

    public function testAppliesToForAllScopeRespectsQuantityCondition(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forAll($discount, 3, null);

        $this->assertTrue($rule->appliesTo($productId, null, 3, Money::of('100.00', 'USD')));
        $this->assertTrue($rule->appliesTo($productId, 'electronics', 5, Money::of('100.00', 'USD')));
        $this->assertFalse($rule->appliesTo($productId, null, 2, Money::of('100.00', 'USD')));
    }

    public function testAppliesToForAllScopeRespectsAmountCondition(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(10.0);
        $minAmount = Money::of('500.00', 'USD');
        $rule = PricingRule::forAll($discount, 1, $minAmount);

        $this->assertTrue($rule->appliesTo($productId, null, 1, Money::of('500.00', 'USD')));
        $this->assertTrue($rule->appliesTo($productId, 'electronics', 1, Money::of('1000.00', 'USD')));
        $this->assertFalse($rule->appliesTo($productId, null, 1, Money::of('400.00', 'USD')));
    }

    // ============================================
    // Serialization Tests
    // ============================================

    public function testToArrayForProductScope(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(15.0);
        $minAmount = Money::of('100.00', 'USD');
        $rule = PricingRule::forProduct($productId, $discount, 5, $minAmount);

        $array = $rule->toArray();

        $this->assertSame('product', $array['scope']);
        $this->assertSame($productId->toString(), $array['product_id']);
        $this->assertNull($array['category_id']);
        $this->assertSame(5, $array['min_quantity']);
        $this->assertIsArray($array['discount']);
        $this->assertSame('percentage', $array['discount']['type']);
        $this->assertSame(15.0, $array['discount']['percentage']);
        $this->assertIsArray($array['min_purchase_amount']);
        $this->assertSame(10000, (int) $array['min_purchase_amount']['amount']);
    }

    public function testToArrayForCategoryScope(): void
    {
        $discount = Discount::fixed(Money::of('20.00', 'EUR'));
        $rule = PricingRule::forCategory('electronics', $discount, 3, null);

        $array = $rule->toArray();

        $this->assertSame('category', $array['scope']);
        $this->assertSame('electronics', $array['category_id']);
        $this->assertNull($array['product_id']);
        $this->assertSame(3, $array['min_quantity']);
        $this->assertNull($array['min_purchase_amount']);
        $this->assertIsArray($array['discount']);
        $this->assertSame('fixed', $array['discount']['type']);
    }

    public function testToArrayForAllScope(): void
    {
        $discount = Discount::percentage(10.0);
        $rule = PricingRule::forAll($discount, 1, null);

        $array = $rule->toArray();

        $this->assertSame('all', $array['scope']);
        $this->assertNull($array['product_id']);
        $this->assertNull($array['category_id']);
        $this->assertSame(1, $array['min_quantity']);
        $this->assertNull($array['min_purchase_amount']);
    }

    // ============================================
    // Getters Tests
    // ============================================

    public function testGettersReturnCorrectValues(): void
    {
        $productId = ProductId::generate();
        $discount = Discount::percentage(20.0);
        $minAmount = Money::of('150.00', 'USD');
        $rule = PricingRule::forProduct($productId, $discount, 7, $minAmount);

        $this->assertSame('product', $rule->getScope());
        $this->assertTrue($rule->getProductId()->equals($productId));
        $this->assertNull($rule->getCategoryId());
        $this->assertSame(7, $rule->getMinQuantity());
        $this->assertTrue($rule->getMinPurchaseAmount()->equals($minAmount));
        $this->assertInstanceOf(Discount::class, $rule->getDiscount());
    }
}
