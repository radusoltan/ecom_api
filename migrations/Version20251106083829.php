<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251106083829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE IF EXISTS product_reviews');
        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_cart_items_cart') THEN
                    ALTER TABLE cart_items DROP CONSTRAINT fk_cart_items_cart;
                END IF;
            END $$;
        ");
        $this->addSql('ALTER TABLE orders ALTER discount_amount TYPE INT');
        $this->addSql('ALTER TABLE orders ALTER tax_amount TYPE INT');
        $this->addSql('DROP INDEX IF EXISTS idx_wishlists_customer_id');
        $this->addSql('ALTER TABLE wishlists ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE wishlists ALTER tenant_id TYPE UUID');
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'idx_wishlists_tenant_id') THEN
                    ALTER INDEX idx_wishlists_tenant_id RENAME TO idx_wishlists_tenant;
                END IF;
            END $$;
        ");
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'uniq_wishlists_customer_tenant') THEN
                    ALTER INDEX uniq_wishlists_customer_tenant RENAME TO uniq_customer_tenant;
                END IF;
            END $$;
        ");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE product_reviews (id UUID NOT NULL, product_id UUID NOT NULL, customer_id UUID DEFAULT NULL, tenant_id UUID NOT NULL, rating SMALLINT NOT NULL, title VARCHAR(255) DEFAULT NULL, content TEXT DEFAULT NULL, is_verified_purchase BOOLEAN DEFAULT false, status VARCHAR(20) DEFAULT \'pending\', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL, customer_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_reviews_customer ON product_reviews (customer_id)');
        $this->addSql('CREATE INDEX idx_reviews_product ON product_reviews (product_id)');
        $this->addSql('CREATE INDEX idx_reviews_product_status ON product_reviews (product_id, status)');
        $this->addSql('CREATE INDEX idx_reviews_status ON product_reviews (status)');
        $this->addSql('CREATE INDEX idx_reviews_tenant ON product_reviews (tenant_id)');
        $this->addSql('CREATE TABLE IF NOT EXISTS audit_log (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID DEFAULT NULL, action_type VARCHAR(50) NOT NULL, resource_type VARCHAR(50) NOT NULL, resource_id VARCHAR(255) NOT NULL, metadata JSONB DEFAULT \'{}\' NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_audit_log_action_type ON audit_log (action_type)');
        $this->addSql('CREATE INDEX idx_audit_log_occurred_at ON audit_log (occurred_at)');
        $this->addSql('CREATE INDEX idx_audit_log_resource_id ON audit_log (resource_id)');
        $this->addSql('CREATE INDEX idx_audit_log_resource_type ON audit_log (resource_type)');
        $this->addSql('CREATE INDEX idx_audit_log_tenant_id ON audit_log (tenant_id)');
        $this->addSql('CREATE INDEX idx_audit_log_user_id ON audit_log (user_id)');
        $this->addSql('COMMENT ON TABLE audit_log IS \'Comprehensive audit trail for all user actions in the system\'');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE wishlists ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE wishlists ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE wishlists ALTER tenant_id TYPE UUID');
        $this->addSql('ALTER TABLE wishlists ALTER tenant_id TYPE UUID');
        $this->addSql('CREATE INDEX idx_wishlists_customer_id ON wishlists (customer_id)');
        $this->addSql('ALTER INDEX idx_wishlists_tenant RENAME TO idx_wishlists_tenant_id');
        $this->addSql('ALTER INDEX uniq_customer_tenant RENAME TO uniq_wishlists_customer_tenant');
        $this->addSql('ALTER TABLE orders ALTER discount_amount TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE orders ALTER tax_amount TYPE DOUBLE PRECISION');
    }
}
