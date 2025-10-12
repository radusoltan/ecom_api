<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Locale;

use App\Shared\Infrastructure\Locale\LocaleNegotiator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LocaleNegotiatorTest extends TestCase
{
    private LocaleNegotiator $localeNegotiator;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->localeNegotiator = new LocaleNegotiator($this->requestStack);
    }

    public function testNegotiateReturnsDefaultWhenNoRequest(): void
    {
        $locale = $this->localeNegotiator->negotiate();

        $this->assertSame('en', $locale);
    }

    public function testNegotiateReturnsDefaultWhenNoLocaleIndicators(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $locale = $this->localeNegotiator->negotiate();

        $this->assertSame('en', $locale);
    }

    public function testNegotiatePrioritizesQueryParameter(): void
    {
        $request = new Request(['locale' => 'fr']);
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('fr', $locale); // Query param wins over header
    }

    public function testNegotiateUsesAcceptLanguageHeaderWhenNoQueryParam(): void
    {
        $request = new Request();
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('de', $locale);
    }

    public function testNegotiateWithQueryParameterEn(): void
    {
        $request = new Request(['locale' => 'en']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('en', $locale);
    }

    public function testNegotiateWithQueryParameterFr(): void
    {
        $request = new Request(['locale' => 'fr']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('fr', $locale);
    }

    public function testNegotiateWithQueryParameterDe(): void
    {
        $request = new Request(['locale' => 'de']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('de', $locale);
    }

    public function testNegotiateIgnoresInvalidQueryParameter(): void
    {
        $request = new Request(['locale' => 'invalid']);
        $request->headers->set('Accept-Language', 'fr-FR');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('fr', $locale); // Falls back to Accept-Language header
    }

    public function testNegotiateWithLocaleVariant(): void
    {
        $request = new Request(['locale' => 'fr_FR']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('fr', $locale); // Normalized to base language
    }

    public function testNegotiateWithLocaleDash(): void
    {
        $request = new Request(['locale' => 'de-DE']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('de', $locale); // Normalized to base language
    }

    public function testNegotiateFallsBackToDefaultForUnsupportedLanguages(): void
    {
        $request = new Request(['locale' => 'es']);
        $request->headers->set('Accept-Language', 'it-IT,it;q=0.9');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('en', $locale);
    }

    public function testGetCurrentLocaleUsesRequestStack(): void
    {
        $request = new Request(['locale' => 'de']);
        $this->requestStack->push($request);

        $locale = $this->localeNegotiator->getCurrentLocale();

        $this->assertSame('de', $locale);
    }

    public function testGetCurrentLocaleReturnsDefaultWhenNoRequest(): void
    {
        $locale = $this->localeNegotiator->getCurrentLocale();

        $this->assertSame('en', $locale);
    }

    public function testIsValidLocaleReturnsTrueForSupported(): void
    {
        $this->assertTrue($this->localeNegotiator->isValidLocale('en'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('fr'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('de'));
    }

    public function testIsValidLocaleReturnsTrueForSupportedWithVariant(): void
    {
        $this->assertTrue($this->localeNegotiator->isValidLocale('fr_FR'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('de-DE'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('en_US'));
    }

    public function testIsValidLocaleReturnsFalseForUnsupported(): void
    {
        $this->assertFalse($this->localeNegotiator->isValidLocale('es'));
        $this->assertFalse($this->localeNegotiator->isValidLocale('it'));
        $this->assertFalse($this->localeNegotiator->isValidLocale('pt'));
    }

    public function testNormalizeLocaleReturnsBareLanguageCode(): void
    {
        $this->assertSame('fr', $this->localeNegotiator->normalizeLocale('fr'));
        $this->assertSame('fr', $this->localeNegotiator->normalizeLocale('fr_FR'));
        $this->assertSame('fr', $this->localeNegotiator->normalizeLocale('fr-FR'));
        $this->assertSame('fr', $this->localeNegotiator->normalizeLocale('fr_CA'));
    }

    public function testNormalizeLocaleReturnsDefaultForInvalid(): void
    {
        $this->assertSame('en', $this->localeNegotiator->normalizeLocale('invalid'));
        $this->assertSame('en', $this->localeNegotiator->normalizeLocale('es_ES'));
    }

    public function testGetSupportedLocales(): void
    {
        $locales = $this->localeNegotiator->getSupportedLocales();

        $this->assertSame(['en', 'fr', 'de'], $locales);
    }

    public function testGetDefaultLocale(): void
    {
        $locale = $this->localeNegotiator->getDefaultLocale();

        $this->assertSame('en', $locale);
    }

    public function testGetLocaleMetadataReturnsCorrectStructure(): void
    {
        $request = new Request(['locale' => 'fr']);
        $this->requestStack->push($request);

        $metadata = $this->localeNegotiator->getLocaleMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('current', $metadata);
        $this->assertArrayHasKey('default', $metadata);
        $this->assertArrayHasKey('supported', $metadata);
        $this->assertSame('fr', $metadata['current']);
        $this->assertSame('en', $metadata['default']);
        $this->assertSame(['en', 'fr', 'de'], $metadata['supported']);
    }

    public function testCreateCacheKeyWithCurrentLocale(): void
    {
        $request = new Request(['locale' => 'de']);
        $this->requestStack->push($request);

        $cacheKey = $this->localeNegotiator->createCacheKey('products');

        $this->assertSame('products_de', $cacheKey);
    }

    public function testCreateCacheKeyWithSpecificLocale(): void
    {
        $cacheKey = $this->localeNegotiator->createCacheKey('categories', 'fr');

        $this->assertSame('categories_fr', $cacheKey);
    }

    public function testCreateCacheKeyUsesDefaultWhenNoRequest(): void
    {
        $cacheKey = $this->localeNegotiator->createCacheKey('cart');

        $this->assertSame('cart_en', $cacheKey);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('acceptLanguageHeaderProvider')]
    public function testNegotiateWithVariousAcceptLanguageHeaders(string $header, string $expectedLocale): void
    {
        $request = new Request();
        $request->headers->set('Accept-Language', $header);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame($expectedLocale, $locale);
    }

    public static function acceptLanguageHeaderProvider(): array
    {
        return [
            'Simple French' => ['fr', 'fr'],
            'Simple German' => ['de', 'de'],
            'Simple English' => ['en', 'en'],
            'French with region' => ['fr-FR', 'fr'],
            'German with region' => ['de-DE', 'de'],
            'English with region' => ['en-US', 'en'],
            'French priority' => ['fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7', 'fr'],
            'German priority' => ['de-DE,de;q=0.9,en;q=0.8', 'de'],
            'Mixed with French first' => ['fr,de;q=0.9,en;q=0.8', 'fr'],
            'Mixed with German first' => ['de,fr;q=0.9,en;q=0.8', 'de'],
            'Unsupported falls to default' => ['es-ES,it-IT', 'en'],
            'Mixed unsupported then German' => ['es,it;q=0.9,de;q=0.8', 'de'],
            'Chrome-like French' => ['fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7', 'fr'],
            'Firefox-like German' => ['de-DE,de;q=0.8,en-US;q=0.5,en;q=0.3', 'de'],
        ];
    }

    public function testNegotiateWithProvidedRequest(): void
    {
        // Request in stack
        $stackRequest = new Request(['locale' => 'fr']);
        $this->requestStack->push($stackRequest);

        // Different request provided
        $providedRequest = new Request(['locale' => 'de']);

        $locale = $this->localeNegotiator->negotiate($providedRequest);

        $this->assertSame('de', $locale); // Should use provided request
    }

    public function testNegotiateWithNullUsesRequestStack(): void
    {
        $request = new Request(['locale' => 'fr']);
        $this->requestStack->push($request);

        $locale = $this->localeNegotiator->negotiate(null);

        $this->assertSame('fr', $locale);
    }
}
