<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Service;

use App\Pricing\Application\DTO\PriceListDTO;
use App\Pricing\Application\DTO\PromotionDTO;
use App\Pricing\Application\Service\PricingExportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PricingExportService::class)]
final class PricingExportServiceTest extends TestCase
{
    private PricingExportService $service;

    protected function setUp(): void
    {
        $this->service = new PricingExportService();
    }

    #[Test]
    public function itExportsPriceListsToCsvWithRules(): void
    {
        $dto = new PriceListDTO(
            id: 'pl-1',
            tenantId: 't-1',
            name: 'Summer Sale',
            priority: 100,
            rules: [
                ['scope' => 'product', 'scope_value' => 'PROD-001', 'discount' => ['type' => 'percentage', 'value' => '20'], 'condition' => ['type' => 'min_quantity', 'value' => '5']],
            ],
            validFrom: '2024-06-01',
            validTo: '2024-08-31',
            isActive: true,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );

        $csv = $this->service->exportPriceListsToCsv([$dto]);

        self::assertStringContainsString('name,priority', $csv);
        self::assertStringContainsString('Summer Sale', $csv);
        self::assertStringContainsString('percentage', $csv);
    }

    #[Test]
    public function itExportsPriceListsToCsvWithoutRules(): void
    {
        $dto = new PriceListDTO(
            id: 'pl-1',
            tenantId: 't-1',
            name: 'Basic List',
            priority: 50,
            rules: [],
            validFrom: null,
            validTo: null,
            isActive: false,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );

        $csv = $this->service->exportPriceListsToCsv([$dto]);

        self::assertStringContainsString('Basic List', $csv);
        self::assertStringContainsString('false', $csv);
    }

    #[Test]
    public function itExportsPriceListsToJson(): void
    {
        $dto = new PriceListDTO(
            id: 'pl-1',
            tenantId: 't-1',
            name: 'Summer Sale',
            priority: 100,
            rules: [],
            validFrom: '2024-06-01',
            validTo: '2024-08-31',
            isActive: true,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );

        $json = $this->service->exportPriceListsToJson([$dto]);
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertCount(1, $decoded);
        self::assertSame('Summer Sale', $decoded[0]['name']);
    }

    #[Test]
    public function itExportsPromotionsToCsv(): void
    {
        $dto = new PromotionDTO(
            id: 'promo-1',
            tenantId: 't-1',
            name: 'Welcome Discount',
            type: 'coupon',
            discountType: 'percentage',
            discountValue: 15.0,
            priority: 200,
            isActive: true,
            couponCode: 'WELCOME15',
            conditions: ['first_order_only' => true],
            validFrom: '2024-01-01',
            validTo: '2024-12-31',
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );

        $csv = $this->service->exportPromotionsToCsv([$dto]);

        self::assertStringContainsString('name,type', $csv);
        self::assertStringContainsString('Welcome Discount', $csv);
        self::assertStringContainsString('WELCOME15', $csv);
    }

    #[Test]
    public function itExportsPromotionsToJson(): void
    {
        $dto = new PromotionDTO(
            id: 'promo-1',
            tenantId: 't-1',
            name: 'Flash Sale',
            type: 'cart_rule',
            discountType: 'fixed_amount',
            discountValue: 10.0,
            priority: 100,
            isActive: false,
            couponCode: null,
            conditions: [],
            validFrom: null,
            validTo: null,
            createdAt: '2024-01-01T00:00:00+00:00',
            updatedAt: '2024-01-01T00:00:00+00:00',
        );

        $json = $this->service->exportPromotionsToJson([$dto]);
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertSame('Flash Sale', $decoded[0]['name']);
        self::assertFalse($decoded[0]['is_active']);
        self::assertNull($decoded[0]['coupon_code']);
    }

    #[Test]
    public function itReturnsPriceListCsvTemplate(): void
    {
        $template = $this->service->getPriceListCsvTemplate();

        self::assertStringContainsString('name,priority', $template);
        self::assertStringContainsString('Summer Sale', $template);
        self::assertStringContainsString('percentage', $template);
    }

    #[Test]
    public function itReturnsPromotionCsvTemplate(): void
    {
        $template = $this->service->getPromotionCsvTemplate();

        self::assertStringContainsString('name,type', $template);
        self::assertStringContainsString('Summer Discount', $template);
        self::assertStringContainsString('Welcome Coupon', $template);
        self::assertStringContainsString('WELCOME10', $template);
    }

    #[Test]
    public function itHandlesEmptyPriceListExport(): void
    {
        $csv = $this->service->exportPriceListsToCsv([]);

        // Should contain only the header
        $lines = array_filter(explode("\n", trim($csv)));
        self::assertCount(1, $lines);
    }

    #[Test]
    public function itHandlesEmptyPromotionExport(): void
    {
        $csv = $this->service->exportPromotionsToCsv([]);

        $lines = array_filter(explode("\n", trim($csv)));
        self::assertCount(1, $lines);
    }

    #[Test]
    public function itHandlesMultiplePriceLists(): void
    {
        $dtos = [];
        for ($i = 0; $i < 5; ++$i) {
            $dtos[] = new PriceListDTO(
                id: "pl-$i",
                tenantId: 't-1',
                name: "List $i",
                priority: $i * 10,
                rules: [],
                validFrom: null,
                validTo: null,
                isActive: true,
                createdAt: '2024-01-01T00:00:00+00:00',
                updatedAt: '2024-01-01T00:00:00+00:00',
            );
        }

        $csv = $this->service->exportPriceListsToCsv($dtos);
        $lines = array_filter(explode("\n", trim($csv)));
        self::assertCount(6, $lines); // 1 header + 5 data rows
    }
}
