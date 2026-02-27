<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P2-2: Add tenant_id + RLS to bundle_items and cart_items child tables.
 *
 * These tables previously relied on FK-JOIN isolation via parent tables.
 * This migration adds explicit tenant_id columns with RLS policies.
 */
final class Version20260227120001_AddRLSToChildTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant_id and RLS policies to bundle_items and cart_items child tables';
    }

    public function up(Schema $schema): void
    {
        // --- bundle_items ---
        // Add tenant_id column (nullable first for backfill)
        $this->addSql('ALTER TABLE bundle_items ADD COLUMN tenant_id UUID');

        // Backfill from parent table (catalog_products via bundle_product_id)
        $this->addSql('UPDATE bundle_items bi SET tenant_id = cp.tenant_id FROM catalog_products cp WHERE bi.bundle_product_id = cp.id');

        // Make NOT NULL after backfill
        $this->addSql('ALTER TABLE bundle_items ALTER COLUMN tenant_id SET NOT NULL');

        // Add index
        $this->addSql('CREATE INDEX idx_bundle_items_tenant_id ON bundle_items (tenant_id)');

        // Enable RLS
        $this->addSql('ALTER TABLE bundle_items ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE bundle_items FORCE ROW LEVEL SECURITY');

        // Create RLS policy
        $this->addSql("CREATE POLICY tenant_isolation ON bundle_items USING (tenant_id::text = current_setting('app.tenant_id', true))");

        // --- cart_items ---
        // Add tenant_id column (nullable first for backfill)
        $this->addSql('ALTER TABLE cart_items ADD COLUMN tenant_id UUID');

        // Backfill from parent table (carts via cart_id)
        $this->addSql('UPDATE cart_items ci SET tenant_id = c.tenant_id FROM carts c WHERE ci.cart_id = c.id');

        // Make NOT NULL after backfill
        $this->addSql('ALTER TABLE cart_items ALTER COLUMN tenant_id SET NOT NULL');

        // Add index
        $this->addSql('CREATE INDEX idx_cart_items_tenant_id ON cart_items (tenant_id)');

        // Enable RLS
        $this->addSql('ALTER TABLE cart_items ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE cart_items FORCE ROW LEVEL SECURITY');

        // Create RLS policy
        $this->addSql("CREATE POLICY tenant_isolation ON cart_items USING (tenant_id::text = current_setting('app.tenant_id', true))");
    }

    public function down(Schema $schema): void
    {
        // bundle_items
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON bundle_items');
        $this->addSql('ALTER TABLE bundle_items DISABLE ROW LEVEL SECURITY');
        $this->addSql('DROP INDEX IF EXISTS idx_bundle_items_tenant_id');
        $this->addSql('ALTER TABLE bundle_items DROP COLUMN IF EXISTS tenant_id');

        // cart_items
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON cart_items');
        $this->addSql('ALTER TABLE cart_items DISABLE ROW LEVEL SECURITY');
        $this->addSql('DROP INDEX IF EXISTS idx_cart_items_tenant_id');
        $this->addSql('ALTER TABLE cart_items DROP COLUMN IF EXISTS tenant_id');
    }
}
