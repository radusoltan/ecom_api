<?php

declare(strict_types=1);

namespace App\Tests\Unit\Invoice\Domain\Model;

use App\Invoice\Domain\Model\InvoiceNumber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceNumber::class)]
final class InvoiceNumberTest extends TestCase
{
    #[Test]
    public function createFormatsNumberCorrectly(): void
    {
        $number = InvoiceNumber::create(2025, 1);

        self::assertSame('INV-2025-000001', $number->toString());
        self::assertSame(2025, $number->year());
        self::assertSame(1, $number->sequence());
    }

    #[Test]
    public function createWithLargeSequence(): void
    {
        $number = InvoiceNumber::create(2025, 999999);

        self::assertSame('INV-2025-999999', $number->toString());
        self::assertSame(999999, $number->sequence());
    }

    #[Test]
    public function fromStringParsesCorrectly(): void
    {
        $number = InvoiceNumber::fromString('INV-2026-000042');

        self::assertSame(2026, $number->year());
        self::assertSame(42, $number->sequence());
    }

    #[Test]
    public function fromStringWithInvalidFormatThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid InvoiceNumber format');

        InvoiceNumber::fromString('INVOICE-2025-001');
    }

    #[Test]
    public function createWithInvalidYearThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Year must be between 2000 and 2099');

        InvoiceNumber::create(1999, 1);
    }

    #[Test]
    public function createWithZeroSequenceThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sequence');

        InvoiceNumber::create(2025, 0);
    }

    #[Test]
    public function createWithSequenceOverMaxThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid InvoiceNumber format');

        InvoiceNumber::create(2025, 1000000);
    }

    #[Test]
    public function equalsReturnsTrueForSameNumber(): void
    {
        $a = InvoiceNumber::create(2025, 42);
        $b = InvoiceNumber::create(2025, 42);

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentNumbers(): void
    {
        $a = InvoiceNumber::create(2025, 1);
        $b = InvoiceNumber::create(2025, 2);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function toStringMatchesExpectedFormat(): void
    {
        $number = InvoiceNumber::create(2025, 123);
        self::assertSame('INV-2025-000123', (string) $number);
    }
}
