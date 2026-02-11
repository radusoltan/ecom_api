<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modify Tenant RLS Policy to Allow Bypass for Superadmins/Tests
 *
 * Updates the tenant_self_isolation RLS policy to allow bypass when app.bypass_rls is set.
 * This enables:
 * - Superadmin users to view all tenants
 * - Functional tests to query tenants without setting tenant context for each one
 * - Admin interfaces to list all tenants
 *
 * Security: bypass_rls should ONLY be set for authenticated superadmin users or test environments.
 */
final class Version20251127090500_AllowRLSBypassForTenants extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modify Tenant RLS policy to allow bypass for superadmins and tests';
    }

    public function up(Schema $schema): void
    {
        // Drop existing tenant_self_isolation policy
        $this->addSql("DROP POLICY IF EXISTS tenant_self_isolation ON tenants;");

        // Recreate policy with bypass condition
        $this->addSql("
            CREATE POLICY tenant_self_isolation ON tenants
                FOR ALL
                USING (
                    -- Allow bypass for tests/superadmins
                    current_setting('app.bypass_rls', true) = 'true'
                    OR
                    -- Standard tenant isolation
                    id = current_setting('app.tenant_id', true)
                );
        ");
    }

    public function down(Schema $schema): void
    {
        // Revert to original strict policy (tenant can only see itself)
        $this->addSql("DROP POLICY IF EXISTS tenant_self_isolation ON tenants;");

        $this->addSql("
            CREATE POLICY tenant_self_isolation ON tenants
                FOR ALL
                USING (id = current_setting('app.tenant_id', true));
        ");
    }
}
