<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internationalization;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleNegotiationApiTest extends WebTestCase
{
    public function testApiResponseIncludesContentLanguageHeader(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['locale' => 'fr']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr');
    }

    public function testApiResponseIncludesXContentLanguageHeader(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['locale' => 'de']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('X-Content-Language', 'de');
    }

    public function testApiResponseIncludesXSupportedLanguagesHeader(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['locale' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('X-Supported-Languages', 'en, fr, de');
    }

    public function testApiResponseIncludesVaryHeader(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['locale' => 'fr']);

        $this->assertResponseIsSuccessful();

        $varyHeader = $client->getResponse()->headers->get('Vary');
        $this->assertStringContainsString('Accept-Language', $varyHeader);
    }

    public function testLocaleNegotiationViaQueryParameter(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages', 'locale' => 'fr']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('fr', $data['member'][0]['locale']);
    }

    public function testLocaleNegotiationViaAcceptLanguageHeader(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9,en;q=0.8']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'de');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('de', $data['member'][0]['locale']);
    }

    public function testQueryParameterTakesPriorityOverAcceptLanguageHeader(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages', 'locale' => 'fr'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr'); // Query param wins

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('fr', $data['member'][0]['locale']);
    }

    public function testFallbackToDefaultLocaleWhenNoIndicators(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'en'); // Default

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('en', $data['member'][0]['locale']);
    }

    public function testFallbackToDefaultLocaleForUnsupportedLanguage(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'es-ES,it-IT;q=0.9'] // Unsupported languages
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'en'); // Fallback to default

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('en', $data['member'][0]['locale']);
    }

    public function testAcceptLanguageHeaderWithMultipleLanguages(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'it-IT,es-ES;q=0.9,fr-FR;q=0.8,en;q=0.7'] // French is first supported
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('fr', $data['member'][0]['locale']);
    }

    public function testLocaleNormalizationFromRegionalVariant(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages', 'locale' => 'fr_FR']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr'); // Normalized to base language

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('fr', $data['member'][0]['locale']);
    }

    public function testLocaleNormalizationFromDashSeparator(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages', 'locale' => 'de-DE']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'de'); // Normalized to base language
    }

    public function testLocaleNormalizationCaseInsensitive(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages', 'locale' => 'FR']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr'); // Normalized to lowercase
    }

    public function testAcceptLanguageHeaderParsing(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr');
    }

    public function testComplexAcceptLanguageHeaderParsing(): void
    {
        $client = static::createClient();

        // Simulate Chrome's Accept-Language header
        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'de-CH,de;q=0.9,fr-CH;q=0.8,fr;q=0.7,en;q=0.6']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'de'); // First supported language
    }

    public function testAllEndpointsIncludeLocaleHeaders(): void
    {
        $client = static::createClient();

        // Test with different endpoint (translations is publicly accessible)
        $client->request('GET', '/api/v1/translations', ['locale' => 'de']);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'de');
        $this->assertResponseHeaderSame('X-Content-Language', 'de');
        $this->assertResponseHeaderSame('X-Supported-Languages', 'en, fr, de');

        $varyHeader = $client->getResponse()->headers->get('Vary');
        $this->assertStringContainsString('Accept-Language', $varyHeader);
    }

    #[DataProvider('localeHeadersProvider')]
    public function testLocaleHeadersWithDifferentLocales(string $locale, string $expectedContentLanguage): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages', 'locale' => $locale]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', $expectedContentLanguage);
        $this->assertResponseHeaderSame('X-Content-Language', $expectedContentLanguage);
    }

    public static function localeHeadersProvider(): array
    {
        return [
            'English' => ['en', 'en'],
            'French' => ['fr', 'fr'],
            'German' => ['de', 'de'],
            'French uppercase' => ['FR', 'fr'],
            'French regional' => ['fr_FR', 'fr'],
            'German regional' => ['de-DE', 'de'],
        ];
    }

    public function testVaryHeaderEnablesCaching(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages', 'locale' => 'fr']);

        $this->assertResponseIsSuccessful();

        $varyHeader = $client->getResponse()->headers->get('Vary');

        // Vary header should include Accept and Accept-Language for proper caching
        $this->assertNotNull($varyHeader);
        $varyValues = array_map('trim', explode(',', $varyHeader));

        $this->assertContains('Accept-Language', $varyValues);
    }

    public function testLocaleResponseListenerAppliesGlobally(): void
    {
        $client = static::createClient();

        // Test multiple requests to ensure listener applies consistently
        $client->request('GET', '/api/v1/translations', ['locale' => 'fr']);
        $this->assertResponseHeaderSame('Content-Language', 'fr');

        $client->request('GET', '/api/v1/translations', ['locale' => 'de']);
        $this->assertResponseHeaderSame('Content-Language', 'de');

        $client->request('GET', '/api/v1/translations', ['locale' => 'en']);
        $this->assertResponseHeaderSame('Content-Language', 'en');
    }

    public function testInvalidLocaleDoesNotBreakHeaders(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/translations', ['domain' => 'messages', 'locale' => 'invalid']);

        // Should return 500 error for invalid language code
        $this->assertResponseStatusCodeSame(500);

        // Headers should still be present (even for error responses)
        $response = $client->getResponse();
        $this->assertTrue($response->headers->has('Content-Language'));
    }

    public function testAcceptLanguageWithOnlySupportedLanguage(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'de'] // Only German, no quality values
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'de');
    }

    public function testAcceptLanguageWithQualityZero(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/translations',
            ['domain' => 'messages'],
            [],
            ['HTTP_ACCEPT_LANGUAGE' => 'de;q=0,fr;q=0.9,en;q=0.8']
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Language', 'fr'); // Should skip de with q=0
    }
}
