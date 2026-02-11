<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Enable RLS on fulfillments table and create product_reviews table
 *
 * This migration:
 * 1. Enables Row-Level Security on the existing fulfillments table
 * 2. Creates the product_reviews table with RLS enabled from the start
 *
 * Both tables require RLS for multi-tenant isolation (PRD Section 2.3)
 */
final class Version20251127140000_EnableRLSAndCreateProductReviews extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable RLS on fulfillments table and create product_reviews table with RLS';
    }

    public function up(Schema $schema): void
    {
        // 1. Create fulfillments table if it doesn't exist, then enable RLS
        $this->addSql("
            DO $$
            BEGIN
                -- Create table if it doesn't exist
                IF NOT EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'fulfillments') THEN
                    CREATE TABLE fulfillments (
                        id VARCHAR(26) PRIMARY KEY,
                        order_id VARCHAR(36) NOT NULL,
                        warehouse_id VARCHAR(26) NOT NULL,
                        tenant_id VARCHAR(36) NOT NULL,
                        status VARCHAR(20) NOT NULL,
                        tracking_number VARCHAR(50),
                        carrier VARCHAR(50),
                        assigned_at TIMESTAMP,
                        picking_started_at TIMESTAMP,
                        packing_started_at TIMESTAMP,
                        shipped_at TIMESTAMP,
                        delivered_at TIMESTAMP,
                        cancelled_at TIMESTAMP,
                        failed_at TIMESTAMP,
                        failure_reason TEXT,
                        created_at TIMESTAMP NOT NULL,
                        updated_at TIMESTAMP NOT NULL
                    );

                    -- Create indexes for common queries
                    CREATE INDEX idx_fulfillments_tenant_id ON fulfillments (tenant_id);
                    CREATE INDEX idx_fulfillments_order_id ON fulfillments (order_id);
                    CREATE INDEX idx_fulfillments_warehouse_id ON fulfillments (warehouse_id);
                    CREATE INDEX idx_fulfillments_status ON fulfillments (status);
                    CREATE INDEX idx_fulfillments_tenant_status ON fulfillments (tenant_id, status);
                    CREATE INDEX idx_fulfillments_created_at ON fulfillments (created_at);

                    -- Add foreign key to tenants
                    ALTER TABLE fulfillments
                        ADD CONSTRAINT fk_fulfillments_tenant
                        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

                    -- Add foreign key to orders
                    ALTER TABLE fulfillments
                        ADD CONSTRAINT fk_fulfillments_order
                        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE;
                END IF;

                -- Enable RLS on table (whether newly created or already existing)
                ALTER TABLE fulfillments ENABLE ROW LEVEL SECURITY;

                -- Force RLS even for table owner
                ALTER TABLE fulfillments FORCE ROW LEVEL SECURITY;

                -- Create policy for tenant isolation (only if it doesn't exist)
                IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE tablename = 'fulfillments' AND policyname = 'tenant_isolation') THEN
                    CREATE POLICY tenant_isolation ON fulfillments
                        FOR ALL
                        USING (tenant_id::text = current_setting('app.tenant_id', true));
                END IF;

                -- Grant necessary permissions
                GRANT ALL ON fulfillments TO ecom_admin;
            END $$;
        ");

        // 2. Create product_reviews table (only if it doesn't exist)
        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'product_reviews') THEN
                    CREATE TABLE product_reviews (
                        id VARCHAR(36) PRIMARY KEY,
                        tenant_id VARCHAR(36) NOT NULL,
                        product_id VARCHAR(36) NOT NULL,
                        customer_id VARCHAR(36),
                        rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
                        title VARCHAR(255),
                        content TEXT,
                        is_verified BOOLEAN DEFAULT false NOT NULL,
                        is_approved BOOLEAN DEFAULT false NOT NULL,
                        created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                        updated_at TIMESTAMP NOT NULL DEFAULT NOW()
                    );

                    -- Create indexes for common queries
                    CREATE INDEX idx_product_reviews_tenant_id ON product_reviews (tenant_id);
                    CREATE INDEX idx_product_reviews_product_id ON product_reviews (product_id);
                    CREATE INDEX idx_product_reviews_customer_id ON product_reviews (customer_id);
                    CREATE INDEX idx_product_reviews_tenant_product ON product_reviews (tenant_id, product_id);
                    CREATE INDEX idx_product_reviews_tenant_approved ON product_reviews (tenant_id, is_approved);
                    CREATE INDEX idx_product_reviews_rating ON product_reviews (rating);
                    CREATE INDEX idx_product_reviews_created_at ON product_reviews (created_at DESC);

                    -- Add foreign key constraints
                    -- Note: Using ON DELETE CASCADE for tenant_id to automatically remove reviews when tenant is deleted
                    -- Using ON DELETE SET NULL for customer_id to preserve reviews even if customer is deleted
                    ALTER TABLE product_reviews
                        ADD CONSTRAINT fk_product_reviews_tenant
                        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

                    -- Add FK to catalog_products
                    ALTER TABLE product_reviews
                        ADD CONSTRAINT fk_product_reviews_product
                        FOREIGN KEY (product_id) REFERENCES catalog_products(id) ON DELETE CASCADE;

                    -- Enable RLS on table
                    ALTER TABLE product_reviews ENABLE ROW LEVEL SECURITY;

                    -- Force RLS even for table owner
                    ALTER TABLE product_reviews FORCE ROW LEVEL SECURITY;

                    -- Create policy for tenant isolation
                    CREATE POLICY tenant_isolation ON product_reviews
                        FOR ALL
                        USING (tenant_id::text = current_setting('app.tenant_id', true));

                    -- Grant necessary permissions
                    GRANT ALL ON product_reviews TO ecom_admin;
                END IF;
            END $$;
        ");
    }

    public function down(Schema $schema): void
    {
        // Drop fulfillments table
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'fulfillments') THEN
                    DROP TABLE fulfillments CASCADE;
                END IF;
            END $$;
        ");

        // Drop product_reviews table
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'product_reviews') THEN
                    DROP TABLE product_reviews CASCADE;
                END IF;
            END $$;
        ");
    }
}
