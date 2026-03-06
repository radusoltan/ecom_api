<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\OptionValueCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptionValueCode::class)]
final class OptionValueCodeTest extends TestCase
{
    // -------------------------------------------------------------------
    // Valid creation
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesFromValidCode(): void
    {
        $code = OptionValueCode::fromString('red');

        self::assertSame('red', $code->toString());
        self::assertSame('red', $code->value());
        self::assertSame('red', (string) $code);
    }

    #[Test]
    public function itNormalizesToLowercase(): void
    {
        $code = OptionValueCode::fromString('RED');

        self::assertSame('red', $code->toString());
    }

    #[Test]
    public function itTrimsWhitespace(): void
    {
        $code = OptionValueCode::fromString('  xl  ');

        self::assertSame('xl', $code->toString());
    }

    #[Test]
    public function itAcceptsCodeStartingWithDigit(): void
    {
        // Pattern allows starting with digit: ^[a-z0-9]
        $code = OptionValueCode::fromString('3xl');

        self::assertSame('3xl', $code->toString());
    }

    #[Test]
    public function itAcceptsCodeWithUnderscore(): void
    {
        $code = OptionValueCode::fromString('navy_blue');

        self::assertSame('navy_blue', $code->toString());
    }

    #[Test]
    public function itAcceptsCodeWithHyphen(): void
    {
        // OptionValueCode allows hyphens, OptionCode does not
        $code = OptionValueCode::fromString('navy-blue');

        self::assertSame('navy-blue', $code->toString());
    }

    #[Test]
    public function itAcceptsSingleCharCode(): void
    {
        // Pattern: ^[a-z0-9][a-z0-9_-]{0,31}$ — minimum 1 char
        $code = OptionValueCode::fromString('s');

        self::assertSame('s', $code->toString());
    }

    #[Test]
    public function itAcceptsMaximumLengthCode(): void
    {
        // 32 chars
        $code = OptionValueCode::fromString(str_repeat('a', 32));

        self::assertSame(str_repeat('a', 32), $code->toString());
    }

    // -------------------------------------------------------------------
    // Equality
    // -------------------------------------------------------------------

    #[Test]
    public function itEqualsSameCode(): void
    {
        $code1 = OptionValueCode::fromString('red');
        $code2 = OptionValueCode::fromString('red');

        self::assertTrue($code1->equals($code2));
    }

    #[Test]
    public function itDoesNotEqualDifferentCode(): void
    {
        $code1 = OptionValueCode::fromString('red');
        $code2 = OptionValueCode::fromString('blue');

        self::assertFalse($code1->equals($code2));
    }

    #[Test]
    public function itEqualsCaseInsensitively(): void
    {
        $code1 = OptionValueCode::fromString('RED');
        $code2 = OptionValueCode::fromString('red');

        self::assertTrue($code1->equals($code2));
    }

    // -------------------------------------------------------------------
    // Invalid creation
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Option value code cannot be empty');

        OptionValueCode::fromString('');
    }

    #[Test]
    public function itThrowsOnTooLongCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot exceed 32 characters');

        // 33 chars
        OptionValueCode::fromString(str_repeat('a', 33));
    }

    #[Test]
    public function itThrowsOnCodeWithSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option value code');

        OptionValueCode::fromString('navy blue');
    }

    #[Test]
    public function itThrowsOnCodeStartingWithHyphen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option value code');

        OptionValueCode::fromString('-red');
    }

    #[Test]
    public function itThrowsOnCodeWithSpecialChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid option value code');

        OptionValueCode::fromString('red@blue');
    }

    #[Test]
    #[DataProvider('validCodesProvider')]
    public function itAcceptsVariousValidCodes(string $input, string $expected): void
    {
        $code = OptionValueCode::fromString($input);

        self::assertSame($expected, $code->toString());
    }

    public static function validCodesProvider(): array
    {
        return [
            'simple word' => ['red', 'red'],
            'uppercase normalized' => ['XL', 'xl'],
            'with underscore' => ['navy_blue', 'navy_blue'],
            'with hyphen' => ['navy-blue', 'navy-blue'],
            'starts with digit' => ['3xl', '3xl'],
            'single char' => ['s', 's'],
            'all digits' => ['100', '100'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCodesProvider')]
    public function itRejectsInvalidCodes(string $invalidCode): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OptionValueCode::fromString($invalidCode);
    }

    public static function invalidCodesProvider(): array
    {
        return [
            'empty' => [''],
            'starts with hyphen' => ['-red'],
            'contains space' => ['navy blue'],
            'contains at sign' => ['red@blue'],
            'too long 33 chars' => [str_repeat('a', 33)],
        ];
    }
}
