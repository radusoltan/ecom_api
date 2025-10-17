<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add JSONB columns for translations to products and categories
 */
final class Version20251116100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add JSONB columns for translations to products and categories, with GIN indexes for performance';
    }

    public function up(Schema $schema): void
    {
        // Add JSONB columns to products
        $this->addSql("ALTER TABLE catalog_products ADD COLUMN name_translations JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("ALTER TABLE catalog_products ADD COLUMN description_translations JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("ALTER TABLE catalog_products ADD COLUMN short_description_translations JSONB NOT NULL DEFAULT '{}'::jsonb");

        // Add JSONB columns to categories
        $this->addSql("ALTER TABLE catalog_categories ADD COLUMN name_translations JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("ALTER TABLE catalog_categories ADD COLUMN description_translations JSONB NOT NULL DEFAULT '{}'::jsonb");

        // Add GIN indexes for JSONB query performance
        $this->addSql('CREATE INDEX idx_products_name_tr_gin ON catalog_products USING GIN (name_translations jsonb_path_ops)');
        $this->addSql('CREATE INDEX idx_products_desc_tr_gin ON catalog_products USING GIN (description_translations jsonb_path_ops)');
        $this->addSql('CREATE INDEX idx_categories_name_tr_gin ON catalog_categories USING GIN (name_translations jsonb_path_ops)');
        $this->addSql('CREATE INDEX idx_categories_desc_tr_gin ON catalog_categories USING GIN (description_translations jsonb_path_ops)');

        // Create tracking table for backfill progress
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS i18n_backfill_tracking (
                id SERIAL PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                tenant_id VARCHAR(36) NOT NULL,
                last_processed_id VARCHAR(36),
                total_count INT DEFAULT 0,
                processed_count INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'pending',
                started_at TIMESTAMP,
                completed_at TIMESTAMP,
                error_message TEXT,
                UNIQUE(entity_type, tenant_id)
            )
        SQL);

        // Add indexes for tracking table
        $this->addSql('CREATE INDEX idx_backfill_status ON i18n_backfill_tracking(status)');
        $this->addSql('CREATE INDEX idx_backfill_entity_tenant ON i18n_backfill_tracking(entity_type, tenant_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove tracking table
        $this->addSql('DROP TABLE IF EXISTS i18n_backfill_tracking');

        // Remove GIN indexes
        $this->addSql('DROP INDEX IF EXISTS idx_products_name_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_products_desc_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_categories_name_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_categories_desc_tr_gin');

        // Remove JSONB columns from products
        $this->addSql('ALTER TABLE catalog_products DROP COLUMN IF EXISTS name_translations');
        $this->addSql('ALTER TABLE catalog_products DROP COLUMN IF EXISTS description_translations');
        $this->addSql('ALTER TABLE catalog_products DROP COLUMN IF EXISTS short_description_translations');

        // Remove JSONB columns from categories
        $this->addSql('ALTER TABLE catalog_categories DROP COLUMN IF EXISTS name_translations');
        $this->addSql('ALTER TABLE catalog_categories DROP COLUMN IF EXISTS description_translations');
    }
}