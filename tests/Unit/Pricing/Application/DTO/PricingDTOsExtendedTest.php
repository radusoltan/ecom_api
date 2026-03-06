<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\DTO;

use App\Pricing\Application\DTO\PriceHistoryDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PriceHistoryDTO::class)]
final class PricingDTOsExtendedTest extends TestCase
{
    // -----------------------------------------------------------------------
    // PriceHistoryDTO — construction
    // -----------------------------------------------------------------------

    #[Test]
    public function priceHistoryDtoConstructionHoldsAllFields(): void
    {
        $oldPrice = ['amount' => 1000, 'currency' => 'USD'];
        $newPrice = ['amount' => 900,  'currency' => 'USD'];
        $diff = ['amount' => -100, 'currency' => 'USD'];
        $timestamp = '2025-06-01T12:00:00+00:00';

        $dto = new PriceHistoryDTO(
            productId: 'prod-abc',
            oldPrice: $oldPrice,
            newPrice: $newPrice,
            reason: 'Seasonal discount',
            source: 'manual',
            sourceLabel: 'Manual Update',
            timestamp: $timestamp,
            priceChangeDifference: $diff,
            priceChangePercentage: -10.0,
            isPriceIncrease: false,
            isPriceDecrease: true,
        );

        self::assertSame('prod-abc', $dto->productId);
        self::assertSame($oldPrice, $dto->oldPrice);
        self::assertSame($newPrice, $dto->newPrice);
        self::assertSame('Seasonal discount', $dto->reason);
        self::assertSame('manual', $dto->source);
        self::assertSame('Manual Update', $dto->sourceLabel);
        self::assertSame($timestamp, $dto->timestamp);
        self::assertSame($diff, $dto->priceChangeDifference);
        self::assertSame(-10.0, $dto->priceChangePercentage);
        self::assertFalse($dto->isPriceIncrease);
        self::assertTrue($dto->isPriceDecrease);
    }

    #[Test]
    public function priceHistoryDtoConstructionWithNullableFields(): void
    {
        $dto = new PriceHistoryDTO(
            productId: 'prod-xyz',
            oldPrice: null,
            newPrice: ['amount' => 500, 'currency' => 'EUR'],
            reason: null,
            source: 'price_list',
            sourceLabel: 'Price List',
            timestamp: '2025-07-01T00:00:00+00:00',
            priceChangeDifference: null,
            priceChangePercentage: null,
            isPriceIncrease: false,
            isPriceDecrease: false,
        );

        self::assertSame('prod-xyz', $dto->productId);
        self::assertNull($dto->oldPrice);
        self::assertNull($dto->reason);
        self::assertNull($dto->priceChangeDifference);
        self::assertNull($dto->priceChangePercentage);
        self::assertFalse($dto->isPriceIncrease);
        self::assertFalse($dto->isPriceDecrease);
    }

    // -----------------------------------------------------------------------
    // PriceHistoryDTO — toArray
    // -----------------------------------------------------------------------

    #[Test]
    public function priceHistoryDtoToArrayMapsAllKeys(): void
    {
        $oldPrice = ['amount' => 2000, 'currency' => 'USD'];
        $newPrice = ['amount' => 2500, 'currency' => 'USD'];
        $diff = ['amount' => 500,  'currency' => 'USD'];

        $dto = new PriceHistoryDTO(
            productId: 'prod-123',
            oldPrice: $oldPrice,
            newPrice: $newPrice,
            reason: 'Cost increase',
            source: 'import',
            sourceLabel: 'Bulk Import',
            timestamp: '2025-09-01T08:00:00+00:00',
            priceChangeDifference: $diff,
            priceChangePercentage: 25.0,
            isPriceIncrease: true,
            isPriceDecrease: false,
        );

        $array = $dto->toArray();

        self::assertSame('prod-123', $array['product_id']);
        self::assertSame($oldPrice, $array['old_price']);
        self::assertSame($newPrice, $array['new_price']);
        self::assertSame('Cost increase', $array['reason']);
        self::assertSame('import', $array['source']);
        self::assertSame('Bulk Import', $array['source_label']);
        self::assertSame('2025-09-01T08:00:00+00:00', $array['timestamp']);
        self::assertSame($diff, $array['price_change_difference']);
        self::assertSame(25.0, $array['price_change_percentage']);
        self::assertTrue($array['is_price_increase']);
        self::assertFalse($array['is_price_decrease']);
    }

    #[Test]
    public function priceHistoryDtoToArrayWithNullFields(): void
    {
        $dto = new PriceHistoryDTO(
            productId: 'prod-null',
            oldPrice: null,
            newPrice: null,
            reason: null,
            source: 'api',
            sourceLabel: 'API',
            timestamp: '2025-01-01T00:00:00+00:00',
            priceChangeDifference: null,
            priceChangePercentage: null,
            isPriceIncrease: false,
            isPriceDecrease: false,
        );

        $array = $dto->toArray();

        self::assertNull($array['old_price']);
        self::assertNull($array['new_price']);
        self::assertNull($array['reason']);
        self::assertNull($array['price_change_difference']);
        self::assertNull($array['price_change_percentage']);
    }

    #[Test]
    public function priceHistoryDtoFlagsPriceDecreaseCorrectly(): void
    {
        $dto = new PriceHistoryDTO(
            productId: 'prod-down',
            oldPrice: ['amount' => 3000, 'currency' => 'USD'],
            newPrice: ['amount' => 2500, 'currency' => 'USD'],
            reason: 'Promo',
            source: 'promotion',
            sourceLabel: 'Promotion',
            timestamp: '2025-11-01T00:00:00+00:00',
            priceChangeDifference: ['amount' => -500, 'currency' => 'USD'],
            priceChangePercentage: -16.67,
            isPriceIncrease: false,
            isPriceDecrease: true,
        );

        self::assertFalse($dto->isPriceIncrease);
        self::assertTrue($dto->isPriceDecrease);
        self::assertSame(-16.67, $dto->priceChangePercentage);
    }
}
