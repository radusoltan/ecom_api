<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\DiscountApplication;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Pricing\Domain\ValueObject\StackedDiscount;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class StackedDiscountTest extends TestCase
{
    public function testCreateValidStackedDiscount(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');
        $finalPrice = Money::of('85.00', 'EUR');
        $totalDiscount = Money::of('15.00', 'EUR');

        $application = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Test',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('15.00', 'EUR'),
            priceAfterDiscount: $finalPrice
        );

        $stacked = new StackedDiscount(
            originalPrice: $originalPrice,
            finalPrice: $finalPrice,
            totalDiscount: $totalDiscount,
            applications: [$application]
        );

        $this->assertTrue($stacked->originalPrice()->equals($originalPrice));
        $this->assertTrue($stacked->finalPrice()->equals($finalPrice));
        $this->assertTrue($stacked->totalDiscount()->equals($totalDiscount));
        $this->assertCount(1, $stacked->applications());
    }

    public function testHasDiscounts(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        // With discounts
        $withDiscounts = new StackedDiscount(
            originalPrice: $originalPrice,
            finalPrice: Money::of('90.00', 'EUR'),
            totalDiscount: Money::of('10.00', 'EUR'),
            applications: [$this->createApplication()]
        );

        $this->assertTrue($withDiscounts->hasDiscounts());

        // Without discounts
        $withoutDiscounts = new StackedDiscount(
            originalPrice: $originalPrice,
            finalPrice: $originalPrice,
            totalDiscount: Money::of('0.00', 'EUR'),
            applications: []
        );

        $this->assertFalse($withoutDiscounts->hasDiscounts());
    }

    public function testDiscountCount(): void
    {
        $stacked = new StackedDiscount(
            originalPrice: Money::of('100.00', 'EUR'),
            finalPrice: Money::of('70.00', 'EUR'),
            totalDiscount: Money::of('30.00', 'EUR'),
            applications: [
                $this->createApplication(),
                $this->createApplication(),
                $this->createApplication(),
            ]
        );

        $this->assertSame(3, $stacked->discountCount());
    }

    public function testEffectiveDiscountPercentage(): void
    {
        $stacked = new StackedDiscount(
            originalPrice: Money::of('100.00', 'EUR'),
            finalPrice: Money::of('75.00', 'EUR'),
            totalDiscount: Money::of('25.00', 'EUR'),
            applications: [$this->createApplication()]
        );

        // 25/100 * 100 = 25%
        $this->assertEqualsWithDelta(25.0, $stacked->effectiveDiscountPercentage(), 0.01);
    }

    public function testEffectiveDiscountPercentageWithZeroOriginalPrice(): void
    {
        $stacked = new StackedDiscount(
            originalPrice: Money::of('0.00', 'EUR'),
            finalPrice: Money::of('0.00', 'EUR'),
            totalDiscount: Money::of('0.00', 'EUR'),
            applications: []
        );

        $this->assertSame(0.0, $stacked->effectiveDiscountPercentage());
    }

    public function testRejectsNegativeTotalDiscount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total discount cannot be negative');

        new StackedDiscount(
            originalPrice: Money::of('100.00', 'EUR'),
            finalPrice: Money::of('110.00', 'EUR'),
            totalDiscount: Money::of('-10.00', 'EUR'),
            applications: []
        );
    }

    public function testRejectsNegativeFinalPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Final price cannot be negative');

        new StackedDiscount(
            originalPrice: Money::of('100.00', 'EUR'),
            finalPrice: Money::of('-10.00', 'EUR'),
            totalDiscount: Money::of('110.00', 'EUR'),
            applications: []
        );
    }

    public function testRejectsInconsistentCalculation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount calculation is inconsistent');

        // 100 - 10 should equal 90, not 85
        new StackedDiscount(
            originalPrice: Money::of('100.00', 'EUR'),
            finalPrice: Money::of('85.00', 'EUR'),
            totalDiscount: Money::of('10.00', 'EUR'),
            applications: []
        );
    }

    public function testToArray(): void
    {
        $application = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Test Promo',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('10.00', 'EUR'),
            priceAfterDiscount: Money::of('90.00', 'EUR')
        );

        $stacked = new StackedDiscount(
            originalPrice: Money::of('100.00', 'EUR'),
            finalPrice: Money::of('90.00', 'EUR'),
            totalDiscount: Money::of('10.00', 'EUR'),
            applications: [$application]
        );

        $array = $stacked->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('originalPrice', $array);
        $this->assertArrayHasKey('finalPrice', $array);
        $this->assertArrayHasKey('totalDiscount', $array);
        $this->assertArrayHasKey('effectiveDiscountPercentage', $array);
        $this->assertArrayHasKey('applications', $array);
        $this->assertCount(1, $array['applications']);
    }

    public function testComplexStackingScenario(): void
    {
        $app1 = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'First 20%',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('20.00', 'EUR'),
            priceAfterDiscount: Money::of('80.00', 'EUR')
        );

        $app2 = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Second 10%',
            promotionType: PromotionType::catalogRule(),
            discountAmount: Money::of('8.00', 'EUR'),
            priceAfterDiscount: Money::of('72.00', 'EUR')
        );

        $stacked = new StackedDiscount(
            originalPrice: Money::of('100.00', 'EUR'),
            finalPrice: Money::of('72.00', 'EUR'),
            totalDiscount: Money::of('28.00', 'EUR'),
            applications: [$app1, $app2]
        );

        $this->assertTrue($stacked->hasDiscounts());
        $this->assertSame(2, $stacked->discountCount());
        $this->assertEqualsWithDelta(28.0, $stacked->effectiveDiscountPercentage(), 0.01);
    }

    public function testNoDiscountsScenario(): void
    {
        $originalPrice = Money::of('100.00', 'EUR');

        $stacked = new StackedDiscount(
            originalPrice: $originalPrice,
            finalPrice: $originalPrice,
            totalDiscount: Money::of('0.00', 'EUR'),
            applications: []
        );

        $this->assertFalse($stacked->hasDiscounts());
        $this->assertSame(0, $stacked->discountCount());
        $this->assertSame(0.0, $stacked->effectiveDiscountPercentage());
    }

    private function createApplication(): DiscountApplication
    {
        return new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Test',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('10.00', 'EUR'),
            priceAfterDiscount: Money::of('90.00', 'EUR')
        );
    }
}
