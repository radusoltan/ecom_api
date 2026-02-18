<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Pricing\Application\Service\DiscountStackingService;
use App\Pricing\Domain\Event\DiscountsStacked;
use App\Pricing\Domain\Model\Promotion;
use App\Pricing\Domain\ValueObject\CouponCode;
use App\Pricing\Domain\ValueObject\Discount;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Pricing\Domain\ValueObject\StackedDiscount;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Unit tests for enhanced DiscountStackingService.
 *
 * Tests the service with domain events, value objects, and conflict detection.
 */
final class DiscountStackingServiceTest extends TestCase
{
    private EventDispatcherInterface $eventDispatcher;
    private DiscountStackingService $service;
    private TenantId $tenantId;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->service = new DiscountStackingService($this->eventDispatcher);
        $this->tenantId = TenantId::generate();
    }

    public function testCalculateStackedDiscountWithNoPromotions(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $result = $this->service->calculateStackedDiscount([], $originalPrice, $this->tenantId);

        $this->assertInstanceOf(StackedDiscount::class, $result);
        $this->assertTrue($result->originalPrice()->equals($originalPrice));
        $this->assertTrue($result->finalPrice()->equals($originalPrice));
        $this->assertFalse($result->hasDiscounts());
        $this->assertSame(0, $result->discountCount());
    }

    public function testCalculateStackedDiscountWithSinglePromotion(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');
        $promotion = $this->createPromotion('10% Off', PromotionType::cartRule(), Discount::percentage(10.0));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(DiscountsStacked::class));

        $result = $this->service->calculateStackedDiscount([$promotion], $originalPrice, $this->tenantId);

        $this->assertTrue($result->finalPrice()->equals(Money::of('90.00', 'EUR')));
        $this->assertTrue($result->totalDiscount()->equals(Money::of('10.00', 'EUR')));
        $this->assertTrue($result->hasDiscounts());
        $this->assertSame(1, $result->discountCount());
    }

    public function testCalculateStackedDiscountWithMultiplePromotions(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $cartRule = $this->createPromotion('Cart 10%', PromotionType::cartRule(), Discount::percentage(10.0));
        $catalogRule = $this->createPromotion('Catalog 5%', PromotionType::catalogRule(), Discount::percentage(5.0));

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch');

        $result = $this->service->calculateStackedDiscount([$catalogRule, $cartRule], $originalPrice, $this->tenantId);

        // Order: cart_rule (10%) -> catalog_rule (5%)
        // 100 - 10% = 90, then 90 - 5% = 85.50
        $this->assertTrue($result->finalPrice()->equals(Money::of('85.50', 'EUR')));
        $this->assertSame(2, $result->discountCount());
    }

    public function testPromotionsAreSortedByTypePriority(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $coupon = $this->createPromotion('Coupon', PromotionType::coupon(), Discount::fixedAmount(5.0), couponCode: CouponCode::fromString('SAVE5'));
        $catalogRule = $this->createPromotion('Catalog', PromotionType::catalogRule(), Discount::fixedAmount(10.0));
        $cartRule = $this->createPromotion('Cart', PromotionType::cartRule(), Discount::fixedAmount(15.0));

        $result = $this->service->calculateStackedDiscount([$coupon, $catalogRule, $cartRule], $originalPrice, $this->tenantId);

        $applications = $result->applications();

        // Verify order: cart_rule -> catalog_rule -> coupon
        $this->assertSame('Cart', $applications[0]->promotionName());
        $this->assertSame('Catalog', $applications[1]->promotionName());
        $this->assertSame('Coupon', $applications[2]->promotionName());
    }

    public function testPromotionsAreSortedByPriorityWithinSameType(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $lowPriority = $this->createPromotion('Low', PromotionType::cartRule(), Discount::fixedAmount(5.0), priority: 100);
        $highPriority = $this->createPromotion('High', PromotionType::cartRule(), Discount::fixedAmount(10.0), priority: 900);

        $result = $this->service->calculateStackedDiscount([$lowPriority, $highPriority], $originalPrice, $this->tenantId);

        $applications = $result->applications();

        // Higher priority should be applied first
        $this->assertSame('High', $applications[0]->promotionName());
        $this->assertSame('Low', $applications[1]->promotionName());
    }

    public function testLimitToMaxThreePromotions(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        // Exactly 3 promotions (max allowed)
        $promo1 = $this->createPromotion('Promo1', PromotionType::cartRule(), Discount::percentage(10.0), priority: 500);
        $promo2 = $this->createPromotion('Promo2', PromotionType::catalogRule(), Discount::percentage(5.0));
        $promo3 = $this->createPromotion('Promo3', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));

        $result = $this->service->calculateStackedDiscount([$promo3, $promo2, $promo1], $originalPrice, $this->tenantId);

        $this->assertSame(3, $result->discountCount());
    }

    public function testValidateStackabilityWithValidCount(): void
    {
        $promo1 = $this->createPromotion('Promo1', PromotionType::cartRule(), Discount::percentage(10.0));
        $promo2 = $this->createPromotion('Promo2', PromotionType::catalogRule(), Discount::percentage(5.0));

        $isValid = $this->service->validateStackability([$promo1, $promo2]);

        $this->assertTrue($isValid);
    }

    public function testValidateStackabilityWithExcessiveCount(): void
    {
        $promo1 = $this->createPromotion('Promo1', PromotionType::cartRule(), Discount::percentage(10.0));
        $promo2 = $this->createPromotion('Promo2', PromotionType::catalogRule(), Discount::percentage(5.0));
        $promo3 = $this->createPromotion('Promo3', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));
        $promo4 = $this->createPromotion('Promo4', PromotionType::cartRule(), Discount::percentage(5.0));

        $isValid = $this->service->validateStackability([$promo1, $promo2, $promo3, $promo4]);

        $this->assertFalse($isValid);
    }

    public function testThrowsExceptionWhenStackabilityValidationFails(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot stack 4 promotions. Maximum allowed: 3');

        $originalPrice = Money::of('100.00', 'EUR');
        $promotions = [
            $this->createPromotion('Promo1', PromotionType::cartRule(), Discount::percentage(10.0)),
            $this->createPromotion('Promo2', PromotionType::catalogRule(), Discount::percentage(5.0)),
            $this->createPromotion('Promo3', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5')),
            $this->createPromotion('Promo4', PromotionType::cartRule(), Discount::percentage(5.0)),
        ];

        $this->service->calculateStackedDiscount($promotions, $originalPrice, $this->tenantId);
    }

    public function testDetectsConflictWithMultipleCoupons(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot stack multiple coupon codes');

        $originalPrice = Money::of('100.00', 'EUR');
        $coupon1 = $this->createPromotion('Coupon1', PromotionType::coupon(), Discount::percentage(10.0), couponCode: CouponCode::fromString('SAVE10'));
        $coupon2 = $this->createPromotion('Coupon2', PromotionType::coupon(), Discount::percentage(5.0), couponCode: CouponCode::fromString('SAVE5'));

        $this->service->calculateStackedDiscount([$coupon1, $coupon2], $originalPrice, $this->tenantId);
    }

    public function testEmitsDiscountsStackedEvent(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');
        $promotion = $this->createPromotion('Test', PromotionType::cartRule(), Discount::percentage(10.0));

        $capturedEvent = null;
        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$capturedEvent) {
                $capturedEvent = $event;

                return $event;
            });

        $this->service->calculateStackedDiscount([$promotion], $originalPrice, $this->tenantId);

        $this->assertInstanceOf(DiscountsStacked::class, $capturedEvent);
        $this->assertTrue($capturedEvent->tenantId()->equals($this->tenantId));
        $this->assertSame(10000, $capturedEvent->originalAmount());
        $this->assertSame(9000, $capturedEvent->finalAmount());
        $this->assertSame(1000, $capturedEvent->totalDiscountAmount());
        $this->assertSame('EUR', $capturedEvent->currency());
    }

    public function testDoesNotEmitEventWhenNoDiscountsApplied(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $this->service->calculateStackedDiscount([], $originalPrice, $this->tenantId);
    }

    public function testGetMaxStackablePromotions(): void
    {
        $max = $this->service->getMaxStackablePromotions();

        $this->assertSame(3, $max);
    }

    public function testComplexRealWorldScenario(): void
    {
        // Black Friday scenario: 25% store-wide + 10% electronics + $10 loyalty coupon
        $originalPrice = Money::of('200.00', 'EUR');

        $blackFriday = $this->createPromotion('Black Friday', PromotionType::cartRule(), Discount::percentage(25.0), priority: 1000);
        $electronics = $this->createPromotion('Electronics', PromotionType::catalogRule(), Discount::percentage(10.0), priority: 500);
        $loyalty = $this->createPromotion('Loyalty', PromotionType::coupon(), Discount::fixedAmount(10.0), couponCode: CouponCode::fromString('LOYAL10'));

        $result = $this->service->calculateStackedDiscount([$loyalty, $electronics, $blackFriday], $originalPrice, $this->tenantId);

        // Step 1: 200 - 25% = 150
        // Step 2: 150 - 10% = 135
        // Step 3: 135 - 10 = 125
        $this->assertTrue($result->finalPrice()->equals(Money::of('125.00', 'EUR')));
        $this->assertTrue($result->totalDiscount()->equals(Money::of('75.00', 'EUR')));
        $this->assertEqualsWithDelta(37.5, $result->effectiveDiscountPercentage(), 0.01);
    }

    public function testDiscountApplicationsContainCorrectData(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');
        $promotion = $this->createPromotion('Test Promo', PromotionType::cartRule(), Discount::percentage(10.0));

        $result = $this->service->calculateStackedDiscount([$promotion], $originalPrice, $this->tenantId);

        $applications = $result->applications();
        $this->assertCount(1, $applications);

        $app = $applications[0];
        $this->assertSame('Test Promo', $app->promotionName());
        $this->assertTrue($app->promotionType()->equals(PromotionType::cartRule()));
        $this->assertTrue($app->discountAmount()->equals(Money::of('10.00', 'EUR')));
        $this->assertTrue($app->priceAfterDiscount()->equals(Money::of('90.00', 'EUR')));
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
