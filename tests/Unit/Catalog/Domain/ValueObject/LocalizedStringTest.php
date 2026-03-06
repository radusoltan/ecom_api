<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\Locale;
use App\Catalog\Domain\ValueObject\LocalizedString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalizedString::class)]
final class LocalizedStringTest extends TestCase
{
    #[Test]
    public function itCreatesFromArray(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Hello', 'fr' => 'Bonjour']);

        self::assertSame(['en' => 'Hello', 'fr' => 'Bonjour'], $ls->toArray());
        self::assertSame(2, $ls->count());
        self::assertFalse($ls->isEmpty());
    }

    #[Test]
    public function itCreatesEmpty(): void
    {
        $ls = LocalizedString::empty();

        self::assertTrue($ls->isEmpty());
        self::assertSame(0, $ls->count());
    }

    #[Test]
    public function itCreatesFromSingleTranslation(): void
    {
        $ls = LocalizedString::fromSingleTranslation('en', 'Hello');

        self::assertSame('Hello', $ls->getTranslation(Locale::fromString('en')));
        self::assertSame(1, $ls->count());
    }

    #[Test]
    public function itGetsTranslation(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color', 'de' => 'Farbe']);

        self::assertSame('Color', $ls->getTranslation(Locale::fromString('en')));
        self::assertSame('Farbe', $ls->getTranslation(Locale::fromString('de')));
        self::assertNull($ls->getTranslation(Locale::fromString('fr')));
    }

    #[Test]
    public function itGetsTranslationWithFallback(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color', 'de' => 'Farbe']);

        $result = $ls->getTranslationWithFallback([
            Locale::fromString('fr'),
            Locale::fromString('de'),
            Locale::fromString('en'),
        ]);

        self::assertSame('Farbe', $result);
    }

    #[Test]
    public function itFallsBackToFirstAvailable(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color']);

        $result = $ls->getTranslationWithFallback([Locale::fromString('fr')]);

        self::assertSame('Color', $result);
    }

    #[Test]
    public function itReturnsNullWhenNoFallbackAndEmpty(): void
    {
        $ls = LocalizedString::empty();

        $result = $ls->getTranslationWithFallback([Locale::fromString('en')]);

        self::assertNull($result);
    }

    #[Test]
    public function itAddsTranslationImmutably(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color']);

        $updated = $ls->withTranslation(Locale::fromString('fr'), 'Couleur');

        self::assertSame(1, $ls->count()); // original unchanged
        self::assertSame(2, $updated->count());
        self::assertSame('Couleur', $updated->getTranslation(Locale::fromString('fr')));
    }

    #[Test]
    public function itRemovesTranslationImmutably(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color', 'fr' => 'Couleur']);

        $updated = $ls->withoutTranslation(Locale::fromString('fr'));

        self::assertSame(2, $ls->count()); // original unchanged
        self::assertSame(1, $updated->count());
        self::assertNull($updated->getTranslation(Locale::fromString('fr')));
    }

    #[Test]
    public function itMerges(): void
    {
        $a = LocalizedString::fromArray(['en' => 'Color', 'de' => 'Farbe']);
        $b = LocalizedString::fromArray(['en' => 'Colour', 'fr' => 'Couleur']);

        $merged = $a->merge($b);

        self::assertSame('Colour', $merged->getTranslation(Locale::fromString('en'))); // b takes precedence
        self::assertSame('Farbe', $merged->getTranslation(Locale::fromString('de')));
        self::assertSame('Couleur', $merged->getTranslation(Locale::fromString('fr')));
    }

    #[Test]
    public function itChecksHasTranslation(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color']);

        self::assertTrue($ls->hasTranslation(Locale::fromString('en')));
        self::assertFalse($ls->hasTranslation(Locale::fromString('fr')));
    }

    #[Test]
    public function itGetsAvailableLocales(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color', 'de' => 'Farbe']);

        $locales = $ls->getAvailableLocales();

        self::assertCount(2, $locales);
    }

    #[Test]
    public function itChecksEquality(): void
    {
        $a = LocalizedString::fromArray(['en' => 'Color']);
        $b = LocalizedString::fromArray(['en' => 'Color']);
        $c = LocalizedString::fromArray(['en' => 'Colour']);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    #[Test]
    public function itSerializesToJson(): void
    {
        $ls = LocalizedString::fromArray(['en' => 'Color']);

        $json = $ls->toJson();

        self::assertSame('{"en":"Color"}', $json);
    }

    #[Test]
    public function itDeserializesFromJson(): void
    {
        $ls = LocalizedString::fromJson('{"en":"Color","fr":"Couleur"}');

        self::assertSame(2, $ls->count());
        self::assertSame('Color', $ls->getTranslation(Locale::fromString('en')));
    }

    #[Test]
    public function itReturnsEmptyForInvalidJson(): void
    {
        $ls = LocalizedString::fromJson('not json');

        self::assertTrue($ls->isEmpty());
    }

    #[Test]
    public function itThrowsOnInvalidLocale(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid locale');
        LocalizedString::fromArray(['invalid_locale_format_here' => 'Value']);
    }
}
