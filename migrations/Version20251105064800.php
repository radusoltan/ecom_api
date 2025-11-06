<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to align product_reviews table with ProductReviewEntity
 */
final class Version20251105064800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align product_reviews table structure with ProductReviewEntity (rename columns, add customer_name, add Gedmo translatable support)';
    }

    public function up(Schema $schema): void
    {
        // Rename 'comment' column to 'content'
        $this->addSql('ALTER TABLE product_reviews RENAME COLUMN comment TO content');

        // Rename 'verified_purchase' to 'is_verified_purchase'
        $this->addSql('ALTER TABLE product_reviews RENAME COLUMN verified_purchase TO is_verified_purchase');

        // Add customer_name column (nullable)
        $this->addSql('ALTER TABLE product_reviews ADD COLUMN customer_name VARCHAR(255) DEFAULT NULL');

        // Drop helpful_count as it's not in current entity (can be added later if needed)
        $this->addSql('ALTER TABLE product_reviews DROP COLUMN IF EXISTS helpful_count');

        // Change rating to smallint for efficiency
        $this->addSql('ALTER TABLE product_reviews ALTER COLUMN rating TYPE SMALLINT');

        // Update customer_id to be nullable (for anonymous ratings)
        $this->addSql('ALTER TABLE product_reviews ALTER COLUMN customer_id DROP NOT NULL');

        // Add indexes for better query performance (matching entity annotations)
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reviews_product ON product_reviews (product_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reviews_tenant ON product_reviews (tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reviews_customer ON product_reviews (customer_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reviews_status ON product_reviews (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reviews_product_status ON product_reviews (product_id, status)');

        // Drop old indexes if they exist with different names
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_product_id');
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_tenant_id');
    }

    public function down(Schema $schema): void
    {
        // Reverse the changes
        $this->addSql('ALTER TABLE product_reviews RENAME COLUMN content TO comment');
        $this->addSql('ALTER TABLE product_reviews RENAME COLUMN is_verified_purchase TO verified_purchase');
        $this->addSql('ALTER TABLE product_reviews DROP COLUMN IF EXISTS customer_name');
        $this->addSql('ALTER TABLE product_reviews ADD COLUMN helpful_count INT DEFAULT 0');
        $this->addSql('ALTER TABLE product_reviews ALTER COLUMN rating TYPE INT');
        $this->addSql('ALTER TABLE product_reviews ALTER COLUMN customer_id SET NOT NULL');

        // Restore old indexes
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_product');
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_customer');
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_product_status');
        $this->addSql('CREATE INDEX idx_reviews_product_id ON product_reviews (product_id)');
        $this->addSql('CREATE INDEX idx_reviews_tenant_id ON product_reviews (tenant_id)');
    }
}
