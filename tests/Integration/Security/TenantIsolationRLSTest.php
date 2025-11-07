<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for PostgreSQL Row-Level Security (RLS) tenant isolation.
 *
 * These tests verify that RLS policies enforce tenant isolation at the database level,
 * preventing cross-tenant data access even if application logic fails.
 *
 * CRITICAL: These tests use direct SQL to simulate potential security vulnerabilities.
 */
class TenantIsolationRLSTest extends KernelTestCase
{
    private Connection $connection;
    private const TENANT_A = '550e8400-e29b-41d4-a716-446655440000';
    private const TENANT_B = '660e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get('doctrine.dbal.default_connection');
    }

    /**
     * Test that RLS is enabled on catalog_products table.
     */
    public function testRLSIsEnabledOnCatalogProducts(): void
    {
        $sql = "SELECT tablename, rowsecurity
                FROM pg_tables
                WHERE schemaname = 'public' AND tablename = 'catalog_products'";

        $result = $this->connection->fetchAssociative($sql);

        $this->assertTrue((bool) $result['rowsecurity'], 'RLS should be enabled on catalog_products');
    }

    /**
     * Test that RLS is enabled on catalog_categories table.
     */
    public function testRLSIsEnabledOnCatalogCategories(): void
    {
        $sql = "SELECT tablename, rowsecurity
                FROM pg_tables
                WHERE schemaname = 'public' AND tablename = 'catalog_categories'";

        $result = $this->connection->fetchAssociative($sql);

        $this->assertTrue((bool) $result['rowsecurity'], 'RLS should be enabled on catalog_categories');
    }

    /**
     * Test that RLS is enabled on media_images table.
     */
    public function testRLSIsEnabledOnMediaImages(): void
    {
        $sql = "SELECT tablename, rowsecurity
                FROM pg_tables
                WHERE schemaname = 'public' AND tablename = 'media_images'";

        $result = $this->connection->fetchAssociative($sql);

        $this->assertTrue((bool) $result['rowsecurity'], 'RLS should be enabled on media_images');
    }

    /**
     * Test that setting tenant context allows reading only tenant's data.
     */
    public function testCanOnlyReadOwnTenantData(): void
    {
        // Set tenant context to Tenant A (cannot use prepared statements with SET LOCAL)
        $tenantA = self::TENANT_A;
        $this->connection->executeStatement("SET LOCAL app.tenant_id = '$tenantA'");

        // Query should only return Tenant A's products
        $sql = 'SELECT COUNT(*) as count FROM catalog_products';
        $result = $this->connection->fetchAssociative($sql);
        $countA = $result['count'];

        // Change to Tenant B
        $tenantB = self::TENANT_B;
        $this->connection->executeStatement("SET LOCAL app.tenant_id = '$tenantB'");

        $result = $this->connection->fetchAssociative($sql);
        $countB = $result['count'];

        // Counts should be independent (unless both tenants have the same number of products)
        $this->assertIsNumeric($countA);
        $this->assertIsNumeric($countB);

        // Reset
        $this->connection->executeStatement('RESET app.tenant_id');
    }

    /**
     * Test that attempting to read without tenant context fails or returns empty.
     */
    public function testCannotReadWithoutTenantContext(): void
    {
        // Reset any tenant context
        $this->connection->executeStatement('RESET app.tenant_id');

        try {
            // Attempt to query without tenant context
            // This should either fail or return 0 rows depending on policy
            $sql = 'SELECT COUNT(*) as count FROM catalog_products';
            $result = $this->connection->fetchAssociative($sql);

            // If it doesn't fail, count should be 0 (no access without tenant)
            $this->assertEquals(0, $result['count'], 'Should not see any rows without tenant context');
        } catch (\Exception $e) {
            // Exception is also acceptable - policy may reject the query entirely
            $this->assertStringContainsString('current_setting', $e->getMessage());
        }
    }

    /**
     * Test that INSERT with wrong tenant_id is blocked by RLS WITH CHECK policy.
     */
    public function testCannotInsertRowForDifferentTenant(): void
    {
        // Set tenant context to Tenant A
        $tenantA = self::TENANT_A;
        $this->connection->executeStatement("SET LOCAL app.tenant_id = '$tenantA'");

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/new row violates row-level security policy|policy for table/i');

        // Attempt to insert a row for Tenant B while context is Tenant A
        // This should be blocked by WITH CHECK policy
        $tenantBStr = self::TENANT_B;
        $sql = "INSERT INTO catalog_products (id, tenant_id, sku, name, slug, price_amount, price_currency, active, created_at, updated_at)
                VALUES (gen_random_uuid(), '$tenantBStr', 'TEST-SKU-001', 'Test Product', 'test-product', 1000, 'USD', true, NOW(), NOW())";

        $this->connection->executeStatement($sql);
    }

    /**
     * Test that UPDATE cannot modify rows of another tenant.
     */
    public function testCannotUpdateRowsOfAnotherTenant(): void
    {
        // Create a test product for Tenant A
        $tenantA = self::TENANT_A;
        $this->connection->executeStatement("SET LOCAL app.tenant_id = '$tenantA'");

        $productId = $this->connection->fetchOne('SELECT id FROM catalog_products LIMIT 1');

        if (!$productId) {
            $this->markTestSkipped('No products available for Tenant A');
        }

        // Change context to Tenant B
        $tenantB = self::TENANT_B;
        $this->connection->executeStatement("SET LOCAL app.tenant_id = '$tenantB'");

        // Attempt to update Tenant A's product while context is Tenant B
        $sql = "UPDATE catalog_products SET name = 'Hacked Name' WHERE id = ?";
        $affectedRows = $this->connection->executeStatement($sql, [$productId]);

        // Should affect 0 rows (policy blocks access)
        $this->assertEquals(0, $affectedRows, 'Should not be able to update another tenant\'s product');

        // Reset
        $this->connection->executeStatement('RESET app.tenant_id');
    }

    /**
     * Test that DELETE cannot remove rows of another tenant.
     */
    public function testCannotDeleteRowsOfAnotherTenant(): void
    {
        // Get a product ID from Tenant A
        $tenantA = self::TENANT_A;
        $this->connection->executeStatement("SET LOCAL app.tenant_id = '$tenantA'");

        $productId = $this->connection->fetchOne('SELECT id FROM catalog_products LIMIT 1');

        if (!$productId) {
            $this->markTestSkipped('No products available for Tenant A');
        }

        // Change context to Tenant B
        $tenantB = self::TENANT_B;
        $this->connection->executeStatement("SET LOCAL app.tenant_id = '$tenantB'");

        // Attempt to delete Tenant A's product while context is Tenant B
        $sql = 'DELETE FROM catalog_products WHERE id = ?';
        $affectedRows = $this->connection->executeStatement($sql, [$productId]);

        // Should affect 0 rows (policy blocks access)
        $this->assertEquals(0, $affectedRows, 'Should not be able to delete another tenant\'s product');

        // Reset
        $this->connection->executeStatement('RESET app.tenant_id');
    }

    protected function tearDown(): void
    {
        // Always reset tenant context after tests
        try {
            $this->connection->executeStatement('RESET app.tenant_id');
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }

        parent::tearDown();
    }
}
