<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Functional test for GET /storefront/products/{slug}.
 *
 * Validates 404 semantics (existence not leaked across tenant boundaries or
 * via active/draft state) and full-payload shape for active matches under the
 * current tenant.
 */
final class StorefrontProductDetailApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const TENANT_A = '11111111-1111-4111-8111-111111111111';
    private const TENANT_B = '22222222-2222-4222-8222-222222222222';

    private const SLUG_ACTIVE_A = 'tsk-716-active-product-tenant-a';
    private const SLUG_INACTIVE_A = 'tsk-716-inactive-product-tenant-a';
    private const SLUG_ACTIVE_B = 'tsk-716-active-product-tenant-b';

    private const CATEGORY_A = '33333333-3333-4333-8333-333333333333';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->connection = $em->getConnection();

        $this->cleanup();

        // catalog_products RLS scopes inserts to current app.tenant_id —
        // switch context per row.
        $this->setTenantContextOnConnection(self::TENANT_A);
        $this->seedProduct(self::TENANT_A, self::SLUG_ACTIVE_A, 'TSK716-A-ACT', 'TSK-716 Active Product (Tenant A)', true, self::CATEGORY_A);
        $this->seedProduct(self::TENANT_A, self::SLUG_INACTIVE_A, 'TSK716-A-INA', 'TSK-716 Inactive Product (Tenant A)', false, null);

        $this->setTenantContextOnConnection(self::TENANT_B);
        $this->seedProduct(self::TENANT_B, self::SLUG_ACTIVE_B, 'TSK716-B-ACT', 'TSK-716 Active Product (Tenant B)', true, null);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testReturns200WithFullPayloadForValidSlugUnderCorrectTenant(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/storefront/products/'.self::SLUG_ACTIVE_A, [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertSame(self::SLUG_ACTIVE_A, $data['slug'] ?? null);
        $this->assertSame('TSK-716 Active Product (Tenant A)', $data['name'] ?? null);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertSame(12345, $data['price']['amount'] ?? null);
        $this->assertSame('USD', $data['price']['currency'] ?? null);
        $this->assertArrayHasKey('categoryId', $data);
        $this->assertSame(self::CATEGORY_A, $data['categoryId']);
        $this->assertArrayHasKey('availability', $data);
    }

    public function testReturns404WhenSlugBelongsToDifferentTenant(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/storefront/products/'.self::SLUG_ACTIVE_B, [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReturns404WhenProductIsInactive(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/storefront/products/'.self::SLUG_INACTIVE_A, [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReturns404ForNonExistentSlug(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/storefront/products/this-slug-does-not-exist-tsk716', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * TSK-738 regression guard: price MUST be a flat object {amount,currency}
     * under Accept: application/ld+json, not a Hydra Collection wrap.
     * Storefront client defaults to ld+json — this is the production shape.
     */
    public function testPriceShapeIsFlatUnderJsonLd(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/storefront/products/'.self::SLUG_ACTIVE_A, [
            'headers' => [
                'Accept' => 'application/ld+json',
                'Accept-Language' => 'en',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('price', $data, 'price field must exist on the response root');
        $this->assertIsArray($data['price'], 'price must serialize to an associative array, not a scalar');

        // The bug: API Platform JSON-LD wraps array<string,mixed> DTO fields as
        // Hydra Collection at top level of a single Get response, hiding the
        // amount/currency values inside `member[]`. Guard against it.
        $this->assertArrayNotHasKey('member', $data['price'], 'price must NOT be wrapped in a Hydra Collection (member key present)');
        $this->assertNotSame('Collection', $data['price']['@type'] ?? null, 'price @type must NOT be Hydra Collection');

        $this->assertArrayHasKey('amount', $data['price'], 'price.amount must be a top-level key, not nested under member[]');
        $this->assertArrayHasKey('currency', $data['price'], 'price.currency must be a top-level key, not nested under member[]');
        $this->assertSame(12345, $data['price']['amount']);
        $this->assertSame('USD', $data['price']['currency']);
    }

    /**
     * TSK-738 regression guard: ensure listing endpoint shape preserved.
     * Listing already serialized price flat under JSON-LD because each DTO
     * is nested in member[] — guard against an inverse regression where the
     * fix breaks the listing path.
     */
    public function testListingPriceShapeStaysFlatUnderJsonLd(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/storefront/products', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('member', $data);

        $found = null;
        foreach ($data['member'] as $product) {
            if (($product['slug'] ?? null) === self::SLUG_ACTIVE_A) {
                $found = $product;
                break;
            }
        }
        $this->assertNotNull($found, 'seeded product must appear in listing');
        $this->assertIsArray($found['price'] ?? null);
        $this->assertSame(12345, $found['price']['amount'] ?? null);
        $this->assertSame('USD', $found['price']['currency'] ?? null);
        $this->assertArrayNotHasKey('member', $found['price'], 'listing price must not be Hydra Collection-wrapped');
        $this->assertNotSame('Collection', $found['price']['@type'] ?? null);
    }

    /**
     * TSK-738 audit: primaryImage is also array<string,mixed> on the DTO.
     * Same Hydra-Collection-wrap risk under JSON-LD. Seed a product with
     * non-null primaryImage and assert flat shape.
     */
    public function testPrimaryImageShapeIsFlatUnderJsonLd(): void
    {
        $this->setTenantContextOnConnection(self::TENANT_A);
        $imageSlug = 'tsk-738-with-image-tenant-a';
        $this->seedProductWithImage(self::TENANT_A, $imageSlug, 'TSK738-IMG', 'TSK-738 Product With Image');

        try {
            $client = static::createClient();
            $response = $client->request('GET', '/api/v1/storefront/products/'.$imageSlug, [
                'headers' => [
                    'Accept' => 'application/ld+json',
                    'X-Tenant-ID' => self::TENANT_A,
                ],
            ]);

            $this->assertResponseIsSuccessful();
            $data = $response->toArray();

            $this->assertArrayHasKey('primaryImage', $data);
            $this->assertIsArray($data['primaryImage']);
            $this->assertArrayNotHasKey('member', $data['primaryImage'], 'primaryImage must NOT be Hydra Collection-wrapped');
            $this->assertNotSame('Collection', $data['primaryImage']['@type'] ?? null);
            $this->assertArrayHasKey('urlSm', $data['primaryImage']);
            $this->assertArrayHasKey('urlMd', $data['primaryImage']);
            $this->assertArrayHasKey('urlLg', $data['primaryImage']);
            $this->assertSame('https://cdn.example/tsk738.jpg', $data['primaryImage']['urlSm']);
        } finally {
            $this->setTenantContextOnConnection(self::TENANT_A);
            $this->connection->executeStatement("DELETE FROM catalog_products WHERE sku = 'TSK738-IMG'");
        }
    }

    private function seedProduct(string $tenantId, string $slug, string $sku, string $name, bool $active, ?string $categoryId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO catalog_products (
                id, tenant_id, sku, name, description, short_description, slug,
                price_amount, price_currency, category_id,
                stock_quantity, track_inventory, allow_backorder, images,
                active, is_featured, created_at, updated_at,
                name_translations, description_translations, short_description_translations
            ) VALUES (
                gen_random_uuid(), :tenantId, :sku, :name, :description, :shortDescription, :slug,
                :priceAmount, :priceCurrency, :categoryId,
                100, true, false, :images,
                :active, false, NOW(), NOW(),
                :nameTranslations::jsonb, :descTranslations::jsonb, :shortDescTranslations::jsonb
            )',
            [
                'tenantId' => $tenantId,
                'sku' => $sku,
                'name' => $name,
                'description' => 'Seeded by StorefrontProductDetailApiTest for TSK-716.',
                'shortDescription' => 'TSK-716 short description.',
                'slug' => $slug,
                'priceAmount' => 12345,
                'priceCurrency' => 'USD',
                'categoryId' => $categoryId,
                'images' => '[]',
                'active' => $active ? 'true' : 'false',
                'nameTranslations' => '{}',
                'descTranslations' => '{}',
                'shortDescTranslations' => '{}',
            ]
        );
    }

    private function seedProductWithImage(string $tenantId, string $slug, string $sku, string $name): void
    {
        $images = json_encode([
            ['url' => 'https://cdn.example/tsk738.jpg', 'isPrimary' => true],
        ], JSON_THROW_ON_ERROR);

        $this->connection->executeStatement(
            'INSERT INTO catalog_products (
                id, tenant_id, sku, name, description, short_description, slug,
                price_amount, price_currency, category_id,
                stock_quantity, track_inventory, allow_backorder, images,
                active, is_featured, created_at, updated_at,
                name_translations, description_translations, short_description_translations
            ) VALUES (
                gen_random_uuid(), :tenantId, :sku, :name, :description, :shortDescription, :slug,
                :priceAmount, :priceCurrency, NULL,
                100, true, false, :images,
                true, false, NOW(), NOW(),
                :nameTranslations::jsonb, :descTranslations::jsonb, :shortDescTranslations::jsonb
            )',
            [
                'tenantId' => $tenantId,
                'sku' => $sku,
                'name' => $name,
                'description' => 'Seeded by StorefrontProductDetailApiTest for TSK-738.',
                'shortDescription' => 'TSK-738 short description.',
                'slug' => $slug,
                'priceAmount' => 9999,
                'priceCurrency' => 'USD',
                'images' => $images,
                'nameTranslations' => '{}',
                'descTranslations' => '{}',
                'shortDescTranslations' => '{}',
            ]
        );
    }

    private function setTenantContextOnConnection(string $tenantId): void
    {
        $this->connection->executeStatement(
            "SELECT set_config('app.tenant_id', :tenantId, false)",
            ['tenantId' => $tenantId]
        );
    }

    private function cleanup(): void
    {
        // RLS-scoped delete per tenant — covers both seeded rows even if
        // a previous run partially failed.
        foreach ([self::TENANT_A, self::TENANT_B] as $tenantId) {
            $this->setTenantContextOnConnection($tenantId);
            $this->connection->executeStatement(
                "DELETE FROM catalog_products WHERE sku LIKE 'TSK716-%' OR sku LIKE 'TSK738-%'"
            );
        }
    }
}
