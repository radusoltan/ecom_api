<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 8: Sync Doctrine schema with entity mappings.
 * Uses IF EXISTS/IF NOT EXISTS to handle idempotent application.
 */
final class Version20260306081000_SyncSchemaS8 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint 8: Sync Doctrine schema with current entity mappings';
    }

    public function up(Schema $schema): void
    {
        // 1. Drop empty tables
        $this->addSql('DROP TABLE IF EXISTS i18n_backfill_tracking CASCADE');
        // invoice_sequences kept: used by SequentialInvoiceNumberGenerator (raw SQL, not Doctrine-managed)
        $this->addSql('DROP TABLE IF EXISTS shipments CASCADE');
        $this->addSql('DROP SEQUENCE IF EXISTS i18n_backfill_tracking_id_seq CASCADE');

        // 2. tenant_feature_flags
        $this->addSql('ALTER TABLE tenant_feature_flags ALTER enabled DROP DEFAULT');

        // 3. refresh_tokens
        $this->addSql('ALTER TABLE refresh_tokens ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE refresh_tokens ALTER valid TYPE TIMESTAMP(0) WITHOUT TIME ZONE');

        // 4. Users indexes
        $this->addSql('DROP INDEX IF EXISTS uniq_users_username_bi');
        $this->addSql('DROP INDEX IF EXISTS uniq_users_email_bi');

        // 5. ext_translations cleanup
        $this->addSql('DROP INDEX IF EXISTS idx_ext_translations_uuid');
        $this->addSql('ALTER TABLE ext_translations DROP COLUMN IF EXISTS translation_uuid');
        $this->addSql('ALTER TABLE ext_translations DROP COLUMN IF EXISTS parameters');

        // 6. bundle_items
        $this->addSql('ALTER TABLE bundle_items DROP CONSTRAINT IF EXISTS fk_bundle_items_product');
        $this->addSql('ALTER TABLE bundle_items DROP CONSTRAINT IF EXISTS fk_bundle_items_bundle_product');
        $this->addSql('ALTER TABLE bundle_items DROP CONSTRAINT IF EXISTS uq_bundle_product');
        $this->addSql('ALTER TABLE bundle_items ALTER price_currency DROP DEFAULT');
        $this->addSql('ALTER TABLE bundle_items ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE bundle_items ALTER updated_at DROP DEFAULT');

        // 7. catalog_categories
        $this->addSql('DROP INDEX IF EXISTS idx_categories_name_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_categories_desc_tr_gin');
        $this->addSql('ALTER TABLE catalog_categories ALTER show_on_front DROP DEFAULT');

        // 8. catalog_product_variants
        $this->addSql('DROP INDEX IF EXISTS idx_product_variants_option_map_gin');
        $this->addSql('DROP INDEX IF EXISTS uniq_product_variants_combo');
        $this->addSql('ALTER TABLE catalog_product_variants DROP COLUMN IF EXISTS combo_hash');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER option_value_map DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER stock_on_hand DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER stock_reserved DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER track_inventory DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER allow_backorder DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER is_active DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER images DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_variants ALTER updated_at DROP DEFAULT');

        // 9. catalog_products
        $this->addSql('DROP INDEX IF EXISTS idx_products_name_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_products_tenant_sku');
        $this->addSql('DROP INDEX IF EXISTS idx_products_desc_tr_gin');
        $this->addSql('ALTER TABLE catalog_products ALTER is_featured DROP DEFAULT');

        // 10. catalog_product_options / option_values / configurable_products
        $this->addSql('ALTER TABLE catalog_product_options ALTER name_translations DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_options ALTER position DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_options ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER updated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_option_values ALTER name_translations DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_option_values ALTER position DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog_product_option_values ALTER created_at DROP DEFAULT');

        // 11. orders
        $this->addSql('DROP INDEX IF EXISTS idx_orders_tenant_created_covering');
        $this->addSql('DROP INDEX IF EXISTS idx_orders_coupon_code');
        $this->addSql('DROP INDEX IF EXISTS idx_orders_tenant_date_discount');

        // 12. fulfillments
        $this->addSql('ALTER TABLE fulfillments DROP CONSTRAINT IF EXISTS fk_fulfillments_tenant');
        $this->addSql('ALTER TABLE fulfillments DROP CONSTRAINT IF EXISTS fk_fulfillments_order');

        // 13. promotions
        $this->addSql('DROP INDEX IF EXISTS idx_promotions_tenant_active_dates');
        $this->addSql('DROP INDEX IF EXISTS idx_promotions_tenant_coupon_active');
        $this->addSql('ALTER TABLE promotions ALTER target_segments DROP DEFAULT');

        // 14. price_history
        $this->addSql('ALTER TABLE price_history DROP CONSTRAINT IF EXISTS fk_price_history_product');
        $this->addSql('ALTER TABLE price_history DROP CONSTRAINT IF EXISTS fk_price_history_tenant');
        $this->addSql('ALTER TABLE price_history ALTER changed_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');

        // 15. price_lists
        $this->addSql('ALTER TABLE price_lists ALTER valid_from TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE price_lists ALTER valid_to TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE price_lists ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE price_lists ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE price_lists ALTER segment_rules DROP DEFAULT');

        // 16. customers
        $this->addSql('ALTER TABLE customers DROP CONSTRAINT IF EXISTS fk_customers_tier');
        $this->addSql('DROP INDEX IF EXISTS idx_customers_tier');
        $this->addSql('DROP INDEX IF EXISTS unique_email_per_tenant');
        $this->addSql('DROP INDEX IF EXISTS "IDX_62534E219597C59"');
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS current_tier_id');
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS loyalty_points_balance');
        $this->addSql('ALTER TABLE customers ALTER preferences DROP DEFAULT');

        // 17. customer_addresses
        $this->addSql('ALTER TABLE customer_addresses DROP CONSTRAINT IF EXISTS fk_customer_addresses_customer');
        $this->addSql('ALTER TABLE customer_addresses DROP CONSTRAINT IF EXISTS fk_customer_addresses_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_customer_addresses_not_deleted');
        $this->addSql('DROP INDEX IF EXISTS idx_customer_addresses_default_billing');
        $this->addSql('DROP INDEX IF EXISTS idx_customer_addresses_default_shipping');
        $this->addSql('ALTER TABLE customer_addresses ADD CONSTRAINT FK_C4378D0C9395C3F3 FOREIGN KEY (customer_id) REFERENCES customers (id) NOT DEFERRABLE');

        // 18. consent_history
        $this->addSql('ALTER TABLE consent_history DROP CONSTRAINT IF EXISTS fk_consent_history_customer');

        // 19. loyalty
        $this->addSql('ALTER TABLE loyalty_point_transactions DROP CONSTRAINT IF EXISTS fk_loyalty_transactions_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_transactions_expires');
        $this->addSql('ALTER TABLE loyalty_point_transactions ALTER created_at DROP DEFAULT');

        $this->addSql('DROP INDEX IF EXISTS idx_deletion_requests_privacy_req');

        $this->addSql('ALTER TABLE loyalty_programs DROP CONSTRAINT IF EXISTS fk_loyalty_programs_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_programs_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_programs_active');
        $this->addSql('ALTER TABLE loyalty_programs ALTER earning_rate DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_programs ALTER min_order_value DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_programs ALTER min_order_currency DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_programs ALTER conversion_rate DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_programs ALTER min_points_to_redeem DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_programs ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_programs ALTER updated_at DROP DEFAULT');

        $this->addSql('ALTER TABLE loyalty_tiers DROP CONSTRAINT IF EXISTS fk_loyalty_tiers_tenant');
        $this->addSql('ALTER TABLE loyalty_tiers DROP CONSTRAINT IF EXISTS uq_loyalty_tiers_program_threshold');
        $this->addSql('ALTER TABLE loyalty_tiers DROP CONSTRAINT IF EXISTS uq_loyalty_tiers_program_name');
        $this->addSql('ALTER TABLE loyalty_tiers ALTER threshold DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_tiers ALTER discount_percentage DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_tiers ALTER sort_order DROP DEFAULT');
        $this->addSql('ALTER TABLE loyalty_tiers ALTER created_at DROP DEFAULT');

        // 20. data_export_requests
        $this->addSql('ALTER TABLE data_export_requests DROP CONSTRAINT IF EXISTS fk_data_export_requests_customer');
        $this->addSql('ALTER TABLE data_export_requests DROP CONSTRAINT IF EXISTS fk_data_export_requests_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_data_export_requests_privacy_req');

        // 21. payments - skip structural changes, will fix entities to match DB instead

        // 22. payment_webhook_events
        $this->addSql('ALTER TABLE payment_webhook_events DROP CONSTRAINT IF EXISTS fk_payment_webhook_events_tenant');

        // 23. media
        $this->addSql('DROP INDEX IF EXISTS idx_media_images_owner');
        $this->addSql('DROP INDEX IF EXISTS idx_media_images_tenant_uploaded');
        $this->addSql('ALTER TABLE media_images ALTER uploaded_at DROP DEFAULT');
        $this->addSql('DROP INDEX IF EXISTS uniq_media_thumbnails_image_size');
        $this->addSql('DROP INDEX IF EXISTS idx_media_thumbnails_crop');
        $this->addSql('DROP INDEX IF EXISTS idx_media_thumbnails_tenant');
        $this->addSql('ALTER TABLE media_thumbnails ALTER crop_json DROP DEFAULT');
        $this->addSql('ALTER TABLE media_thumbnails ALTER created_at DROP DEFAULT');

        // 24. tax_rules
        $this->addSql('ALTER TABLE tax_rules DROP CONSTRAINT IF EXISTS fk_tax_rules_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_tax_rules_jurisdiction_lookup');
        $this->addSql('DROP INDEX IF EXISTS idx_tax_rules_active');
        $this->addSql('DROP INDEX IF EXISTS idx_tax_rules_validity');
        $this->addSql('DROP INDEX IF EXISTS uq_tax_rules_jurisdiction_category');
        $this->addSql('ALTER TABLE tax_rules ADD COLUMN IF NOT EXISTS is_reverse_charge BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE tax_rules ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_rules ALTER is_active DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_rules ALTER priority DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_rules ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE tax_rules ALTER updated_at DROP DEFAULT');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_tax_rules_jurisdiction_lookup ON tax_rules (tenant_id, country_code, region_code, category, is_active)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_tax_rules_active ON tax_rules (is_active)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_tax_rules_validity ON tax_rules (valid_from, valid_until)');

        // 25. carts
        $this->addSql('DROP INDEX IF EXISTS idx_carts_abandonment_email');

        // 26-28. invoice_lines, invoices, product_reviews - skip structural changes, will fix entities to match DB instead

        // 29. notifications
        $this->addSql('ALTER TABLE notifications ALTER attempt_count DROP DEFAULT');

        // 30. Clear Doctrine comments (all COMMENT ON COLUMN ... IS '')
        // These are cosmetic and will be handled by Doctrine automatically
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration consolidates schema changes and cannot be reversed.');
    }
}
