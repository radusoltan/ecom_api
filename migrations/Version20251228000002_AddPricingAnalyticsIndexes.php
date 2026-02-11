<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add indexes for pricing analytics queries.
 *
 * These indexes optimize:
 * - Promotion performance analytics (JOIN orders with promotions)
 * - Discount usage analytics (coupon code queries)
 * - Date range filtering on orders
 * - Revenue and discount aggregations
 */
final class Version20251228000002_AddPricingAnalyticsIndexes extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add database indexes for pricing analytics performance';
    }

    public function up(Schema $schema): void
    {
        // Composite index for date range queries on orders
        // Used in: GetPromotionPerformanceQueryHandler, GetDiscountUsageQueryHandler, GetPricingSummaryQueryHandler
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_orders_tenant_date_discount ON orders (tenant_id, created_at, discount_amount)');

        // Index for coupon code lookups in orders
        // Used in: GetDiscountUsageQueryHandler (top coupons, unused promotions)
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_orders_coupon_code ON orders (tenant_id, coupon_code) WHERE coupon_code IS NOT NULL');

        // Composite index for promotion date range filtering
        // Used in: GetPromotionPerformanceQueryHandler (active promotions in period)
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_promotions_tenant_active_dates ON promotions (tenant_id, is_active, valid_from, valid_to)');

        // Index for JSON search in applied_promotions (cast to text for LIKE queries)
        // Used in: GetPromotionPerformanceQueryHandler (JOIN on applied_promotions)
        // Note: Using B-tree index on text cast for LIKE queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_orders_applied_promotions_text ON orders ((applied_promotions::text))');

        // Index for promotions with coupon codes
        // Used in: GetDiscountUsageQueryHandler (unused promotions)
        // Note: unique_coupon_per_tenant already exists, but this is for analytics queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_promotions_tenant_coupon_active ON promotions (tenant_id, coupon_code, is_active) WHERE coupon_code IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_orders_tenant_date_discount');
        $this->addSql('DROP INDEX IF EXISTS idx_orders_coupon_code');
        $this->addSql('DROP INDEX IF EXISTS idx_promotions_tenant_active_dates');
        $this->addSql('DROP INDEX IF EXISTS idx_orders_applied_promotions_text');
        $this->addSql('DROP INDEX IF EXISTS idx_promotions_tenant_coupon_active');
    }
}
