<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\DiscountType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiscountType::class)]
final class DiscountTypeTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Factory methods
    // -----------------------------------------------------------------------

    #[Test]
    public function itCreatesPercentageType(): void
    {
        $type = DiscountType::percentage();

        self::assertSame('percentage', $type->toString());
        self::assertTrue($type->isPercentage());
        self::assertFalse($type->isFixedAmount());
    }

    #[Test]
    public function itCreatesFixedAmountType(): void
    {
        $type = DiscountType::fixedAmount();

        self::assertSame('fixed_amount', $type->toString());
        self::assertFalse($type->isPercentage());
        self::assertTrue($type->isFixedAmount());
    }

    #[Test]
    public function itCreatesPercentageFromString(): void
    {
        $type = DiscountType::fromString('percentage');

        self::assertTrue($type->isPercentage());
        self::assertSame('percentage', $type->toString());
    }

    #[Test]
    public function itCreatesFixedAmountFromString(): void
    {
        $type = DiscountType::fromString('fixed_amount');

        self::assertTrue($type->isFixedAmount());
        self::assertSame('fixed_amount', $type->toString());
    }

    #[Test]
    public function itThrowsForInvalidTypeString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DiscountType: "invalid"');

        DiscountType::fromString('invalid');
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DiscountType::fromString('');
    }

    #[Test]
    public function itThrowsForUppercaseString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DiscountType::fromString('PERCENTAGE');
    }

    #[Test]
    public function itThrowsForPartialMatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DiscountType::fromString('fixed');
    }

    // -----------------------------------------------------------------------
    // equals()
    // -----------------------------------------------------------------------

    #[Test]
    public function itEqualsItself(): void
    {
        $type = DiscountType::percentage();

        self::assertTrue($type->equals($type));
    }

    #[Test]
    public function itEqualsAnotherInstanceWithSameValue(): void
    {
        $a = DiscountType::percentage();
        $b = DiscountType::percentage();

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualDifferentType(): void
    {
        $percentage = DiscountType::percentage();
        $fixed = DiscountType::fixedAmount();

        self::assertFalse($percentage->equals($fixed));
        self::assertFalse($fixed->equals($percentage));
    }

    // -----------------------------------------------------------------------
    // Constants
    // -----------------------------------------------------------------------

    #[Test]
    public function itHasCorrectConstantValues(): void
    {
        self::assertSame('percentage', DiscountType::PERCENTAGE);
        self::assertSame('fixed_amount', DiscountType::FIXED_AMOUNT);
    }

    // -----------------------------------------------------------------------
    // Data provider for round-trip tests
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('validTypesProvider')]
    public function itRoundTripsFromStringToString(string $value): void
    {
        $type = DiscountType::fromString($value);

        self::assertSame($value, $type->toString());
    }

    public static function validTypesProvider(): array
    {
        return [
            'percentage' => ['percentage'],
            'fixed_amount' => ['fixed_amount'],
        ];
    }

    #[Test]
    #[DataProvider('invalidTypesProvider')]
    public function itThrowsForInvalidType(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DiscountType::fromString($value);
    }

    public static function invalidTypesProvider(): array
    {
        return [
            'empty' => [''],
            'uppercase_percentage' => ['PERCENTAGE'],
            'uppercase_fixed_amount' => ['FIXED_AMOUNT'],
            'typo' => ['percentag'],
            'numeric' => ['1'],
            'whitespace' => [' percentage'],
        ];
    }
}
