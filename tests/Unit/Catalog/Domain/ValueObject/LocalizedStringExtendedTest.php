<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalizedString::class)]
#[CoversClass(Locale::class)]
final class LocalizedStringExtendedTest extends TestCase
{
    // -----------------------------------------------------------------------
    // getTranslationWithFallback() edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itSkipsNonLocaleObjectsInFallbackChain(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Hello']);

        // Mix of valid Locale objects and non-Locale items (strings, nulls)
        $result = $ls->getTranslationWithFallback([
            'not-a-locale-object',
            null,
            Locale::fromString('en'),
        ]);

        self::assertSame('Hello', $result);
    }

    #[Test]
    public function itReturnsFallbackToFirstAvailableWhenChainMisses(): void
    {
        $ls = LocalizedString::fromArray(['de' => 'Hallo', 'fr' => 'Bonjour']);

        // None of the chain locales match
        $result = $ls->getTranslationWithFallback([
            Locale::fromString('es'),
            Locale::fromString('it'),
        ]);

        // Falls back to first available translation
        self::assertNotNull($result);
        self::assertContains($result, ['Hallo', 'Bonjour']);
    }

    #[Test]
    public function itReturnsNullWhenFallbackChainEmptyAndTranslationsEmpty(): void
    {
        $ls = LocalizedString::empty();
        $result = $ls->getTranslationWithFallback([]);
        self::assertNull($result);
    }

    #[Test]
    public function itReturnsFirstMatchInFallbackChain(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Hello', 'fr' => 'Bonjour', 'de' => 'Hallo']);

        $result = $ls->getTranslationWithFallback([
            Locale::fromString('fr'),
            Locale::fromString('en'),
        ]);

        self::assertSame('Bonjour', $result);
    }

    // -----------------------------------------------------------------------
    // withTranslation() – update existing key
    // -----------------------------------------------------------------------

    #[Test]
    public function itUpdatesExistingTranslationImmutably(): void
    {
        $original = LocalizedString::fromArray(['en' => 'Color']);
        $updated = $original->withTranslation(Locale::fromString('en'), 'Colour');

        self::assertSame('Color', $original->getTranslation(Locale::fromString('en')));
        self::assertSame('Colour', $updated->getTranslation(Locale::fromString('en')));
    }

    // -----------------------------------------------------------------------
    // withoutTranslation() – removing non-existent key
    // -----------------------------------------------------------------------

    #[Test]
    public function itWithoutTranslationForNonExistentLocaleIsNoOp(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Hello']);
        $result = $ls->withoutTranslation(Locale::fromString('fr'));

        self::assertSame(1, $result->count());
        self::assertSame('Hello', $result->getTranslation(Locale::fromString('en')));
    }

    // -----------------------------------------------------------------------
    // merge() edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itMergesEmptyWithNonEmpty(): void
    {
        $empty = LocalizedString::empty();
        $nonEmpty = LocalizedString::fromArray(['en' => 'Hello']);

        $merged = $empty->merge($nonEmpty);

        self::assertSame(1, $merged->count());
        self::assertSame('Hello', $merged->getTranslation(Locale::fromString('en')));
    }

    #[Test]
    public function itMergesNonEmptyWithEmpty(): void
    {
        $nonEmpty = LocalizedString::fromArray(['en' => 'Hello']);
        $empty = LocalizedString::empty();

        $merged = $nonEmpty->merge($empty);

        self::assertSame(1, $merged->count());
        self::assertSame('Hello', $merged->getTranslation(Locale::fromString('en')));
    }

    #[Test]
    public function itMergesTwoEmptyStrings(): void
    {
        $a = LocalizedString::empty();
        $b = LocalizedString::empty();

        $merged = $a->merge($b);

        self::assertTrue($merged->isEmpty());
    }

    // -----------------------------------------------------------------------
    // getAvailableLocales()
    // -----------------------------------------------------------------------

    #[Test]
    public function itGetsAvailableLocalesReturnsLocaleObjects(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Hello', 'fr' => 'Bonjour']);
        $locales = $ls->getAvailableLocales();

        self::assertCount(2, $locales);
        foreach ($locales as $locale) {
            self::assertInstanceOf(Locale::class, $locale);
        }
    }

    #[Test]
    public function itGetsAvailableLocalesForEmpty(): void
    {
        $ls = LocalizedString::empty();
        $locales = $ls->getAvailableLocales();

        self::assertCount(0, $locales);
    }

    // -----------------------------------------------------------------------
    // fromJson() edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itReturnsEmptyForNonArrayJson(): void
    {
        $ls = LocalizedString::fromJson('"just a string"');
        self::assertTrue($ls->isEmpty());
    }

    #[Test]
    public function itReturnsEmptyForNullJson(): void
    {
        $ls = LocalizedString::fromJson('null');
        self::assertTrue($ls->isEmpty());
    }

    #[Test]
    public function itDeserializesValidMultipleLocalesJson(): void
    {
        $ls = LocalizedString::fromJson('{"en":"Color","de":"Farbe","fr":"Couleur"}');

        self::assertSame(3, $ls->count());
        self::assertSame('Color', $ls->getTranslation(Locale::fromString('en')));
        self::assertSame('Farbe', $ls->getTranslation(Locale::fromString('de')));
        self::assertSame('Couleur', $ls->getTranslation(Locale::fromString('fr')));
    }

    #[Test]
    public function itRoundTripsJsonSerialization(): void
    {
        $original = LocalizedString::fromArray(['en' => 'Size', 'de' => 'Größe']);

        $json = $original->toJson();
        $restored = LocalizedString::fromJson($json);

        self::assertTrue($original->equals($restored));
    }

    // -----------------------------------------------------------------------
    // equals() edge cases
    // -----------------------------------------------------------------------

    #[Test]
    public function itNotEqualsWithDifferentLocaleValues(): void
    {
        $a = LocalizedString::fromArray(['en' => 'Color']);
        $b = LocalizedString::fromArray(['en' => 'Colour']);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itNotEqualsWithAdditionalLocale(): void
    {
        $a = LocalizedString::fromArray(['en' => 'Color']);
        $b = LocalizedString::fromArray(['en' => 'Color', 'fr' => 'Couleur']);

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itTwoEmptyLocaleStringsAreEqual(): void
    {
        $a = LocalizedString::empty();
        $b = LocalizedString::empty();

        self::assertTrue($a->equals($b));
    }

    // -----------------------------------------------------------------------
    // validate() – invalid locale formats
    // -----------------------------------------------------------------------

    #[Test]
    public function itThrowsOnLocaleWithNumbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LocalizedString::fromArray(['e1' => 'Hello']);
    }

    #[Test]
    public function itThrowsOnLocaleWithLowercaseCountryCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LocalizedString::fromArray(['en_us' => 'Hello']);
    }

    #[Test]
    public function itAcceptsRegionalLocales(): void
    {
        $ls = LocalizedString::fromArray([
            'en_US' => 'Color',
            'en_GB' => 'Colour',
            'fr_FR' => 'Couleur',
            'de_DE' => 'Farbe',
        ]);

        self::assertSame(4, $ls->count());
    }

    // -----------------------------------------------------------------------
    // Locale value object additional coverage
    // -----------------------------------------------------------------------

    #[Test]
    public function itGetsLanguageCodeFromSimpleLocale(): void
    {
        $locale = Locale::fromString('en');
        self::assertSame('en', $locale->getLanguageCode());
    }

    #[Test]
    public function itGetsLanguageCodeFromRegionalLocale(): void
    {
        $locale = Locale::fromString('en_US');
        self::assertSame('en', $locale->getLanguageCode());
    }

    #[Test]
    public function itGetsCountryCodeFromRegionalLocale(): void
    {
        $locale = Locale::fromString('en_US');
        self::assertSame('US', $locale->getCountryCode());
    }

    #[Test]
    public function itReturnsNullCountryCodeForSimpleLocale(): void
    {
        $locale = Locale::fromString('en');
        self::assertNull($locale->getCountryCode());
    }

    #[Test]
    public function itIsCompatibleWithSameLocale(): void
    {
        $a = Locale::fromString('en');
        $b = Locale::fromString('en');
        self::assertTrue($a->isCompatibleWith($b));
    }

    #[Test]
    public function itIsCompatibleWithSameLanguageRegional(): void
    {
        $en = Locale::fromString('en');
        $enUs = Locale::fromString('en_US');
        self::assertTrue($en->isCompatibleWith($enUs));
        self::assertTrue($enUs->isCompatibleWith($en));
    }

    #[Test]
    public function itIsNotCompatibleWithDifferentLanguage(): void
    {
        $en = Locale::fromString('en');
        $de = Locale::fromString('de');
        self::assertFalse($en->isCompatibleWith($de));
    }

    #[Test]
    public function itGetsBaseLocaleFromRegionalLocale(): void
    {
        $locale = Locale::fromString('en_US');
        $base = $locale->getBaseLocale();

        self::assertSame('en', $base->toString());
    }

    #[Test]
    public function itGetsBaseLocaleFromSimpleLocale(): void
    {
        $locale = Locale::fromString('en');
        $base = $locale->getBaseLocale();

        self::assertSame('en', $base->toString());
        self::assertSame($locale, $base);
    }

    #[Test]
    public function itGetsFallbackForRegionalLocale(): void
    {
        $locale = Locale::fromString('en_US');
        $fallback = $locale->getFallback();

        self::assertNotNull($fallback);
        self::assertSame('en', $fallback->toString());
    }

    #[Test]
    public function itReturnsFallbackNullForSimpleLocale(): void
    {
        $locale = Locale::fromString('en');
        self::assertNull($locale->getFallback());
    }

    #[Test]
    public function itNormalizesLocaleLowerUpperCase(): void
    {
        $locale = Locale::normalize('EN_us');
        self::assertSame('en_US', $locale->toString());
    }

    #[Test]
    public function itNormalizesLocaleWithDash(): void
    {
        $locale = Locale::normalize('en-US');
        self::assertSame('en_US', $locale->toString());
    }

    #[Test]
    public function itNormalizesSimpleLocale(): void
    {
        $locale = Locale::normalize('EN');
        self::assertSame('en', $locale->toString());
    }

    #[Test]
    public function itNormalizeThrowsOnTooManyParts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Locale::normalize('en_US_extra');
    }

    #[Test]
    public function itLocaleToStringAndValueReturnSame(): void
    {
        $locale = Locale::fromString('fr_FR');
        self::assertSame('fr_FR', $locale->toString());
        self::assertSame('fr_FR', $locale->value());
    }

    #[Test]
    public function itLocaleEqualsIdenticalLocale(): void
    {
        $a = Locale::fromString('de');
        $b = Locale::fromString('de');
        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itLocaleNotEqualsDifferentLocale(): void
    {
        $a = Locale::fromString('de');
        $b = Locale::fromString('fr');
        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itThrowsOnInvalidLocaleFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::fromString('invalid');
    }

    #[Test]
    public function itThrowsOnEmptyLocale(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Locale::fromString('');
    }
}
