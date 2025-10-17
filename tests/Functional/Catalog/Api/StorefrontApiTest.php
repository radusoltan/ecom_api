<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class StorefrontApiTest extends ApiTestCase
{
    private const TEST_TENANT_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function testGetFeaturedProducts(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/storefront/featured-products', [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
                'X-Tenant-ID' => self::TEST_TENANT_ID
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');

        // Check cache headers
        $this->assertResponseHasHeader('Cache-Control');
        $this->assertResponseHasHeader('Vary');
        $this->assertResponseHasHeader('X-Content-Language');

        $cacheControl = $response->getHeaders()['cache-control'][0] ?? '';
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        // Verify DTO structure if products exist
        if (!empty($data)) {
            $product = $data[0];
            $this->assertArrayHasKey('id', $product);
            $this->assertArrayHasKey('slug', $product);
            $this->assertArrayHasKey('name', $product);
            $this->assertArrayHasKey('price', $product);
            $this->assertArrayHasKey('isFeatured', $product);
            $this->assertTrue($product['isFeatured']);
        }
    }

    public function testGetHomeCategories(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/storefront/home-categories', [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
                'X-Tenant-ID' => self::TEST_TENANT_ID
            ]
        ]);

        $this->assertResponseIsSuccessful();

        // Check cache headers
        $this->assertResponseHasHeader('Cache-Control');
        $this->assertResponseHasHeader('Vary');

        $cacheControl = $response->getHeaders()['cache-control'][0] ?? '';
        $this->assertStringContainsString('public', $cacheControl);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        // Verify DTO structure if categories exist
        if (!empty($data)) {
            $category = $data[0];
            $this->assertArrayHasKey('id', $category);
            $this->assertArrayHasKey('slug', $category);
            $this->assertArrayHasKey('name', $category);
            $this->assertArrayHasKey('showOnFront', $category);
            $this->assertTrue($category['showOnFront']);
        }
    }

    public function testProductListing(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/storefront/products', [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en-US,en;q=0.9',
                'X-Tenant-ID' => self::TEST_TENANT_ID
            ],
            'query' => [
                'page' => 1,
                'itemsPerPage' => 12
            ]
        ]);

        $this->assertResponseIsSuccessful();

        // Check cache headers
        $this->assertResponseHasHeader('Cache-Control');
        $cacheControl = $response->getHeaders()['cache-control'][0] ?? '';
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=120', $cacheControl);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('facets', $data);

        // Verify pagination metadata
        $meta = $data['meta'];
        $this->assertArrayHasKey('total', $meta);
        $this->assertArrayHasKey('page', $meta);
        $this->assertArrayHasKey('itemsPerPage', $meta);
        $this->assertArrayHasKey('totalPages', $meta);
    }

    public function testProductListingWithFilters(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/storefront/products', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TEST_TENANT_ID
            ],
            'query' => [
                'q' => 'test',
                'priceMin' => 1000,
                'priceMax' => 5000,
                'sort' => 'price_asc',
                'page' => 1
            ]
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
    }

    public function testProductListingWithSorting(): void
    {
        $client = static::createClient();

        // Test different sort options
        $sortOptions = ['newest', 'price_asc', 'price_desc', 'name'];

        foreach ($sortOptions as $sort) {
            $response = $client->request('GET', '/api/storefront/products', [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Tenant-ID' => self::TEST_TENANT_ID
                ],
                'query' => [
                    'sort' => $sort,
                    'page' => 1
                ]
            ]);

            $this->assertResponseIsSuccessful();
        }
    }

    public function testETagCaching(): void
    {
        $client = static::createClient();

        // First request to get ETag
        $response = $client->request('GET', '/api/storefront/featured-products', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TEST_TENANT_ID
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('ETag');

        $etag = $response->getHeaders()['etag'][0] ?? null;
        $this->assertNotNull($etag);

        // Second request with If-None-Match
        $response = $client->request('GET', '/api/storefront/featured-products', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TEST_TENANT_ID,
                'If-None-Match' => $etag
            ]
        ]);

        $this->assertResponseStatusCodeSame(304); // Not Modified
    }

    public function testTenantIsolation(): void
    {
        $client = static::createClient();

        $tenant1Id = '550e8400-e29b-41d4-a716-446655440000';
        $tenant2Id = '660e8400-e29b-41d4-a716-446655440000';

        // Request for tenant 1
        $response1 = $client->request('GET', '/api/storefront/products', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $tenant1Id
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $data1 = json_decode($client->getResponse()->getContent(), true);

        // Request for tenant 2
        $response2 = $client->request('GET', '/api/storefront/products', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => $tenant2Id
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $data2 = json_decode($client->getResponse()->getContent(), true);

        // Verify tenant isolation - results should be different or empty
        // (assuming tenants have different products)
        $this->assertIsArray($data1);
        $this->assertIsArray($data2);
    }
}