<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Catalog\Domain\Model\ProductId;
use App\Invoice\Domain\Model\InvoiceLine;
use App\Invoice\Domain\Model\InvoiceLineId;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceLine::class)]
final class InvoiceLineTest extends TestCase
{
    #[Test]
    public function createCalculatesLineTotalCorrectly(): void
    {
        $line = InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget A',
            3,
            Money::fromScalars(1000, 'USD'),
            20.0
        );

        self::assertSame(3000, $line->lineTotal()->getAmount());
        self::assertSame('Widget A', $line->description());
        self::assertSame(3, $line->quantity());
        self::assertSame(1000, $line->unitPrice()->getAmount());
    }

    #[Test]
    public function createCalculatesTaxAmountCorrectly(): void
    {
        $line = InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget A',
            2,
            Money::fromScalars(1000, 'USD'),
            20.0
        );

        // Line total = 2000, tax = 2000 * 20% = 400
        self::assertSame(400, $line->taxAmount()->getAmount());
        self::assertSame(20.0, $line->taxRate());
    }

    #[Test]
    public function grossTotalReturnsLineTotalPlusTax(): void
    {
        $line = InvoiceLine::create(
            InvoiceLineId::generate(),
            'Widget A',
            1,
            Money::fromScalars(1000, 'USD'),
            10.0
        );

        // Gross = 1000 + 100 = 1100
        self::assertSame(1100, $line->grossTotal()->getAmount());
    }

    #[Test]
    public function createWithZeroTaxRateResultsInZeroTaxAmount(): void
    {
        $line = InvoiceLine::create(
            InvoiceLineId::generate(),
            'Tax-Free Item',
            1,
            Money::fromScalars(5000, 'USD'),
            0.0
        );

        self::assertSame(0, $line->taxAmount()->getAmount());
        self::assertSame(5000, $line->grossTotal()->getAmount());
    }

    #[Test]
    public function createWithProductIdAndSku(): void
    {
        $productId = ProductId::generate();
        $line = InvoiceLine::create(
            InvoiceLineId::generate(),
            'Product With SKU',
            1,
            Money::fromScalars(1000, 'EUR'),
            20.0,
            $productId,
            'SKU-001'
        );

        self::assertTrue($line->productId()->equals($productId));
        self::assertSame('SKU-001', $line->sku());
    }

    #[Test]
    public function createWithPositionSetsPosition(): void
    {
        $line = InvoiceLine::create(
            InvoiceLineId::generate(),
            'Line Item',
            1,
            Money::fromScalars(100, 'USD'),
            0.0,
            position: 5
        );

        self::assertSame(5, $line->position());
    }

    #[Test]
    public function createWithTooShortDescriptionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 3 characters');

        InvoiceLine::create(
            InvoiceLineId::generate(),
            'AB',
            1,
            Money::fromScalars(100, 'USD'),
            0.0
        );
    }

    #[Test]
    public function createWithZeroQuantityThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 1');

        InvoiceLine::create(
            InvoiceLineId::generate(),
            'Valid Description',
            0,
            Money::fromScalars(100, 'USD'),
            0.0
        );
    }

    #[Test]
    public function createWithZeroUnitPriceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be positive');

        InvoiceLine::create(
            InvoiceLineId::generate(),
            'Valid Description',
            1,
            Money::fromScalars(0, 'USD'),
            0.0
        );
    }

    #[Test]
    public function createWithNegativeTaxRateThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 0 and 100');

        InvoiceLine::create(
            InvoiceLineId::generate(),
            'Valid Description',
            1,
            Money::fromScalars(100, 'USD'),
            -5.0
        );
    }

    #[Test]
    public function createWithTaxRateOver100ThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between 0 and 100');

        InvoiceLine::create(
            InvoiceLineId::generate(),
            'Valid Description',
            1,
            Money::fromScalars(100, 'USD'),
            101.0
        );
    }

    #[Test]
    public function createWithNegativePositionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative');

        InvoiceLine::create(
            InvoiceLineId::generate(),
            'Valid Description',
            1,
            Money::fromScalars(100, 'USD'),
            0.0,
            position: -1
        );
    }
}
