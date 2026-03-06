<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\DiscountApplication;
use App\Pricing\Domain\ValueObject\PromotionId;
use App\Pricing\Domain\ValueObject\PromotionType;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiscountApplication::class)]
final class DiscountApplicationExtendedTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Construction & getters
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesWithValidData(): void
    {
        $promotionId = PromotionId::generate();
        $discountAmount = Money::of('10.00', 'USD');
        $priceAfterDiscount = Money::of('90.00', 'USD');

        $application = new DiscountApplication(
            promotionId: $promotionId,
            promotionName: 'Test Promo',
            promotionType: PromotionType::cartRule(),
            discountAmount: $discountAmount,
            priceAfterDiscount: $priceAfterDiscount,
        );

        self::assertTrue($application->promotionId()->equals($promotionId));
        self::assertSame('Test Promo', $application->promotionName());
        self::assertTrue($application->promotionType()->isCartRule());
        self::assertTrue($application->discountAmount()->equals($discountAmount));
        self::assertTrue($application->priceAfterDiscount()->equals($priceAfterDiscount));
    }

    #[Test]
    public function itAcceptsZeroDiscountAmount(): void
    {
        $application = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'No Discount',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('0.00', 'USD'),
            priceAfterDiscount: Money::of('100.00', 'USD'),
        );

        self::assertSame(0, $application->discountAmount()->getAmount());
    }

    #[Test]
    public function itAcceptsZeroPriceAfterDiscount(): void
    {
        $application = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Free Item',
            promotionType: PromotionType::catalogRule(),
            discountAmount: Money::of('100.00', 'USD'),
            priceAfterDiscount: Money::of('0.00', 'USD'),
        );

        self::assertSame(0, $application->priceAfterDiscount()->getAmount());
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsForNegativeDiscountAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount amount cannot be negative');

        new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Invalid',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('-5.00', 'USD'),
            priceAfterDiscount: Money::of('100.00', 'USD'),
        );
    }

    #[Test]
    public function itThrowsForNegativePriceAfterDiscount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price after discount cannot be negative');

        new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Invalid',
            promotionType: PromotionType::cartRule(),
            discountAmount: Money::of('10.00', 'USD'),
            priceAfterDiscount: Money::of('-5.00', 'USD'),
        );
    }

    // -----------------------------------------------------------------------
    // toArray()
    // -----------------------------------------------------------------------

    #[Test]
    public function itConvertsToArrayWithCorrectKeys(): void
    {
        $promotionId = PromotionId::generate();

        $application = new DiscountApplication(
            promotionId: $promotionId,
            promotionName: 'Summer Sale',
            promotionType: PromotionType::coupon(),
            discountAmount: Money::of('15.00', 'EUR'),
            priceAfterDiscount: Money::of('85.00', 'EUR'),
        );

        $array = $application->toArray();

        self::assertSame($promotionId->toString(), $array['promotionId']);
        self::assertSame('Summer Sale', $array['promotionName']);
        self::assertSame('coupon', $array['promotionType']);
        self::assertIsArray($array['discountAmount']);
        self::assertIsArray($array['priceAfterDiscount']);
    }

    #[Test]
    public function itConvertsMoneyToArrayCorrectly(): void
    {
        $application = new DiscountApplication(
            promotionId: PromotionId::generate(),
            promotionName: 'Black Friday',
            promotionType: PromotionType::catalogRule(),
            discountAmount: Money::of('25.00', 'EUR'),
            priceAfterDiscount: Money::of('75.00', 'EUR'),
        );

        $array = $application->toArray();

        self::assertSame(2500, (int) $array['discountAmount']['amount']);
        self::assertSame('EUR', $array['discountAmount']['currency']);
        self::assertSame(7500, (int) $array['priceAfterDiscount']['amount']);
        self::assertSame('EUR', $array['priceAfterDiscount']['currency']);
    }

    #[Test]
    public function itIncludesAllPromotionTypes(): void
    {
        $types = [
            PromotionType::cartRule(),
            PromotionType::catalogRule(),
            PromotionType::coupon(),
        ];

        foreach ($types as $type) {
            $application = new DiscountApplication(
                promotionId: PromotionId::generate(),
                promotionName: 'Type Test',
                promotionType: $type,
                discountAmount: Money::of('10.00', 'USD'),
                priceAfterDiscount: Money::of('90.00', 'USD'),
            );

            $array = $application->toArray();
            self::assertSame($type->toString(), $array['promotionType']);
        }
    }
}
