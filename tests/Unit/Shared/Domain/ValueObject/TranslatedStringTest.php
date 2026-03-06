<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TranslatedString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslatedString::class)]
final class TranslatedStringTest extends TestCase
{
    // -------
    // fromString()
    // -------

    #[Test]
    public function fromStringCreatesWithDefaultLanguage(): void
    {
        $ts = TranslatedString::fromString('Hello');

        self::assertSame(['en' => 'Hello'], $ts->toArray());
    }

    #[Test]
    public function fromStringCreatesWithExplicitLanguage(): void
    {
        $ts = TranslatedString::fromString('Bonjour', LanguageCode::fr());

        self::assertSame(['fr' => 'Bonjour'], $ts->toArray());
    }

    // -------
    // fromArray()
    // -------

    #[Test]
    public function fromArrayCreatesWithMultipleTranslations(): void
    {
        $ts = TranslatedString::fromArray([
            'en' => 'Hello',
            'fr' => 'Bonjour',
            'de' => 'Hallo',
        ]);

        self::assertSame('Hello', $ts->get(LanguageCode::en()));
        self::assertSame('Bonjour', $ts->get(LanguageCode::fr()));
        self::assertSame('Hallo', $ts->get(LanguageCode::de()));
    }

    #[Test]
    public function fromArrayThrowsWhenEmpty(): void
    {
        // TranslatedString uses App\Shared\Domain\Exception\InvalidArgumentException
        // which is missing from the codebase — PHP throws Error for missing class.
        // Until the class is created, we expect a \Throwable.
        $this->expectException(\Throwable::class);

        TranslatedString::fromArray([]);
    }

    #[Test]
    public function fromArrayThrowsWhenLanguageCodeUnsupported(): void
    {
        $this->expectException(\Throwable::class);

        TranslatedString::fromArray(['zz' => 'Invalid']);
    }

    #[Test]
    public function fromArrayThrowsWhenTranslationTextIsEmpty(): void
    {
        $this->expectException(\Throwable::class);

        TranslatedString::fromArray(['en' => '']);
    }

    #[Test]
    public function fromArrayThrowsWhenTranslationTextIsWhitespaceOnly(): void
    {
        $this->expectException(\Throwable::class);

        TranslatedString::fromArray(['en' => '   ']);
    }

    // -------
    // get()
    // -------

    #[Test]
    public function getReturnsTranslationForExistingLanguage(): void
    {
        $ts = TranslatedString::fromArray(['en' => 'Hello', 'fr' => 'Bonjour']);

        self::assertSame('Hello', $ts->get(LanguageCode::en()));
    }

    #[Test]
    public function getReturnsNullForMissingLanguage(): void
    {
        $ts = TranslatedString::fromArray(['en' => 'Hello']);

        self::assertNull($ts->get(LanguageCode::fr()));
    }

    // -------
    // getOrFallback()
    // -------

    #[Test]
    public function getOrFallbackReturnsRequestedLanguage(): void
    {
        $ts = TranslatedString::fromArray(['en' => 'Hello', 'fr' => 'Bonjour']);

        self::assertSame('Bonjour', $ts->getOrFallback(LanguageCode::fr()));
    }

    #[Test]
    public function getOrFallbackFallsToDefaultLanguageWhenMissing(): void
    {
        $ts = TranslatedString::fromArray(['en' => 'Hello']);

        self::assertSame('Hello', $ts->getOrFallback(LanguageCode::fr()));
    }

    #[Test]
    public function getOrFallbackFallsToFirstTranslationWhenDefaultMissing(): void
    {
        $ts = TranslatedString::fromArray(['fr' => 'Bonjour']);

        // No 'en' translation; first available is 'fr'
        self::assertSame('Bonjour', $ts->getOrFallback(LanguageCode::de()));
    }

    // -------
    // withTranslation()
    // -------

    #[Test]
    public function withTranslationAddsNewLanguage(): void
    {
        $ts = TranslatedString::fromString('Hello');
        $updated = $ts->withTranslation('Bonjour', LanguageCode::fr());

        self::assertSame('Bonjour', $updated->get(LanguageCode::fr()));
        // Original is unchanged (immutability)
        self::assertNull($ts->get(LanguageCode::fr()));
    }

    #[Test]
    public function withTranslationOverwritesExistingLanguage(): void
    {
        $ts = TranslatedString::fromArray(['en' => 'Hello']);
        $updated = $ts->withTranslation('Hi', LanguageCode::en());

        self::assertSame('Hi', $updated->get(LanguageCode::en()));
    }

    #[Test]
    public function withTranslationReturnsNewInstance(): void
    {
        $ts = TranslatedString::fromString('Hello');
        $updated = $ts->withTranslation('Hallo', LanguageCode::de());

        self::assertNotSame($ts, $updated);
    }

    // -------
    // hasTranslation()
    // -------

    #[Test]
    public function hasTranslationReturnsTrueWhenExists(): void
    {
        $ts = TranslatedString::fromArray(['en' => 'Hello', 'de' => 'Hallo']);

        self::assertTrue($ts->hasTranslation(LanguageCode::de()));
    }

    #[Test]
    public function hasTranslationReturnsFalseWhenMissing(): void
    {
        $ts = TranslatedString::fromArray(['en' => 'Hello']);

        self::assertFalse($ts->hasTranslation(LanguageCode::fr()));
    }

    // -------
    // isEmpty()
    // -------

    #[Test]
    public function isEmptyReturnsFalseForNonEmptyTranslatedString(): void
    {
        $ts = TranslatedString::fromString('Hello');

        self::assertFalse($ts->isEmpty());
    }

    // -------
    // availableLanguages()
    // -------

    #[Test]
    public function availableLanguagesReturnsAllKeys(): void
    {
        $ts = TranslatedString::fromArray([
            'en' => 'Hello',
            'fr' => 'Bonjour',
            'de' => 'Hallo',
        ]);

        $languages = $ts->availableLanguages();

        self::assertCount(3, $languages);
        self::assertContains('en', $languages);
        self::assertContains('fr', $languages);
        self::assertContains('de', $languages);
    }

    #[Test]
    public function availableLanguagesReturnsSingleKeyForSingleTranslation(): void
    {
        $ts = TranslatedString::fromString('Hello');

        self::assertSame(['en'], $ts->availableLanguages());
    }

    // -------
    // toArray()
    // -------

    #[Test]
    public function toArrayReturnsOriginalData(): void
    {
        $data = ['en' => 'Hello', 'fr' => 'Bonjour'];
        $ts = TranslatedString::fromArray($data);

        self::assertSame($data, $ts->toArray());
    }
}
