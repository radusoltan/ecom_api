<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Functional tests for GET /api/v1/products/{productId}/variants.
 *
 * Coverage:
 *  - JSON + JSON-LD content-type axis
 *  - Configurable product with variants -> 200 + populated array
 *  - Simple product (no configurable entry) -> 200 + [] (NOT 404)
 *  - Non-existent product UUID -> 404
 *  - Wrong tenant RLS isolation -> 404 (no cross-tenant leak)
 *  - activeOnly=true (default) vs activeOnly=false filtering
 *
 * TSK-736 Phase 2 Path (a).
 */
final class StorefrontProductVariantsApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const TENANT_A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaab';
    private const TENANT_B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbc';

    private const PRODUCT_ID_CONFIGURABLE_A = 'cccccccc-cccc-4ccc-8ccc-cccccccccc11';
    private const PRODUCT_ID_SIMPLE_A = 'cccccccc-cccc-4ccc-8ccc-cccccccccc12';
    private const PRODUCT_ID_CONFIGURABLE_B = 'cccccccc-cccc-4ccc-8ccc-cccccccccc13';
    private const CONFIGURABLE_A_ID = 'dddddddd-dddd-4ddd-8ddd-dddddddddd11';
    private const OPTION_A_ID = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeee11';
    private const OPTION_VALUE_A_ID = 'ffffffff-ffff-4fff-8fff-fffffffffff1';
    private const VARIANT_ACTIVE_ID = '11111111-1111-4111-8111-111111111101';
    private const VARIANT_INACTIVE_ID = '11111111-1111-4111-8111-111111111102';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->connection = $em->getConnection();

        $this->cleanup();

        // Seed configurable product for Tenant A with one active + one inactive variant
        $this->setTenantContextOnConnection(self::TENANT_A);
        $this->seedProduct(self::PRODUCT_ID_CONFIGURABLE_A, self::TENANT_A, 'TSK736-VAR-CONF-A', 'Configurable Product A');
        $this->seedConfigurableProduct(self::CONFIGURABLE_A_ID, self::PRODUCT_ID_CONFIGURABLE_A, self::TENANT_A);
        $this->seedOption(self::OPTION_A_ID, self::CONFIGURABLE_A_ID, self::TENANT_A, 'size', 1);
        $this->seedOptionValue(self::OPTION_VALUE_A_ID, self::OPTION_A_ID, self::TENANT_A, 'xl', 1);
        $this->seedVariant(
            self::VARIANT_ACTIVE_ID,
            self::CONFIGURABLE_A_ID,
            self::TENANT_A,
            'VAR-TSK-736001-xl',
            ['size' => 'xl'],
            true,
        );
        $this->seedVariant(
            self::VARIANT_INACTIVE_ID,
            self::CONFIGURABLE_A_ID,
            self::TENANT_A,
            'VAR-TSK-736002-sm',
            ['size' => 'sm'],
            false,
        );

        // Seed simple product for Tenant A (no configurable entry)
        $this->seedProduct(self::PRODUCT_ID_SIMPLE_A, self::TENANT_A, 'TSK736-VAR-SIMPLE-A', 'Simple Product A');

        // Seed configurable product for Tenant B (cross-tenant isolation test)
        $this->setTenantContextOnConnection(self::TENANT_B);
        $this->seedProduct(self::PRODUCT_ID_CONFIGURABLE_B, self::TENANT_B, 'TSK736-VAR-CONF-B', 'Configurable Product B');
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    // === Content-type axis: JSON ===

    public function testConfigurableProductReturns200WithVariantsJson(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/variants', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertNotEmpty($data, 'Configurable product must return non-empty variants array');

        $variant = $data[0];
        $this->assertArrayHasKey('id', $variant);
        $this->assertArrayHasKey('sku', $variant);
        $this->assertArrayHasKey('optionValueMap', $variant);
        $this->assertArrayHasKey('priceAmount', $variant);
        $this->assertArrayHasKey('priceCurrency', $variant);
        $this->assertArrayHasKey('isActive', $variant);
        $this->assertTrue($variant['isActive'], 'Default activeOnly=true should only return active variants');
    }

    public function testSimpleProductReturns200WithEmptyArrayJson(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_SIMPLE_A.'/variants', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        // CRITICAL: simple products must return 200 + [], NOT 404
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertEmpty($data, 'Simple product (no configurable entry) must return empty variants array');
    }

    public function testNonExistentProductReturns404Json(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/products/00000000-0000-4000-8000-000000000099/variants', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testWrongTenantReturns404Json(): void
    {
        // Tenant B requests variants for Tenant A's configurable product — must be 404 (RLS isolation)
        $client = static::createClient();
        $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/variants', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_B,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // === Content-type axis: JSON-LD ===

    public function testConfigurableProductReturns200WithVariantsJsonLd(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/variants', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertNotEmpty($data['member'], 'Configurable product must return non-empty variants collection under JSON-LD');

        $variant = $data['member'][0];
        $this->assertArrayHasKey('sku', $variant);
        $this->assertArrayHasKey('priceAmount', $variant);
    }

    public function testSimpleProductReturns200WithEmptyArrayJsonLd(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_SIMPLE_A.'/variants', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertEmpty($data['member'], 'Simple product must return empty collection under JSON-LD');
    }

    public function testNonExistentProductReturns404JsonLd(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/products/00000000-0000-4000-8000-000000000099/variants', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testWrongTenantReturns404JsonLd(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/variants', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_B,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // === activeOnly filter axis ===

    public function testActiveOnlyTrueReturnsOnlyActiveVariants(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/variants?activeOnly=true', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertNotEmpty($data);
        foreach ($data as $variant) {
            $this->assertTrue($variant['isActive'], 'activeOnly=true must return only active variants');
        }

        // We seeded 2 variants (1 active, 1 inactive), so only 1 must be returned
        $this->assertCount(1, $data, 'activeOnly=true must filter out inactive variants');
    }

    public function testActiveOnlyFalseReturnsBothActiveAndInactiveVariants(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/variants?activeOnly=false', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // We seeded 2 variants (1 active, 1 inactive), both must be returned
        $this->assertCount(2, $data, 'activeOnly=false must return all variants (active + inactive)');
    }

    // === Helpers ===

    private function setTenantContextOnConnection(string $tenantId): void
    {
        $this->connection->executeStatement(
            "SELECT set_config('app.tenant_id', :tenantId, false)",
            ['tenantId' => $tenantId]
        );
    }

    private function seedProduct(string $id, string $tenantId, string $sku, string $name): void
    {
        $this->connection->executeStatement(
            'INSERT INTO catalog_products (
                id, tenant_id, sku, name, description, short_description, slug,
                price_amount, price_currency, category_id,
                stock_quantity, track_inventory, allow_backorder, images,
                active, is_featured, created_at, updated_at,
                name_translations, description_translations, short_description_translations
            ) VALUES (
                :id, :tenantId, :sku, :name, :description, :shortDescription, :slug,
                9999, \'USD\', NULL,
                100, true, false, \'[]\',
                true, false, NOW(), NOW(),
                :nameTranslations::jsonb, :descTranslations::jsonb, :shortDescTranslations::jsonb
            )
            ON CONFLICT (id) DO NOTHING',
            [
                'id' => $id,
                'tenantId' => $tenantId,
                'sku' => $sku,
                'name' => $name,
                'description' => 'Seeded by StorefrontProductVariantsApiTest for TSK-736.',
                'shortDescription' => 'TSK-736 short description.',
                'slug' => strtolower(str_replace(['_', ' '], '-', $sku)),
                'nameTranslations' => '{}',
                'descTranslations' => '{}',
                'shortDescTranslations' => '{}',
            ]
        );
    }

    private function seedConfigurableProduct(string $id, string $productId, string $tenantId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO catalog_configurable_products (id, product_id, tenant_id, created_at, updated_at)
             VALUES (:id, :productId, :tenantId, NOW(), NOW())
             ON CONFLICT (id) DO NOTHING',
            ['id' => $id, 'productId' => $productId, 'tenantId' => $tenantId]
        );
    }

    private function seedOption(string $id, string $configurableProductId, string $tenantId, string $code, int $position): void
    {
        $this->connection->executeStatement(
            'INSERT INTO catalog_product_options (id, configurable_product_id, code, name_translations, position, created_at, tenant_id)
             VALUES (:id, :configurableProductId, :code, :nameTranslations::jsonb, :position, NOW(), :tenantId)',
            [
                'id' => $id,
                'configurableProductId' => $configurableProductId,
                'code' => $code,
                'nameTranslations' => json_encode(['en' => ucfirst($code)], JSON_THROW_ON_ERROR),
                'position' => $position,
                'tenantId' => $tenantId,
            ]
        );
    }

    private function seedOptionValue(string $id, string $optionId, string $tenantId, string $code, int $position): void
    {
        $this->connection->executeStatement(
            'INSERT INTO catalog_product_option_values (id, option_id, code, name_translations, position, created_at, tenant_id)
             VALUES (:id, :optionId, :code, :nameTranslations::jsonb, :position, NOW(), :tenantId)',
            [
                'id' => $id,
                'optionId' => $optionId,
                'code' => $code,
                'nameTranslations' => json_encode(['en' => ucfirst($code)], JSON_THROW_ON_ERROR),
                'position' => $position,
                'tenantId' => $tenantId,
            ]
        );
    }

    /**
     * @param array<string, string> $optionValueMap
     */
    private function seedVariant(
        string $id,
        string $configurableProductId,
        string $tenantId,
        string $sku,
        array $optionValueMap,
        bool $isActive,
    ): void {
        $this->connection->executeStatement(
            'INSERT INTO catalog_product_variants (
                id, configurable_product_id, sku, option_value_map,
                price_amount, price_currency,
                stock_on_hand, stock_reserved, track_inventory, allow_backorder,
                is_active, images, created_at, updated_at, tenant_id
            ) VALUES (
                :id, :configurableProductId, :sku, :optionValueMap::jsonb,
                4999, \'USD\',
                10, 0, true, false,
                :isActive, \'[]\', NOW(), NOW(), :tenantId
            )',
            [
                'id' => $id,
                'configurableProductId' => $configurableProductId,
                'sku' => $sku,
                'optionValueMap' => json_encode($optionValueMap, JSON_THROW_ON_ERROR),
                'isActive' => $isActive ? 'true' : 'false',
                'tenantId' => $tenantId,
            ]
        );
    }

    private function cleanup(): void
    {
        // Delete configurable product by fixed ID (cascades to options + values + variants via FK)
        $this->setTenantContextOnConnection(self::TENANT_A);
        $this->connection->executeStatement(
            'DELETE FROM catalog_configurable_products WHERE id = :id',
            ['id' => self::CONFIGURABLE_A_ID]
        );

        foreach ([self::TENANT_A, self::TENANT_B] as $tenantId) {
            $this->setTenantContextOnConnection($tenantId);
            $this->connection->executeStatement(
                "DELETE FROM catalog_products WHERE sku LIKE 'TSK736-VAR-%'"
            );
        }
    }
}
