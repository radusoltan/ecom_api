<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\OptionCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptionCode::class)]
final class OptionCodeTest extends TestCase
{
    // -------------------------------------------------------------------
    // Valid creation
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidCode(): void
    {
        $code = OptionCode::fromString('color');

        self::assertSame('color', $code->toString());
        self::assertSame('color', $code->value());
        self::assertSame('color', (string) $code);
    }

    #[Test]
    public function itNormalizesToLowercase(): void
    {
        $code = OptionCode::fromString('COLOR');

        self::assertSame('color', $code->toString());
    }

    #[Test]
    public function itTrimsWhitespace(): void
    {
        $code = OptionCode::fromString('  size  ');

        self::assertSame('size', $code->toString());
    }

    #[Test]
    public function itAcceptsCodeWithUnderscore(): void
    {
        $code = OptionCode::fromString('shoe_size');

        self::assertSame('shoe_size', $code->toString());
    }

    #[Test]
    public function itAcceptsCodeWithNumbers(): void
    {
        $code = OptionCode::fromString('size2');

        self::assertSame('size2', $code->toString());
    }

    #[Test]
    public function itAcceptsMinimumLengthCode(): void
    {
        // Pattern: ^[a-z][a-z0-9_]{1,31}$ — minimum 2 chars
        $code = OptionCode::fromString('ab');

        self::assertSame('ab', $code->toString());
    }

    #[Test]
    public function itAcceptsMaximumLengthCode(): void
    {
        // 32 chars: 1 letter + 31 more
        $code = OptionCode::fromString('a'.str_repeat('b', 31));

        self::assertSame('a'.str_repeat('b', 31), $code->toString());
    }

    // -------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------

    #[Test]
    public function itEqualsSameCode(): void
    {
        $code1 = OptionCode::fromString('color');
        $code2 = OptionCode::fromString('color');

        self::assertTrue($code1->equals($code2));
    }

    #[Test]
    public function itDoesNotEqualDifferentCode(): void
    {
        $code1 = OptionCode::fromString('color');
        $code2 = OptionCode::fromString('size');

        self::assertFalse($code1->equals($code2));
    }

    #[Test]
    public function itEqualsCaseInsensitively(): void
    {
        $code1 = OptionCode::fromString('COLOR');
        $code2 = OptionCode::fromString('color');

        self::assertTrue($code1->equals($code2));
    }

    // -------------------------------------------------------------------
    // Invalid creation
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Option code cannot be empty');

        OptionCode::fromString('');
    }

    #[Test]
    public function itThrowsOnTooLongCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed 32 characters');

        // 33 chars
        OptionCode::fromString('a'.str_repeat('b', 32));
    }

    #[Test]
    public function itThrowsWhenStartingWithDigit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option code');

        OptionCode::fromString('1color');
    }

    #[Test]
    public function itThrowsWhenStartingWithUnderscore(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option code');

        OptionCode::fromString('_color');
    }

    #[Test]
    public function itThrowsOnCodeWithHyphen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option code');

        OptionCode::fromString('shoe-size');
    }

    #[Test]
    public function itThrowsOnCodeWithSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option code');

        OptionCode::fromString('shoe size');
    }

    #[Test]
    #[DataProvider('validCodesProvider')]
    public function itAcceptsVariousValidCodes(string $input, string $expected): void
    {
        $code = OptionCode::fromString($input);

        self::assertSame($expected, $code->toString());
    }

    public static function validCodesProvider(): array
    {
        return [
            'simple word' => ['color', 'color'],
            'uppercase normalized' => ['SIZE', 'size'],
            'with underscore' => ['shoe_size', 'shoe_size'],
            'with numbers' => ['color2', 'color2'],
            'two chars' => ['ab', 'ab'],
            'mixed case' => ['ShoeSize', 'shoesize'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCodesProvider')]
    public function itRejectsInvalidCodes(string $invalidCode): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OptionCode::fromString($invalidCode);
    }

    public static function invalidCodesProvider(): array
    {
        return [
            'empty' => [''],
            'starts with digit' => ['1color'],
            'starts with underscore' => ['_color'],
            'contains hyphen' => ['shoe-size'],
            'single char' => ['c'],
            'too long 33 chars' => ['a'.str_repeat('b', 32)],
        ];
    }
}
