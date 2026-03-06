<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add tenant_id + RLS + FORCE ROW LEVEL SECURITY to child tables
 * that inherit tenant isolation from their parent aggregates.
 *
 * Tables affected:
 * - catalog_product_options (parent: catalog_configurable_products)
 * - catalog_product_option_values (parent: catalog_product_options -> catalog_configurable_products)
 * - catalog_product_variants (parent: catalog_configurable_products)
 * - transactions (parent: payments)
 */
final class Version20260306190000_ForceRLSOnChildTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant_id column, RLS policies, and FORCE RLS to catalog child tables and transactions';
    }

    public function up(Schema $schema): void
    {
        // 1. Add tenant_id columns (nullable first for backfill)
        $this->addSql('ALTER TABLE catalog_product_options ADD COLUMN IF NOT EXISTS tenant_id UUID');
        $this->addSql('ALTER TABLE catalog_product_option_values ADD COLUMN IF NOT EXISTS tenant_id UUID');
        $this->addSql('ALTER TABLE catalog_product_variants ADD COLUMN IF NOT EXISTS tenant_id UUID');
        $this->addSql('ALTER TABLE transactions ADD COLUMN IF NOT EXISTS tenant_id UUID');

        // 2. Backfill tenant_id from parent tables
        $defaultTenant = '00000000-0000-4000-8000-000000000001';

        $this->addSql('
            UPDATE catalog_product_options o
            SET tenant_id = cp.tenant_id
            FROM catalog_configurable_products cp
            WHERE o.configurable_product_id = cp.id
            AND o.tenant_id IS NULL
        ');

        // Assign default tenant to orphaned records (no matching parent)
        $this->addSql("
            UPDATE catalog_product_options
            SET tenant_id = '{$defaultTenant}'::uuid
            WHERE tenant_id IS NULL
        ");

        $this->addSql('
            UPDATE catalog_product_option_values ov
            SET tenant_id = o.tenant_id
            FROM catalog_product_options o
            WHERE ov.option_id = o.id
            AND ov.tenant_id IS NULL
        ');

        $this->addSql("
            UPDATE catalog_product_option_values
            SET tenant_id = '{$defaultTenant}'::uuid
            WHERE tenant_id IS NULL
        ");

        $this->addSql('
            UPDATE catalog_product_variants v
            SET tenant_id = cp.tenant_id
            FROM catalog_configurable_products cp
            WHERE v.configurable_product_id = cp.id
            AND v.tenant_id IS NULL
        ');

        $this->addSql("
            UPDATE catalog_product_variants
            SET tenant_id = '{$defaultTenant}'::uuid
            WHERE tenant_id IS NULL
        ");

        $this->addSql('
            UPDATE transactions t
            SET tenant_id = p.tenant_id
            FROM payments p
            WHERE t.payment_id = p.id::text
            AND t.tenant_id IS NULL
        ');

        $this->addSql("
            UPDATE transactions
            SET tenant_id = '{$defaultTenant}'::uuid
            WHERE tenant_id IS NULL
        ");

        // 3. Set NOT NULL constraint
        $this->addSql('ALTER TABLE catalog_product_options ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql('ALTER TABLE catalog_product_option_values ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql('ALTER TABLE transactions ALTER COLUMN tenant_id SET NOT NULL');

        // 4. Add indexes
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_options_tenant ON catalog_product_options (tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_option_values_tenant ON catalog_product_option_values (tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_variants_tenant ON catalog_product_variants (tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_transactions_tenant ON transactions (tenant_id)');

        // 5. Create RLS policies and enable FORCE RLS
        $tables = [
            'catalog_product_options',
            'catalog_product_option_values',
            'catalog_product_variants',
            'transactions',
        ];

        foreach ($tables as $table) {
            $this->addSql(sprintf("
                DO $$ BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies WHERE tablename = '%s' AND policyname = 'tenant_isolation'
                    ) THEN
                        CREATE POLICY tenant_isolation ON %s
                            FOR ALL
                            USING (tenant_id::text = current_setting('app.tenant_id', true));
                    END IF;
                END $$;
            ", $table, $table));

            $this->addSql(sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
            $this->addSql(sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table));
            $this->addSql(sprintf('GRANT ALL ON %s TO ecom_admin', $table));
        }
    }

    public function down(Schema $schema): void
    {
        $tables = [
            'catalog_product_options',
            'catalog_product_option_values',
            'catalog_product_variants',
            'transactions',
        ];

        foreach ($tables as $table) {
            $this->addSql(sprintf('DROP POLICY IF EXISTS tenant_isolation ON %s', $table));
            $this->addSql(sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $table));
            $this->addSql(sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table));
        }

        $this->addSql('DROP INDEX IF EXISTS idx_product_options_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_option_values_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_product_variants_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_transactions_tenant');

        $this->addSql('ALTER TABLE catalog_product_options DROP COLUMN IF EXISTS tenant_id');
        $this->addSql('ALTER TABLE catalog_product_option_values DROP COLUMN IF EXISTS tenant_id');
        $this->addSql('ALTER TABLE catalog_product_variants DROP COLUMN IF EXISTS tenant_id');
        $this->addSql('ALTER TABLE transactions DROP COLUMN IF EXISTS tenant_id');
    }
}
