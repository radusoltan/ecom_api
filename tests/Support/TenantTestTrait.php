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
     * Get entity manager from test container.
     *
     * Override this in your test class if you need custom EM access.
     */
    private function getEntityManager(): EntityManagerInterface
    {
        if (!method_exists($this, 'getContainer')) {
            throw new \RuntimeException('getEntityManager() requires getContainer() method. Make sure your test extends KernelTestCase.');
        }

        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
