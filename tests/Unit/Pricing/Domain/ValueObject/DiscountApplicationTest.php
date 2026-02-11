<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\DiscountApplication;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class DiscountApplicationTest extends TestCase
{
    public function testCreateValidDiscountApplication(): void
    {
        $promotionId = PromotionId::generate();
        $discountAmount = Money::of('10.00', 'EUR');
        $priceAfterDiscount = Money::of('90.00', 'EUR');

        $application = new DiscountApplication(
            promotionId: $promotionId,
            promotionName: 'Test Promotion',
            promotionType: PromotionType::cartRule(),
            discountAmount: $discountAmount,
            priceAfterDiscount: $priceAfterDiscount
        );

        $this->assertTrue($application->promotionId()->equals($promotionId));
        $this->assertSame('Test Promotion', $application->promotionName());
        $this->assertTrue($application->promotionType()->equals(PromotionType::cartRule()));
        $this->assertTrue($application->discountAmount()->equals($discountAmount));
        $this->assertTrue($application->priceAfterDiscount()->equals($priceAfterDiscount));
    }

    public function testRejectsNegativeDiscountAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount amount cannot be negative');

        new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Test',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('-10.00', 'EUR'),
            priceAfterDiscount: Money::of('100.00', 'EUR')
        );
    }

    public function testRejectsNegativePriceAfterDiscount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price after discount cannot be negative');

        new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Test',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('10.00', 'EUR'),
            priceAfterDiscount: Money::of('-5.00', 'EUR')
        );
    }

    public function testToArray(): void
    {
        $promotionId = PromotionId::generate();
        $application = new DiscountApplication(
            promotionId: $promotionId,
            promotionName: 'Summer Sale',
            promotionType: PromotionType::catalogRule(),
            discountAmount: Money::of('15.50', 'USD'),
            priceAfterDiscount: Money::of('84.50', 'USD')
        );

        $array = $application->toArray();

        $this->assertIsArray($array);
        $this->assertSame($promotionId->toString(), $array['promotionId']);
        $this->assertSame('Summer Sale', $array['promotionName']);
        $this->assertSame('catalog_rule', $array['promotionType']);
        $this->assertArrayHasKey('discountAmount', $array);
        $this->assertArrayHasKey('priceAfterDiscount', $array);
    }

    public function testDifferentPromotionTypes(): void
    {
        $types = [
            PromotionType::cartRule(),
            PromotionType::catalogRule(),
            PromotionType::coupon(),
        ];

        foreach ($types as $type) {
            $application = new DiscountApplication(
                promotionId: PromotionId::generate(),
                promotionName: 'Test',
                promotionType: $type,
                discountAmount: Money::of('5.00', 'EUR'),
                priceAfterDiscount: Money::of('95.00', 'EUR')
            );

            $this->assertTrue($application->promotionType()->equals($type));
        }
    }

    public function testZeroDiscountAmount(): void
    {
        $application = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'No Discount',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('0.00', 'EUR'),
            priceAfterDiscount: Money::of('100.00', 'EUR')
        );

        $this->assertTrue($application->discountAmount()->equals(Money::of('0.00', 'EUR')));
    }
}
