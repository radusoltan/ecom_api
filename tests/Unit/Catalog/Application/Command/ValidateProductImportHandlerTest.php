<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application\Command;

use App\Catalog\Application\Command\ValidateProductImport\ValidateProductImportCommand;
use App\Catalog\Application\Command\ValidateProductImport\ValidateProductImportHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidateProductImportHandler::class)]
final class ValidateProductImportHandlerTest extends TestCase
{
    private ValidateProductImportHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ValidateProductImportHandler();
    }

    // ---------------------------------------------------------------------------
    // All valid rows
    // ---------------------------------------------------------------------------

    #[Test]
    public function itReturnsValidTrueWhenAllRowsAreValid(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Widget Alpha',
                'priceAmount' => 1999,
                'priceCurrency' => 'EUR',
                'stockQuantity' => 10,
            ],
            [
                'name' => 'Widget Beta',
                'priceAmount' => 500,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertTrue($result['valid']);
        self::assertSame(2, $result['validRows']);
        self::assertSame(0, $result['invalidRows']);
        self::assertEmpty($result['errors']);
        self::assertCount(2, $result['data']);
    }

    // ---------------------------------------------------------------------------
    // Missing name
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRejectsRowWithMissingName(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'priceAmount' => 1000,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame(0, $result['validRows']);
        self::assertSame(1, $result['invalidRows']);
        self::assertCount(1, $result['errors']);
        self::assertSame('name', $result['errors'][0]['field']);
    }

    #[Test]
    public function itRejectsRowWithEmptyName(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => '   ',
                'priceAmount' => 1000,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame('name', $result['errors'][0]['field']);
        self::assertStringContainsString('required', $result['errors'][0]['message']);
    }

    // ---------------------------------------------------------------------------
    // Missing price amount
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRejectsRowWithMissingPriceAmount(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Test Product',
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame('priceAmount', $result['errors'][0]['field']);
        self::assertStringContainsString('required', $result['errors'][0]['message']);
    }

    // ---------------------------------------------------------------------------
    // Non-numeric or zero/negative price amount
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRejectsNonNumericPriceAmount(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Test Product',
                'priceAmount' => 'not-a-number',
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame('priceAmount', $result['errors'][0]['field']);
        self::assertStringContainsString('positive', $result['errors'][0]['message']);
    }

    #[Test]
    public function itRejectsZeroPriceAmount(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Test Product',
                'priceAmount' => 0,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
    }

    // ---------------------------------------------------------------------------
    // Price amount too large
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRejectsPriceAmountExceedingMaximum(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Expensive Product',
                'priceAmount' => 1000000,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame('priceAmount', $result['errors'][0]['field']);
        self::assertStringContainsString('too large', $result['errors'][0]['message']);
    }

    #[Test]
    public function itAcceptsPriceAmountAtMaximum(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Max Price Product',
                'priceAmount' => 999999.99,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertTrue($result['valid']);
    }

    // ---------------------------------------------------------------------------
    // Invalid currency code
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRejectsInvalidCurrencyCode(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Test Product',
                'priceAmount' => 1000,
                'priceCurrency' => 'XYZ',
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame('priceCurrency', $result['errors'][0]['field']);
    }

    #[Test]
    public function itAcceptsValidCurrencyCodes(): void
    {
        $command = new ValidateProductImportCommand([
            ['name' => 'P1', 'priceAmount' => 100, 'priceCurrency' => 'USD'],
            ['name' => 'P2', 'priceAmount' => 100, 'priceCurrency' => 'EUR'],
            ['name' => 'P3', 'priceAmount' => 100, 'priceCurrency' => 'GBP'],
            ['name' => 'P4', 'priceAmount' => 100, 'priceCurrency' => 'RON'],
        ]);

        $result = ($this->handler)($command);

        self::assertTrue($result['valid']);
        self::assertSame(4, $result['validRows']);
    }

    // ---------------------------------------------------------------------------
    // Invalid stock quantity
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRejectsNegativeStockQuantity(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Test Product',
                'priceAmount' => 1000,
                'stockQuantity' => -5,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame('stockQuantity', $result['errors'][0]['field']);
    }

    #[Test]
    public function itAcceptsZeroStockQuantity(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Out of Stock',
                'priceAmount' => 100,
                'stockQuantity' => 0,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertTrue($result['valid']);
    }

    // ---------------------------------------------------------------------------
    // Name too long
    // ---------------------------------------------------------------------------

    #[Test]
    public function itRejectsNameExceeding255Characters(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => str_repeat('A', 256),
                'priceAmount' => 1000,
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame('name', $result['errors'][0]['field']);
        self::assertStringContainsString('too long', $result['errors'][0]['message']);
    }

    // ---------------------------------------------------------------------------
    // Multiple errors in one row
    // ---------------------------------------------------------------------------

    #[Test]
    public function itCollectsMultipleErrorsForOneRow(): void
    {
        $command = new ValidateProductImportCommand([
            [
                // Missing name and invalid currency
                'priceAmount' => 1000,
                'priceCurrency' => 'INVALID',
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertGreaterThanOrEqual(2, count($result['errors']));
    }

    // ---------------------------------------------------------------------------
    // Mixed valid and invalid rows
    // ---------------------------------------------------------------------------

    #[Test]
    public function itHandlesMixedValidAndInvalidRows(): void
    {
        $command = new ValidateProductImportCommand([
            ['name' => 'Valid Product', 'priceAmount' => 100],
            ['priceAmount' => 100], // missing name
            ['name' => 'Another Valid', 'priceAmount' => 200],
        ]);

        $result = ($this->handler)($command);

        self::assertFalse($result['valid']);
        self::assertSame(2, $result['validRows']);
        self::assertSame(1, $result['invalidRows']);
        self::assertCount(2, $result['data']);
    }

    // ---------------------------------------------------------------------------
    // Row numbers are 1-based
    // ---------------------------------------------------------------------------

    #[Test]
    public function itReportsCorrectRowNumbers(): void
    {
        $command = new ValidateProductImportCommand([
            ['name' => 'Valid', 'priceAmount' => 100],
            ['priceAmount' => 50], // row 2: missing name
        ]);

        $result = ($this->handler)($command);

        self::assertSame(2, $result['errors'][0]['row']);
    }

    // ---------------------------------------------------------------------------
    // Empty products array
    // ---------------------------------------------------------------------------

    #[Test]
    public function itHandlesEmptyProductsArray(): void
    {
        $command = new ValidateProductImportCommand([]);

        $result = ($this->handler)($command);

        self::assertTrue($result['valid']);
        self::assertSame(0, $result['validRows']);
        self::assertSame(0, $result['invalidRows']);
        self::assertEmpty($result['errors']);
        self::assertEmpty($result['data']);
    }

    // ---------------------------------------------------------------------------
    // Case-insensitive currency matching
    // ---------------------------------------------------------------------------

    #[Test]
    public function itNormalizesCurrencyCodeToUppercase(): void
    {
        $command = new ValidateProductImportCommand([
            [
                'name' => 'Test Product',
                'priceAmount' => 100,
                'priceCurrency' => 'usd', // lowercase
            ],
        ]);

        $result = ($this->handler)($command);

        self::assertTrue($result['valid']);
    }
}
