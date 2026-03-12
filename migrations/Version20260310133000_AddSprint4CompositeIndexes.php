<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260310133000_AddSprint4CompositeIndexes extends AbstractMigration
{
    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Add Sprint 4 RLS-aware composite indexes using tenant_id::text leading expressions';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->indexStatements() as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        foreach ($this->indexNames() as $indexName) {
            $this->addSql(sprintf('DROP INDEX CONCURRENTLY IF EXISTS %s', $indexName));
        }
    }

    /**
     * @return list<string>
     */
    private function indexStatements(): array
    {
        return [
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_products_tenant_text_active_created ON catalog_products ((tenant_id::text), active, created_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_products_tenant_text_cat_active ON catalog_products ((tenant_id::text), category_id, active, created_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_products_tenant_text_featured ON catalog_products ((tenant_id::text), is_featured, active, created_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_products_tenant_text_active_price ON catalog_products ((tenant_id::text), active, price_amount)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_categories_tenant_text_slug ON catalog_categories ((tenant_id::text), slug)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_categories_tenant_text_parent_pos ON catalog_categories ((tenant_id::text), parent_id, position)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_categories_tenant_text_front ON catalog_categories ((tenant_id::text), show_on_front, created_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_orders_tenant_text_created ON orders ((tenant_id::text), created_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_orders_tenant_text_status_created ON orders ((tenant_id::text), status, created_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_option_values_tenant_text_option ON catalog_product_option_values ((tenant_id::text), option_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_reviews_tenant_text_product_status ON product_reviews ((tenant_id::text), product_id, status, created_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_images_tenant_text_owner ON media_images ((tenant_id::text), owner_type, owner_id, uploaded_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_tenant_occurred ON audit_log (tenant_id, occurred_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_tenant_resource ON audit_log (tenant_id, resource_type, resource_id, occurred_at DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_tenant_text_customer_status ON carts ((tenant_id::text), customer_id, status)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_tenant_text_session_status ON carts ((tenant_id::text), session_id, status)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_carts_status_updated ON carts (status, updated_at)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_price_lists_tenant_text_active_pri ON price_lists ((tenant_id::text), is_active, priority DESC, valid_from, valid_to)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_promotions_tenant_text_active_pri ON promotions ((tenant_id::text), is_active, priority DESC, valid_from, valid_to)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_fulfillments_tenant_text_order ON fulfillments ((tenant_id::text), order_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_warehouses_tenant_text_active_pri ON warehouses ((tenant_id::text), is_active, priority, name)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_stock_tenant_text_product_wh ON stock_items ((tenant_id::text), product_id, warehouse_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_customers_tenant_text_email ON customers ((tenant_id::text), email_blind_index)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_variants_tenant_text_product ON catalog_product_variants ((tenant_id::text), configurable_product_id, is_active)',
        ];
    }

    /**
     * @return list<string>
     */
    private function indexNames(): array
    {
        return [
            'idx_products_tenant_text_active_created',
            'idx_products_tenant_text_cat_active',
            'idx_products_tenant_text_featured',
            'idx_products_tenant_text_active_price',
            'idx_categories_tenant_text_slug',
            'idx_categories_tenant_text_parent_pos',
            'idx_categories_tenant_text_front',
            'idx_orders_tenant_text_created',
            'idx_orders_tenant_text_status_created',
            'idx_option_values_tenant_text_option',
            'idx_reviews_tenant_text_product_status',
            'idx_images_tenant_text_owner',
            'idx_audit_tenant_occurred',
            'idx_audit_tenant_resource',
            'idx_carts_tenant_text_customer_status',
            'idx_carts_tenant_text_session_status',
            'idx_carts_status_updated',
            'idx_price_lists_tenant_text_active_pri',
            'idx_promotions_tenant_text_active_pri',
            'idx_fulfillments_tenant_text_order',
            'idx_warehouses_tenant_text_active_pri',
            'idx_stock_tenant_text_product_wh',
            'idx_customers_tenant_text_email',
            'idx_variants_tenant_text_product',
        ];
    }
}
