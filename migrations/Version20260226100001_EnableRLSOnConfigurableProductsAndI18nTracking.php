<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enable Row-Level Security on tables that have tenant_id but were missing RLS policies:
 * - catalog_configurable_products (CRITICAL: FK parent of variants/options/option_values)
 * - i18n_backfill_tracking (MEDIUM: operational tracking table)
 *
 * These tables were originally intended to have RLS (listed in Version20251106143000)
 * but were skipped because they didn't exist at migration time.
 */
final class Version20260226100001_EnableRLSOnConfigurableProductsAndI18nTracking extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable RLS and tenant isolation policies on catalog_configurable_products and i18n_backfill_tracking';
    }

    public function up(Schema $schema): void
    {
        // catalog_configurable_products — CRITICAL
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'catalog_configurable_products') THEN
                    ALTER TABLE catalog_configurable_products ENABLE ROW LEVEL SECURITY;
                    ALTER TABLE catalog_configurable_products FORCE ROW LEVEL SECURITY;

                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies
                        WHERE tablename = 'catalog_configurable_products'
                        AND policyname = 'tenant_isolation'
                    ) THEN
                        CREATE POLICY tenant_isolation ON catalog_configurable_products
                            FOR ALL
                            USING (tenant_id::text = current_setting('app.tenant_id', true));
                    END IF;

                    GRANT ALL ON catalog_configurable_products TO ecom_admin;
                END IF;
            END $$;
            SQL);

        // i18n_backfill_tracking — MEDIUM
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'i18n_backfill_tracking') THEN
                    ALTER TABLE i18n_backfill_tracking ENABLE ROW LEVEL SECURITY;
                    ALTER TABLE i18n_backfill_tracking FORCE ROW LEVEL SECURITY;

                    IF NOT EXISTS (
                        SELECT 1 FROM pg_policies
                        WHERE tablename = 'i18n_backfill_tracking'
                        AND policyname = 'tenant_isolation'
                    ) THEN
                        CREATE POLICY tenant_isolation ON i18n_backfill_tracking
                            FOR ALL
                            USING (tenant_id::text = current_setting('app.tenant_id', true));
                    END IF;

                    GRANT ALL ON i18n_backfill_tracking TO ecom_admin;
                END IF;
            END $$;
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'catalog_configurable_products') THEN
                    DROP POLICY IF EXISTS tenant_isolation ON catalog_configurable_products;
                    ALTER TABLE catalog_configurable_products DISABLE ROW LEVEL SECURITY;
                END IF;
            END $$;
            SQL);

        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'i18n_backfill_tracking') THEN
                    DROP POLICY IF EXISTS tenant_isolation ON i18n_backfill_tracking;
                    ALTER TABLE i18n_backfill_tracking DISABLE ROW LEVEL SECURITY;
                END IF;
            END $$;
            SQL);
    }
}
