<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Pricing\Application\Service\PromotionStackingService;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PromotionStackingService.
 *
 * Business Rules:
 * - Maximum 3 promotions can be stacked
 * - Promotions are applied in priority order: cart_rule -> catalog_rule -> coupon
 * - Within same type, higher priority number applied first
 * - Each promotion applies to already-discounted price
 * - Total discount cannot exceed original price
 */
final class PromotionStackingServiceTest extends TestCase
{
    private PromotionStackingService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->service = new PromotionStackingService();
        $this->tenantId = TenantId::generate();
    }

    public function testCalculatePriceWithNoPromotions(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $result = $this->service->calculatePriceWithPromotions([], $originalPrice);

        $this->assertTrue($result['finalPrice']->equals($originalPrice));
        $this->assertSame([], $result['appliedPromotions']);
        $this->assertTrue($result['totalDiscount']->equals(Money::fromScalars(0, 'EUR')));
    }

    public function testCalculatePriceWithSinglePercentageDiscount(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');
        $promotion = $this->createPromotion('10% Off', PromotionType::cartRule(), Discount::percentage(10.0));

        $result = $this->service->calculatePriceWithPromotions([$promotion], $originalPrice);

        // 100 - 10% = 90
        $this->assertTrue($result['finalPrice']->equals(Money::of('90.00', 'EUR')));
        $this->assertCount(1, $result['appliedPromotions']);
        $this->assertTrue($result['totalDiscount']->equals(Money::of('10.00', 'EUR')));
    }

    public function testCalculatePriceWithSingleFixedAmountDiscount(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');
        $promotion = $this->createPromotion('$5 Off', PromotionType::cartRule(), Discount::fixedAmount(5.0));

        $result = $this->service->calculatePriceWithPromotions([$promotion], $originalPrice);

        // 100 - 5 = 95
        $this->assertTrue($result['finalPrice']->equals(Money::of('95.00', 'EUR')));
        $this->assertCount(1, $result['appliedPromotions']);
        $this->assertTrue($result['totalDiscount']->equals(Money::of('5.00', 'EUR')));
    }

    public function testStackTwoPromotionsInCorrectOrder(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $cartRule = $this->createPromotion('Cart 10%', PromotionType::cartRule(), Discount::percentage(10.0));
        $coupon = $this->createPromotion('Coupon 5%', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));

        $result = $this->service->calculatePriceWithPromotions([$coupon, $cartRule], $originalPrice);

        // Order should be: cart_rule first, then coupon
        // Step 1: 100 - 10% = 90
        // Step 2: 90 - 5% = 85.50
        $this->assertTrue($result['finalPrice']->equals(Money::of('85.50', 'EUR')));
        $this->assertCount(2, $result['appliedPromotions']);

        // Verify order of application
        $this->assertSame('cart_rule', $result['appliedPromotions'][0]['type']);
        $this->assertSame('coupon', $result['appliedPromotions'][1]['type']);
    }

    public function testStackThreePromotionsMaximumLimit(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $cartRule = $this->createPromotion('Cart 10%', PromotionType::cartRule(), Discount::percentage(10.0));
        $catalogRule = $this->createPromotion('Catalog 5%', PromotionType::catalogRule(), Discount::percentage(5.0));
        $coupon = $this->createPromotion('Coupon 5%', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));

        $result = $this->service->calculatePriceWithPromotions([$coupon, $cartRule, $catalogRule], $originalPrice);

        // Order: cart_rule -> catalog_rule -> coupon
        // Step 1: 100 - 10% = 90
        // Step 2: 90 - 5% = 85.50
        // Step 3: 85.50 - 5% = 81.225 (rounded)
        $this->assertCount(3, $result['appliedPromotions']);
        $this->assertSame('cart_rule', $result['appliedPromotions'][0]['type']);
        $this->assertSame('catalog_rule', $result['appliedPromotions'][1]['type']);
        $this->assertSame('coupon', $result['appliedPromotions'][2]['type']);
    }

    public function testLimitToMaxThreePromotions(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $promo1 = $this->createPromotion('Cart 1', PromotionType::cartRule(), Discount::percentage(10.0), priority: 500);
        $promo2 = $this->createPromotion('Cart 2', PromotionType::cartRule(), Discount::percentage(5.0), priority: 400);
        $promo3 = $this->createPromotion('Catalog 1', PromotionType::catalogRule(), Discount::percentage(5.0));
        $promo4 = $this->createPromotion('Coupon 1', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));

        $result = $this->service->calculatePriceWithPromotions([$promo4, $promo3, $promo2, $promo1], $originalPrice);

        // Should only apply first 3 promotions after sorting
        $this->assertCount(3, $result['appliedPromotions']);
    }

    public function testPriorityOrderWithinSameType(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        // Two cart_rule promotions with different priorities
        $lowPriority = $this->createPromotion('Cart Low', PromotionType::cartRule(), Discount::fixedAmount(5.0), priority: 100);
        $highPriority = $this->createPromotion('Cart High', PromotionType::cartRule(), Discount::fixedAmount(10.0), priority: 900);

        $result = $this->service->calculatePriceWithPromotions([$lowPriority, $highPriority], $originalPrice);

        // Higher priority should be applied first
        $this->assertSame('Cart High', $result['appliedPromotions'][0]['name']);
        $this->assertSame('Cart Low', $result['appliedPromotions'][1]['name']);
    }

    public function testSequentialDiscountApplication(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $first = $this->createPromotion('First 20%', PromotionType::cartRule(), Discount::percentage(20.0));
        $second = $this->createPromotion('Second 10%', PromotionType::catalogRule(), Discount::percentage(10.0));

        $result = $this->service->calculatePriceWithPromotions([$first, $second], $originalPrice);

        // Step 1: 100 - 20% = 80
        $this->assertSame(8000, $result['appliedPromotions'][0]['priceAfterDiscount']);

        // Step 2: 80 - 10% = 72
        $this->assertSame(7200, $result['appliedPromotions'][1]['priceAfterDiscount']);

        $this->assertTrue($result['finalPrice']->equals(Money::of('72.00', 'EUR')));
    }

    public function testMixedPercentageAndFixedDiscounts(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $percentageDiscount = $this->createPromotion('10% Off', PromotionType::cartRule(), Discount::percentage(10.0));
        $fixedDiscount = $this->createPromotion('$5 Off', PromotionType::catalogRule(), Discount::fixedAmount(5.0));

        $result = $this->service->calculatePriceWithPromotions([$percentageDiscount, $fixedDiscount], $originalPrice);

        // Step 1: 100 - 10% = 90
        // Step 2: 90 - 5 = 85
        $this->assertTrue($result['finalPrice']->equals(Money::of('85.00', 'EUR')));
    }

    public function testTotalDiscountCalculation(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $promo1 = $this->createPromotion('First', PromotionType::cartRule(), Discount::fixedAmount(10.0));
        $promo2 = $this->createPromotion('Second', PromotionType::catalogRule(), Discount::fixedAmount(5.0));

        $result = $this->service->calculatePriceWithPromotions([$promo1, $promo2], $originalPrice);

        // Total discount: 10 + 5 = 15
        $this->assertTrue($result['totalDiscount']->equals(Money::of('15.00', 'EUR')));
        $this->assertTrue($result['finalPrice']->equals(Money::of('85.00', 'EUR')));
    }

    public function testAppliedPromotionsDetails(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $promotion = $this->createPromotion('Test Promo', PromotionType::cartRule(), Discount::percentage(10.0));

        $result = $this->service->calculatePriceWithPromotions([$promotion], $originalPrice);

        $this->assertCount(1, $result['appliedPromotions']);

        $appliedPromo = $result['appliedPromotions'][0];
        $this->assertArrayHasKey('promotionId', $appliedPromo);
        $this->assertArrayHasKey('name', $appliedPromo);
        $this->assertArrayHasKey('type', $appliedPromo);
        $this->assertArrayHasKey('discountAmount', $appliedPromo);
        $this->assertArrayHasKey('priceAfterDiscount', $appliedPromo);

        $this->assertSame('Test Promo', $appliedPromo['name']);
        $this->assertSame('cart_rule', $appliedPromo['type']);
        $this->assertSame(1000, $appliedPromo['discountAmount']); // 10 EUR in minor units
        $this->assertSame(9000, $appliedPromo['priceAfterDiscount']); // 90 EUR in minor units
    }

    public function testComplexStackingScenario(): void
    {
        // Real-world scenario: Black Friday sale
        $originalPrice = Money::of('200.00', 'EUR');

        $blackFriday = $this->createPromotion('Black Friday 25%', PromotionType::cartRule(), Discount::percentage(25.0), priority: 1000);
        $categoryDiscount = $this->createPromotion('Electronics 10%', PromotionType::catalogRule(), Discount::percentage(10.0), priority: 500);
        $loyaltyCoupon = $this->createPromotion('Loyalty $10', PromotionType::coupon(), Discount::fixedAmount(10.0), couponCode: CouponCode::fromString('LOYALTY10'));

        $result = $this->service->calculatePriceWithPromotions([$loyaltyCoupon, $categoryDiscount, $blackFriday], $originalPrice);

        // Step 1: 200 - 25% = 150 (Black Friday)
        // Step 2: 150 - 10% = 135 (Electronics)
        // Step 3: 135 - 10 = 125 (Loyalty coupon)

        $this->assertTrue($result['finalPrice']->equals(Money::of('125.00', 'EUR')));
        $this->assertTrue($result['totalDiscount']->equals(Money::of('75.00', 'EUR')));
    }

    public function testGetMaxStackablePromotions(): void
    {
        $max = $this->service->getMaxStackablePromotions();

        $this->assertSame(3, $max);
    }

    public function testValidateStackabilityWithValidCount(): void
    {
        $promo1 = $this->createPromotion('Promo 1', PromotionType::cartRule(), Discount::percentage(10.0));
        $promo2 = $this->createPromotion('Promo 2', PromotionType::catalogRule(), Discount::percentage(5.0));

        $isValid = $this->service->validateStackability([$promo1, $promo2]);

        $this->assertTrue($isValid);
    }

    public function testValidateStackabilityWithExcessiveCount(): void
    {
        $promo1 = $this->createPromotion('Promo 1', PromotionType::cartRule(), Discount::percentage(10.0));
        $promo2 = $this->createPromotion('Promo 2', PromotionType::catalogRule(), Discount::percentage(5.0));
        $promo3 = $this->createPromotion('Promo 3', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));
        $promo4 = $this->createPromotion('Promo 4', PromotionType::cartRule(), Discount::percentage(5.0));

        $isValid = $this->service->validateStackability([$promo1, $promo2, $promo3, $promo4]);

        $this->assertFalse($isValid);
    }

    public function testStackingOrderIsConsistentAcrossMultipleCalls(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $coupon = $this->createPromotion('Coupon', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));
        $catalogRule = $this->createPromotion('Catalog', PromotionType::catalogRule(), Discount::percentage(10.0));
        $cartRule = $this->createPromotion('Cart', PromotionType::cartRule(), Discount::percentage(15.0));

        // Call multiple times with different input order
        $result1 = $this->service->calculatePriceWithPromotions([$coupon, $catalogRule, $cartRule], $originalPrice);
        $result2 = $this->service->calculatePriceWithPromotions([$cartRule, $coupon, $catalogRule], $originalPrice);
        $result3 = $this->service->calculatePriceWithPromotions([$catalogRule, $cartRule, $coupon], $originalPrice);

        // All results should be identical
        $this->assertTrue($result1['finalPrice']->equals($result2['finalPrice']));
        $this->assertTrue($result2['finalPrice']->equals($result3['finalPrice']));

        // Verify application order is always the same
        $this->assertSame('cart_rule', $result1['appliedPromotions'][0]['type']);
        $this->assertSame('cart_rule', $result2['appliedPromotions'][0]['type']);
        $this->assertSame('cart_rule', $result3['appliedPromotions'][0]['type']);
    }

    public function testZeroDiscountDoesNotChangePrice(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        // Create promotion with minimum allowed percentage (0.01%)
        $promotion = $this->createPromotion('Tiny Discount', PromotionType::cartRule(), Discount::percentage(0.01));

        $result = $this->service->calculatePriceWithPromotions([$promotion], $originalPrice);

        // 100 - 0.01% = 99.99
        $this->assertTrue($result['finalPrice']->equals(Money::of('99.99', 'EUR')));
    }

    public function testDifferentCurrencies(): void
    {
        $originalPriceUSD = Money::of('100.00', 'USD');
        $promotion = $this->createPromotion('10% Off', PromotionType::cartRule(), Discount::percentage(10.0));

        $result = $this->service->calculatePriceWithPromotions([$promotion], $originalPriceUSD);

        $this->assertSame('USD', $result['finalPrice']->getCurrency()->getCurrencyCode());
        $this->assertTrue($result['finalPrice']->equals(Money::of('90.00', 'USD')));
    }

    private function createPromotion(
        string $name,
        PromotionType $type,
        Discount $discount,
        int $priority = 100,
        ?CouponCode $couponCode = null,
    ): Promotion {
        $promotion = Promotion::create(
            id: PromotionId::generate(),
            tenantId: $this->tenantId,
            name: $name,
            type: $type,
            discount: $discount,
            priority: $priority,
            couponCode: $couponCode
        );

        $promotion->activate();

        return $promotion;
    }
}
