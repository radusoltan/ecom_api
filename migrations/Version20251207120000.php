<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bundle Items Migration.
 *
 * Creates the bundle_items table for storing product bundle configurations.
 *
 * Business Rules:
 * - Each bundle item references a bundle product and a bundled product
 * - Quantity must be at least 1
 * - Price captured at bundle creation time (fixed, not dynamic)
 * - Unique constraint: one product can appear only once per bundle
 * - Cascade delete: removing bundle product removes all bundle items
 */
final class Version20251207120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bundle_items table for product bundles functionality';
    }

    public function up(Schema $schema): void
    {
        // Check if table already exists (idempotency)
        if ($schema->hasTable('bundle_items')) {
            return;
        }

        $this->addSql('
            CREATE TABLE bundle_items (
                id UUID PRIMARY KEY,
                bundle_product_id UUID NOT NULL,
                product_id UUID NOT NULL,
                quantity INT NOT NULL CHECK (quantity >= 1),
                price_amount INT NOT NULL CHECK (price_amount >= 0),
                price_currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                -- Foreign keys
                CONSTRAINT fk_bundle_items_bundle_product
                    FOREIGN KEY (bundle_product_id)
                    REFERENCES catalog_products(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_bundle_items_product
                    FOREIGN KEY (product_id)
                    REFERENCES catalog_products(id)
                    ON DELETE CASCADE,

                -- Unique constraint: one product per bundle
                CONSTRAINT uq_bundle_product
                    UNIQUE (bundle_product_id, product_id)
            )
        ');

        // Create indexes for performance
        $this->addSql('CREATE INDEX idx_bundle_items_bundle_product ON bundle_items(bundle_product_id)');
        $this->addSql('CREATE INDEX idx_bundle_items_product ON bundle_items(product_id)');
        $this->addSql('CREATE INDEX idx_bundle_items_created_at ON bundle_items(created_at)');

        // Add comment to table
        $this->addSql('COMMENT ON TABLE bundle_items IS \'Stores bundle item configurations for bundle products\'');
        $this->addSql('COMMENT ON COLUMN bundle_items.bundle_product_id IS \'Reference to the bundle product (type=bundle)\'');
        $this->addSql('COMMENT ON COLUMN bundle_items.product_id IS \'Reference to the product included in the bundle\'');
        $this->addSql('COMMENT ON COLUMN bundle_items.quantity IS \'Quantity of this product in the bundle (min: 1)\'');
        $this->addSql('COMMENT ON COLUMN bundle_items.price_amount IS \'Price captured at bundle creation (cents, immutable)\'');
        $this->addSql('COMMENT ON COLUMN bundle_items.price_currency IS \'Currency code (ISO 4217)\'');
    }

    public function down(Schema $schema): void
    {
        // Drop table (cascade will handle foreign keys)
        $this->addSql('DROP TABLE IF EXISTS bundle_items CASCADE');
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
