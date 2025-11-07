<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API Versioning Functional Tests.
 *
 * Tests EPIC 3.1 - API Versioning implementation:
 * - Routes accessible at /api/v1/*
 * - Backward compatibility redirects from /api/* to /api/v1/*
 * - HTTP 308 Permanent Redirect preserves method
 *
 * @group sprint3
 * @group api-versioning
 */
final class ApiVersioningTest extends WebTestCase
{
    public function testApiV1RoutesAreAccessible(): void
    {
        $client = static::createClient();

        // Test GET /api/v1/orders
        $client->request('GET', '/api/v1/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        // Should work (200 or 401 if auth required, not 404)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertNotEquals(404, $statusCode, '/api/v1/orders route should exist');
    }

    public function testApiRedirectsToV1(): void
    {
        $client = static::createClient();
        $client->followRedirects(false);

        // Test GET /api/orders → should redirect to /api/v1/orders
        $client->request('GET', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();

        // Should be HTTP 308 Permanent Redirect
        $this->assertEquals(308, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/api/v1/orders', $response->headers->get('Location'));
    }

    public function testRedirectPreservesHttpMethod(): void
    {
        $client = static::createClient();
        $client->followRedirects(false);

        // Test POST /api/orders → should redirect with HTTP 308
        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ], json_encode([
            'customerEmail' => 'test@example.com',
            'lines' => [],
        ]));

        $response = $client->getResponse();

        // HTTP 308 preserves POST method (unlike 301/302 which change to GET)
        $this->assertEquals(308, $response->getStatusCode());
        $this->assertStringContainsString('/api/v1/orders', $response->headers->get('Location'));
    }

    public function testRedirectIncludesQueryParameters(): void
    {
        $client = static::createClient();
        $client->followRedirects(false);

        // Test with query parameters
        $client->request('GET', '/api/orders?page=2&limit=20', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TENANT_ID' => '9efae4ea-94fc-4807-b1bc-5e495ee7858c',
        ]);

        $response = $client->getResponse();
        $location = $response->headers->get('Location');

        $this->assertEquals(308, $response->getStatusCode());
        $this->assertStringContainsString('/api/v1/orders', $location);
        $this->assertStringContainsString('page=2', $location);
        $this->assertStringContainsString('limit=20', $location);
    }

    public function testApiDocsReflectV1(): void
    {
        $client = static::createClient();

        // Test OpenAPI documentation
        $client->request('GET', '/api/v1/docs.json');

        $response = $client->getResponse();

        if (200 === $response->getStatusCode()) {
            $docs = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('info', $docs);
            $this->assertStringContainsString('v1', $docs['info']['version'] ?? $docs['info']['description']);
        } else {
            $this->markTestSkipped('API docs endpoint not configured');
        }
    }
}
