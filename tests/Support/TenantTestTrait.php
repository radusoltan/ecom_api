<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Domain\ValueObject\TenantId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * TenantTestTrait.
 *
 * Provides helper methods for setting tenant context in integration and functional tests
 * to avoid PostgreSQL Row-Level Security (RLS) violations.
 *
 * Usage:
 * - Integration Tests: Use setTenantContext() in setUp()
 * - Functional Tests: Use withTenantHeader() to add X-Tenant-ID header
 */
trait TenantTestTrait
{
    /**
     * Set tenant context for integration tests (direct DB access).
     *
     * This executes PostgreSQL's SET to set the app.tenant_id session variable
     * which is required by Row-Level Security (RLS) policies.
     *
     * Note: We use SET (not SET LOCAL) to persist across transactions within the same connection.
     *
     * @throws \RuntimeException if getEntityManager() method is not available
     */
    private function setTenantContext(string $tenantId): void
    {
        if (!method_exists($this, 'getEntityManager')) {
            throw new \RuntimeException('setTenantContext() requires getEntityManager() method. Make sure your test extends KernelTestCase and has access to EntityManager.');
        }

        /** @var EntityManagerInterface $em */
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Set PostgreSQL session variable for RLS (persists for entire connection)
        // Note: SET doesn't support prepared statements, so we use sprintf
        $connection->executeStatement(
            sprintf("SET app.tenant_id = '%s'", $tenantId)
        );
    }

    /**
     * Enable RLS bypass for tests (allows querying all tenants).
     *
     * This sets app.bypass_rls = 'true' which allows tests to bypass Row-Level Security
     * policies. Useful for functional tests that need to query multiple tenants or list
     * all tenants (e.g., admin interfaces).
     *
     * Security: This should ONLY be used in test environments.
     */
    private function enableRLSBypass(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Enable RLS bypass for this connection
        $connection->executeStatement("SET app.bypass_rls = 'true'");
    }

    /**
     * Disable RLS bypass (restore standard RLS protection).
     */
    private function disableRLSBypass(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Disable RLS bypass
        $connection->executeStatement("SET app.bypass_rls = 'false'");
    }

    /**
     * Get default tenant ID from environment.
     *
     * Returns the test tenant UUID configured in tests/bootstrap.php
     *
     * @throws \RuntimeException if DEFAULT_TENANT_ID is not set
     */
    private function getDefaultTenantId(): TenantId
    {
        if (!isset($_ENV['DEFAULT_TENANT_ID'])) {
            throw new \RuntimeException('DEFAULT_TENANT_ID not set in test environment. Check tests/bootstrap.php configuration.');
        }

        return TenantId::fromString($_ENV['DEFAULT_TENANT_ID']);
    }

    /**
     * Add X-Tenant-ID header for functional tests.
     *
     * Use this to add the tenant header to HTTP requests in functional tests.
     *
     * Example:
     * ```php
     * $client->request(
     *     'GET',
     *     '/api/products',
     *     [],
     *     [],
     *     $this->withTenantHeader([], $tenantId)
     * );
     * ```
     *
     * @param array<string, mixed> $headers  Existing headers
     * @param string               $tenantId Tenant UUID
     *
     * @return array<string, mixed> Headers with X-Tenant-ID added
     */
    private function withTenantHeader(array $headers, string $tenantId): array
    {
        return array_merge($headers, [
            'HTTP_X_TENANT_ID' => $tenantId,
        ]);
    }

    /**
     * Clean up test data for the current tenant.
     *
     * This method deletes all test data for the current tenant across all tables.
     * It should be called in setUp() and tearDown() to ensure test isolation.
     *
     * Note: Requires setTenantContext() to be called first to set RLS context.
     */
    private function cleanupTestData(): void
    {
        if (!isset($this->tenantId)) {
            return; // No tenant set, skip cleanup
        }

        $em = $this->getEntityManager();
        $connection = $em->getConnection();

        // Tables to clean up (in order due to foreign key constraints)
        $tables = [
            'password_reset_tokens',
            'cart_items',
            'carts',
            'stock_reservations',
            'stock_items',
            'payments',
            'return_requests',
            'orders',
            'catalog_variants',
            'catalog_options',
            'catalog_configurable_products',
            'catalog_products',
            'catalog_categories',
            'price_lists',
            'promotions',
            'tax_rules',
            'warehouses',
            'customers',
        ];

        foreach ($tables as $table) {
            try {
                $connection->executeStatement(
                    "DELETE FROM {$table} WHERE tenant_id = :tenant_id",
                    ['tenant_id' => $this->tenantId->toString()]
                );
            } catch (\Exception $e) {
                // Table might not exist or might not have tenant_id column - ignore
                continue;
            }
        }
    }

    /**
     * Get entity manager from test container.
     *
     * Override this in your test class if you need custom EM access.
     */
    private function getEntityManager(): EntityManagerInterface
    {
        // For ApiTestCase, use createClient() to get container
        if (method_exists($this, 'createClient')) {
            $client = static::createClient();
            /** @var EntityManagerInterface $em */
            $em = $client->getContainer()->get('doctrine.orm.entity_manager');

            return $em;
        }

        // For KernelTestCase, use getContainer() directly
        if (method_exists($this, 'getContainer')) {
            /** @var EntityManagerInterface $em */
            $em = $this->getContainer()->get('doctrine.orm.entity_manager');

            return $em;
        }

        throw new \RuntimeException('getEntityManager() requires getContainer() or createClient() method. Make sure your test extends KernelTestCase or ApiTestCase.');
    }
}
