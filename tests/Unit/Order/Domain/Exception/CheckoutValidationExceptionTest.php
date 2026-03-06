<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Exception;

use App\Order\Domain\Exception\CheckoutValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckoutValidationException::class)]
final class CheckoutValidationExceptionTest extends TestCase
{
    #[Test]
    public function itCreatesWithPriceChanges(): void
    {
        $priceChanges = [
            ['productId' => 'p1', 'productName' => 'Widget', 'oldPrice' => 1000, 'newPrice' => 1200, 'currency' => 'USD'],
        ];

        $exception = CheckoutValidationException::withIssues($priceChanges, []);

        self::assertTrue($exception->hasPriceChanges());
        self::assertFalse($exception->hasStockIssues());
        self::assertCount(1, $exception->priceChanges());
        self::assertStringContainsString('1 product(s) have changed price', $exception->getMessage());
    }

    #[Test]
    public function itCreatesWithStockIssues(): void
    {
        $stockIssues = [
            ['productId' => 'p1', 'productName' => 'Widget', 'requestedQuantity' => 5, 'availableQuantity' => 2],
        ];

        $exception = CheckoutValidationException::withIssues([], $stockIssues);

        self::assertFalse($exception->hasPriceChanges());
        self::assertTrue($exception->hasStockIssues());
        self::assertCount(1, $exception->stockIssues());
        self::assertStringContainsString('insufficient stock', $exception->getMessage());
    }

    #[Test]
    public function itCreatesWithBothIssues(): void
    {
        $priceChanges = [
            ['productId' => 'p1', 'productName' => 'A', 'oldPrice' => 100, 'newPrice' => 150, 'currency' => 'USD'],
            ['productId' => 'p2', 'productName' => 'B', 'oldPrice' => 200, 'newPrice' => 250, 'currency' => 'USD'],
        ];
        $stockIssues = [
            ['productId' => 'p3', 'productName' => 'C', 'requestedQuantity' => 10, 'availableQuantity' => 0],
        ];

        $exception = CheckoutValidationException::withIssues($priceChanges, $stockIssues);

        self::assertTrue($exception->hasPriceChanges());
        self::assertTrue($exception->hasStockIssues());
        self::assertStringContainsString('2 product(s) have changed price', $exception->getMessage());
        self::assertStringContainsString('1 product(s) have insufficient stock', $exception->getMessage());
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $priceChanges = [
            ['productId' => 'p1', 'productName' => 'Widget', 'oldPrice' => 1000, 'newPrice' => 1200, 'currency' => 'USD'],
        ];

        $exception = CheckoutValidationException::withIssues($priceChanges, []);
        $array = $exception->toArray();

        self::assertSame('checkout_validation_failed', $array['error']);
        self::assertArrayHasKey('message', $array);
        self::assertArrayHasKey('priceChanges', $array);
        self::assertArrayHasKey('stockIssues', $array);
        self::assertCount(1, $array['priceChanges']);
        self::assertCount(0, $array['stockIssues']);
    }
}
