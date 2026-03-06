<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locale::class)]
final class LocaleTest extends TestCase
{
    // -------------------------------------------------------------------
    // fromString() — valid
    // -------------------------------------------------------------------

    #[Test]
    public function itCreatesFromLanguageOnlyCode(): void
    {
        $locale = Locale::fromString('en');

        self::assertSame('en', $locale->toString());
        self::assertSame('en', $locale->value());
    }

    #[Test]
    public function itCreatesFromLanguageCountryCode(): void
    {
        $locale = Locale::fromString('en_US');

        self::assertSame('en_US', $locale->toString());
    }

    // -------------------------------------------------------------------
    // fromString() — invalid
    // -------------------------------------------------------------------

    #[Test]
    public function itThrowsOnInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::fromString('invalid');
    }

    #[Test]
    public function itThrowsOnUppercaseLanguage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::fromString('EN');
    }

    #[Test]
    public function itThrowsOnLowercaseCountry(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::fromString('en_us');
    }

    #[Test]
    public function itThrowsOnThreeLetterLanguage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::fromString('eng');
    }

    #[Test]
    public function itThrowsOnSingleLetterLanguage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::fromString('e');
    }

    // -------------------------------------------------------------------
    // equals()
    // -------------------------------------------------------------------

    #[Test]
    public function itEqualsSameLocale(): void
    {
        $locale1 = Locale::fromString('en_US');
        $locale2 = Locale::fromString('en_US');

        self::assertTrue($locale1->equals($locale2));
    }

    #[Test]
    public function itDoesNotEqualDifferentLocale(): void
    {
        $locale1 = Locale::fromString('en_US');
        $locale2 = Locale::fromString('en_GB');

        self::assertFalse($locale1->equals($locale2));
    }

    #[Test]
    public function itDoesNotEqualLanguageCodeVsFullLocale(): void
    {
        $lang = Locale::fromString('en');
        $full = Locale::fromString('en_US');

        self::assertFalse($lang->equals($full));
    }

    // -------------------------------------------------------------------
    // getLanguageCode()
    // -------------------------------------------------------------------

    #[Test]
    public function itExtractsLanguageCodeFromFullLocale(): void
    {
        $locale = Locale::fromString('en_US');

        self::assertSame('en', $locale->getLanguageCode());
    }

    #[Test]
    public function itReturnsWholeValueForLanguageOnlyLocale(): void
    {
        $locale = Locale::fromString('fr');

        self::assertSame('fr', $locale->getLanguageCode());
    }

    // -------------------------------------------------------------------
    // getCountryCode()
    // -------------------------------------------------------------------

    #[Test]
    public function itExtractsCountryCodeFromFullLocale(): void
    {
        $locale = Locale::fromString('en_US');

        self::assertSame('US', $locale->getCountryCode());
    }

    #[Test]
    public function itReturnsNullCountryCodeForLanguageOnlyLocale(): void
    {
        $locale = Locale::fromString('en');

        self::assertNull($locale->getCountryCode());
    }

    // -------------------------------------------------------------------
    // isCompatibleWith()
    // -------------------------------------------------------------------

    #[Test]
    public function itIsCompatibleWithExactMatch(): void
    {
        $locale1 = Locale::fromString('en_US');
        $locale2 = Locale::fromString('en_US');

        self::assertTrue($locale1->isCompatibleWith($locale2));
    }

    #[Test]
    public function itIsCompatibleWithSameLanguageDifferentCountry(): void
    {
        $locale1 = Locale::fromString('en_US');
        $locale2 = Locale::fromString('en_GB');

        self::assertTrue($locale1->isCompatibleWith($locale2));
    }

    #[Test]
    public function itIsCompatibleBetweenFullAndBaseLocale(): void
    {
        $full = Locale::fromString('fr_FR');
        $base = Locale::fromString('fr');

        self::assertTrue($full->isCompatibleWith($base));
    }

    #[Test]
    public function itIsNotCompatibleWithDifferentLanguage(): void
    {
        $en = Locale::fromString('en_US');
        $fr = Locale::fromString('fr_FR');

        self::assertFalse($en->isCompatibleWith($fr));
    }

    // -------------------------------------------------------------------
    // getBaseLocale()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsBaseLocaleFromFullLocale(): void
    {
        $locale = Locale::fromString('en_US');
        $base = $locale->getBaseLocale();

        self::assertSame('en', $base->toString());
    }

    #[Test]
    public function itReturnsSelfAsBaseLocaleForLanguageOnly(): void
    {
        $locale = Locale::fromString('en');
        $base = $locale->getBaseLocale();

        self::assertTrue($locale->equals($base));
    }

    // -------------------------------------------------------------------
    // getFallback()
    // -------------------------------------------------------------------

    #[Test]
    public function itReturnsFallbackForRegionalLocale(): void
    {
        $locale = Locale::fromString('en_US');
        $fallback = $locale->getFallback();

        self::assertNotNull($fallback);
        self::assertSame('en', $fallback->toString());
    }

    #[Test]
    public function itReturnsNullFallbackForBaseLocale(): void
    {
        $locale = Locale::fromString('en');

        self::assertNull($locale->getFallback());
    }

    // -------------------------------------------------------------------
    // normalize()
    // -------------------------------------------------------------------

    #[Test]
    public function itNormalizesHyphenSeparatedLocale(): void
    {
        $locale = Locale::normalize('en-US');

        self::assertSame('en_US', $locale->toString());
    }

    #[Test]
    public function itNormalizesUppercaseLanguage(): void
    {
        $locale = Locale::normalize('EN_US');

        self::assertSame('en_US', $locale->toString());
    }

    #[Test]
    public function itNormalizesLanguageOnlyLocale(): void
    {
        $locale = Locale::normalize('FR');

        self::assertSame('fr', $locale->toString());
    }

    #[Test]
    public function itNormalizesLowercaseCountry(): void
    {
        $locale = Locale::normalize('fr_fr');

        self::assertSame('fr_FR', $locale->toString());
    }

    #[Test]
    public function itThrowsNormalizeOnTooManyParts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::normalize('en_US_extra');
    }

    // -------------------------------------------------------------------
    // Data Providers — round-trip creation
    // -------------------------------------------------------------------

    #[Test]
    #[DataProvider('validLocalesProvider')]
    public function itAcceptsVariousValidLocales(string $locale): void
    {
        $obj = Locale::fromString($locale);

        self::assertSame($locale, $obj->toString());
    }

    public static function validLocalesProvider(): array
    {
        return [
            'en' => ['en'],
            'fr' => ['fr'],
            'de' => ['de'],
            'ro' => ['ro'],
            'en_US' => ['en_US'],
            'en_GB' => ['en_GB'],
            'fr_FR' => ['fr_FR'],
            'fr_CA' => ['fr_CA'],
            'de_DE' => ['de_DE'],
            'ro_RO' => ['ro_RO'],
        ];
    }
}
