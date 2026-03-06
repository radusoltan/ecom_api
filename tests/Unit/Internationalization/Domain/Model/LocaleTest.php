<?php

declare(strict_types=1);

namespace App\Tests\Unit\Internationalization\Domain\Model;

use App\Internationalization\Domain\Model\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Locale::class)]
final class LocaleTest extends TestCase
{
    // -------
    // fromString() — long format
    // -------

    #[Test]
    public function itCreatesFromLongFormat(): void
    {
        $locale = Locale::fromString('en_US');

        self::assertSame('en', $locale->getLanguage());
        self::assertSame('US', $locale->getCountry());
    }

    #[Test]
    public function itCreatesRoRoFromLongFormat(): void
    {
        $locale = Locale::fromString('ro_RO');

        self::assertSame('ro', $locale->getLanguage());
        self::assertSame('RO', $locale->getCountry());
    }

    #[Test]
    public function itCreatesDeDeFromLongFormat(): void
    {
        $locale = Locale::fromString('de_DE');

        self::assertSame('de', $locale->getLanguage());
        self::assertSame('DE', $locale->getCountry());
    }

    #[Test]
    public function itCreatesFrFrFromLongFormat(): void
    {
        $locale = Locale::fromString('fr_FR');

        self::assertSame('fr', $locale->getLanguage());
        self::assertSame('FR', $locale->getCountry());
    }

    #[Test]
    public function itCreatesEsEsFromLongFormat(): void
    {
        $locale = Locale::fromString('es_ES');

        self::assertSame('es', $locale->getLanguage());
        self::assertSame('ES', $locale->getCountry());
    }

    #[Test]
    public function itCreatesItItFromLongFormat(): void
    {
        $locale = Locale::fromString('it_IT');

        self::assertSame('it', $locale->getLanguage());
        self::assertSame('IT', $locale->getCountry());
    }

    // -------
    // fromString() — short format (language only)
    // -------

    #[Test]
    public function itCreatesEnUsFromShortFormat(): void
    {
        $locale = Locale::fromString('en');

        self::assertSame('en', $locale->getLanguage());
        self::assertSame('US', $locale->getCountry());
    }

    #[Test]
    public function itCreatesRoRoFromShortFormat(): void
    {
        $locale = Locale::fromString('ro');

        self::assertSame('ro', $locale->getLanguage());
        self::assertSame('RO', $locale->getCountry());
    }

    #[Test]
    public function itCreatesDeDeFromShortFormat(): void
    {
        $locale = Locale::fromString('de');

        self::assertSame('de', $locale->getLanguage());
        self::assertSame('DE', $locale->getCountry());
    }

    #[Test]
    public function itCreatesFrFrFromShortFormat(): void
    {
        $locale = Locale::fromString('fr');

        self::assertSame('fr', $locale->getLanguage());
        self::assertSame('FR', $locale->getCountry());
    }

    #[Test]
    public function itCreatesEsEsFromShortFormat(): void
    {
        $locale = Locale::fromString('es');

        self::assertSame('es', $locale->getLanguage());
        self::assertSame('ES', $locale->getCountry());
    }

    #[Test]
    public function itCreatesItItFromShortFormat(): void
    {
        $locale = Locale::fromString('it');

        self::assertSame('it', $locale->getLanguage());
        self::assertSame('IT', $locale->getCountry());
    }

    #[Test]
    public function unknownShortFormatFallsBackToUs(): void
    {
        // Unknown language gets default country 'US'
        // but 'xx_US' is not in supported locales — so this must throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported locale');

        Locale::fromString('xx');
    }

    // -------
    // Validation — unsupported locales
    // -------

    #[Test]
    public function itThrowsForUnsupportedLongLocale(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported locale');

        Locale::fromString('en_GB');
    }

    #[Test]
    public function itThrowsForInvalidFormatWithThreeParts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale format');

        Locale::fromString('en_US_POSIX');
    }

    #[Test]
    public function itNormalisesLanguageToLowercase(): void
    {
        $locale = Locale::fromString('EN_US');

        self::assertSame('en', $locale->getLanguage());
    }

    #[Test]
    public function itNormalisesCountryToUppercase(): void
    {
        $locale = Locale::fromString('en_us');

        self::assertSame('US', $locale->getCountry());
    }

    // -------
    // toString() / __toString()
    // -------

    #[Test]
    public function toStringReturnsLanguageUnderscoreCountry(): void
    {
        $locale = Locale::fromString('en_US');

        self::assertSame('en_US', $locale->toString());
    }

    #[Test]
    public function stringCastReturnsToString(): void
    {
        $locale = Locale::fromString('fr_FR');

        self::assertSame('fr_FR', (string) $locale);
    }

    // -------
    // equals()
    // -------

    #[Test]
    public function itEqualsTheSameLocale(): void
    {
        $a = Locale::fromString('en_US');
        $b = Locale::fromString('en_US');

        self::assertTrue($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualADifferentLocale(): void
    {
        $a = Locale::fromString('en_US');
        $b = Locale::fromString('fr_FR');

        self::assertFalse($a->equals($b));
    }

    #[Test]
    public function itDoesNotEqualWhenOnlyCountryDiffers(): void
    {
        // Two locales sharing a language but different countries would both need to be
        // in SUPPORTED_LOCALES. Since only en_US is supported (not en_GB), we test via
        // equality of two supported locales with the same language.
        $a = Locale::fromString('en_US');
        $b = Locale::fromString('de_DE');

        self::assertFalse($a->equals($b));
    }

    // -------
    // supportedLocales()
    // -------

    #[Test]
    public function supportedLocalesContainsEnUs(): void
    {
        self::assertContains('en_US', Locale::supportedLocales());
    }

    #[Test]
    public function supportedLocalesContainsRoRo(): void
    {
        self::assertContains('ro_RO', Locale::supportedLocales());
    }

    #[Test]
    public function supportedLocalesContainsDeDe(): void
    {
        self::assertContains('de_DE', Locale::supportedLocales());
    }

    #[Test]
    public function supportedLocalesContainsFrFr(): void
    {
        self::assertContains('fr_FR', Locale::supportedLocales());
    }

    #[Test]
    public function supportedLocalesContainsEsEs(): void
    {
        self::assertContains('es_ES', Locale::supportedLocales());
    }

    #[Test]
    public function supportedLocalesContainsItIt(): void
    {
        self::assertContains('it_IT', Locale::supportedLocales());
    }

    #[Test]
    public function supportedLocalesReturnsSixLocales(): void
    {
        self::assertCount(6, Locale::supportedLocales());
    }

    // -------
    // DataProvider — all supported locales round-trip
    // -------

    /**
     * @return array<string, array{string}>
     */
    public static function supportedLocaleProvider(): array
    {
        return [
            'en_US' => ['en_US'],
            'ro_RO' => ['ro_RO'],
            'de_DE' => ['de_DE'],
            'fr_FR' => ['fr_FR'],
            'es_ES' => ['es_ES'],
            'it_IT' => ['it_IT'],
        ];
    }

    #[Test]
    #[DataProvider('supportedLocaleProvider')]
    public function allSupportedLocalesRoundTripThroughFromString(string $localeString): void
    {
        $locale = Locale::fromString($localeString);

        self::assertSame($localeString, $locale->toString());
    }
}
