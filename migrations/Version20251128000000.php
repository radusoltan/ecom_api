<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Price History Table Migration.
 *
 * Creates the price_history table for tracking all price changes.
 * Required for:
 * - Audit trail and compliance
 * - EU consumer protection laws (lowest price in last 30 days)
 * - Analytics and reporting
 */
final class Version20251128000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create price_history table for price change audit trail';
    }

    public function up(Schema $schema): void
    {
        // Create price_history table
        $this->addSql('
            CREATE TABLE IF NOT EXISTS price_history (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                product_id UUID NOT NULL,
                old_price_amount NUMERIC(19, 4) DEFAULT NULL,
                old_price_currency VARCHAR(3) DEFAULT NULL,
                new_price_amount NUMERIC(19, 4) DEFAULT NULL,
                new_price_currency VARCHAR(3) DEFAULT NULL,
                change_reason TEXT DEFAULT NULL,
                changed_by UUID DEFAULT NULL,
                changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                source VARCHAR(20) NOT NULL,
                PRIMARY KEY(id)
            )
        ');

        // Add comment to table
        $this->addSql('
            COMMENT ON TABLE price_history IS
            \'Audit trail for all price changes. Immutable records for compliance.\'
        ');

        // Add comments to columns
        $this->addSql('COMMENT ON COLUMN price_history.source IS \'manual, import, promotion, flash_sale\'');
        $this->addSql('COMMENT ON COLUMN price_history.changed_at IS \'Timestamp stored as IMMUTABLE\'');

        // Convert changed_at column to timestamp with immutable type
        $this->addSql('ALTER TABLE price_history ALTER COLUMN changed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN price_history.changed_at IS \'(DC2Type:datetime_immutable)\'');

        // Create indexes for efficient querying
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_price_history_tenant ON price_history (tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_price_history_product ON price_history (product_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_price_history_changed_at ON price_history (changed_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_price_history_source ON price_history (source)');

        // Composite index for most common query pattern (tenant + product + date)
        $this->addSql('
            CREATE INDEX IF NOT EXISTS idx_price_history_tenant_product_date
            ON price_history (tenant_id, product_id, changed_at DESC)
        ');

        // Foreign key to products table (with cascade on delete)
        $this->addSql('
            ALTER TABLE price_history
            ADD CONSTRAINT fk_price_history_product
            FOREIGN KEY (product_id)
            REFERENCES catalog_products (id)
            ON DELETE CASCADE
        ');

        // Foreign key to tenants table (with cascade on delete)
        $this->addSql('
            ALTER TABLE price_history
            ADD CONSTRAINT fk_price_history_tenant
            FOREIGN KEY (tenant_id)
            REFERENCES tenants (id)
            ON DELETE CASCADE
        ');

        // Optional: Foreign key to users table (if exists)
        // Commented out because changed_by can be NULL (system changes)
        // and we don't want to block user deletion
        /*
        $this->addSql('
            ALTER TABLE price_history
            ADD CONSTRAINT fk_price_history_user
            FOREIGN KEY (changed_by)
            REFERENCES users (id)
            ON DELETE SET NULL
        ');
        */

        // Add check constraint for source values
        $this->addSql('
            ALTER TABLE price_history
            ADD CONSTRAINT chk_price_history_source
            CHECK (source IN (\'manual\', \'import\', \'promotion\', \'flash_sale\'))
        ');

        // Add check constraint: at least one price must be present
        $this->addSql('
            ALTER TABLE price_history
            ADD CONSTRAINT chk_price_history_has_price
            CHECK (
                old_price_amount IS NOT NULL OR new_price_amount IS NOT NULL
            )
        ');

        // Add check constraint: if price exists, currency must exist
        $this->addSql('
            ALTER TABLE price_history
            ADD CONSTRAINT chk_price_history_old_price_currency
            CHECK (
                (old_price_amount IS NULL AND old_price_currency IS NULL) OR
                (old_price_amount IS NOT NULL AND old_price_currency IS NOT NULL)
            )
        ');

        $this->addSql('
            ALTER TABLE price_history
            ADD CONSTRAINT chk_price_history_new_price_currency
            CHECK (
                (new_price_amount IS NULL AND new_price_currency IS NULL) OR
                (new_price_amount IS NOT NULL AND new_price_currency IS NOT NULL)
            )
        ');

        // Enable Row-Level Security (RLS) for multi-tenancy
        $this->addSql('ALTER TABLE price_history ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for tenant isolation
        $this->addSql('
            CREATE POLICY tenant_isolation ON price_history
            FOR ALL
            USING (tenant_id::text = current_setting(\'app.tenant_id\', true))
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop RLS policy
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON price_history');

        // Disable RLS
        $this->addSql('ALTER TABLE price_history DISABLE ROW LEVEL SECURITY');

        // Drop table (cascades will handle foreign keys)
        $this->addSql('DROP TABLE IF EXISTS price_history CASCADE');
    }
}
