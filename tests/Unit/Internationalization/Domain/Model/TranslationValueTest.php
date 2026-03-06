<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Model;

use App\Internationalization\Domain\Model\TranslationValue;
use PHPUnit\Framework\TestCase;

final class TranslationValueTest extends TestCase
{
    public function testFromStringCreatesWithSimpleText(): void
    {
        $value = TranslationValue::fromString('Hello, World!');
        self::assertSame('Hello, World!', $value->value());
    }

    public function testFromStringCreatesWithEmptyString(): void
    {
        $value = TranslationValue::fromString('');
        self::assertSame('', $value->value());
    }

    public function testFromStringCreatesWithMultilineText(): void
    {
        $text = "Line one\nLine two\nLine three";
        $value = TranslationValue::fromString($text);
        self::assertSame($text, $value->value());
    }

    public function testFromStringCreatesWithHtmlContent(): void
    {
        $html = '<p>Welcome to our <strong>store</strong>!</p>';
        $value = TranslationValue::fromString($html);
        self::assertSame($html, $value->value());
    }

    public function testFromStringCreatesWithUnicodeText(): void
    {
        $unicode = 'Bine ați venit! Découvrez nos produits. 您好！';
        $value = TranslationValue::fromString($unicode);
        self::assertSame($unicode, $value->value());
    }

    public function testFromStringCreatesWithInterpolationPlaceholders(): void
    {
        $template = 'Hello, %name%! You have %count% items in your cart.';
        $value = TranslationValue::fromString($template);
        self::assertSame($template, $value->value());
    }

    public function testFromStringThrowsForValueExceedingMaxLength(): void
    {
        $tooLong = str_repeat('a', 10001);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation value cannot exceed 10000 characters');
        TranslationValue::fromString($tooLong);
    }

    public function testFromStringAcceptsValueAtMaxLength(): void
    {
        $atMax = str_repeat('a', 10000);
        $value = TranslationValue::fromString($atMax);
        self::assertSame(10000, $value->length());
    }

    public function testEmptyCreatesEmptyValue(): void
    {
        $value = TranslationValue::empty();
        self::assertSame('', $value->value());
    }

    public function testEmptyIsEmpty(): void
    {
        $value = TranslationValue::empty();
        self::assertTrue($value->isEmpty());
    }

    public function testIsEmptyReturnsTrueForEmptyString(): void
    {
        $value = TranslationValue::fromString('');
        self::assertTrue($value->isEmpty());
    }

    public function testIsEmptyReturnsTrueForWhitespaceOnly(): void
    {
        $value = TranslationValue::fromString('   ');
        self::assertTrue($value->isEmpty());
    }

    public function testIsEmptyReturnsFalseForTextWithContent(): void
    {
        $value = TranslationValue::fromString('Hello');
        self::assertFalse($value->isEmpty());
    }

    public function testIsEmptyReturnsFalseForTextWithLeadingWhitespace(): void
    {
        $value = TranslationValue::fromString('  Hello  ');
        self::assertFalse($value->isEmpty());
    }

    public function testLengthReturnsCorrectCount(): void
    {
        $value = TranslationValue::fromString('Hello');
        self::assertSame(5, $value->length());
    }

    public function testLengthReturnsZeroForEmpty(): void
    {
        $value = TranslationValue::fromString('');
        self::assertSame(0, $value->length());
    }

    public function testLengthCountsByteLength(): void
    {
        // strlen counts bytes, not characters
        $value = TranslationValue::fromString('abc');
        self::assertSame(3, $value->length());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $value1 = TranslationValue::fromString('Hello');
        $value2 = TranslationValue::fromString('Hello');
        self::assertTrue($value1->equals($value2));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $value1 = TranslationValue::fromString('Hello');
        $value2 = TranslationValue::fromString('Goodbye');
        self::assertFalse($value1->equals($value2));
    }

    public function testEqualsReturnsTrueForTwoEmptyInstances(): void
    {
        $value1 = TranslationValue::empty();
        $value2 = TranslationValue::fromString('');
        self::assertTrue($value1->equals($value2));
    }

    public function testEqualsIsCaseSensitive(): void
    {
        $value1 = TranslationValue::fromString('Hello');
        $value2 = TranslationValue::fromString('hello');
        self::assertFalse($value1->equals($value2));
    }

    public function testEqualsReturnsFalseComparingEmptyAndWhitespace(): void
    {
        $value1 = TranslationValue::fromString('');
        $value2 = TranslationValue::fromString(' ');
        self::assertFalse($value1->equals($value2));
    }
}
