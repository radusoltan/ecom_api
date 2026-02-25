<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Locale Headers Functional Tests.
 *
 * Tests EPIC 3.2 - i18n Headers Compliance:
 * - X-Content-Language header present
 * - X-Fallback-Language header present
 * - X-Supported-Languages header present
 * - Content-Language standard header present
 * - Vary: Accept-Language header for proper caching
 *
 * @group sprint3
 * @group i18n-headers
 */
final class LocaleHeadersTest extends WebTestCase
{
    public function testApiResponseIncludesLocaleHeaders(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();

        // X-Content-Language header
        $this->assertTrue($response->headers->has('X-Content-Language'));
        $this->assertNotEmpty($response->headers->get('X-Content-Language'));

        // X-Fallback-Language header
        $this->assertTrue($response->headers->has('X-Fallback-Language'));
        $this->assertEquals('en', $response->headers->get('X-Fallback-Language'));

        // X-Supported-Languages header
        $this->assertTrue($response->headers->has('X-Supported-Languages'));
        $supportedLanguages = $response->headers->get('X-Supported-Languages');
        $this->assertStringContainsString('en', $supportedLanguages);

        // Standard Content-Language header
        $this->assertTrue($response->headers->has('Content-Language'));
        $this->assertNotEmpty($response->headers->get('Content-Language'));
    }

    public function testVaryHeaderIncludesAcceptLanguage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();

        $this->assertTrue($response->headers->has('Vary'));
        $varyHeader = $response->headers->get('Vary');
        $this->assertStringContainsString('Accept-Language', $varyHeader);
    }

    public function testLocaleNegotiationViaAcceptLanguage(): void
    {
        $client = static::createClient();

        // Request with Accept-Language: fr
        $client->request('GET', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();

        $contentLanguage = $response->headers->get('X-Content-Language');
        $this->assertEquals('fr', $contentLanguage, 'Should negotiate to French');
    }

    public function testLocaleNegotiationViaQueryParameter(): void
    {
        $client = static::createClient();

        // Request with ?locale=de parameter
        $client->request('GET', '/api/v1/orders?locale=de', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en', // Should be overridden by query param
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();

        $contentLanguage = $response->headers->get('X-Content-Language');
        $this->assertEquals('de', $contentLanguage, 'Query parameter should override Accept-Language');
    }

    public function testFallbackToDefaultLocale(): void
    {
        $client = static::createClient();

        // Request with unsupported locale
        $client->request('GET', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'xx-INVALID',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();

        $contentLanguage = $response->headers->get('X-Content-Language');
        $fallbackLanguage = $response->headers->get('X-Fallback-Language');

        $this->assertEquals($fallbackLanguage, $contentLanguage, 'Should fallback to default locale');
        $this->assertEquals('en', $contentLanguage);
    }

    public function testSupportedLanguagesListIsCorrect(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();

        $supportedLanguages = $response->headers->get('X-Supported-Languages');
        $languagesArray = array_map('trim', explode(',', $supportedLanguages));

        // Should contain at least EN, FR, DE
        $this->assertContains('en', $languagesArray);
        $this->assertContains('fr', $languagesArray);
        $this->assertContains('de', $languagesArray);
    }

    public function testNonApiRoutesDoNotHaveLocaleHeaders(): void
    {
        $client = static::createClient();

        // Request a non-API path. In this project '/' has no route and
        // returns 404, which is the expected behaviour for a pure API app.
        // Regardless of the HTTP status code the locale middleware must NOT
        // add X-Content-Language (or related headers) to non-API responses.
        $client->request('GET', '/');

        $response = $client->getResponse();

        $this->assertFalse(
            $response->headers->has('X-Content-Language'),
            'Non-API routes should not have X-Content-Language header'
        );
    }
}
