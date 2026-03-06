<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\DTO;

use App\Pricing\Application\DTO\Analytics\DateRangeFilter;
use App\Pricing\Application\DTO\Analytics\DiscountUsageDTO;
use App\Pricing\Application\DTO\Analytics\PricingSummaryDTO;
use App\Pricing\Application\DTO\Analytics\PromotionPerformanceDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateRangeFilter::class)]
#[CoversClass(DiscountUsageDTO::class)]
#[CoversClass(PricingSummaryDTO::class)]
final class PricingAnalyticsDTOsTest extends TestCase
{
    // -----------------------------------------------------------------------
    // DateRangeFilter — construction
    // -----------------------------------------------------------------------

    #[Test]
    public function dateRangeFilterConstructionWithAllNulls(): void
    {
        $filter = new DateRangeFilter();

        self::assertNull($filter->startDate);
        self::assertNull($filter->endDate);
        self::assertNull($filter->preset);
        self::assertFalse($filter->hasStartDate());
        self::assertFalse($filter->hasEndDate());
        self::assertFalse($filter->isPreset());
        self::assertSame(0, $filter->getDays());
    }

    #[Test]
    public function dateRangeFilterConstructionWithValues(): void
    {
        $start = new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable('2025-01-31 23:59:59', new \DateTimeZone('UTC'));

        $filter = new DateRangeFilter(startDate: $start, endDate: $end, preset: 'this_month');

        self::assertSame($start, $filter->startDate);
        self::assertSame($end, $filter->endDate);
        self::assertSame('this_month', $filter->preset);
        self::assertTrue($filter->hasStartDate());
        self::assertTrue($filter->hasEndDate());
        self::assertTrue($filter->isPreset());
    }

    // -----------------------------------------------------------------------
    // DateRangeFilter — fromDates
    // -----------------------------------------------------------------------

    #[Test]
    public function dateRangeFilterFromDatesCreatesCorrectFilter(): void
    {
        $start = new \DateTimeImmutable('2025-03-01');
        $end = new \DateTimeImmutable('2025-03-31');

        $filter = DateRangeFilter::fromDates($start, $end);

        self::assertSame($start, $filter->startDate);
        self::assertSame($end, $filter->endDate);
        self::assertNull($filter->preset);
        self::assertFalse($filter->isPreset());
    }

    #[Test]
    public function dateRangeFilterFromDatesWithSameDayIsAllowed(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');

        $filter = DateRangeFilter::fromDates($date, $date);

        self::assertSame($date, $filter->startDate);
        self::assertSame($date, $filter->endDate);
        self::assertSame(0, $filter->getDays());
    }

    #[Test]
    public function dateRangeFilterFromDatesThrowsWhenStartAfterEnd(): void
    {
        $start = new \DateTimeImmutable('2025-06-30');
        $end = new \DateTimeImmutable('2025-06-01');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Start date must be before or equal to end date');

        DateRangeFilter::fromDates($start, $end);
    }

    // -----------------------------------------------------------------------
    // DateRangeFilter — getDays
    // -----------------------------------------------------------------------

    #[Test]
    public function dateRangeFilterGetDaysCalculatesCorrectly(): void
    {
        $filter = DateRangeFilter::fromDates(
            new \DateTimeImmutable('2025-01-01'),
            new \DateTimeImmutable('2025-01-31')
        );

        self::assertSame(30, $filter->getDays());
    }

    #[Test]
    public function dateRangeFilterGetDaysReturnsZeroWhenNoDates(): void
    {
        $filter = new DateRangeFilter();

        self::assertSame(0, $filter->getDays());
    }

    #[Test]
    public function dateRangeFilterGetDaysReturnsZeroWhenOnlyStartDate(): void
    {
        $filter = new DateRangeFilter(startDate: new \DateTimeImmutable('2025-01-01'));

        self::assertSame(0, $filter->getDays());
    }

    // -----------------------------------------------------------------------
    // DateRangeFilter — fromPreset
    // -----------------------------------------------------------------------

