<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Loyalty Program Database Migration.
 *
 * Creates tables for the Customer Loyalty Program bounded context:
 * - loyalty_programs: Tenant-wide loyalty program configuration
 * - loyalty_tiers: Tiered benefits based on customer loyalty points
 * - loyalty_point_transactions: Complete audit trail of all point movements
 * - Extends customers table with tier association
 *
 * Business Rules:
 * - One loyalty program per tenant (enforced by unique constraint)
 * - Tiers must have unique thresholds per program
 * - All point transactions are immutable (audit trail)
 * - Customers can have at most one tier at a time
 */
final class Version20251228000003_LoyaltyProgram extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create loyalty program tables with multi-tenant support and RLS';
    }

    public function up(Schema $schema): void
    {
        // ============================================
        // 1. loyalty_programs TABLE
        // ============================================
        $this->addSql('
            CREATE TABLE IF NOT EXISTS loyalty_programs (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                earning_rate DECIMAL(10,4) NOT NULL DEFAULT 1.0,
                min_order_value INTEGER NOT NULL DEFAULT 0,
                min_order_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
                conversion_rate DECIMAL(10,4) NOT NULL DEFAULT 100.0,
                min_points_to_redeem INTEGER NOT NULL DEFAULT 100,
                max_points_per_order INTEGER,
                validity_days INTEGER,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_loyalty_programs_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT uq_loyalty_programs_tenant
                    UNIQUE (tenant_id)
            )
        ');

        // ============================================
        // 2. loyalty_tiers TABLE
        // ============================================
        $this->addSql('
            CREATE TABLE IF NOT EXISTS loyalty_tiers (
                id UUID PRIMARY KEY,
                program_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                name VARCHAR(50) NOT NULL,
                threshold INTEGER NOT NULL DEFAULT 0,
                discount_percentage INTEGER NOT NULL DEFAULT 0,
                free_shipping_min_order INTEGER,
                free_shipping_currency VARCHAR(3),
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_loyalty_tiers_program
                    FOREIGN KEY (program_id) REFERENCES loyalty_programs(id) ON DELETE CASCADE,
                CONSTRAINT fk_loyalty_tiers_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT uq_loyalty_tiers_program_name
                    UNIQUE (program_id, name),
                CONSTRAINT uq_loyalty_tiers_program_threshold
                    UNIQUE (program_id, threshold),
                CONSTRAINT chk_discount_percentage
                    CHECK (discount_percentage >= 0 AND discount_percentage <= 100)
            )
        ');

        // ============================================
        // 3. loyalty_point_transactions TABLE
        // ============================================
        $this->addSql('
            CREATE TABLE IF NOT EXISTS loyalty_point_transactions (
                id UUID PRIMARY KEY,
                customer_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                type VARCHAR(20) NOT NULL,
                points INTEGER NOT NULL,
                balance_after INTEGER NOT NULL,
                reason VARCHAR(255) NOT NULL,
                order_id UUID,
                expires_at TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_loyalty_transactions_customer
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                CONSTRAINT fk_loyalty_transactions_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT chk_transaction_type
                    CHECK (type IN (\'earned\', \'redeemed\', \'expired\', \'bonus\', \'adjustment\'))
            )
        ');

        // ============================================
        // 4. ALTER customers TABLE
        // ============================================
        $this->addSql('ALTER TABLE customers ADD COLUMN IF NOT EXISTS current_tier_id UUID');
        $this->addSql('ALTER TABLE customers ADD COLUMN IF NOT EXISTS loyalty_points_balance INTEGER NOT NULL DEFAULT 0');

        // Add foreign key constraint (only if column was just created)
        $this->addSql('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = \'fk_customers_tier\'
                ) THEN
                    ALTER TABLE customers
                    ADD CONSTRAINT fk_customers_tier
                        FOREIGN KEY (current_tier_id) REFERENCES loyalty_tiers(id) ON DELETE SET NULL;
                END IF;
            END $$;
        ');

        // ============================================
        // 5. CREATE INDEXES
        // ============================================

        // loyalty_programs indexes
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_programs_tenant ON loyalty_programs(tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_programs_active ON loyalty_programs(is_active) WHERE is_active = true');

        // loyalty_tiers indexes
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_tiers_program ON loyalty_tiers(program_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_tiers_tenant ON loyalty_tiers(tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_tiers_threshold ON loyalty_tiers(program_id, threshold)');

        // loyalty_point_transactions indexes
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_customer ON loyalty_point_transactions(customer_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_tenant ON loyalty_point_transactions(tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_type ON loyalty_point_transactions(customer_id, type)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_created ON loyalty_point_transactions(created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_expires ON loyalty_point_transactions(expires_at) WHERE expires_at IS NOT NULL');

        // customers index for tier
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_customers_tier ON customers(current_tier_id) WHERE current_tier_id IS NOT NULL');

        // ============================================
        // 6. ENABLE ROW-LEVEL SECURITY (RLS)
        // ============================================

        $this->addSql('ALTER TABLE loyalty_programs ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE loyalty_tiers ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE loyalty_point_transactions ENABLE ROW LEVEL SECURITY');

        // ============================================
        // 7. CREATE RLS POLICIES
        // ============================================

        $this->addSql('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_policies WHERE tablename = \'loyalty_programs\' AND policyname = \'tenant_isolation_loyalty_programs\'
                ) THEN
                    CREATE POLICY tenant_isolation_loyalty_programs ON loyalty_programs
                        USING (tenant_id::text = current_setting(\'app.tenant_id\', true));
                END IF;
            END $$;
        ');

        $this->addSql('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_policies WHERE tablename = \'loyalty_tiers\' AND policyname = \'tenant_isolation_loyalty_tiers\'
                ) THEN
                    CREATE POLICY tenant_isolation_loyalty_tiers ON loyalty_tiers
                        USING (tenant_id::text = current_setting(\'app.tenant_id\', true));
                END IF;
            END $$;
        ');

        $this->addSql('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_policies WHERE tablename = \'loyalty_point_transactions\' AND policyname = \'tenant_isolation_loyalty_transactions\'
                ) THEN
                    CREATE POLICY tenant_isolation_loyalty_transactions ON loyalty_point_transactions
                        USING (tenant_id::text = current_setting(\'app.tenant_id\', true));
                END IF;
            END $$;
        ');

        // Add comments for documentation
        $this->addSql('COMMENT ON TABLE loyalty_programs IS \'Tenant-wide loyalty program configuration (one per tenant)\'');
        $this->addSql('COMMENT ON TABLE loyalty_tiers IS \'Loyalty tiers with benefits based on points threshold\'');
        $this->addSql('COMMENT ON TABLE loyalty_point_transactions IS \'Immutable audit trail of all loyalty point movements\'');
        $this->addSql('COMMENT ON COLUMN customers.current_tier_id IS \'Current loyalty tier based on points balance\'');
        $this->addSql('COMMENT ON COLUMN customers.loyalty_points_balance IS \'Current loyalty points balance (derived from transactions)\'');
    }

    public function down(Schema $schema): void
    {
        // ============================================
        // ROLLBACK IN REVERSE ORDER
        // ============================================

        // 1. Drop RLS policies
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_loyalty_transactions ON loyalty_point_transactions');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_loyalty_tiers ON loyalty_tiers');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_loyalty_programs ON loyalty_programs');

        // 2. Drop indexes on customers
        $this->addSql('DROP INDEX IF EXISTS idx_customers_tier');

        // 3. Drop loyalty_point_transactions indexes
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_transactions_expires');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_transactions_created');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_transactions_type');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_transactions_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_transactions_customer');

        // 4. Drop loyalty_tiers indexes
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_tiers_threshold');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_tiers_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_tiers_program');

        // 5. Drop loyalty_programs indexes
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_programs_active');
        $this->addSql('DROP INDEX IF EXISTS idx_loyalty_programs_tenant');

        // 6. Drop foreign key from customers (if exists)
        $this->addSql('
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = \'fk_customers_tier\'
                ) THEN
                    ALTER TABLE customers DROP CONSTRAINT fk_customers_tier;
                END IF;
            END $$;
        ');

        // 7. Drop columns from customers
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS loyalty_points_balance');
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS current_tier_id');

        // 8. Drop tables (CASCADE will handle foreign key dependencies)
        $this->addSql('DROP TABLE IF EXISTS loyalty_point_transactions CASCADE');
        $this->addSql('DROP TABLE IF EXISTS loyalty_tiers CASCADE');
        $this->addSql('DROP TABLE IF EXISTS loyalty_programs CASCADE');
    }
}
