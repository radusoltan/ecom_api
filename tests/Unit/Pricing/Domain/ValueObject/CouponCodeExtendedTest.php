<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\ValueObject;

use App\Pricing\Domain\ValueObject\CouponCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CouponCode::class)]
final class CouponCodeExtendedTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Additional creation tests not in the original CouponCodeTest
    // -----------------------------------------------------------------------

    #[Test]
    public function itAcceptsAllNumericCode(): void
    {
        $code = CouponCode::fromString('12345678');

        self::assertSame('12345678', $code->toString());
    }

    #[Test]
    public function itAcceptsAllUppercaseLetters(): void
    {
        $code = CouponCode::fromString('ABCD');

        self::assertSame('ABCD', $code->toString());
    }

    #[Test]
    public function itConvertsLowercaseToUppercase(): void
    {
        $code = CouponCode::fromString('abcd');

        self::assertSame('ABCD', $code->toString());
    }

    #[Test]
    public function itTrimsLeadingAndTrailingSpaces(): void
    {
        $code = CouponCode::fromString('  CODE1  ');

        self::assertSame('CODE1', $code->toString());
    }

    #[Test]
    public function itTrimsAndConvertsToUppercase(): void
    {
        $code = CouponCode::fromString('  save2024  ');

        self::assertSame('SAVE2024', $code->toString());
    }

    #[Test]
    public function itThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must be between 4 and 20 characters');

        CouponCode::fromString('');
    }

    #[Test]
    public function itThrowsForWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must be between 4 and 20 characters');

        CouponCode::fromString('     ');
    }

    #[Test]
    public function itThrowsForCodeWithHyphen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must contain only uppercase alphanumeric characters');

        CouponCode::fromString('SAVE-20');
    }

    #[Test]
    public function itThrowsForCodeWithUnderscore(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must contain only uppercase alphanumeric characters');

        CouponCode::fromString('SAVE_20');
    }

    #[Test]
    public function itThrowsForCodeWithSpecialCharacter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CouponCode::fromString('SAVE@20');
    }

    #[Test]
    public function itThrowsForCodeWithSpace(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CouponCode::fromString('SAVE 20');
    }

    #[Test]
    public function itThrowsForCodeWithThreeCharactersAfterTrim(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must be between 4 and 20 characters');

        CouponCode::fromString('  ABC  ');
    }

    #[Test]
    public function itThrowsForCodeWithTwentyOneCharactersAfterTrim(): void
    {
        // 21 uppercase chars
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CouponCode must be between 4 and 20 characters');

        CouponCode::fromString('ABCDEFGHIJ12345678901');
    }

    // -----------------------------------------------------------------------
    // Equality
    // -----------------------------------------------------------------------

    #[Test]
    public function itEqualsCodeCreatedWithDifferentCasing(): void
    {
        $code1 = CouponCode::fromString('save20');
        $code2 = CouponCode::fromString('SAVE20');

        self::assertTrue($code1->equals($code2));
    }

    #[Test]
    public function itEqualsCodeCreatedWithWhitespace(): void
    {
        $code1 = CouponCode::fromString('  SAVE20  ');
        $code2 = CouponCode::fromString('SAVE20');

        self::assertTrue($code1->equals($code2));
    }

    #[Test]
    public function itDoesNotEqualCodeWithDifferentValue(): void
    {
        $code1 = CouponCode::fromString('SAVE20');
        $code2 = CouponCode::fromString('SAVE30');

        self::assertFalse($code1->equals($code2));
    }

    // -----------------------------------------------------------------------
    // Data provider tests
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('validCodesProvider')]
    public function itAcceptsValidCode(string $input, string $expected): void
    {
        $code = CouponCode::fromString($input);

        self::assertSame($expected, $code->toString());
    }

    public static function validCodesProvider(): array
    {
        return [
            'four chars' => ['ABCD', 'ABCD'],
            'twenty chars' => ['ABCDEFGHIJ1234567890', 'ABCDEFGHIJ1234567890'],
            'lowercase' => ['save20', 'SAVE20'],
            'mixed case with trim' => ['  Sale2024  ', 'SALE2024'],
            'all digits' => ['1234', '1234'],
            'alphanumeric' => ['A1B2C3D4', 'A1B2C3D4'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCodesProvider')]
    public function itRejectsInvalidCode(string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CouponCode::fromString($input);
    }

    public static function invalidCodesProvider(): array
    {
        return [
            'empty' => [''],
            'too short' => ['ABC'],
            'too long' => ['ABCDEFGHIJ12345678901'],
            'hyphen' => ['SAVE-20'],
            'underscore' => ['SAVE_20'],
            'space within' => ['SAV E20'],
            'special char' => ['SAVE!20'],
            'dot' => ['SAVE.20'],
        ];
    }
}
