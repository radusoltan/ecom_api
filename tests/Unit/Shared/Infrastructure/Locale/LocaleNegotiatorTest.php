<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Locale;

use App\Shared\Application\Service\TenantLocaleProviderInterface;
use App\Shared\Application\Service\UserLocaleProviderInterface;
use App\Shared\Infrastructure\Locale\LocaleNegotiator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LocaleNegotiatorTest extends TestCase
{
    private LocaleNegotiator $localeNegotiator;
    private RequestStack $requestStack;
    private UserLocaleProviderInterface&MockObject $userLocaleProvider;
    private TenantLocaleProviderInterface&MockObject $tenantLocaleProvider;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->userLocaleProvider = $this->createMock(UserLocaleProviderInterface::class);
        $this->tenantLocaleProvider = $this->createMock(TenantLocaleProviderInterface::class);

        $this->localeNegotiator = new LocaleNegotiator(
            $this->requestStack,
            $this->userLocaleProvider,
            $this->tenantLocaleProvider,
        );
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

        $this->userLocaleProvider->method('getPreferredLocale')->willReturn(null);
        $this->tenantLocaleProvider->method('getDefaultLocale')->willReturn(null);

        $locale = $this->localeNegotiator->negotiate();

        $this->assertSame('en', $locale);
    }

    // Step 1: Query parameter takes highest priority
    public function testNegotiatePrioritizesQueryParameter(): void
    {
        $request = new Request(['locale' => 'fr']);
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('fr', $locale);
    }

    // Step 2: Accept-Language header
    public function testNegotiateUsesAcceptLanguageHeaderWhenNoQueryParam(): void
    {
        $request = new Request();
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('de', $locale);
    }

    // Step 3: User preferred locale
    public function testNegotiateUsesUserPreferredLocaleWhenNoHeader(): void
    {
        $request = new Request();
        $this->requestStack->push($request);

        $this->userLocaleProvider->method('getPreferredLocale')->willReturn('ro');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('ro', $locale);
    }

    // Step 4: Tenant default locale
    public function testNegotiateUsesTenantDefaultLocaleWhenNoUserPref(): void
    {
        $request = new Request();

        $this->userLocaleProvider->method('getPreferredLocale')->willReturn(null);
        $this->tenantLocaleProvider->method('getDefaultLocale')->willReturn('de');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('de', $locale);
    }

    // Step 5: Fallback to en
    public function testNegotiateFallsBackToEnWhenNothingElse(): void
    {
        $request = new Request();

        $this->userLocaleProvider->method('getPreferredLocale')->willReturn(null);
        $this->tenantLocaleProvider->method('getDefaultLocale')->willReturn(null);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('en', $locale);
    }

    // Priority: query param > Accept-Language > user pref
    public function testQueryParameterOverridesAll(): void
    {
        $request = new Request(['locale' => 'it']);
        $request->headers->set('Accept-Language', 'de-DE');

        $this->userLocaleProvider->method('getPreferredLocale')->willReturn('ro');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('it', $locale);
    }

    public function testNegotiateIgnoresInvalidQueryParameter(): void
    {
        $request = new Request(['locale' => 'invalid']);
        $request->headers->set('Accept-Language', 'fr-FR');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('fr', $locale);
    }

    public function testNegotiateWithLocaleVariant(): void
    {
        $request = new Request(['locale' => 'fr_FR']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('fr', $locale);
    }

    public function testNegotiateWithLocaleDash(): void
    {
        $request = new Request(['locale' => 'de-DE']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('de', $locale);
    }

    public function testNegotiateWithNewLocaleRo(): void
    {
        $request = new Request(['locale' => 'ro']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('ro', $locale);
    }

    public function testNegotiateWithNewLocaleEs(): void
    {
        $request = new Request(['locale' => 'es']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('es', $locale);
    }

    public function testNegotiateWithNewLocaleIt(): void
    {
        $request = new Request(['locale' => 'it']);

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('it', $locale);
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

    public function testIsValidLocaleReturnsTrueForAllSupported(): void
    {
        $this->assertTrue($this->localeNegotiator->isValidLocale('en'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('fr'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('de'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('ro'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('es'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('it'));
    }

    public function testIsValidLocaleReturnsTrueForSupportedWithVariant(): void
    {
        $this->assertTrue($this->localeNegotiator->isValidLocale('fr_FR'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('de-DE'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('en_US'));
        $this->assertTrue($this->localeNegotiator->isValidLocale('ro_RO'));
    }

    public function testIsValidLocaleReturnsFalseForUnsupported(): void
    {
        $this->assertFalse($this->localeNegotiator->isValidLocale('pt'));
        $this->assertFalse($this->localeNegotiator->isValidLocale('zh'));
        $this->assertFalse($this->localeNegotiator->isValidLocale('ja'));
    }

    public function testNormalizeLocaleReturnsBareLanguageCode(): void
    {
        $this->assertSame('fr', $this->localeNegotiator->normalizeLocale('fr'));
        $this->assertSame('fr', $this->localeNegotiator->normalizeLocale('fr_FR'));
        $this->assertSame('fr', $this->localeNegotiator->normalizeLocale('fr-FR'));
        $this->assertSame('ro', $this->localeNegotiator->normalizeLocale('ro_RO'));
    }

    public function testNormalizeLocaleReturnsDefaultForInvalid(): void
    {
        $this->assertSame('en', $this->localeNegotiator->normalizeLocale('invalid'));
        $this->assertSame('en', $this->localeNegotiator->normalizeLocale('pt_BR'));
    }

    public function testGetSupportedLocales(): void
    {
        $locales = $this->localeNegotiator->getSupportedLocales();

        $this->assertSame(['en', 'fr', 'de', 'ro', 'es', 'it'], $locales);
    }

    public function testGetDefaultLocale(): void
    {
        $this->assertSame('en', $this->localeNegotiator->getDefaultLocale());
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
        $this->assertSame(['en', 'fr', 'de', 'ro', 'es', 'it'], $metadata['supported']);
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
            'Simple Romanian' => ['ro', 'ro'],
            'French with region' => ['fr-FR', 'fr'],
            'German with region' => ['de-DE', 'de'],
            'English with region' => ['en-US', 'en'],
            'French priority' => ['fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7', 'fr'],
            'German priority' => ['de-DE,de;q=0.9,en;q=0.8', 'de'],
            'Mixed with French first' => ['fr,de;q=0.9,en;q=0.8', 'fr'],
            'Mixed with German first' => ['de,fr;q=0.9,en;q=0.8', 'de'],
            'Unsupported falls to default' => ['zh-CN,ja-JP', 'en'],
            'Mixed unsupported then German' => ['zh,ja;q=0.9,de;q=0.8', 'de'],
        ];
    }

    public function testNegotiateWithProvidedRequest(): void
    {
        $stackRequest = new Request(['locale' => 'fr']);
        $this->requestStack->push($stackRequest);

        $providedRequest = new Request(['locale' => 'de']);

        $locale = $this->localeNegotiator->negotiate($providedRequest);

        $this->assertSame('de', $locale);
    }

    public function testNegotiateWithNullUsesRequestStack(): void
    {
        $request = new Request(['locale' => 'fr']);
        $this->requestStack->push($request);

        $locale = $this->localeNegotiator->negotiate(null);

        $this->assertSame('fr', $locale);
    }

    public function testUserPreferredLocaleNullDoesNotBlock(): void
    {
        $request = new Request();

        $this->userLocaleProvider->method('getPreferredLocale')->willReturn(null);
        $this->tenantLocaleProvider->method('getDefaultLocale')->willReturn('es');

        $locale = $this->localeNegotiator->negotiate($request);

        $this->assertSame('es', $locale);
    }
}
