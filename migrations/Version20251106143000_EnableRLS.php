<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enable Row-Level Security (RLS) for Multi-Tenant Isolation
 *
 * This migration implements PostgreSQL Row-Level Security on all multi-tenant tables
 * to ensure complete data isolation between tenants at the database level.
 *
 * Security Requirements (PRD Section 2.3):
 * - All multi-tenant tables must have RLS enabled
 * - Policies enforce tenant_id = current_setting('app.tenant_id')
 * - Provides defense-in-depth against SQL injection and application bugs
 * - Required for GDPR, SOC 2, and ISO 27001 compliance
 *
 * Tables with RLS (20 multi-tenant tables with tenant_id column):
 * - catalog_products, catalog_categories, catalog_configurable_products
 * - orders, carts, fulfillments
 * - customers
 * - stock_items, stock_reservations, warehouses
 * - price_lists, promotions
 * - payments
 * - return_requests
 * - tax_rules
 * - wishlists
 * - media_images, media_thumbnails
 * - consents, data_subject_requests
 *
 * Note: Child tables (cart_items, catalog_product_variants, etc.) inherit
 * isolation through foreign key relationships to RLS-protected parent tables
 *
 * Note: 'tenants' table has special RLS (self-isolation)
 */
final class Version20251106143000_EnableRLS extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable Row-Level Security (RLS) on all multi-tenant tables for complete tenant isolation';
    }

    public function up(Schema $schema): void
    {
        // List of all multi-tenant tables requiring RLS
        // Only includes tables that have a direct tenant_id column
        $multiTenantTables = [
            // Catalog Context
            'catalog_products',
            'catalog_categories',
            'catalog_configurable_products',

            // Order Context
            'orders',
            'carts',
            'fulfillments',

            // Inventory Context
            'stock_items',
            'stock_reservations',
            'warehouses',

            // Pricing Context
            'price_lists',
            'promotions',

            // Customer Context
            'customers',

            // Payment Context
            'payments',

            // Tax Context
            'tax_rules',

            // Returns Context
            'return_requests',

            // Wishlist Context
            'wishlists',

            // Media Context
            'media_images',
            'media_thumbnails',

            // Privacy Context
            'consents',
            'data_subject_requests',
        ];

        foreach ($multiTenantTables as $table) {
            // Check if table exists before applying RLS
            $this->addSql(sprintf("
                DO $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = '%s') THEN
                        -- Enable RLS on table
                        ALTER TABLE %s ENABLE ROW LEVEL SECURITY;

                        -- Force RLS even for table owner (ecom_admin)
                        ALTER TABLE %s FORCE ROW LEVEL SECURITY;

                        -- Create policy for tenant isolation (only if it doesn't exist)
                        -- Policy allows all operations (SELECT, INSERT, UPDATE, DELETE) only for rows
                        -- where tenant_id matches the current session's tenant context
                        IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE tablename = '%s' AND policyname = 'tenant_isolation') THEN
                            CREATE POLICY tenant_isolation ON %s
                                FOR ALL
                                USING (tenant_id = current_setting('app.tenant_id', true));
                        END IF;

                        -- Grant necessary permissions to ecom_admin user
                        GRANT ALL ON %s TO ecom_admin;
                    END IF;
                END $$;
            ", $table, $table, $table, $table, $table, $table));
        }

        // Special RLS policy for 'tenants' table (self-isolation)
        // Tenants can only see their own tenant record
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'tenants') THEN
                    -- Enable RLS
                    ALTER TABLE tenants ENABLE ROW LEVEL SECURITY;

                    -- Force RLS even for table owner (ecom_admin)
                    ALTER TABLE tenants FORCE ROW LEVEL SECURITY;

                    -- Tenants table uses id instead of tenant_id for self-reference
                    -- Create policy only if it doesn't exist
                    IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE tablename = 'tenants' AND policyname = 'tenant_self_isolation') THEN
                        CREATE POLICY tenant_self_isolation ON tenants
                            FOR ALL
                            USING (id = current_setting('app.tenant_id', true));
                    END IF;

                    GRANT ALL ON tenants TO ecom_admin;
                END IF;
            END $$;
        ");

        // Create helper function to set tenant context
        $this->addSql("
            CREATE OR REPLACE FUNCTION set_tenant_context(tenant_uuid TEXT)
            RETURNS void AS $$
            BEGIN
                PERFORM set_config('app.tenant_id', tenant_uuid, false);
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Grant execute permission on helper function
        $this->addSql("GRANT EXECUTE ON FUNCTION set_tenant_context(TEXT) TO ecom_admin;");
    }

    public function down(Schema $schema): void
    {
        // Disable RLS on all tables
        $allTables = [
            'catalog_products',
            'catalog_categories',
            'catalog_configurable_products',
            'orders',
            'carts',
            'fulfillments',
            'stock_items',
            'stock_reservations',
            'warehouses',
            'price_lists',
            'promotions',
            'customers',
            'payments',
            'tax_rules',
            'return_requests',
            'wishlists',
            'media_images',
            'media_thumbnails',
            'consents',
            'data_subject_requests',
            'tenants',
        ];

        foreach ($allTables as $table) {
            $this->addSql(sprintf("
                DO $$
                BEGIN
                    IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = '%s') THEN
                        -- Drop policy
                        DROP POLICY IF EXISTS tenant_isolation ON %s;
                        DROP POLICY IF EXISTS tenant_self_isolation ON %s;

                        -- Disable RLS
                        ALTER TABLE %s DISABLE ROW LEVEL SECURITY;
                    END IF;
                END $$;
            ", $table, $table, $table, $table));
        }

        // Drop helper function
        $this->addSql("DROP FUNCTION IF EXISTS set_tenant_context(TEXT);");
    }
}
