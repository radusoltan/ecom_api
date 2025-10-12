<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251011212530 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_categories (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description TEXT DEFAULT NULL,
              slug VARCHAR(255) NOT NULL,
              parent_id VARCHAR(36) DEFAULT NULL,
              position INT NOT NULL,
              active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8FD9B4B3989D9B62 ON catalog_categories (slug)');
        $this->addSql('CREATE INDEX idx_categories_tenant_id ON catalog_categories (tenant_id)');
        $this->addSql('CREATE INDEX idx_categories_tenant_active ON catalog_categories (tenant_id, active)');
        $this->addSql('CREATE INDEX idx_categories_parent_position ON catalog_categories (parent_id, position)');
        $this->addSql('CREATE INDEX idx_categories_slug ON catalog_categories (slug)');
        $this->addSql('COMMENT ON COLUMN catalog_categories.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN catalog_categories.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_products (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              sku VARCHAR(50) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description TEXT DEFAULT NULL,
              short_description VARCHAR(500) DEFAULT NULL,
              slug VARCHAR(255) NOT NULL,
              price_amount INT NOT NULL,
              price_currency VARCHAR(3) NOT NULL,
              category_id VARCHAR(36) DEFAULT NULL,
              stock_quantity INT NOT NULL,
              track_inventory BOOLEAN NOT NULL,
              allow_backorder BOOLEAN NOT NULL,
              images JSON NOT NULL,
              active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_816D8444F9038C4 ON catalog_products (sku)');
        $this->addSql('CREATE INDEX idx_products_tenant_id ON catalog_products (tenant_id)');
        $this->addSql('CREATE INDEX idx_products_tenant_sku ON catalog_products (tenant_id, sku)');
        $this->addSql('CREATE INDEX idx_products_tenant_slug ON catalog_products (tenant_id, slug)');
        $this->addSql('CREATE INDEX idx_products_tenant_active ON catalog_products (tenant_id, active)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_products_tenant_active_created ON catalog_products (tenant_id, active, created_at)
        SQL);
        $this->addSql('CREATE INDEX idx_products_category_id ON catalog_products (category_id)');
        $this->addSql('CREATE INDEX idx_products_created_at ON catalog_products (created_at)');
        $this->addSql('CREATE INDEX idx_products_price_amount ON catalog_products (price_amount)');
        $this->addSql('COMMENT ON COLUMN catalog_products.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN catalog_products.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE customers (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              email VARCHAR(255) NOT NULL,
              first_name VARCHAR(50) NOT NULL,
              last_name VARCHAR(50) NOT NULL,
              phone_number VARCHAR(20) DEFAULT NULL,
              segment VARCHAR(20) NOT NULL,
              loyalty_points INT NOT NULL,
              is_active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_62534E219033212A ON customers (tenant_id)');
        $this->addSql('CREATE INDEX IDX_62534E21E7927C74 ON customers (email)');
        $this->addSql('CREATE INDEX IDX_62534E211881F565 ON customers (segment)');
        $this->addSql('CREATE INDEX IDX_62534E211B5771DD ON customers (is_active)');
        $this->addSql('CREATE UNIQUE INDEX unique_email_per_tenant ON customers (tenant_id, email)');
        $this->addSql('COMMENT ON COLUMN customers.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN customers.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE ext_translations (
              id SERIAL NOT NULL,
              locale VARCHAR(8) NOT NULL,
              object_class VARCHAR(191) NOT NULL,
              field VARCHAR(32) NOT NULL,
              foreign_key VARCHAR(64) NOT NULL,
              content TEXT DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX translations_lookup_idx ON ext_translations (locale, object_class, foreign_key)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX lookup_unique_idx ON ext_translations (
              locale, object_class, field, foreign_key
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE orders (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              customer_email VARCHAR(255) NOT NULL,
              status VARCHAR(20) NOT NULL,
              lines JSON NOT NULL,
              shipping_address JSON NOT NULL,
              billing_address JSON NOT NULL,
              applied_promotions JSON DEFAULT NULL,
              discount_amount DOUBLE PRECISION DEFAULT NULL,
              discount_currency VARCHAR(3) DEFAULT NULL,
              coupon_code VARCHAR(20) DEFAULT NULL,
              tax_amount DOUBLE PRECISION DEFAULT NULL,
              tax_currency VARCHAR(3) DEFAULT NULL,
              tax_jurisdiction VARCHAR(10) DEFAULT NULL,
              tax_rule_id VARCHAR(36) DEFAULT NULL,
              tax_rate DOUBLE PRECISION DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_orders_tenant_id ON orders (tenant_id)');
        $this->addSql('CREATE INDEX idx_orders_tenant_customer ON orders (tenant_id, customer_email)');
        $this->addSql('CREATE INDEX idx_orders_status ON orders (status)');
        $this->addSql('CREATE INDEX idx_orders_created_at ON orders (created_at)');
        $this->addSql('CREATE INDEX idx_orders_customer_created ON orders (customer_email, created_at)');
        $this->addSql('CREATE INDEX idx_orders_tenant_status_created ON orders (tenant_id, status, created_at)');
        $this->addSql('COMMENT ON COLUMN orders.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN orders.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE payments (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              order_id VARCHAR(36) NOT NULL,
              amount_in_cents INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              method VARCHAR(20) NOT NULL,
              gateway VARCHAR(20) NOT NULL,
              status VARCHAR(20) NOT NULL,
              gateway_transaction_id VARCHAR(255) DEFAULT NULL,
              error_message TEXT DEFAULT NULL,
              refunded_amount_in_cents INT DEFAULT 0 NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_payments_tenant_id ON payments (tenant_id)');
        $this->addSql('CREATE INDEX idx_payments_order_id ON payments (order_id)');
        $this->addSql('CREATE INDEX idx_payments_status ON payments (status)');
        $this->addSql('CREATE INDEX idx_payments_gateway ON payments (gateway)');
        $this->addSql('CREATE INDEX idx_payments_tenant_status ON payments (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_payments_created_at ON payments (created_at)');
        $this->addSql('COMMENT ON COLUMN payments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payments.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE price_lists (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              name VARCHAR(100) NOT NULL,
              priority INT NOT NULL,
              rules JSON NOT NULL,
              valid_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              valid_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              is_active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_price_lists_tenant ON price_lists (tenant_id)');
        $this->addSql('CREATE INDEX idx_price_lists_active ON price_lists (is_active)');
        $this->addSql('CREATE INDEX idx_price_lists_tenant_active ON price_lists (tenant_id, is_active)');
        $this->addSql('COMMENT ON COLUMN price_lists.valid_from IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN price_lists.valid_to IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN price_lists.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN price_lists.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE promotions (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              name VARCHAR(100) NOT NULL,
              type VARCHAR(20) NOT NULL,
              discount_type VARCHAR(20) NOT NULL,
              discount_value DOUBLE PRECISION NOT NULL,
              priority INT NOT NULL,
              is_active BOOLEAN NOT NULL,
              coupon_code VARCHAR(20) DEFAULT NULL,
              conditions JSON NOT NULL,
              valid_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              valid_to TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_promotions_tenant_id ON promotions (tenant_id)');
        $this->addSql('CREATE INDEX idx_promotions_type ON promotions (type)');
        $this->addSql('CREATE INDEX idx_promotions_is_active ON promotions (is_active)');
        $this->addSql('CREATE INDEX idx_promotions_priority ON promotions (priority)');
        $this->addSql('CREATE INDEX idx_promotions_coupon_code ON promotions (coupon_code)');
        $this->addSql('CREATE INDEX idx_promotions_tenant_active ON promotions (tenant_id, is_active)');
        $this->addSql('CREATE INDEX idx_promotions_tenant_type ON promotions (tenant_id, type)');
        $this->addSql('CREATE INDEX idx_promotions_created_at ON promotions (created_at)');
        $this->addSql('CREATE UNIQUE INDEX unique_coupon_per_tenant ON promotions (tenant_id, coupon_code)');
        $this->addSql('COMMENT ON COLUMN promotions.valid_from IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN promotions.valid_to IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN promotions.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN promotions.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
              id SERIAL NOT NULL,
              refresh_token VARCHAR(128) NOT NULL,
              username VARCHAR(255) NOT NULL,
              valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1C74F2195 ON refresh_tokens (refresh_token)');
        $this->addSql(<<<'SQL'
            CREATE TABLE return_requests (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              order_id VARCHAR(36) NOT NULL,
              reason VARCHAR(500) NOT NULL,
              status VARCHAR(20) NOT NULL,
              warehouse_id VARCHAR(36) DEFAULT NULL,
              refund_amount DOUBLE PRECISION DEFAULT NULL,
              refund_currency VARCHAR(3) DEFAULT NULL,
              is_resellable BOOLEAN DEFAULT NULL,
              inspection_notes TEXT DEFAULT NULL,
              rejection_reason TEXT DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_returns_tenant_id ON return_requests (tenant_id)');
        $this->addSql('CREATE INDEX idx_returns_order_id ON return_requests (order_id)');
        $this->addSql('CREATE INDEX idx_returns_status ON return_requests (status)');
        $this->addSql('CREATE INDEX idx_returns_tenant_status ON return_requests (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_returns_created_at ON return_requests (created_at)');
        $this->addSql('COMMENT ON COLUMN return_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN return_requests.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE stock_items (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              product_id VARCHAR(36) NOT NULL,
              warehouse_id VARCHAR(36) NOT NULL,
              on_hand INT NOT NULL,
              reserved INT NOT NULL,
              allocated INT NOT NULL,
              low_stock_threshold INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_stock_tenant_product ON stock_items (tenant_id, product_id)');
        $this->addSql('CREATE INDEX idx_stock_tenant_warehouse ON stock_items (tenant_id, warehouse_id)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_stock_product_warehouse ON stock_items (
              tenant_id, product_id, warehouse_id
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN stock_items.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN stock_items.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE stock_reservations (
              id VARCHAR(36) NOT NULL,
              stock_item_id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              quantity INT NOT NULL,
              reserved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              is_released BOOLEAN NOT NULL,
              released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_reservation_expiry ON stock_reservations (tenant_id, expires_at, is_released)');
        $this->addSql('CREATE INDEX idx_reservation_stock_item ON stock_reservations (stock_item_id)');
        $this->addSql('COMMENT ON COLUMN stock_reservations.reserved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN stock_reservations.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN stock_reservations.released_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE tax_rules (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              name VARCHAR(100) NOT NULL,
              country_code VARCHAR(2) NOT NULL,
              region_code VARCHAR(3) DEFAULT NULL,
              rate_percentage NUMERIC(5, 2) NOT NULL,
              is_active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_tax_rules_tenant_id ON tax_rules (tenant_id)');
        $this->addSql('CREATE INDEX idx_tax_rules_jurisdiction ON tax_rules (country_code, region_code)');
        $this->addSql('CREATE INDEX idx_tax_rules_is_active ON tax_rules (is_active)');
        $this->addSql('CREATE INDEX idx_tax_rules_tenant_active ON tax_rules (tenant_id, is_active)');
        $this->addSql('CREATE INDEX idx_tax_rules_created_at ON tax_rules (created_at)');
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX tax_rules_jurisdiction_unique ON tax_rules (
              tenant_id, country_code, region_code
            )
        SQL);
        $this->addSql('COMMENT ON COLUMN tax_rules.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tax_rules.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE tenants (
              id VARCHAR(36) NOT NULL,
              name VARCHAR(100) NOT NULL,
              owner_email VARCHAR(255) NOT NULL,
              status VARCHAR(20) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              description TEXT DEFAULT NULL,
              slug VARCHAR(255) NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8FC96BB170B4953 ON tenants (owner_email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B8FC96BB989D9B62 ON tenants (slug)');
        $this->addSql('CREATE INDEX idx_tenants_owner_email ON tenants (owner_email)');
        $this->addSql('CREATE INDEX idx_tenants_status ON tenants (status)');
        $this->addSql('COMMENT ON COLUMN tenants.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE test_translatable (
              id SERIAL NOT NULL,
              name VARCHAR(255) NOT NULL,
              description TEXT DEFAULT NULL,
              slug VARCHAR(255) NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E1BF56D8989D9B62 ON test_translatable (slug)');
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
              id VARCHAR(36) NOT NULL,
              email VARCHAR(255) NOT NULL,
              username VARCHAR(50) NOT NULL,
              password VARCHAR(255) NOT NULL,
              roles JSON NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql(<<<'SQL'
            CREATE TABLE warehouses (
              id VARCHAR(36) NOT NULL,
              tenant_id VARCHAR(36) NOT NULL,
              code VARCHAR(10) NOT NULL,
              name VARCHAR(100) NOT NULL,
              address JSON NOT NULL,
              priority INT NOT NULL,
              is_active BOOLEAN NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_warehouses_tenant_id ON warehouses (tenant_id)');
        $this->addSql('CREATE INDEX idx_warehouses_is_active ON warehouses (is_active)');
        $this->addSql('CREATE INDEX idx_warehouses_priority ON warehouses (priority)');
        $this->addSql('CREATE UNIQUE INDEX uniq_warehouse_code_tenant ON warehouses (code, tenant_id)');
        $this->addSql('COMMENT ON COLUMN warehouses.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN warehouses.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE catalog_categories');
        $this->addSql('DROP TABLE catalog_products');
        $this->addSql('DROP TABLE customers');
        $this->addSql('DROP TABLE ext_translations');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE price_lists');
        $this->addSql('DROP TABLE promotions');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE return_requests');
        $this->addSql('DROP TABLE stock_items');
        $this->addSql('DROP TABLE stock_reservations');
        $this->addSql('DROP TABLE tax_rules');
        $this->addSql('DROP TABLE tenants');
        $this->addSql('DROP TABLE test_translatable');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE warehouses');
    }
}
