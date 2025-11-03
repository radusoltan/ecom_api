<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 1 - Day 1-2: Create tables for Cart bounded context
 */
final class Version20251101100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create carts and cart_items tables for Cart bounded context';
    }

    public function up(Schema $schema): void
    {
        // Create carts table
        $this->addSql('
            CREATE TABLE carts (
                id VARCHAR(26) NOT NULL,
                tenant_id VARCHAR(36) NOT NULL,
                customer_id VARCHAR(36) DEFAULT NULL,
                session_id VARCHAR(36) DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ');

        // Add comment for timestamp format
        $this->addSql('COMMENT ON COLUMN carts.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN carts.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Add indexes for carts table
        $this->addSql('CREATE INDEX idx_carts_tenant_id ON carts (tenant_id)');
        $this->addSql('CREATE INDEX idx_carts_customer_id ON carts (customer_id)');
        $this->addSql('CREATE INDEX idx_carts_session_id ON carts (session_id)');
        $this->addSql('CREATE INDEX idx_carts_status ON carts (status)');
        $this->addSql('CREATE INDEX idx_carts_updated_at ON carts (updated_at)');
        $this->addSql('CREATE INDEX idx_carts_tenant_customer ON carts (tenant_id, customer_id)');
        $this->addSql('CREATE INDEX idx_carts_tenant_session ON carts (tenant_id, session_id)');

        // Create cart_items table
        $this->addSql('
            CREATE TABLE cart_items (
                id VARCHAR(26) NOT NULL,
                cart_id VARCHAR(26) NOT NULL,
                product_id VARCHAR(36) NOT NULL,
                variant_id VARCHAR(36) DEFAULT NULL,
                quantity INT NOT NULL,
                unit_price_amount DOUBLE PRECISION NOT NULL,
                unit_price_currency VARCHAR(3) NOT NULL,
                PRIMARY KEY(id)
            )
        ');

        // Add indexes for cart_items table
        $this->addSql('CREATE INDEX idx_cart_items_cart_id ON cart_items (cart_id)');
        $this->addSql('CREATE INDEX idx_cart_items_product ON cart_items (product_id, variant_id)');

        // Add foreign key constraint
        $this->addSql('
            ALTER TABLE cart_items
            ADD CONSTRAINT FK_cart_items_cart
            FOREIGN KEY (cart_id)
            REFERENCES carts(id)
            ON DELETE CASCADE
        ');

        // Add check constraints for business rules
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT check_quantity_range CHECK (quantity >= 1 AND quantity <= 999)');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT check_unit_price_positive CHECK (unit_price_amount >= 0)');
        $this->addSql('ALTER TABLE carts ADD CONSTRAINT check_status_valid CHECK (status IN (\'active\', \'expired\', \'converted\'))');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order
        $this->addSql('DROP TABLE IF EXISTS cart_items');
        $this->addSql('DROP TABLE IF EXISTS carts');
    }
}