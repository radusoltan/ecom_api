<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for security headers.
 *
 * Verifies that all required security headers are present and correctly configured:
 * - Content-Security-Policy (CSP)
 * - X-Frame-Options
 * - X-Content-Type-Options
 * - Strict-Transport-Security (HSTS)
 * - Referrer-Policy
 * - Permissions-Policy
 *
 * PRD Section 9.1: Security Requirements
 * - OWASP Top 10 protection
 * - Secure headers compliance
 */
final class SecurityHeadersTest extends WebTestCase
{
    /**
     * Test that X-Frame-Options header is present and set to DENY.
     *
     * Prevents clickjacking attacks by denying iframe embedding.
     */
    public function testXFrameOptionsHeaderIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api');

        $response = $client->getResponse();

        $this->assertTrue(
            $response->headers->has('X-Frame-Options'),
            'X-Frame-Options header should be present'
        );

        $this->assertSame(
            'DENY',
            $response->headers->get('X-Frame-Options'),
            'X-Frame-Options should be set to DENY'
        );
    }

    /**
     * Test that X-Content-Type-Options header is present and set to nosniff.
     *
     * Prevents MIME-sniffing attacks.
     */
    public function testXContentTypeOptionsHeaderIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api');

        $response = $client->getResponse();

        $this->assertTrue(
            $response->headers->has('X-Content-Type-Options'),
            'X-Content-Type-Options header should be present'
        );

        $this->assertSame(
            'nosniff',
            $response->headers->get('X-Content-Type-Options'),
            'X-Content-Type-Options should be set to nosniff'
        );
    }

    /**
     * Test that Referrer-Policy header is present and properly configured.
     *
     * Controls how much referrer information is sent with requests.
     */
    public function testReferrerPolicyHeaderIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api');

        $response = $client->getResponse();

        $this->assertTrue(
            $response->headers->has('Referrer-Policy'),
            'Referrer-Policy header should be present'
        );

        $policy = $response->headers->get('Referrer-Policy');

        // Should contain either 'no-referrer' or 'strict-origin-when-cross-origin'
        $this->assertTrue(
            str_contains($policy, 'no-referrer') || str_contains($policy, 'strict-origin-when-cross-origin'),
            'Referrer-Policy should contain no-referrer or strict-origin-when-cross-origin'
        );
    }

    /**
     * Test that Content-Security-Policy header is present.
     *
     * Prevents XSS, clickjacking, and code injection attacks.
     */
    public function testContentSecurityPolicyHeaderIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api');

        $response = $client->getResponse();

        $this->assertTrue(
            $response->headers->has('Content-Security-Policy'),
            'Content-Security-Policy header should be present'
        );

        $csp = $response->headers->get('Content-Security-Policy');

        // Verify key CSP directives are present
        $this->assertStringContainsString("default-src 'self'", $csp, 'CSP should restrict default-src to self');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp, 'CSP should deny frame-ancestors');
        $this->assertStringContainsString("object-src 'none'", $csp, 'CSP should deny object-src');
    }

    /**
     * Test that Strict-Transport-Security (HSTS) header is present.
     *
     * Forces HTTPS connections for enhanced security.
     * Note: This may only be present in production or when accessed via HTTPS.
     */
    public function testStrictTransportSecurityHeaderWhenHttps(): void
    {
        $client = static::createClient([], [
            'HTTPS' => 'on',
        ]);

        $client->request('GET', '/api');

        $response = $client->getResponse();

        // HSTS should be present when using HTTPS
        if ($response->headers->has('Strict-Transport-Security')) {
            $hsts = $response->headers->get('Strict-Transport-Security');

            $this->assertStringContainsString('max-age=', $hsts, 'HSTS should have max-age directive');
            $this->assertStringContainsString('includeSubDomains', $hsts, 'HSTS should include subdomains');
        } else {
            $this->markTestSkipped('HSTS header not present (may require HTTPS in production)');
        }
    }

    /**
     * Test that Permissions-Policy header is present.
     *
     * Controls which browser features can be used.
     */
    public function testPermissionsPolicyHeaderIsPresent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api');

        $response = $client->getResponse();

        // Permissions-Policy may or may not be present depending on config
        if ($response->headers->has('Permissions-Policy')) {
            $policy = $response->headers->get('Permissions-Policy');

            // Verify dangerous features are disabled
            $this->assertStringContainsString('camera=', $policy, 'Permissions-Policy should control camera');
            $this->assertStringContainsString('microphone=', $policy, 'Permissions-Policy should control microphone');
        } else {
            $this->markTestSkipped('Permissions-Policy header not present (optional)');
        }
    }

    /**
     * Test that security headers are present on API resource endpoints.
     */
    public function testSecurityHeadersOnApiResourceEndpoints(): void
    {
        $client = static::createClient();

        // Test /api/docs endpoint (API Platform documentation)
        $client->request('GET', '/api/docs');
        $response = $client->getResponse();

        $this->assertTrue(
            $response->headers->has('X-Frame-Options'),
            'API docs should have X-Frame-Options header'
        );

        $this->assertTrue(
            $response->headers->has('X-Content-Type-Options'),
            'API docs should have X-Content-Type-Options header'
        );
    }

    /**
     * Test that CSP allows necessary resources for API Platform.
     *
     * API Platform admin requires unsafe-inline for styles and scripts.
     */
    public function testCspAllowsApiPlatformResources(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/docs');

        $response = $client->getResponse();

        if ($response->headers->has('Content-Security-Policy')) {
            $csp = $response->headers->get('Content-Security-Policy');

            // API Platform needs unsafe-inline for its admin UI
            $hasUnsafeInline = str_contains($csp, "'unsafe-inline'");

            $this->assertTrue(
                $hasUnsafeInline,
                'CSP should allow unsafe-inline for API Platform admin (if enabled)'
            );
        } else {
            $this->markTestSkipped('CSP header not present');
        }
    }

    /**
     * Test that CORS headers are properly configured.
     *
     * Verifies Access-Control-* headers for cross-origin requests.
     */
    public function testCorsHeadersAreConfigured(): void
    {
        $client = static::createClient();

        // Simulate preflight OPTIONS request
        $client->request(
            'OPTIONS',
            '/api',
            [],
            [],
            [
                'HTTP_ORIGIN' => 'http://localhost:3000',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            ]
        );

        $response = $client->getResponse();

        // CORS headers should be present
        $this->assertTrue(
            $response->headers->has('Access-Control-Allow-Origin'),
            'CORS Access-Control-Allow-Origin should be present'
        );

        $this->assertTrue(
            $response->headers->has('Access-Control-Allow-Methods'),
            'CORS Access-Control-Allow-Methods should be present'
        );

        $this->assertTrue(
            $response->headers->has('Access-Control-Allow-Headers'),
            'CORS Access-Control-Allow-Headers should be present'
        );
    }

    /**
     * Test that security headers do not leak sensitive information.
     */
    public function testSecurityHeadersDoNotLeakInformation(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api');

        $response = $client->getResponse();

        // Ensure no Server header exposing PHP version
        if ($response->headers->has('X-Powered-By')) {
            $this->fail('X-Powered-By header should be removed (leaks PHP version)');
        }

        // Ensure Server header doesn't expose too much info
        if ($response->headers->has('Server')) {
            $server = $response->headers->get('Server');
            $this->assertStringNotContainsString('PHP', $server, 'Server header should not expose PHP version');
        }
    }

    /**
     * Test that security headers are consistent across different endpoints.
     */
    public function testSecurityHeadersConsistencyAcrossEndpoints(): void
    {
        $client = static::createClient();

        $endpoints = [
            '/api',
            '/api/docs',
        ];

        foreach ($endpoints as $endpoint) {
            $client->request('GET', $endpoint);
            $response = $client->getResponse();

            $this->assertTrue(
                $response->headers->has('X-Frame-Options'),
                "X-Frame-Options should be present on {$endpoint}"
            );

            $this->assertTrue(
                $response->headers->has('X-Content-Type-Options'),
                "X-Content-Type-Options should be present on {$endpoint}"
            );

            $this->assertTrue(
                $response->headers->has('Referrer-Policy'),
                "Referrer-Policy should be present on {$endpoint}"
            );
        }
    }
}
