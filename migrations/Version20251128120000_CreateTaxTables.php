<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create Tax bounded context tables with Row-Level Security
 *
 * This migration creates the tax_rules and vat_validations tables for the Tax context.
 * Both tables are multi-tenant with RLS policies for complete tenant isolation.
 *
 * Business Requirements (PRD Section 6.1 - Tax Management):
 * - Support multiple tax jurisdictions (country + region)
 * - Category-based tax rates (standard, reduced, zero, exempt)
 * - Time-based validity periods for tax rules
 * - VAT validation caching (24h TTL) for EU VIES integration
 * - Multi-tenant isolation at database level
 *
 * Tables:
 * - tax_rules: Tax rates per jurisdiction and product category
 * - vat_validations: Cached VAT number validation results from VIES
 */
final class Version20251128120000_CreateTaxTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tax_rules and vat_validations tables with RLS for Tax bounded context';
    }

    public function up(Schema $schema): void
    {
        // =========================================================
        // Create tax_rules table
        // =========================================================
        $this->addSql('
            CREATE TABLE tax_rules (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                country_code VARCHAR(2) NOT NULL,
                region_code VARCHAR(10),
                category VARCHAR(20) NOT NULL,
                rate NUMERIC(5,2) NOT NULL,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                is_active BOOLEAN NOT NULL DEFAULT true,
                valid_from TIMESTAMP WITH TIME ZONE,
                valid_until TIMESTAMP WITH TIME ZONE,
                priority INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),

                CONSTRAINT fk_tax_rules_tenant FOREIGN KEY (tenant_id)
                    REFERENCES tenants(id) ON DELETE CASCADE,

                -- Business Rule: Only one active rule per jurisdiction + category per tenant
                -- Ensures deterministic tax calculation
                CONSTRAINT uq_tax_rules_jurisdiction_category
                    UNIQUE NULLS NOT DISTINCT (tenant_id, country_code, region_code, category, is_active)
                    WHERE is_active = true,

                -- Business Rule: Rate must be between 0% and 100%
                CONSTRAINT chk_tax_rules_rate_valid
                    CHECK (rate >= 0.00 AND rate <= 100.00),

                -- Business Rule: Valid category values
                CONSTRAINT chk_tax_rules_category_valid
                    CHECK (category IN (\'standard\', \'reduced\', \'zero\', \'exempt\', \'reverse_charge\')),

                -- Business Rule: Country code must be ISO 3166-1 alpha-2
                CONSTRAINT chk_tax_rules_country_code_valid
                    CHECK (country_code ~ \'^[A-Z]{2}$\'),

                -- Business Rule: Valid period (from < until)
                CONSTRAINT chk_tax_rules_validity_period
                    CHECK (valid_from IS NULL OR valid_until IS NULL OR valid_from < valid_until)
            )
        ');

        // =========================================================
        // Indexes for tax_rules
        // =========================================================

        // Primary tenant isolation index (CRITICAL for RLS performance)
        $this->addSql('CREATE INDEX idx_tax_rules_tenant ON tax_rules(tenant_id)');

        // Lookup index for tax calculation (most frequent query pattern)
        $this->addSql('
            CREATE INDEX idx_tax_rules_jurisdiction_lookup
            ON tax_rules(tenant_id, country_code, region_code, category, is_active)
            WHERE is_active = true
        ');

        // Index for country-based queries
        $this->addSql('CREATE INDEX idx_tax_rules_country ON tax_rules(country_code)');

        // Index for category filtering
        $this->addSql('CREATE INDEX idx_tax_rules_category ON tax_rules(category)');

        // Index for active rules filtering
        $this->addSql('CREATE INDEX idx_tax_rules_active ON tax_rules(is_active) WHERE is_active = true');

        // Index for validity period queries (find applicable rules for a given date)
        $this->addSql('
            CREATE INDEX idx_tax_rules_validity
            ON tax_rules(valid_from, valid_until)
            WHERE is_active = true
        ');

        // =========================================================
        // Create vat_validations table
        // =========================================================
        $this->addSql('
            CREATE TABLE vat_validations (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id UUID NOT NULL,
                vat_number VARCHAR(20) NOT NULL,
                country_code VARCHAR(2) NOT NULL,
                is_valid BOOLEAN NOT NULL,
                business_name VARCHAR(255),
                business_address TEXT,
                request_id VARCHAR(100),
                error_message TEXT,
                validated_at TIMESTAMP WITH TIME ZONE NOT NULL,
                expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
                created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),

                CONSTRAINT fk_vat_validations_tenant FOREIGN KEY (tenant_id)
                    REFERENCES tenants(id) ON DELETE CASCADE,

                -- Business Rule: Country code must be ISO 3166-1 alpha-2
                CONSTRAINT chk_vat_validations_country_code_valid
                    CHECK (country_code ~ \'^[A-Z]{2}$\'),

                -- Business Rule: Expires at must be after validated at
                CONSTRAINT chk_vat_validations_expiry_valid
                    CHECK (expires_at > validated_at)
            )
        ');

        // =========================================================
        // Indexes for vat_validations
        // =========================================================

        // Primary tenant isolation index (CRITICAL for RLS performance)
        $this->addSql('CREATE INDEX idx_vat_validations_tenant ON vat_validations(tenant_id)');

        // Cache lookup index (most frequent query pattern: check if validation exists and is not expired)
        $this->addSql('
            CREATE INDEX idx_vat_validations_lookup
            ON vat_validations(tenant_id, vat_number, expires_at)
        ');

        // Index for VAT number queries
        $this->addSql('CREATE INDEX idx_vat_validations_number ON vat_validations(vat_number)');

        // Index for cleanup of expired validations (background job)
        $this->addSql('CREATE INDEX idx_vat_validations_expires ON vat_validations(expires_at)');

        // =========================================================
        // Enable Row-Level Security on tax_rules
        // =========================================================
        $this->addSql('ALTER TABLE tax_rules ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE tax_rules FORCE ROW LEVEL SECURITY');

        $this->addSql("
            CREATE POLICY tax_rules_tenant_isolation ON tax_rules
            FOR ALL
            USING (tenant_id::text = current_setting('app.tenant_id', true))
            WITH CHECK (tenant_id::text = current_setting('app.tenant_id', true))
        ");

        // =========================================================
        // Enable Row-Level Security on vat_validations
        // =========================================================
        $this->addSql('ALTER TABLE vat_validations ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE vat_validations FORCE ROW LEVEL SECURITY');

        $this->addSql("
            CREATE POLICY vat_validations_tenant_isolation ON vat_validations
            FOR ALL
            USING (tenant_id::text = current_setting('app.tenant_id', true))
            WITH CHECK (tenant_id::text = current_setting('app.tenant_id', true))
        ");

        // =========================================================
        // Table documentation
        // =========================================================
        $this->addSql("
            COMMENT ON TABLE tax_rules IS
            'Tax rules per jurisdiction and category with multi-tenant RLS isolation.
            Supports time-based validity periods and priority-based rule resolution.'
        ");

        $this->addSql("
            COMMENT ON TABLE vat_validations IS
            'Cached VAT validation results from EU VIES service with 24h TTL.
            Reduces API calls and improves performance for repeated validations.'
        ");

        $this->addSql("COMMENT ON COLUMN tax_rules.rate IS 'Tax rate percentage (0.00-100.00)'");
        $this->addSql("COMMENT ON COLUMN tax_rules.category IS 'Tax category: standard, reduced, zero, exempt, reverse_charge'");
        $this->addSql("COMMENT ON COLUMN tax_rules.priority IS 'Higher priority rules override lower priority when multiple rules match'");
        $this->addSql("COMMENT ON COLUMN vat_validations.expires_at IS 'Cache expiry timestamp (typically validated_at + 24 hours)'");
    }

    public function down(Schema $schema): void
    {
        // Drop RLS policies first
        $this->addSql('DROP POLICY IF EXISTS vat_validations_tenant_isolation ON vat_validations');
        $this->addSql('DROP POLICY IF EXISTS tax_rules_tenant_isolation ON tax_rules');

        // Drop tables (indexes and constraints are dropped automatically)
        $this->addSql('DROP TABLE IF EXISTS vat_validations');
        $this->addSql('DROP TABLE IF EXISTS tax_rules');
    }
}
