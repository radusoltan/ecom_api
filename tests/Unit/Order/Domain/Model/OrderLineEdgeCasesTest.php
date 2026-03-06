<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Model;

use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\ValueObject\OrderProductId;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderLine::class)]
final class OrderLineEdgeCasesTest extends TestCase
{
    private function makeLine(int $qty, int $unitCents): OrderLine
    {
        return OrderLine::create(
            OrderProductId::generate(),
            'Product',
            $qty,
            Money::fromScalars($unitCents, 'USD'),
        );
    }

    // ---------------------------------------------------------------
    // withQuantity
    // ---------------------------------------------------------------

    #[Test]
    public function itCreatesNewLineWithUpdatedQuantity(): void
    {
        $original = $this->makeLine(2, 1000);

        $updated = $original->withQuantity(5);

        self::assertSame(5, $updated->quantity());
        self::assertSame(2, $original->quantity()); // original unchanged
    }

    #[Test]
    public function itPreservesProductIdOnWithQuantity(): void
    {
        $productId = OrderProductId::generate();
        $line = OrderLine::create($productId, 'Keep Me', 1, Money::fromScalars(500, 'USD'));

        $updated = $line->withQuantity(10);

        self::assertTrue($productId->equals($updated->productId()));
        self::assertSame('Keep Me', $updated->productName());
    }

    #[Test]
    public function itPreservesUnitPriceOnWithQuantity(): void
    {
        $unitPrice = Money::fromScalars(3500, 'USD');
        $line = OrderLine::create(OrderProductId::generate(), 'P', 1, $unitPrice);

        $updated = $line->withQuantity(3);

        self::assertTrue($unitPrice->equals($updated->unitPrice()));
    }

    #[Test]
    public function itThrowsWhenWithQuantityUsesZero(): void
    {
        $line = $this->makeLine(2, 1000);

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Order line quantity must be greater than 0');

        $line->withQuantity(0);
    }

    #[Test]
    public function itThrowsWhenWithQuantityUsesNegative(): void
    {
        $line = $this->makeLine(2, 1000);

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Order line quantity must be greater than 0');

        $line->withQuantity(-3);
    }

    // ---------------------------------------------------------------
    // total calculations
    // ---------------------------------------------------------------

    #[Test]
    public function itCalculatesTotalForSingleUnit(): void
    {
        $line = $this->makeLine(1, 9999);

        self::assertSame(9999, $line->total()->getAmount());
    }

    #[Test]
    public function itCalculatesTotalForLargeQuantity(): void
    {
        $line = $this->makeLine(100, 250);

        self::assertSame(25000, $line->total()->getAmount());
    }

    // ---------------------------------------------------------------
    // Getters
    // ---------------------------------------------------------------

    #[Test]
    public function itExposesAllGetters(): void
    {
        $productId = OrderProductId::generate();
        $unitPrice = Money::fromScalars(7500, 'EUR');
        $line = OrderLine::create($productId, 'Fancy Widget', 4, $unitPrice);

        self::assertTrue($productId->equals($line->productId()));
        self::assertSame('Fancy Widget', $line->productName());
        self::assertSame(4, $line->quantity());
        self::assertTrue($unitPrice->equals($line->unitPrice()));
        self::assertSame(30000, $line->total()->getAmount());
        self::assertSame('EUR', $line->total()->getCurrency()->getCurrencyCode());
    }
}
