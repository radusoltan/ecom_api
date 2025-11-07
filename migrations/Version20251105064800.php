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
        // Only apply if product_reviews table exists
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'product_reviews') THEN
                    -- Rename 'comment' column to 'content'
                    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product_reviews' AND column_name = 'comment') THEN
                        ALTER TABLE product_reviews RENAME COLUMN comment TO content;
                    END IF;

                    -- Rename 'verified_purchase' to 'is_verified_purchase'
                    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product_reviews' AND column_name = 'verified_purchase') THEN
                        ALTER TABLE product_reviews RENAME COLUMN verified_purchase TO is_verified_purchase;
                    END IF;

                    -- Add customer_name column (nullable)
                    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'product_reviews' AND column_name = 'customer_name') THEN
                        ALTER TABLE product_reviews ADD COLUMN customer_name VARCHAR(255) DEFAULT NULL;
                    END IF;

                    -- Drop helpful_count as it's not in current entity
                    ALTER TABLE product_reviews DROP COLUMN IF EXISTS helpful_count;

                    -- Change rating to smallint for efficiency
                    ALTER TABLE product_reviews ALTER COLUMN rating TYPE SMALLINT;

                    -- Update customer_id to be nullable (for anonymous ratings)
                    ALTER TABLE product_reviews ALTER COLUMN customer_id DROP NOT NULL;

                    -- Add indexes for better query performance
                    CREATE INDEX IF NOT EXISTS idx_reviews_product ON product_reviews (product_id);
                    CREATE INDEX IF NOT EXISTS idx_reviews_tenant ON product_reviews (tenant_id);
                    CREATE INDEX IF NOT EXISTS idx_reviews_customer ON product_reviews (customer_id);
                    CREATE INDEX IF NOT EXISTS idx_reviews_status ON product_reviews (status);
                    CREATE INDEX IF NOT EXISTS idx_reviews_product_status ON product_reviews (product_id, status);

                    -- Drop old indexes if they exist with different names
                    DROP INDEX IF EXISTS idx_reviews_product_id;
                    DROP INDEX IF EXISTS idx_reviews_tenant_id;
                END IF;
            END $$;
        ");
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
