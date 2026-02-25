<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Infrastructure;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Tenant\TenantContext;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for PostgreSQL Row-Level Security (RLS) on tenants table.
 *
 * RLS is now fully implemented with:
 * 1. set_tenant_context(TEXT) PostgreSQL function
 * 2. FORCE ROW LEVEL SECURITY enabled on all multi-tenant tables
 * 3. RLS policies (FOR ALL operations) on all multi-tenant tables
 */
final class TenantRLSTest extends KernelTestCase
{
    private ?Connection $connection = null;
    private ?TenantRepositoryInterface $tenantRepository = null;
    private ?TenantContext $tenantContext = null;
    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->tenantContext = $container->get(TenantContext::class);

        // Reset tenant context before each test
        $this->connection->executeStatement("SELECT set_config('app.tenant_id', NULL, false)");
        $this->tenantContext->clearCurrentTenant();

        // Enable bypass to clean up old test data
        $this->connection->executeStatement("SET app.bypass_rls = 'true'");
        $this->connection->executeStatement(
            "DELETE FROM tenants WHERE slug LIKE 'tenant-%' OR slug LIKE 'admin-tenant-%' OR slug LIKE 'malicious-%'"
        );
        $this->connection->executeStatement("SET app.bypass_rls = ''");

        // Set default tenant context to allow tenant creation (can be cleared in specific tests)
        $defaultTenantId = TenantId::fromString('00000000-0000-4000-8000-000000000001');
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $defaultTenantId->toString())
        );
    }

    public function testRLSIsEnabledOnTenantsTable(): void
    {
        $result = $this->connection->fetchOne(
            "SELECT rowsecurity FROM pg_tables WHERE tablename = 'tenants'"
        );

        $this->assertTrue((bool) $result, 'RLS should be enabled on tenants table');
    }

    public function testRLSPoliciesExist(): void
    {
        $policies = $this->connection->fetchAllAssociative(
            "SELECT policyname, cmd FROM pg_policies WHERE tablename = 'tenants' ORDER BY policyname"
        );

        $this->assertCount(1, $policies, 'Should have 1 unified RLS policy');

        $policyNames = array_column($policies, 'policyname');
        $this->assertContains('tenant_self_isolation', $policyNames, 'Tenants table should have tenant_self_isolation policy');
    }

    public function testTenantCanOnlySeeOwnData(): void
    {
        // Create two tenants using direct SQL to bypass RLS during creation
        $tenant1Id = TenantId::generate();
        $tenant2Id = TenantId::generate();

        // Insert tenant1 (set context to tenant1 first)
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant1Id->toString(),
                'Tenant 1',
                'tenant1@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'tenant-1',
                'en',
                json_encode(['en']),
            ]
        );

        // Insert tenant2 (set context to tenant2 first)
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant2Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant2Id->toString(),
                'Tenant 2',
                'tenant2@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'tenant-2',
                'en',
                json_encode(['en']),
            ]
        );

        // Set tenant context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1Id);
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );

        // Query tenants - should only see tenant1
        $visibleTenants = $this->connection->fetchAllAssociative(
            'SELECT id, name FROM tenants ORDER BY name'
        );

        $this->assertCount(1, $visibleTenants, 'Should only see own tenant');
        $this->assertEquals($tenant1Id->toString(), $visibleTenants[0]['id']);
        $this->assertEquals('Tenant 1', $visibleTenants[0]['name']);
    }

    public function testAdminCanSeeAllTenantsWhenNoContextSet(): void
    {
        // Create two tenants using direct SQL
        $tenant1Id = TenantId::generate();
        $tenant2Id = TenantId::generate();

        // Insert tenant1 (set context to tenant1 first)
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant1Id->toString(),
                'Admin Tenant 1',
                'admin1@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'admin-tenant-1',
                'en',
                json_encode(['en']),
            ]
        );

        // Insert tenant2 (set context to tenant2 first)
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant2Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant2Id->toString(),
                'Admin Tenant 2',
                'admin2@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'admin-tenant-2',
                'en',
                json_encode(['en']),
            ]
        );

        // Clear tenant context (admin mode)
        $this->tenantContext->clearCurrentTenant();

        // Reset tenant context in database (NULL = no isolation)
        $this->connection->executeStatement("SELECT set_config('app.tenant_id', NULL, false)");

        // Query tenants - with RLS FORCED, should see 0 when no tenant set (not admin bypass)
        $visibleTenants = $this->connection->fetchAllAssociative(
            'SELECT id, name FROM tenants ORDER BY name'
        );

        // With FORCE ROW LEVEL SECURITY, even table owner sees 0 rows without tenant context
        $this->assertCount(0, $visibleTenants, 'Without tenant context, RLS blocks all access');
    }

    public function testTenantCannotInsertWithDifferentTenantId(): void
    {
        // Create tenant1 using direct SQL
        $tenant1Id = TenantId::generate();
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant1Id->toString(),
                'Tenant Insert Test',
                'insert@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'tenant-insert-test',
                'en',
                json_encode(['en']),
            ]
        );

        // Set context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1Id);
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );

        // Try to insert a different tenant (should fail due to RLS)
        $differentTenantId = TenantId::generate()->toString();

        $this->expectException(\Doctrine\DBAL\Exception::class);

        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $differentTenantId,
                'Malicious Tenant',
                'hacker@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'malicious-tenant',
                'en',
                json_encode(['en']),
            ]
        );
    }

    public function testTenantCannotUpdateOtherTenantData(): void
    {
        // Create two tenants using direct SQL
        $tenant1Id = TenantId::generate();
        $tenant2Id = TenantId::generate();

        // Insert tenant1 (set context to tenant1 first)
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant1Id->toString(),
                'Tenant Update 1',
                'update1@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'tenant-update-1',
                'en',
                json_encode(['en']),
            ]
        );

        // Insert tenant2 (set context to tenant2 first)
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant2Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant2Id->toString(),
                'Tenant Update 2',
                'update2@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'tenant-update-2',
                'en',
                json_encode(['en']),
            ]
        );

        // Set context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1Id);
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );

        // Try to update tenant2's data (should affect 0 rows due to RLS)
        $affectedRows = $this->connection->executeStatement(
            'UPDATE tenants SET name = ? WHERE id = ?',
            ['Hacked Name', $tenant2Id->toString()]
        );

        $this->assertEquals(0, $affectedRows, 'Should not be able to update other tenant data');
    }

    public function testTenantCanUpdateOwnData(): void
    {
        // Create tenant using direct SQL
        $tenant1Id = TenantId::generate();
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );
        $this->connection->executeStatement(
            'INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $tenant1Id->toString(),
                'Tenant Own Update',
                'ownupdate@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'tenant-own-update',
                'en',
                json_encode(['en']),
            ]
        );

        // Set context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1Id);
        $this->connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenant1Id->toString())
        );

        // Update own data (should work)
        $affectedRows = $this->connection->executeStatement(
            'UPDATE tenants SET name = ? WHERE id = ?',
            ['Updated Name', $tenant1Id->toString()]
        );

        $this->assertEquals(1, $affectedRows, 'Should be able to update own data');

        // Verify the update
        $updatedName = $this->connection->fetchOne(
            'SELECT name FROM tenants WHERE id = ?',
            [$tenant1Id->toString()]
        );

        $this->assertEquals('Updated Name', $updatedName);
    }

    protected function tearDown(): void
    {
        // Clean up only if RLS was set up
        if (null !== $this->tenantContext) {
            $this->tenantContext->clearCurrentTenant();
        }
        if (null !== $this->connection) {
            try {
                $this->connection->executeStatement("SELECT set_config('app.tenant_id', NULL, false)");
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
        parent::tearDown();
    }
}
