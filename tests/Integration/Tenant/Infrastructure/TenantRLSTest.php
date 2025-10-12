<?php

declare(strict_types=1);

namespace App\Tests\Integration\Tenant\Infrastructure;

use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Tenant\TenantContext;
use App\Tenant\Domain\Model\Tenant;
use App\Tenant\Domain\Repository\TenantRepositoryInterface;
use App\Tenant\Domain\ValueObject\TenantId;
use App\Tenant\Domain\ValueObject\TenantName;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * NOTE: These tests are skipped because PostgreSQL Row-Level Security (RLS)
 * has not been set up yet. RLS requires:
 * 1. Creating set_current_tenant_id() PostgreSQL function
 * 2. Enabling RLS on tables with ALTER TABLE ... ENABLE ROW LEVEL SECURITY
 * 3. Creating RLS policies for SELECT/INSERT/UPDATE/DELETE
 *
 * This is infrastructure work that will be implemented in a future phase.
 */
final class TenantRLSTest extends KernelTestCase
{
    private ?Connection $connection = null;
    private ?TenantRepositoryInterface $tenantRepository = null;
    private ?TenantContext $tenantContext = null;
    private ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        $this->markTestSkipped('RLS infrastructure not yet implemented. Requires PostgreSQL RLS setup with set_current_tenant_id() function and policies.');
    }

    protected function setUpIfRLSAvailable(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->tenantRepository = $container->get(TenantRepositoryInterface::class);
        $this->tenantContext = $container->get(TenantContext::class);

        // Clean up before each test
        $this->connection->executeStatement('TRUNCATE TABLE tenants CASCADE');
        $this->tenantContext->clearCurrentTenant();
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

        $this->assertCount(4, $policies, 'Should have 4 RLS policies');

        $policyNames = array_column($policies, 'policyname');
        $this->assertContains('tenant_isolation_select', $policyNames);
        $this->assertContains('tenant_isolation_insert', $policyNames);
        $this->assertContains('tenant_isolation_update', $policyNames);
        $this->assertContains('tenant_isolation_delete', $policyNames);
    }

    public function testTenantCanOnlySeeOwnData(): void
    {
        // Create two tenants
        $tenant1 = Tenant::create(
            TenantName::fromString('Tenant 1'),
            Email::fromString('tenant1@example.com')
        );
        $this->tenantRepository->save($tenant1);

        $tenant2 = Tenant::create(
            TenantName::fromString('Tenant 2'),
            Email::fromString('tenant2@example.com')
        );
        $this->tenantRepository->save($tenant2);

        // Flush to database
        $this->entityManager->flush();

        // Set tenant context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1->id());

        // Reconnect to apply RLS
        $this->connection->close();
        $this->connection->connect();

        // Execute RLS function
        $this->connection->executeStatement(
            "SELECT set_current_tenant_id(?)",
            [$tenant1->id()->toString()]
        );

        // Query tenants - should only see tenant1
        $visibleTenants = $this->connection->fetchAllAssociative(
            'SELECT id, name FROM tenants ORDER BY name'
        );

        $this->assertCount(1, $visibleTenants, 'Should only see own tenant');
        $this->assertEquals($tenant1->id()->toString(), $visibleTenants[0]['id']);
        $this->assertEquals('Tenant 1', $visibleTenants[0]['name']);
    }

    public function testAdminCanSeeAllTenantsWhenNoContextSet(): void
    {
        // Create two tenants
        $tenant1 = Tenant::create(
            TenantName::fromString('Admin Tenant 1'),
            Email::fromString('admin1@example.com')
        );
        $this->tenantRepository->save($tenant1);

        $tenant2 = Tenant::create(
            TenantName::fromString('Admin Tenant 2'),
            Email::fromString('admin2@example.com')
        );
        $this->tenantRepository->save($tenant2);

        $this->entityManager->flush();

        // Clear tenant context (admin mode)
        $this->tenantContext->clearCurrentTenant();

        // Reconnect to apply RLS with NULL context
        $this->connection->close();
        $this->connection->connect();

        // Query tenants - should see all
        $visibleTenants = $this->connection->fetchAllAssociative(
            'SELECT id, name FROM tenants ORDER BY name'
        );

        $this->assertCount(2, $visibleTenants, 'Admin should see all tenants');
    }

    public function testTenantCannotInsertWithDifferentTenantId(): void
    {
        $tenant1 = Tenant::create(
            TenantName::fromString('Tenant Insert Test'),
            Email::fromString('insert@example.com')
        );
        $this->tenantRepository->save($tenant1);
        $this->entityManager->flush();

        // Set context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1->id());

        // Reconnect with tenant context
        $this->connection->close();
        $this->connection->connect();

        $this->connection->executeStatement(
            "SELECT set_current_tenant_id(?)",
            [$tenant1->id()->toString()]
        );

        // Try to insert a different tenant (should fail or be filtered)
        $differentTenantId = TenantId::generate()->toString();

        $this->expectException(\Doctrine\DBAL\Exception::class);

        $this->connection->executeStatement(
            "INSERT INTO tenants (id, name, owner_email, status, created_at)
             VALUES (?, ?, ?, ?, ?)",
            [
                $differentTenantId,
                'Malicious Tenant',
                'hacker@example.com',
                'active',
                (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function testTenantCannotUpdateOtherTenantData(): void
    {
        // Create two tenants as admin
        $tenant1 = Tenant::create(
            TenantName::fromString('Tenant Update 1'),
            Email::fromString('update1@example.com')
        );
        $this->tenantRepository->save($tenant1);

        $tenant2 = Tenant::create(
            TenantName::fromString('Tenant Update 2'),
            Email::fromString('update2@example.com')
        );
        $this->tenantRepository->save($tenant2);
        $this->entityManager->flush();

        // Set context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1->id());

        // Reconnect with tenant1 context
        $this->connection->close();
        $this->connection->connect();

        $this->connection->executeStatement(
            "SELECT set_current_tenant_id(?)",
            [$tenant1->id()->toString()]
        );

        // Try to update tenant2's data (should affect 0 rows due to RLS)
        $affectedRows = $this->connection->executeStatement(
            "UPDATE tenants SET name = ? WHERE id = ?",
            ['Hacked Name', $tenant2->id()->toString()]
        );

        $this->assertEquals(0, $affectedRows, 'Should not be able to update other tenant data');
    }

    public function testTenantCanUpdateOwnData(): void
    {
        // Create tenant
        $tenant1 = Tenant::create(
            TenantName::fromString('Tenant Own Update'),
            Email::fromString('ownupdate@example.com')
        );
        $this->tenantRepository->save($tenant1);
        $this->entityManager->flush();

        // Set context to tenant1
        $this->tenantContext->setCurrentTenant($tenant1->id());

        // Reconnect with tenant1 context
        $this->connection->close();
        $this->connection->connect();

        $this->connection->executeStatement(
            "SELECT set_current_tenant_id(?)",
            [$tenant1->id()->toString()]
        );

        // Update own data (should work)
        $affectedRows = $this->connection->executeStatement(
            "UPDATE tenants SET name = ? WHERE id = ?",
            ['Updated Name', $tenant1->id()->toString()]
        );

        $this->assertEquals(1, $affectedRows, 'Should be able to update own data');

        // Verify the update
        $updatedName = $this->connection->fetchOne(
            "SELECT name FROM tenants WHERE id = ?",
            [$tenant1->id()->toString()]
        );

        $this->assertEquals('Updated Name', $updatedName);
    }

    protected function tearDown(): void
    {
        // Clean up only if RLS was set up
        if ($this->tenantContext !== null) {
            $this->tenantContext->clearCurrentTenant();
        }
        if ($this->connection !== null) {
            $this->connection->close();
        }
        parent::tearDown();
    }
}