    #[Test]
    public function dateRangeFilterFromPresetToday(): void
    {
        $filter = DateRangeFilter::fromPreset('today');

        self::assertSame('today', $filter->preset);
        self::assertTrue($filter->isPreset());
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->startDate);
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->endDate);
        self::assertSame('00:00:00', $filter->startDate->format('H:i:s'));
        self::assertSame('23:59:59', $filter->endDate->format('H:i:s'));
    }

    #[Test]
    public function dateRangeFilterFromPresetYesterday(): void
    {
        $filter = DateRangeFilter::fromPreset('yesterday');

        self::assertSame('yesterday', $filter->preset);
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->startDate);
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->endDate);
    }

    #[Test]
    public function dateRangeFilterFromPresetLast7Days(): void
    {
        $filter = DateRangeFilter::fromPreset('last_7_days');

        self::assertSame('last_7_days', $filter->preset);
        // 7-day span results in 7 days diff
        self::assertSame(7, $filter->getDays());
    }

    #[Test]
    public function dateRangeFilterFromPresetLast30Days(): void
    {
        $filter = DateRangeFilter::fromPreset('last_30_days');

        self::assertSame('last_30_days', $filter->preset);
        self::assertSame(30, $filter->getDays());
    }

    #[Test]
    public function dateRangeFilterFromPresetThisMonth(): void
    {
        $filter = DateRangeFilter::fromPreset('this_month');

        self::assertSame('this_month', $filter->preset);
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->startDate);
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->endDate);
        self::assertSame('00:00:00', $filter->startDate->format('H:i:s'));
        self::assertSame('23:59:59', $filter->endDate->format('H:i:s'));
    }

    #[Test]
    public function dateRangeFilterFromPresetLastMonth(): void
    {
        $filter = DateRangeFilter::fromPreset('last_month');

        self::assertSame('last_month', $filter->preset);
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->startDate);
        self::assertInstanceOf(\DateTimeImmutable::class, $filter->endDate);
    }

    #[Test]
    public function dateRangeFilterFromPresetThrowsForInvalidPreset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid preset: invalid_preset');

        DateRangeFilter::fromPreset('invalid_preset');
    }

    #[Test]
    public function dateRangeFilterDefaultCreatesLast30Days(): void
    {
        $filter = DateRangeFilter::default();

        self::assertSame('last_30_days', $filter->preset);
        self::assertSame(30, $filter->getDays());
    }

    // -----------------------------------------------------------------------
    // DiscountUsageDTO — construction
    // -----------------------------------------------------------------------

    #[Test]
    public function discountUsageDtoConstructionHoldsAllFields(): void
    {
        $topCoupons = [
            ['coupon_code' => 'SAVE10', 'promotion_name' => 'Summer Sale', 'usage_count' => 50, 'total_discount' => 5000, 'currency' => 'USD'],
        ];
        $unusedPromotions = [
            ['id' => 'promo-1', 'name' => 'Unused Promo', 'type' => 'percentage', 'coupon_code' => 'UNUSED'],
        ];

        $dto = new DiscountUsageDTO(
            totalCouponsUsed: 150,
            totalDiscountAmount: 75000,
            totalDiscountCurrency: 'USD',
            averageDiscountPerOrder: 25.50,
            topCoupons: $topCoupons,
            unusedPromotions: $unusedPromotions,
            activeCouponsCount: 12,
            expiredCouponsCount: 3,
        );

        self::assertSame(150, $dto->totalCouponsUsed);
        self::assertSame(75000, $dto->totalDiscountAmount);
        self::assertSame('USD', $dto->totalDiscountCurrency);
        self::assertSame(25.50, $dto->averageDiscountPerOrder);
        self::assertSame($topCoupons, $dto->topCoupons);
        self::assertSame($unusedPromotions, $dto->unusedPromotions);
        self::assertSame(12, $dto->activeCouponsCount);
        self::assertSame(3, $dto->expiredCouponsCount);
    }

    #[Test]
    public function discountUsageDtoFromCreate(): void
    {
        $summary = [
            'total_coupons_used' => '42',
            'total_discount_amount' => '10000',
            'total_discount_currency' => 'EUR',
            'average_discount_per_order' => '15.75',
            'active_coupons_count' => '8',
            'expired_coupons_count' => '2',
        ];

        $dto = DiscountUsageDTO::create($summary, [], []);

        self::assertSame(42, $dto->totalCouponsUsed);
        self::assertSame(10000, $dto->totalDiscountAmount);
        self::assertSame('EUR', $dto->totalDiscountCurrency);
        self::assertSame(15.75, $dto->averageDiscountPerOrder);
        self::assertSame([], $dto->topCoupons);
        self::assertSame([], $dto->unusedPromotions);
        self::assertSame(8, $dto->activeCouponsCount);
        self::assertSame(2, $dto->expiredCouponsCount);
    }

    #[Test]
    public function discountUsageDtoFromCreateDefaultsCurrencyToUsd(): void
    {
        $summary = [
            'total_coupons_used' => '0',
            'total_discount_amount' => '0',
            'average_discount_per_order' => '0',
            'active_coupons_count' => '0',
            'expired_coupons_count' => '0',
            // no total_discount_currency key
        ];

        $dto = DiscountUsageDTO::create($summary, [], []);

        self::assertSame('USD', $dto->totalDiscountCurrency);
    }

    // -----------------------------------------------------------------------
    // DiscountUsageDTO — toArray
    // -----------------------------------------------------------------------

    #[Test]
    public function discountUsageDtoToArrayHasSummaryStructure(): void
    {
        $dto = new DiscountUsageDTO(
            totalCouponsUsed: 20,
            totalDiscountAmount: 5000,
            totalDiscountCurrency: 'USD',
            averageDiscountPerOrder: 10.5,
            topCoupons: [],
            unusedPromotions: [],
            activeCouponsCount: 5,
            expiredCouponsCount: 1,
        );

        $array = $dto->toArray();

        self::assertArrayHasKey('summary', $array);
        self::assertArrayHasKey('topCoupons', $array);
        self::assertArrayHasKey('unusedPromotions', $array);

        $summary = $array['summary'];
        self::assertSame(20, $summary['totalCouponsUsed']);
        self::assertSame(5000, $summary['totalDiscount']['amount']);
        self::assertSame('USD', $summary['totalDiscount']['currency']);
        self::assertSame('50.00 USD', $summary['totalDiscount']['formatted']);
        self::assertSame(10.5, $summary['averageDiscountPerOrder']);
        self::assertSame(5, $summary['activeCouponsCount']);
        self::assertSame(1, $summary['expiredCouponsCount']);
    }

    #[Test]
    public function discountUsageDtoToArrayMapsTopCoupons(): void
    {
        $topCoupons = [
            [
                'coupon_code' => 'SAVE20',
                'promotion_name' => 'Big Sale',
                'usage_count' => '100',
                'total_discount' => '20000',
                'currency' => 'EUR',
            ],
        ];

        $dto = new DiscountUsageDTO(
            totalCouponsUsed: 100,
            totalDiscountAmount: 20000,
            totalDiscountCurrency: 'EUR',
            averageDiscountPerOrder: 200.0,
            topCoupons: $topCoupons,
            unusedPromotions: [],
            activeCouponsCount: 3,
            expiredCouponsCount: 0,
        );

        $array = $dto->toArray();
        self::assertCount(1, $array['topCoupons']);
        $coupon = $array['topCoupons'][0];
        self::assertSame('SAVE20', $coupon['code']);
        self::assertSame('Big Sale', $coupon['name']);
        self::assertSame(100, $coupon['usageCount']);
        self::assertSame(20000, $coupon['totalDiscount']['amount']);
        self::assertSame('EUR', $coupon['totalDiscount']['currency']);
    }

    #[Test]
    public function discountUsageDtoToArrayMapsUnusedPromotions(): void
    {
        $unusedPromotions = [
            [
                'id' => 'promo-x',
                'name' => 'Ghost Promo',
                'type' => 'percentage',
                'coupon_code' => 'GHOST',
                'valid_from' => '2025-01-01 00:00:00',
                'valid_to' => '2025-06-30 23:59:59',
            ],
        ];

        $dto = new DiscountUsageDTO(
            totalCouponsUsed: 0,
            totalDiscountAmount: 0,
            totalDiscountCurrency: 'USD',
            averageDiscountPerOrder: 0.0,
            topCoupons: [],
            unusedPromotions: $unusedPromotions,
            activeCouponsCount: 1,
            expiredCouponsCount: 0,
        );

        $array = $dto->toArray();
        self::assertCount(1, $array['unusedPromotions']);
        $promo = $array['unusedPromotions'][0];
        self::assertSame('promo-x', $promo['id']);
        self::assertSame('Ghost Promo', $promo['name']);
        self::assertSame('percentage', $promo['type']);
        self::assertSame('GHOST', $promo['couponCode']);
        // Dates should be ISO 8601
        self::assertStringContainsString('2025-01-01', $promo['validFrom']);
        self::assertStringContainsString('2025-06-30', $promo['validTo']);
    }

    #[Test]
    public function discountUsageDtoToArrayHandlesNullDatesInUnusedPromotions(): void
    {
        $unusedPromotions = [
            ['id' => 'p1', 'name' => 'Eternal', 'type' => 'fixed'],
        ];

        $dto = new DiscountUsageDTO(
            totalCouponsUsed: 0,
            totalDiscountAmount: 0,
            totalDiscountCurrency: 'USD',
            averageDiscountPerOrder: 0.0,
            topCoupons: [],
            unusedPromotions: $unusedPromotions,
            activeCouponsCount: 0,
            expiredCouponsCount: 0,
        );

        $array = $dto->toArray();
        $promo = $array['unusedPromotions'][0];
        self::assertNull($promo['couponCode']);
        self::assertNull($promo['validFrom']);
        self::assertNull($promo['validTo']);
    }

    // -----------------------------------------------------------------------
    // PricingSummaryDTO — construction
    // -----------------------------------------------------------------------

    #[Test]
    public function pricingSummaryDtoConstructionHoldsAllFields(): void
    {
        $period = DateRangeFilter::fromPreset('last_30_days');
        $topPromotion = new PromotionPerformanceDTO(
            promotionId: 'promo-1',
            promotionName: 'Top Sale',
            promotionType: 'percentage',
            ordersCount: 100,
            totalRevenueAmount: 50000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 5000,
            totalDiscountCurrency: 'USD',
            averageDiscountAmount: 50.0,
            conversionRate: 10.0,
        );

        $dto = new PricingSummaryDTO(
            totalOrdersCount: 500,
            totalRevenueAmount: 250000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 25000,
            totalDiscountCurrency: 'USD',
            averageOrderValue: 500.0,
            discountRate: 10.0,
            promotionsActiveCount: 5,
            couponsUsedCount: 42,
            topPromotions: [$topPromotion],
            period: $period,
        );

        self::assertSame(500, $dto->totalOrdersCount);
        self::assertSame(250000, $dto->totalRevenueAmount);
        self::assertSame('USD', $dto->totalRevenueCurrency);
        self::assertSame(25000, $dto->totalDiscountAmount);
        self::assertSame('USD', $dto->totalDiscountCurrency);
        self::assertSame(500.0, $dto->averageOrderValue);
        self::assertSame(10.0, $dto->discountRate);
        self::assertSame(5, $dto->promotionsActiveCount);
        self::assertSame(42, $dto->couponsUsedCount);
        self::assertCount(1, $dto->topPromotions);
        self::assertSame($period, $dto->period);
    }

    #[Test]
    public function pricingSummaryDtoFromCreate(): void
    {
        $period = DateRangeFilter::fromPreset('last_7_days');
        $summary = [
            'total_orders_count' => '100',
            'total_revenue_amount' => '500000',
            'total_revenue_currency' => 'EUR',
            'total_discount_amount' => '25000',
            'total_discount_currency' => 'EUR',
            'average_order_value' => '5000.0',
            'discount_rate' => '5.0',
            'promotions_active_count' => '3',
            'coupons_used_count' => '15',
        ];

        $dto = PricingSummaryDTO::create($summary, [], $period);

        self::assertSame(100, $dto->totalOrdersCount);
        self::assertSame(500000, $dto->totalRevenueAmount);
        self::assertSame('EUR', $dto->totalRevenueCurrency);
        self::assertSame(25000, $dto->totalDiscountAmount);
        self::assertSame('EUR', $dto->totalDiscountCurrency);
        self::assertSame(5000.0, $dto->averageOrderValue);
        self::assertSame(5.0, $dto->discountRate);
        self::assertSame(3, $dto->promotionsActiveCount);
        self::assertSame(15, $dto->couponsUsedCount);
        self::assertSame([], $dto->topPromotions);
        self::assertSame($period, $dto->period);
    }

    #[Test]
    public function pricingSummaryDtoFromCreateDefaultsCurrencyToUsd(): void
    {
        $period = DateRangeFilter::fromPreset('today');
        $summary = [
            'total_orders_count' => '0',
            'total_revenue_amount' => '0',
            // no currency keys
            'total_discount_amount' => '0',
            'average_order_value' => '0',
            'discount_rate' => '0',
            'promotions_active_count' => '0',
            'coupons_used_count' => '0',
        ];

        $dto = PricingSummaryDTO::create($summary, [], $period);

        self::assertSame('USD', $dto->totalRevenueCurrency);
        self::assertSame('USD', $dto->totalDiscountCurrency);
    }

    // -----------------------------------------------------------------------
    // PricingSummaryDTO — toArray
    // -----------------------------------------------------------------------

    #[Test]
    public function pricingSummaryDtoToArrayHasExpectedStructure(): void
    {
        $period = DateRangeFilter::fromDates(
            new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new \DateTimeImmutable('2025-01-31T23:59:59+00:00')
        );

        $dto = new PricingSummaryDTO(
            totalOrdersCount: 200,
            totalRevenueAmount: 100000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 10000,
            totalDiscountCurrency: 'USD',
            averageOrderValue: 500.0,
            discountRate: 10.0,
            promotionsActiveCount: 3,
            couponsUsedCount: 20,
            topPromotions: [],
            period: $period,
        );

        $array = $dto->toArray();

        self::assertArrayHasKey('period', $array);
        self::assertArrayHasKey('summary', $array);
        self::assertArrayHasKey('topPromotions', $array);

        // period
        self::assertStringContainsString('2025-01-01', $array['period']['startDate']);
        self::assertStringContainsString('2025-01-31', $array['period']['endDate']);
        self::assertNull($array['period']['preset']);
        self::assertSame(30, $array['period']['days']);

        // summary
        $s = $array['summary'];
        self::assertSame(200, $s['totalOrders']);
        self::assertSame(100000, $s['totalRevenue']['amount']);
        self::assertSame('USD', $s['totalRevenue']['currency']);
        self::assertSame('1,000.00 USD', $s['totalRevenue']['formatted']);
        self::assertSame(10000, $s['totalDiscount']['amount']);
        self::assertSame('USD', $s['totalDiscount']['currency']);
        self::assertSame('100.00 USD', $s['totalDiscount']['formatted']);
        self::assertSame(500.0, $s['averageOrderValue']);
        self::assertSame('10%', $s['discountRate']);
        self::assertSame(3, $s['promotionsActive']);
        self::assertSame(20, $s['couponsUsed']);

        // topPromotions
        self::assertSame([], $array['topPromotions']);
    }

    #[Test]
    public function pricingSummaryDtoToArrayWithPresetPeriod(): void
    {
        $period = DateRangeFilter::fromPreset('last_30_days');

        $dto = new PricingSummaryDTO(
            totalOrdersCount: 0,
            totalRevenueAmount: 0,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 0,
            totalDiscountCurrency: 'USD',
            averageOrderValue: 0.0,
            discountRate: 0.0,
            promotionsActiveCount: 0,
            couponsUsedCount: 0,
            topPromotions: [],
            period: $period,
        );

        $array = $dto->toArray();

        self::assertSame('last_30_days', $array['period']['preset']);
    }

    #[Test]
    public function pricingSummaryDtoToArrayMapsTopPromotions(): void
    {
        $period = DateRangeFilter::fromPreset('today');
        $promotion = new PromotionPerformanceDTO(
            promotionId: 'p-1',
            promotionName: 'Flash Sale',
            promotionType: 'percentage',
            ordersCount: 50,
            totalRevenueAmount: 25000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 2500,
            totalDiscountCurrency: 'USD',
            averageDiscountAmount: 50.0,
            conversionRate: 8.5,
        );

        $dto = new PricingSummaryDTO(
            totalOrdersCount: 50,
            totalRevenueAmount: 25000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 2500,
            totalDiscountCurrency: 'USD',
            averageOrderValue: 500.0,
            discountRate: 10.0,
            promotionsActiveCount: 1,
            couponsUsedCount: 5,
            topPromotions: [$promotion],
            period: $period,
        );

        $array = $dto->toArray();

        self::assertCount(1, $array['topPromotions']);
        self::assertSame('p-1', $array['topPromotions'][0]['promotionId']);
    }
}
