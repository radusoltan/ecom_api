<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\DTO\Analytics;

use App\Pricing\Application\DTO\Analytics\PromotionPerformanceDTO;
use PHPUnit\Framework\TestCase;

final class PromotionPerformanceDTOTest extends TestCase
{
    public function testConstructor(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01', new \DateTimeZone('UTC'));
        $validTo = new \DateTimeImmutable('2025-01-31', new \DateTimeZone('UTC'));

        $dto = new PromotionPerformanceDTO(
            promotionId: 'promo-123',
            promotionName: 'Summer Sale',
            promotionType: 'percentage',
            ordersCount: 150,
            totalRevenueAmount: 50000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 7500,
            totalDiscountCurrency: 'USD',
            averageDiscountAmount: 50.0,
            conversionRate: 15.5,
            validFrom: $validFrom,
            validTo: $validTo
        );

        $this->assertSame('promo-123', $dto->promotionId);
        $this->assertSame('Summer Sale', $dto->promotionName);
        $this->assertSame('percentage', $dto->promotionType);
        $this->assertSame(150, $dto->ordersCount);
        $this->assertSame(50000, $dto->totalRevenueAmount);
        $this->assertSame('USD', $dto->totalRevenueCurrency);
        $this->assertSame(7500, $dto->totalDiscountAmount);
        $this->assertSame('USD', $dto->totalDiscountCurrency);
        $this->assertSame(50.0, $dto->averageDiscountAmount);
        $this->assertSame(15.5, $dto->conversionRate);
        $this->assertSame($validFrom, $dto->validFrom);
        $this->assertSame($validTo, $dto->validTo);
    }

    public function testFromArray(): void
    {
        $data = [
            'promotion_id' => 'promo-456',
            'promotion_name' => 'Black Friday',
            'promotion_type' => 'fixed',
            'orders_count' => '200',
            'total_revenue_amount' => '100000',
            'total_revenue_currency' => 'EUR',
            'total_discount_amount' => '15000',
            'total_discount_currency' => 'EUR',
            'average_discount_amount' => '75.5',
            'conversion_rate' => '22.3',
            'valid_from' => '2025-11-25 00:00:00',
            'valid_to' => '2025-11-30 23:59:59',
        ];

        $dto = PromotionPerformanceDTO::fromArray($data);

        $this->assertSame('promo-456', $dto->promotionId);
        $this->assertSame('Black Friday', $dto->promotionName);
        $this->assertSame('fixed', $dto->promotionType);
        $this->assertSame(200, $dto->ordersCount);
        $this->assertSame(100000, $dto->totalRevenueAmount);
        $this->assertSame('EUR', $dto->totalRevenueCurrency);
        $this->assertSame(15000, $dto->totalDiscountAmount);
        $this->assertSame('EUR', $dto->totalDiscountCurrency);
        $this->assertSame(75.5, $dto->averageDiscountAmount);
        $this->assertSame(22.3, $dto->conversionRate);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->validFrom);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->validTo);
        $this->assertSame('2025-11-25', $dto->validFrom->format('Y-m-d'));
        $this->assertSame('2025-11-30', $dto->validTo->format('Y-m-d'));
    }

    public function testFromArrayWithNullDates(): void
    {
        $data = [
            'promotion_id' => 'promo-789',
            'promotion_name' => 'Permanent Discount',
            'promotion_type' => 'percentage',
            'orders_count' => '50',
            'total_revenue_amount' => '10000',
            'total_revenue_currency' => 'USD',
            'total_discount_amount' => '2000',
            'total_discount_currency' => 'USD',
            'average_discount_amount' => '40.0',
            'conversion_rate' => '5.0',
            'valid_from' => null,
            'valid_to' => null,
        ];

        $dto = PromotionPerformanceDTO::fromArray($data);

        $this->assertNull($dto->validFrom);
        $this->assertNull($dto->validTo);
    }

    public function testFromArrayWithMissingCurrency(): void
    {
        $data = [
            'promotion_id' => 'promo-999',
            'promotion_name' => 'Test Promo',
            'promotion_type' => 'percentage',
            'orders_count' => '10',
            'total_revenue_amount' => '1000',
            'total_discount_amount' => '100',
            'average_discount_amount' => '10.0',
            'conversion_rate' => '1.0',
        ];

        $dto = PromotionPerformanceDTO::fromArray($data);

        $this->assertSame('USD', $dto->totalRevenueCurrency);
        $this->assertSame('USD', $dto->totalDiscountCurrency);
    }

    public function testToArray(): void
    {
        $validFrom = new \DateTimeImmutable('2025-01-01 00:00:00', new \DateTimeZone('UTC'));
        $validTo = new \DateTimeImmutable('2025-01-31 23:59:59', new \DateTimeZone('UTC'));

        $dto = new PromotionPerformanceDTO(
            promotionId: 'promo-123',
            promotionName: 'Summer Sale',
            promotionType: 'percentage',
            ordersCount: 150,
            totalRevenueAmount: 50000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 7500,
            totalDiscountCurrency: 'USD',
            averageDiscountAmount: 50.5,
            conversionRate: 15.75,
            validFrom: $validFrom,
            validTo: $validTo
        );

        $array = $dto->toArray();

        $this->assertSame('promo-123', $array['promotionId']);
        $this->assertSame('Summer Sale', $array['promotionName']);
        $this->assertSame('percentage', $array['promotionType']);
        $this->assertSame(150, $array['ordersCount']);
        $this->assertSame(50000, $array['totalRevenue']['amount']);
        $this->assertSame('USD', $array['totalRevenue']['currency']);
        $this->assertSame('500.00 USD', $array['totalRevenue']['formatted']);
        $this->assertSame(7500, $array['totalDiscount']['amount']);
        $this->assertSame('USD', $array['totalDiscount']['currency']);
        $this->assertSame('75.00 USD', $array['totalDiscount']['formatted']);
        $this->assertSame(50.5, $array['averageDiscountAmount']);
        $this->assertSame(15.75, $array['conversionRate']);
        $this->assertSame($validFrom->format(\DateTimeInterface::ATOM), $array['validFrom']);
        $this->assertSame($validTo->format(\DateTimeInterface::ATOM), $array['validTo']);
    }

    public function testToArrayWithNullDates(): void
    {
        $dto = new PromotionPerformanceDTO(
            promotionId: 'promo-123',
            promotionName: 'Test',
            promotionType: 'percentage',
            ordersCount: 10,
            totalRevenueAmount: 1000,
            totalRevenueCurrency: 'USD',
            totalDiscountAmount: 100,
            totalDiscountCurrency: 'USD',
            averageDiscountAmount: 10.0,
            conversionRate: 5.0,
            validFrom: null,
            validTo: null
        );

        $array = $dto->toArray();

        $this->assertNull($array['validFrom']);
        $this->assertNull($array['validTo']);
    }

    public function testFormattedAmounts(): void
    {
        $dto = new PromotionPerformanceDTO(
            promotionId: 'promo-123',
            promotionName: 'Test',
            promotionType: 'percentage',
            ordersCount: 10,
            totalRevenueAmount: 123456, // 1234.56 USD
            totalRevenueCurrency: 'EUR',
            totalDiscountAmount: 9999, // 99.99 EUR
            totalDiscountCurrency: 'EUR',
            averageDiscountAmount: 10.0,
            conversionRate: 5.0
        );

        $array = $dto->toArray();

        // number_format adds thousands separator
        $this->assertSame('1,234.56 EUR', $array['totalRevenue']['formatted']);
        $this->assertSame('99.99 EUR', $array['totalDiscount']['formatted']);
    }
}
