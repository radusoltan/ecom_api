<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add bundle discount column to catalog_products.
 *
 * Adds discount_percentage column for bundle products.
 */
final class Version20251207120001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bundle_discount_percentage column to catalog_products table';
    }

    public function up(Schema $schema): void
    {
        // Check if column already exists (idempotency)
        $table = $schema->getTable('catalog_products');
        if ($table->hasColumn('bundle_discount_percentage')) {
            return;
        }

        $this->addSql('
            ALTER TABLE catalog_products
            ADD COLUMN bundle_discount_percentage DECIMAL(5,2)
            CHECK (bundle_discount_percentage IS NULL OR (bundle_discount_percentage >= 0 AND bundle_discount_percentage <= 50))
        ');

        $this->addSql('COMMENT ON COLUMN catalog_products.bundle_discount_percentage IS \'Bundle discount percentage (0-50%, NULL for non-bundle products)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_products DROP COLUMN IF EXISTS bundle_discount_percentage');
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
