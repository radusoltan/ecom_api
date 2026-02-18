<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Catalog\Domain\Model\ProductId;
use App\Pricing\Domain\ValueObject\PriceChange;
use App\Pricing\Domain\ValueObject\PriceChangeSource;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PriceChangeTest extends TestCase
{
    private ProductId $productId;
    private Money $oldPrice;
    private Money $newPrice;

    protected function setUp(): void
    {
        $this->productId = ProductId::generate();
        $this->oldPrice = Money::of('100.00', 'USD');
        $this->newPrice = Money::of('89.99', 'USD');
    }

    public function testCanCreatePriceChange(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Black Friday Sale',
            PriceChangeSource::PROMOTION
        );

        $this->assertTrue($priceChange->productId()->equals($this->productId));
        $this->assertTrue($priceChange->oldPrice()->equals($this->oldPrice));
        $this->assertTrue($priceChange->newPrice()->equals($this->newPrice));
        $this->assertSame('Black Friday Sale', $priceChange->reason());
        $this->assertSame(PriceChangeSource::PROMOTION, $priceChange->source());
        $this->assertInstanceOf(\DateTimeImmutable::class, $priceChange->timestamp());
    }

    public function testCanCreateWithCustomTimestamp(): void
    {
        $customTime = new \DateTimeImmutable('2025-01-01 12:00:00');
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL,
            $customTime
        );

        $this->assertEquals($customTime, $priceChange->timestamp());
    }

    public function testFromPriceListRule(): void
    {
        $priceChange = PriceChange::fromPriceListRule(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Custom reason'
        );

        $this->assertSame('Custom reason', $priceChange->reason());
        $this->assertSame(PriceChangeSource::MANUAL, $priceChange->source());
    }

    public function testFromPriceListRuleWithDefaultReason(): void
    {
        $priceChange = PriceChange::fromPriceListRule(
            $this->productId,
            null,
            $this->newPrice
        );

        $this->assertSame('Price list rule applied', $priceChange->reason());
    }

    public function testFromPromotion(): void
    {
        $priceChange = PriceChange::fromPromotion(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Summer Sale'
        );

        $this->assertSame('Promotion applied: Summer Sale', $priceChange->reason());
        $this->assertSame(PriceChangeSource::PROMOTION, $priceChange->source());
    }

    public function testFromFlashSale(): void
    {
        $priceChange = PriceChange::fromFlashSale(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Lightning Deal'
        );

        $this->assertSame('Flash sale: Lightning Deal', $priceChange->reason());
        $this->assertSame(PriceChangeSource::FLASH_SALE, $priceChange->source());
    }

    public function testFromImport(): void
    {
        $priceChange = PriceChange::fromImport(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'CSV Upload'
        );

        $this->assertSame('Imported from: CSV Upload', $priceChange->reason());
        $this->assertSame(PriceChangeSource::IMPORT, $priceChange->source());
    }

    public function testCanCreateWithNullOldPrice(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            null,
            $this->newPrice,
            'Initial price',
            PriceChangeSource::MANUAL
        );

        $this->assertNull($priceChange->oldPrice());
        $this->assertTrue($priceChange->newPrice()->equals($this->newPrice));
    }

    public function testCanCreateWithNullNewPrice(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            null,
            'Price removed',
            PriceChangeSource::MANUAL
        );

        $this->assertTrue($priceChange->oldPrice()->equals($this->oldPrice));
        $this->assertNull($priceChange->newPrice());
    }

    public function testThrowsExceptionWhenBothPricesAreNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one price (old or new) must be specified');

        PriceChange::create(
            $this->productId,
            null,
            null,
            'Invalid',
            PriceChangeSource::MANUAL
        );
    }

    public function testThrowsExceptionWhenTimestampIsInFuture(): void
    {
        $futureTime = new \DateTimeImmutable('+1 day');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price change timestamp cannot be in the future');

        PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL,
            $futureTime
        );
    }

    public function testThrowsExceptionWhenReasonIsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price change reason cannot be an empty string');

        PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            '',
            PriceChangeSource::MANUAL
        );
    }

    public function testThrowsExceptionWhenCurrenciesDontMatch(): void
    {
        $eurPrice = Money::of('85.00', 'EUR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Old and new prices must have the same currency');

        PriceChange::create(
            $this->productId,
            $this->oldPrice, // USD
            $eurPrice,       // EUR
            'Test',
            PriceChangeSource::MANUAL
        );
    }

    public function testIsPriceIncrease(): void
    {
        $increasedPrice = Money::of('120.00', 'USD');
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $increasedPrice,
            'Price increase',
            PriceChangeSource::MANUAL
        );

        $this->assertTrue($priceChange->isPriceIncrease());
        $this->assertFalse($priceChange->isPriceDecrease());
    }

    public function testIsPriceDecrease(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice, // Lower than oldPrice
            'Price decrease',
            PriceChangeSource::MANUAL
        );

        $this->assertTrue($priceChange->isPriceDecrease());
        $this->assertFalse($priceChange->isPriceIncrease());
    }

    public function testIsPriceIncreaseReturnsFalseWhenOldPriceIsNull(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            null,
            $this->newPrice,
            'Initial price',
            PriceChangeSource::MANUAL
        );

        $this->assertFalse($priceChange->isPriceIncrease());
        $this->assertFalse($priceChange->isPriceDecrease());
    }

    public function testPriceChangeDifference(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,    // 100.00
            $this->newPrice,    // 89.99
            'Test',
            PriceChangeSource::MANUAL
        );

        $difference = $priceChange->priceChangeDifference();
        $this->assertNotNull($difference);
        $this->assertSame(-10.01, $difference->amount());
    }

    public function testPriceChangeDifferenceReturnsNullWhenOldPriceIsNull(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            null,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL
        );

        $this->assertNull($priceChange->priceChangeDifference());
    }

    public function testPriceChangePercentage(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,    // 100.00
            $this->newPrice,    // 89.99
            'Test',
            PriceChangeSource::MANUAL
        );

        $percentage = $priceChange->priceChangePercentage();
        $this->assertNotNull($percentage);
        $this->assertEqualsWithDelta(-10.01, $percentage, 0.01);
    }

    public function testPriceChangePercentageReturnsNullWhenOldPriceIsZero(): void
    {
        $zeroPrice = Money::of('0.00', 'USD');
        $priceChange = PriceChange::create(
            $this->productId,
            $zeroPrice,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL
        );

        $this->assertNull($priceChange->priceChangePercentage());
    }

    public function testEqualsReturnsTrueForIdenticalPriceChanges(): void
    {
        $timestamp = new \DateTimeImmutable();

        $priceChange1 = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL,
            $timestamp
        );

        $priceChange2 = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL,
            $timestamp
        );

        $this->assertTrue($priceChange1->equals($priceChange2));
    }

    public function testEqualsReturnsFalseForDifferentPriceChanges(): void
    {
        $priceChange1 = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL
        );

        $priceChange2 = PriceChange::create(
            ProductId::generate(), // Different product
            $this->oldPrice,
            $this->newPrice,
            'Test',
            PriceChangeSource::MANUAL
        );

        $this->assertFalse($priceChange1->equals($priceChange2));
    }

    public function testToArrayReturnsCompleteData(): void
    {
        $priceChange = PriceChange::create(
            $this->productId,
            $this->oldPrice,
            $this->newPrice,
            'Test reason',
            PriceChangeSource::PROMOTION
        );

        $array = $priceChange->toArray();

        $this->assertArrayHasKey('product_id', $array);
        $this->assertArrayHasKey('old_price', $array);
        $this->assertArrayHasKey('new_price', $array);
        $this->assertArrayHasKey('reason', $array);
        $this->assertArrayHasKey('source', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('price_change_difference', $array);
        $this->assertArrayHasKey('price_change_percentage', $array);
        $this->assertArrayHasKey('is_price_increase', $array);
        $this->assertArrayHasKey('is_price_decrease', $array);

        $this->assertSame($this->productId->toString(), $array['product_id']);
        $this->assertSame('Test reason', $array['reason']);
        $this->assertSame('promotion', $array['source']);
    }
}
