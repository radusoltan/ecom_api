<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create customer_addresses table with RLS for multi-tenant isolation.
 *
 * Business Requirements (PRD Section 3.2 - Customer Management):
 * - Customers can have multiple addresses (shipping, billing, or both)
 * - Each address can be marked as default for shipping/billing
 * - Supports soft delete (is_deleted flag)
 * - Full address details: street, street2, city, state, postal code, country
 * - Type constraint: shipping, billing, or both
 *
 * Security Requirements:
 * - RLS enabled for complete tenant isolation
 * - All addresses isolated by tenant_id
 * - Foreign key to customers table with CASCADE delete
 */
final class Version20251128085910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create customer_addresses table with RLS for multi-tenant isolation';
    }

    public function up(Schema $schema): void
    {
        // Create customer_addresses table
        $this->addSql("
            CREATE TABLE IF NOT EXISTS customer_addresses (
                id UUID NOT NULL,
                customer_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                street VARCHAR(255) NOT NULL,
                street2 VARCHAR(255) DEFAULT NULL,
                city VARCHAR(100) NOT NULL,
                state VARCHAR(100) DEFAULT NULL,
                postal_code VARCHAR(20) NOT NULL,
                country CHAR(2) NOT NULL,
                type VARCHAR(20) NOT NULL,
                is_default_shipping BOOLEAN DEFAULT FALSE NOT NULL,
                is_default_billing BOOLEAN DEFAULT FALSE NOT NULL,
                is_deleted BOOLEAN DEFAULT FALSE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id),
                CONSTRAINT chk_address_type CHECK (type IN ('shipping', 'billing', 'both'))
            )
        ");

        // Create indexes for performance
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_customer_addresses_customer ON customer_addresses(customer_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_customer_addresses_tenant ON customer_addresses(tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_customer_addresses_not_deleted ON customer_addresses(customer_id) WHERE is_deleted = false');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_customer_addresses_default_shipping ON customer_addresses(customer_id, is_default_shipping) WHERE is_deleted = false');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_customer_addresses_default_billing ON customer_addresses(customer_id, is_default_billing) WHERE is_deleted = false');

        // Add foreign keys with CASCADE behavior
        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'fk_customer_addresses_customer'
                ) THEN
                    ALTER TABLE customer_addresses
                    ADD CONSTRAINT fk_customer_addresses_customer
                    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;
                END IF;
            END $$;
        ");

        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'fk_customer_addresses_tenant'
                ) THEN
                    ALTER TABLE customer_addresses
                    ADD CONSTRAINT fk_customer_addresses_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
                END IF;
            END $$;
        ");

        // Enable RLS for tenant isolation
        $this->addSql('ALTER TABLE customer_addresses ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE customer_addresses FORCE ROW LEVEL SECURITY');

        // Create RLS policy for tenant isolation
        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_policies
                    WHERE tablename = 'customer_addresses'
                    AND policyname = 'tenant_isolation'
                ) THEN
                    CREATE POLICY tenant_isolation ON customer_addresses
                        FOR ALL
                        USING (tenant_id::text = current_setting('app.tenant_id', true));
                END IF;
            END $$;
        ");

        // Grant necessary permissions
        $this->addSql('GRANT ALL ON customer_addresses TO ecom_admin');

        // Add table and column comments for documentation
        $this->addSql("COMMENT ON TABLE customer_addresses IS 'Customer shipping and billing addresses with multi-tenant isolation'");
        $this->addSql("COMMENT ON COLUMN customer_addresses.type IS 'Address type: shipping, billing, or both'");
        $this->addSql("COMMENT ON COLUMN customer_addresses.is_default_shipping IS 'Is this the default shipping address for the customer'");
        $this->addSql("COMMENT ON COLUMN customer_addresses.is_default_billing IS 'Is this the default billing address for the customer'");
        $this->addSql("COMMENT ON COLUMN customer_addresses.is_deleted IS 'Soft delete flag - deleted addresses are hidden but retained for historical records'");
        $this->addSql("COMMENT ON COLUMN customer_addresses.street2 IS 'Optional additional street address line (apartment, suite, etc.)'");
    }

    public function down(Schema $schema): void
    {
        // Drop RLS policy
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON customer_addresses');

        // Drop table (will cascade drop all foreign keys and indexes)
        $this->addSql('DROP TABLE IF EXISTS customer_addresses');
    }
}
