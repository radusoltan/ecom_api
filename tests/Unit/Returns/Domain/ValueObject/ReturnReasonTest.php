<?php

declare(strict_types=1);

namespace App\Tests\Unit\Returns\Domain\ValueObject;

use App\Returns\Domain\ValueObject\ReturnReason;
use PHPUnit\Framework\TestCase;

final class ReturnReasonTest extends TestCase
{
    public function testFromStringWithValidReason(): void
    {
        $reason = ReturnReason::fromString('Product was defective and not working properly');

        $this->assertInstanceOf(ReturnReason::class, $reason);
        $this->assertEquals('Product was defective and not working properly', $reason->value());
    }

    public function testFromStringThrowsExceptionForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Return reason cannot be empty.');

        ReturnReason::fromString('');
    }

    public function testFromStringThrowsExceptionForWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Return reason cannot be empty.');

        ReturnReason::fromString('   ');
    }

    public function testFromStringThrowsExceptionForTooShortReason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Return reason must be at least 10 characters');

        ReturnReason::fromString('Too short');
    }

    public function testFromStringThrowsExceptionForExactly9Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Return reason must be at least 10 characters');

        ReturnReason::fromString('123456789');
    }

    public function testFromStringAcceptsExactly10Characters(): void
    {
        $reason = ReturnReason::fromString('1234567890');

        $this->assertInstanceOf(ReturnReason::class, $reason);
        $this->assertEquals('1234567890', $reason->value());
    }

    public function testFromStringThrowsExceptionForTooLongReason(): void
    {
        $longReason = str_repeat('a', 501);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Return reason must not exceed 500 characters');

        ReturnReason::fromString($longReason);
    }

    public function testFromStringAcceptsExactly500Characters(): void
    {
        $reason500 = str_repeat('a', 500);
        $reason = ReturnReason::fromString($reason500);

        $this->assertInstanceOf(ReturnReason::class, $reason);
        $this->assertEquals(500, mb_strlen($reason->value()));
    }

    public function testFromStringTrimsWhitespace(): void
    {
        $reason = ReturnReason::fromString('  Product defective and broken  ');

        $this->assertEquals('Product defective and broken', $reason->value());
    }

    public function testValueReturnsOriginalValue(): void
    {
        $reason = ReturnReason::fromString('Item not as described in listing');

        $this->assertEquals('Item not as described in listing', $reason->value());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $reason1 = ReturnReason::fromString('Wrong item sent');
        $reason2 = ReturnReason::fromString('Wrong item sent');

        $this->assertTrue($reason1->equals($reason2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $reason1 = ReturnReason::fromString('Wrong item sent');
        $reason2 = ReturnReason::fromString('Product defective');

        $this->assertFalse($reason1->equals($reason2));
    }

    public function testEqualsComparesAfterTrimming(): void
    {
        $reason1 = ReturnReason::fromString('Product defective');
        $reason2 = ReturnReason::fromString('  Product defective  ');

        $this->assertTrue($reason1->equals($reason2));
    }

    public function testToStringReturnsValue(): void
    {
        $reason = ReturnReason::fromString('Changed my mind after purchase');

        $this->assertEquals('Changed my mind after purchase', (string) $reason);
    }

    public function testAcceptsMultibyteCharacters(): void
    {
        $reason = ReturnReason::fromString('Produsul este defect și nu funcționează');

        $this->assertInstanceOf(ReturnReason::class, $reason);
        $this->assertGreaterThanOrEqual(10, mb_strlen($reason->value()));
    }

    public function testAcceptsSpecialCharacters(): void
    {
        $reason = ReturnReason::fromString('Item broken & damaged! ($50 loss)');

        $this->assertInstanceOf(ReturnReason::class, $reason);
        $this->assertEquals('Item broken & damaged! ($50 loss)', $reason->value());
    }

    public function testAcceptsNewlinesAndTabs(): void
    {
        $reason = ReturnReason::fromString("Item damaged\nPackaging torn\tNot usable");

        $this->assertInstanceOf(ReturnReason::class, $reason);
        $this->assertStringContainsString("\n", $reason->value());
    }

    public function testMinimumLengthIsEnforced(): void
    {
        // 10 characters is minimum
        $validReasons = [
            '1234567890',
            'Too small!!',
            'Broken box',
        ];

        foreach ($validReasons as $reasonText) {
            $reason = ReturnReason::fromString($reasonText);
            $this->assertGreaterThanOrEqual(10, mb_strlen($reason->value()));
        }
    }

    public function testMaximumLengthIsEnforced(): void
    {
        // 500 characters is maximum
        $reason499 = str_repeat('a', 499);
        $reason500 = str_repeat('a', 500);

        $this->assertInstanceOf(ReturnReason::class, ReturnReason::fromString($reason499));
        $this->assertInstanceOf(ReturnReason::class, ReturnReason::fromString($reason500));

        $this->expectException(\InvalidArgumentException::class);
        ReturnReason::fromString(str_repeat('a', 501));
    }
}
