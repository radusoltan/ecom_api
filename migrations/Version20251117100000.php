<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 3: Create tables for configurable products and variants
 */
final class Version20251117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for configurable products, options, option values, and variants';
    }

    public function up(Schema $schema): void
    {
        // Create configurable_products table
        $this->addSql('
            CREATE TABLE catalog_configurable_products (
                id UUID NOT NULL,
                product_id UUID NOT NULL,
                tenant_id VARCHAR(36) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id)
            )
        ');

        // Add indexes for configurable_products
        $this->addSql('CREATE UNIQUE INDEX uniq_configurable_products_product ON catalog_configurable_products (product_id)');
        $this->addSql('CREATE INDEX idx_configurable_products_tenant ON catalog_configurable_products (tenant_id)');
        $this->addSql('CREATE INDEX idx_configurable_products_tenant_product ON catalog_configurable_products (tenant_id, product_id)');

        // Create product_options table
        $this->addSql('
            CREATE TABLE catalog_product_options (
                id UUID NOT NULL,
                configurable_product_id UUID NOT NULL,
                code VARCHAR(32) NOT NULL,
                name_translations JSONB NOT NULL DEFAULT \'{}\',
                position INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                CONSTRAINT FK_options_configurable_product
                    FOREIGN KEY (configurable_product_id)
                    REFERENCES catalog_configurable_products(id)
                    ON DELETE CASCADE
            )
        ');

        // Add indexes for product_options
        $this->addSql('CREATE UNIQUE INDEX uniq_product_options_product_code ON catalog_product_options (configurable_product_id, code)');
        $this->addSql('CREATE INDEX idx_product_options_position ON catalog_product_options (configurable_product_id, position)');

        // Create product_option_values table
        $this->addSql('
            CREATE TABLE catalog_product_option_values (
                id UUID NOT NULL,
                option_id UUID NOT NULL,
                code VARCHAR(32) NOT NULL,
                name_translations JSONB NOT NULL DEFAULT \'{}\',
                position INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                CONSTRAINT FK_option_values_option
                    FOREIGN KEY (option_id)
                    REFERENCES catalog_product_options(id)
                    ON DELETE CASCADE
            )
        ');

        // Add indexes for product_option_values
        $this->addSql('CREATE UNIQUE INDEX uniq_option_values_option_code ON catalog_product_option_values (option_id, code)');
        $this->addSql('CREATE INDEX idx_option_values_position ON catalog_product_option_values (option_id, position)');

        // Create product_variants table
        $this->addSql('
            CREATE TABLE catalog_product_variants (
                id UUID NOT NULL,
                configurable_product_id UUID NOT NULL,
                sku VARCHAR(64) NOT NULL,
                option_value_map JSONB NOT NULL DEFAULT \'{}\',
                price_amount BIGINT NOT NULL,
                price_currency VARCHAR(3) NOT NULL,
                stock_on_hand INT NOT NULL DEFAULT 0,
                stock_reserved INT NOT NULL DEFAULT 0,
                track_inventory BOOLEAN NOT NULL DEFAULT true,
                allow_backorder BOOLEAN NOT NULL DEFAULT false,
                is_active BOOLEAN NOT NULL DEFAULT true,
                images JSONB NOT NULL DEFAULT \'[]\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(id),
                CONSTRAINT FK_variants_configurable_product
                    FOREIGN KEY (configurable_product_id)
                    REFERENCES catalog_configurable_products(id)
                    ON DELETE CASCADE
            )
        ');

        // Add indexes for product_variants
        $this->addSql('CREATE UNIQUE INDEX uniq_product_variants_sku ON catalog_product_variants (sku)');
        $this->addSql('CREATE INDEX idx_product_variants_product ON catalog_product_variants (configurable_product_id)');
        $this->addSql('CREATE INDEX idx_product_variants_product_active ON catalog_product_variants (configurable_product_id, is_active)');
        $this->addSql('CREATE INDEX idx_product_variants_option_map_gin ON catalog_product_variants USING GIN (option_value_map jsonb_path_ops)');

        // Add generated column for combination hash to ensure uniqueness
        $this->addSql('
            ALTER TABLE catalog_product_variants
            ADD COLUMN combo_hash VARCHAR(64) GENERATED ALWAYS AS (
                MD5(option_value_map::text)
            ) STORED
        ');

        $this->addSql('CREATE UNIQUE INDEX uniq_product_variants_combo ON catalog_product_variants (configurable_product_id, combo_hash)');

        // Add check constraints
        $this->addSql('ALTER TABLE catalog_product_options ADD CONSTRAINT check_option_code CHECK (code ~ \'^[a-z][a-z0-9_]{1,31}$\')');
        $this->addSql('ALTER TABLE catalog_product_option_values ADD CONSTRAINT check_value_code CHECK (code ~ \'^[a-z0-9][a-z0-9_-]{0,31}$\')');
        $this->addSql('ALTER TABLE catalog_product_variants ADD CONSTRAINT check_price_positive CHECK (price_amount >= 0)');
        $this->addSql('ALTER TABLE catalog_product_variants ADD CONSTRAINT check_stock_non_negative CHECK (stock_on_hand >= 0 AND stock_reserved >= 0)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order
        $this->addSql('DROP TABLE IF EXISTS catalog_product_variants');
        $this->addSql('DROP TABLE IF EXISTS catalog_product_option_values');
        $this->addSql('DROP TABLE IF EXISTS catalog_product_options');
        $this->addSql('DROP TABLE IF EXISTS catalog_configurable_products');
    }
}