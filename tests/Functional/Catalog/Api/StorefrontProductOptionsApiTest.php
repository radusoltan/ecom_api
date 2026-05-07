<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Support\TenantTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Functional tests for GET /api/v1/products/{productId}/options.
 *
 * Coverage:
 *  - JSON + JSON-LD content-type axis
 *  - Configurable product with options -> 200 + populated array
 *  - Simple product (no configurable entry) -> 200 + [] (NOT 404)
 *  - Non-existent product UUID -> 404
 *  - Wrong tenant RLS isolation -> 404 (no cross-tenant leak)
 *
 * TSK-736 Phase 2 Path (a).
 */
final class StorefrontProductOptionsApiTest extends ApiTestCase
{
    use TenantTestTrait;

    private const TENANT_A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const TENANT_B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

    private const PRODUCT_ID_CONFIGURABLE_A = 'cccccccc-cccc-4ccc-8ccc-cccccccccc01';
    private const PRODUCT_ID_SIMPLE_A = 'cccccccc-cccc-4ccc-8ccc-cccccccccc02';
    private const PRODUCT_ID_CONFIGURABLE_B = 'cccccccc-cccc-4ccc-8ccc-cccccccccc03';
    private const CONFIGURABLE_A_ID = 'dddddddd-dddd-4ddd-8ddd-dddddddddd01';
    private const OPTION_A_ID = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeee01';
    private const OPTION_VALUE_A_ID = 'ffffffff-ffff-4fff-8fff-ffffffffffff';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->connection = $em->getConnection();

        $this->cleanup();

        // Seed configurable product for Tenant A
        $this->setTenantContextOnConnection(self::TENANT_A);
        $this->seedProduct(self::PRODUCT_ID_CONFIGURABLE_A, self::TENANT_A, 'TSK736-OPT-CONF-A', 'Configurable Product A');
        $this->seedConfigurableProduct(self::CONFIGURABLE_A_ID, self::PRODUCT_ID_CONFIGURABLE_A, self::TENANT_A);
        $this->seedOption(self::OPTION_A_ID, self::CONFIGURABLE_A_ID, self::TENANT_A, 'color', 1);
        $this->seedOptionValue(self::OPTION_VALUE_A_ID, self::OPTION_A_ID, self::TENANT_A, 'red', 1);

        // Seed simple product for Tenant A (no configurable entry)
        $this->seedProduct(self::PRODUCT_ID_SIMPLE_A, self::TENANT_A, 'TSK736-OPT-SIMPLE-A', 'Simple Product A');

        // Seed configurable product for Tenant B (cross-tenant isolation test)
        $this->setTenantContextOnConnection(self::TENANT_B);
        $this->seedProduct(self::PRODUCT_ID_CONFIGURABLE_B, self::TENANT_B, 'TSK736-OPT-CONF-B', 'Configurable Product B');
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    // === Content-type axis: JSON ===

    public function testConfigurableProductReturns200WithOptionsJson(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/options', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertNotEmpty($data, 'Configurable product must return non-empty options array');

        $option = $data[0];
        $this->assertArrayHasKey('id', $option);
        $this->assertArrayHasKey('code', $option);
        $this->assertArrayHasKey('name', $option);
        $this->assertArrayHasKey('position', $option);
        $this->assertArrayHasKey('values', $option);
        $this->assertSame('color', $option['code']);
        $this->assertNotEmpty($option['values'], 'Option must have at least one value');

        $value = $option['values'][0];
        $this->assertArrayHasKey('code', $value);
        $this->assertSame('red', $value['code']);
    }

    public function testSimpleProductReturns200WithEmptyArrayJson(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_SIMPLE_A.'/options', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        // CRITICAL: simple products must return 200 + [], NOT 404
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertEmpty($data, 'Simple product (no configurable entry) must return empty options array');
    }

    public function testNonExistentProductReturns404Json(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/products/00000000-0000-4000-8000-000000000000/options', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testWrongTenantReturns404Json(): void
    {
        // Tenant B requests options for Tenant A's configurable product — must be 404 (RLS isolation)
        $client = static::createClient();
        $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/options', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Tenant-ID' => self::TENANT_B,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // === Content-type axis: JSON-LD ===

    public function testConfigurableProductReturns200WithOptionsJsonLd(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/options', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // JSON-LD collection shape
        $this->assertArrayHasKey('member', $data);
        $this->assertNotEmpty($data['member'], 'Configurable product must return non-empty options collection under JSON-LD');

        $option = $data['member'][0];
        $this->assertArrayHasKey('code', $option);
        $this->assertSame('color', $option['code']);
        $this->assertArrayHasKey('values', $option);
    }

    public function testSimpleProductReturns200WithEmptyArrayJsonLd(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_SIMPLE_A.'/options', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_A,
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertEmpty($data['member'], 'Simple product must return empty collection under JSON-LD');
        $this->assertSame(0, $data['totalItems'] ?? $data['hydra:totalItems'] ?? 0);
    }

    public function testNonExistentProductReturns404JsonLd(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/products/00000000-0000-4000-8000-000000000000/options', [
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
        $client->request('GET', '/api/v1/products/'.self::PRODUCT_ID_CONFIGURABLE_A.'/options', [
            'headers' => [
                'Accept' => 'application/ld+json',
                'X-Tenant-ID' => self::TENANT_B,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
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
                'description' => 'Seeded by StorefrontProductOptionsApiTest for TSK-736.',
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

    private function cleanup(): void
    {
        // Delete configurable products by fixed IDs (cascades to options + values via FK)
        // Must set tenant context per tenant for RLS
        $this->setTenantContextOnConnection(self::TENANT_A);
        $this->connection->executeStatement(
            'DELETE FROM catalog_configurable_products WHERE id = :id',
            ['id' => self::CONFIGURABLE_A_ID]
        );

        foreach ([self::TENANT_A, self::TENANT_B] as $tenantId) {
            $this->setTenantContextOnConnection($tenantId);
            $this->connection->executeStatement(
                "DELETE FROM catalog_products WHERE sku LIKE 'TSK736-OPT-%'"
            );
        }
    }
}
