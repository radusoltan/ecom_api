<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Model;

use App\Internationalization\Domain\Model\TranslationKey;
use PHPUnit\Framework\TestCase;

final class TranslationKeyTest extends TestCase
{
    public function testFromStringCreatesWithSimpleKey(): void
    {
        $key = TranslationKey::fromString('greeting');
        self::assertSame('greeting', $key->value());
    }

    public function testFromStringCreatesWithDottedKey(): void
    {
        $key = TranslationKey::fromString('greeting.hello');
        self::assertSame('greeting.hello', $key->value());
    }

    public function testFromStringCreatesWithDeepDottedKey(): void
    {
        $key = TranslationKey::fromString('common.buttons.add_to_cart');
        self::assertSame('common.buttons.add_to_cart', $key->value());
    }

    public function testFromStringCreatesWithUnderscores(): void
    {
        $key = TranslationKey::fromString('error_messages.not_found');
        self::assertSame('error_messages.not_found', $key->value());
    }

    public function testFromStringCreatesWithHyphens(): void
    {
        $key = TranslationKey::fromString('email-subject.order-placed');
        self::assertSame('email-subject.order-placed', $key->value());
    }

    public function testFromStringCreatesWithAlphanumericKey(): void
    {
        $key = TranslationKey::fromString('button123');
        self::assertSame('button123', $key->value());
    }

    public function testFromStringTrimsSurroundingWhitespace(): void
    {
        $key = TranslationKey::fromString('  greeting.hello  ');
        self::assertSame('greeting.hello', $key->value());
    }

    public function testFromStringThrowsForEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation key cannot be empty');
        TranslationKey::fromString('');
    }

    public function testFromStringThrowsForWhitespaceOnly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation key cannot be empty');
        TranslationKey::fromString('   ');
    }

    public function testFromStringThrowsForKeyExceedingMaxLength(): void
    {
        $longKey = str_repeat('a', 256);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation key cannot exceed 255 characters');
        TranslationKey::fromString($longKey);
    }

    public function testFromStringAcceptsKeyAtMaxLength(): void
    {
        $maxKey = str_repeat('a', 255);
        $key = TranslationKey::fromString($maxKey);
        self::assertSame($maxKey, $key->value());
    }

    public function testFromStringThrowsForKeyWithSpaces(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation key must contain only alphanumeric characters, dots, underscores, and hyphens');
        TranslationKey::fromString('greeting hello');
    }

    public function testFromStringThrowsForKeyWithSpecialCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationKey::fromString('greeting@hello');
    }

    public function testFromStringThrowsForKeyWithSlashes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TranslationKey::fromString('greeting/hello');
    }

    public function testEqualsReturnsTrueForSameKey(): void
    {
        $key1 = TranslationKey::fromString('greeting.hello');
        $key2 = TranslationKey::fromString('greeting.hello');
        self::assertTrue($key1->equals($key2));
    }

    public function testEqualsReturnsFalseForDifferentKey(): void
    {
        $key1 = TranslationKey::fromString('greeting.hello');
        $key2 = TranslationKey::fromString('greeting.goodbye');
        self::assertFalse($key1->equals($key2));
    }

    public function testEqualsIsCaseSensitive(): void
    {
        $key1 = TranslationKey::fromString('Greeting.Hello');
        $key2 = TranslationKey::fromString('greeting.hello');
        self::assertFalse($key1->equals($key2));
    }

    public function testContainsReturnsTrueWhenSubstringPresent(): void
    {
        $key = TranslationKey::fromString('greeting.hello');
        self::assertTrue($key->contains('greeting'));
    }

    public function testContainsReturnsTrueForDotSeparator(): void
    {
        $key = TranslationKey::fromString('greeting.hello');
        self::assertTrue($key->contains('.'));
    }

    public function testContainsReturnsFalseWhenSubstringAbsent(): void
    {
        $key = TranslationKey::fromString('greeting.hello');
        self::assertFalse($key->contains('goodbye'));
    }

    public function testContainsReturnsTrueForFullValue(): void
    {
        $key = TranslationKey::fromString('greeting.hello');
        self::assertTrue($key->contains('greeting.hello'));
    }

    public function testContainsReturnsFalseForEmptyKeyAfterTrim(): void
    {
        $key = TranslationKey::fromString('greeting.hello');
        self::assertFalse($key->contains('missing'));
    }
}
