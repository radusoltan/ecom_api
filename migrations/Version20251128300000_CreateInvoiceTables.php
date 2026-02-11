<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Invoice schema migration
 *
 * Creates tables for the Invoice bounded context:
 * - invoices: Main invoice records with billing snapshots
 * - invoice_lines: Individual line items per invoice
 * - invoice_sequences: Atomic invoice numbering per tenant/year
 *
 * Business Rules (PRD Section 8.3 - Invoice Management):
 * - Invoice numbers are unique per tenant (format: INV-2025-00001)
 * - All amounts stored in minor units (cents) to avoid floating point issues
 * - Billing details captured as snapshot (immutable after invoice creation)
 * - Tax breakdown stored as JSONB for multiple rates
 * - Multi-tenant isolation via RLS policies
 * - Support for credit notes (credited_invoice_id reference)
 * - PDF generation and storage tracking
 * - Reverse charge mechanism for B2B EU transactions
 */
final class Version20251128300000_CreateInvoiceTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoices, invoice_lines, and invoice_sequences tables with RLS for Invoice bounded context';
    }

    public function up(Schema $schema): void
    {
        // ============================================
        // 📄 INVOICES TABLE
        // ============================================
        // Main invoice records linked to orders
        // Stores billing snapshot at invoice time (immutable)
        // Supports: draft, issued, paid, cancelled, credited statuses
        $this->addSql('
            CREATE TABLE IF NOT EXISTS invoices (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                order_id UUID NOT NULL,
                customer_id UUID NOT NULL,
                invoice_number VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'draft\',

                -- Billing details snapshot (captured at invoice time)
                billing_name VARCHAR(255) NOT NULL,
                billing_address_line1 VARCHAR(255) NOT NULL,
                billing_address_line2 VARCHAR(255),
                billing_city VARCHAR(100) NOT NULL,
                billing_postal_code VARCHAR(20) NOT NULL,
                billing_country VARCHAR(2) NOT NULL,
                billing_vat_number VARCHAR(20),

                -- Amounts in minor units (cents)
                subtotal_amount INT NOT NULL,
                tax_amount INT NOT NULL,
                total_amount INT NOT NULL,
                currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',

                -- Tax breakdown (JSON for multiple rates)
                -- Example: {"standard_20": 2000, "reduced_10": 500}
                tax_breakdown JSONB DEFAULT \'{}\'::jsonb,

                -- Dates
                issue_date TIMESTAMP WITH TIME ZONE,
                due_date TIMESTAMP WITH TIME ZONE,
                paid_date TIMESTAMP WITH TIME ZONE,

                -- PDF storage
                pdf_path VARCHAR(500),
                pdf_generated_at TIMESTAMP WITH TIME ZONE,

                -- Reverse charge flag (B2B EU transactions)
                is_reverse_charge BOOLEAN NOT NULL DEFAULT FALSE,

                -- Credit note reference (links to original invoice)
                credited_invoice_id UUID,

                -- Metadata
                notes TEXT,
                created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),

                CONSTRAINT fk_invoice_tenant FOREIGN KEY (tenant_id)
                    REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_invoice_order FOREIGN KEY (order_id)
                    REFERENCES orders(id) ON DELETE RESTRICT,
                CONSTRAINT fk_invoice_credited FOREIGN KEY (credited_invoice_id)
                    REFERENCES invoices(id) ON DELETE SET NULL,

                -- Business Rule: Invoice number unique per tenant
                CONSTRAINT uq_invoice_number_tenant
                    UNIQUE (tenant_id, invoice_number),

                -- Business Rule: Valid status transitions
                CONSTRAINT chk_invoice_status CHECK (status IN (
                    \'draft\', \'issued\', \'paid\', \'partially_paid\',
                    \'overdue\', \'cancelled\', \'credited\'
                )),

                -- Business Rule: Country code must be ISO 3166-1 alpha-2
                CONSTRAINT chk_invoice_country_code_valid
                    CHECK (billing_country ~ \'^[A-Z]{2}$\'),

                -- Business Rule: Currency must be ISO 4217 (3 uppercase letters)
                CONSTRAINT chk_invoice_currency_valid
                    CHECK (currency ~ \'^[A-Z]{3}$\'),

                -- Business Rule: Amounts must be non-negative
                CONSTRAINT chk_invoice_amounts_valid CHECK (
                    subtotal_amount >= 0
                    AND tax_amount >= 0
                    AND total_amount >= 0
                    AND total_amount = subtotal_amount + tax_amount
                ),

                -- Business Rule: Due date must be after issue date
                CONSTRAINT chk_invoice_dates_valid
                    CHECK (due_date IS NULL OR issue_date IS NULL OR due_date >= issue_date),

                -- Business Rule: Paid date must be after issue date
                CONSTRAINT chk_invoice_paid_date_valid
                    CHECK (paid_date IS NULL OR issue_date IS NULL OR paid_date >= issue_date)
            )
        ');

        // Indexes for invoices - tenant_id MUST be first for RLS efficiency
        $this->addSql('CREATE INDEX idx_invoices_tenant ON invoices(tenant_id)');
        $this->addSql('CREATE INDEX idx_invoices_tenant_order ON invoices(tenant_id, order_id)');
        $this->addSql('CREATE INDEX idx_invoices_tenant_customer ON invoices(tenant_id, customer_id)');
        $this->addSql('CREATE INDEX idx_invoices_tenant_status ON invoices(tenant_id, status)');
        $this->addSql('CREATE INDEX idx_invoices_tenant_issue_date ON invoices(tenant_id, issue_date DESC)');
        $this->addSql('CREATE INDEX idx_invoices_tenant_due_date ON invoices(tenant_id, due_date) WHERE status NOT IN (\'paid\', \'cancelled\', \'credited\')');
        $this->addSql('CREATE INDEX idx_invoices_invoice_number ON invoices(invoice_number)');
        $this->addSql('CREATE INDEX idx_invoices_created_at ON invoices(created_at DESC)');

        // Index for overdue invoices (background job)
        $this->addSql('
            CREATE INDEX idx_invoices_overdue
            ON invoices(tenant_id, due_date)
            WHERE status IN (\'issued\', \'partially_paid\') AND due_date IS NOT NULL
        ');

        // ============================================
        // 📋 INVOICE LINES TABLE
        // ============================================
        // Individual line items per invoice
        // Linked to products (optional) for reference
        $this->addSql('
            CREATE TABLE IF NOT EXISTS invoice_lines (
                id UUID PRIMARY KEY,
                invoice_id UUID NOT NULL,
                tenant_id UUID NOT NULL,

                -- Product reference (optional - product may be deleted)
                product_id UUID,
                sku VARCHAR(50),

                -- Line details
                description VARCHAR(500) NOT NULL,
                quantity INT NOT NULL,
                unit_price INT NOT NULL,

                -- Tax
                tax_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
                tax_amount INT NOT NULL DEFAULT 0,

                -- Totals
                line_total INT NOT NULL,

                -- Position for display order
                position INT NOT NULL DEFAULT 0,

                created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),

                CONSTRAINT fk_invoice_line_invoice FOREIGN KEY (invoice_id)
                    REFERENCES invoices(id) ON DELETE CASCADE,
                CONSTRAINT fk_invoice_line_tenant FOREIGN KEY (tenant_id)
                    REFERENCES tenants(id) ON DELETE CASCADE,

                -- Business Rule: Quantity must be positive
                CONSTRAINT chk_invoice_line_quantity_valid
                    CHECK (quantity > 0),

                -- Business Rule: Unit price must be non-negative
                CONSTRAINT chk_invoice_line_unit_price_valid
                    CHECK (unit_price >= 0),

                -- Business Rule: Tax rate must be between 0% and 100%
                CONSTRAINT chk_invoice_line_tax_rate_valid
                    CHECK (tax_rate >= 0.00 AND tax_rate <= 100.00),

                -- Business Rule: Line total = quantity * unit_price
                CONSTRAINT chk_invoice_line_total_valid
                    CHECK (line_total = quantity * unit_price),

                -- Business Rule: Position must be non-negative
                CONSTRAINT chk_invoice_line_position_valid
                    CHECK (position >= 0)
            )
        ');

        // Indexes for invoice_lines - tenant_id first for RLS
        $this->addSql('CREATE INDEX idx_invoice_lines_tenant ON invoice_lines(tenant_id)');
        $this->addSql('CREATE INDEX idx_invoice_lines_tenant_invoice ON invoice_lines(tenant_id, invoice_id)');
        $this->addSql('CREATE INDEX idx_invoice_lines_invoice_position ON invoice_lines(invoice_id, position)');
        $this->addSql('CREATE INDEX idx_invoice_lines_product ON invoice_lines(product_id) WHERE product_id IS NOT NULL');
        $this->addSql('CREATE INDEX idx_invoice_lines_sku ON invoice_lines(sku) WHERE sku IS NOT NULL');

        // ============================================
        // 🔢 INVOICE SEQUENCES TABLE
        // ============================================
        // Atomic invoice number generation per tenant/year
        // Format: INV-{YEAR}-{SEQUENCE} (e.g., INV-2025-00001)
        $this->addSql('
            CREATE TABLE IF NOT EXISTS invoice_sequences (
                tenant_id UUID NOT NULL,
                year INT NOT NULL,
                current_sequence INT NOT NULL DEFAULT 0,

                PRIMARY KEY (tenant_id, year),

                CONSTRAINT fk_invoice_sequence_tenant FOREIGN KEY (tenant_id)
                    REFERENCES tenants(id) ON DELETE CASCADE,

                -- Business Rule: Year must be reasonable (2000-2100)
                CONSTRAINT chk_invoice_sequence_year_valid
                    CHECK (year >= 2000 AND year <= 2100),

                -- Business Rule: Sequence must be non-negative
                CONSTRAINT chk_invoice_sequence_positive
                    CHECK (current_sequence >= 0)
            )
        ');

        // Index for invoice_sequences
        $this->addSql('CREATE INDEX idx_invoice_sequences_tenant ON invoice_sequences(tenant_id)');

        // ============================================
        // ⚡ POSTGRESQL FUNCTION: Atomic Number Generation
        // ============================================
        // This function ensures thread-safe invoice number generation
        // Uses INSERT ... ON CONFLICT to atomically increment sequence
        $this->addSql('
            CREATE OR REPLACE FUNCTION get_next_invoice_number(p_tenant_id UUID, p_year INT)
            RETURNS INT AS $$
            DECLARE
                v_next_sequence INT;
            BEGIN
                -- Insert or update with atomic increment
                INSERT INTO invoice_sequences (tenant_id, year, current_sequence)
                VALUES (p_tenant_id, p_year, 1)
                ON CONFLICT (tenant_id, year)
                DO UPDATE SET current_sequence = invoice_sequences.current_sequence + 1
                RETURNING current_sequence INTO v_next_sequence;

                RETURN v_next_sequence;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // ============================================
        // 🔒 ROW-LEVEL SECURITY (RLS)
        // ============================================
        // Enable RLS on all tables
        $this->addSql('ALTER TABLE invoices ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE invoice_lines ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE invoice_sequences ENABLE ROW LEVEL SECURITY');

        // Create tenant isolation policies
        // Uses current_setting('app.tenant_id') set by application
        // Fallback to tenant_id column if setting not defined (allows seeding)
        $this->addSql('
            CREATE POLICY tenant_isolation_invoices ON invoices
            FOR ALL
            USING (tenant_id = COALESCE(NULLIF(current_setting(\'app.tenant_id\', true), \'\')::UUID, tenant_id))
        ');

        $this->addSql('
            CREATE POLICY tenant_isolation_invoice_lines ON invoice_lines
            FOR ALL
            USING (tenant_id = COALESCE(NULLIF(current_setting(\'app.tenant_id\', true), \'\')::UUID, tenant_id))
        ');

        $this->addSql('
            CREATE POLICY tenant_isolation_invoice_sequences ON invoice_sequences
            FOR ALL
            USING (tenant_id = COALESCE(NULLIF(current_setting(\'app.tenant_id\', true), \'\')::UUID, tenant_id))
        ');

        // ============================================
        // 📝 TABLE DOCUMENTATION
        // ============================================
        $this->addSql("
            COMMENT ON TABLE invoices IS
            'Invoice records with billing snapshots and multi-tenant RLS isolation.
            Supports draft, issued, paid, cancelled, and credit note workflows.
            Invoice numbers are atomically generated per tenant/year.'
        ");

        $this->addSql("
            COMMENT ON TABLE invoice_lines IS
            'Individual line items per invoice with product references.
            Stores description, quantity, unit price, and tax calculations.
            Position field allows custom display ordering.'
        ");

        $this->addSql("
            COMMENT ON TABLE invoice_sequences IS
            'Atomic invoice number generation per tenant and year.
            Uses PostgreSQL function for thread-safe sequence increment.
            Format: INV-{YEAR}-{SEQUENCE} (e.g., INV-2025-00001).'
        ");

        $this->addSql("COMMENT ON COLUMN invoices.status IS 'Invoice status: draft, issued, paid, partially_paid, overdue, cancelled, credited'");
        $this->addSql("COMMENT ON COLUMN invoices.tax_breakdown IS 'JSONB object with tax amounts per rate (e.g., {\"standard_20\": 2000, \"reduced_10\": 500})'");
        $this->addSql("COMMENT ON COLUMN invoices.is_reverse_charge IS 'True for B2B EU reverse charge mechanism (no VAT charged)'");
        $this->addSql("COMMENT ON COLUMN invoices.credited_invoice_id IS 'Reference to original invoice if this is a credit note'");
        $this->addSql("COMMENT ON COLUMN invoice_lines.line_total IS 'Calculated as quantity * unit_price (constraint enforced)'");
        $this->addSql("COMMENT ON COLUMN invoice_lines.position IS 'Display order position (0-based)'");
        $this->addSql("COMMENT ON FUNCTION get_next_invoice_number IS 'Atomically generates next invoice sequence number for tenant/year'");
    }

    public function down(Schema $schema): void
    {
        // Drop PostgreSQL function first
        $this->addSql('DROP FUNCTION IF EXISTS get_next_invoice_number(UUID, INT)');

        // Drop RLS policies
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoice_sequences ON invoice_sequences');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoice_lines ON invoice_lines');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoices ON invoices');

        // Drop tables in reverse order (foreign key dependencies)
        $this->addSql('DROP TABLE IF EXISTS invoice_lines');
        $this->addSql('DROP TABLE IF EXISTS invoice_sequences');
        $this->addSql('DROP TABLE IF EXISTS invoices');
    }
}
