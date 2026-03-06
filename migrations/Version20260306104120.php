<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Structural migration for 4 entities with schema drift:
 * - payments: rename columns, add gateway/retry fields, drop legacy fields
 * - invoices: consolidate billing address, add currency columns, adjust nullability
 * - invoice_lines: rename unit_price, add currency columns, drop computed/legacy columns
 * - product_reviews: replace is_verified/is_approved with status/is_verified_purchase
 * - Drop unused invoice_sequences table
 */
final class Version20260306104120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align payments, invoices, invoice_lines, product_reviews with Doctrine entities; drop invoice_sequences';
    }

    public function up(Schema $schema): void
    {
        // ── invoice_sequences: drop RLS then table ──
        $this->addSql('ALTER TABLE invoice_sequences DISABLE ROW LEVEL SECURITY');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON invoice_sequences');
        $this->addSql('DROP TABLE invoice_sequences');

        // ── payments ──
        // Drop check constraints that reference columns being dropped/renamed
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT IF EXISTS chk_payment_amounts');
        // Rename columns
        $this->addSql('ALTER TABLE payments RENAME COLUMN amount_minor TO amount_in_cents');
        $this->addSql('ALTER TABLE payments RENAME COLUMN failure_code TO error_code');
        // Add new columns
        $this->addSql('ALTER TABLE payments ADD gateway VARCHAR(20) NOT NULL DEFAULT \'unknown\'');
        $this->addSql('ALTER TABLE payments ADD gateway_transaction_id TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD refunded_amount_in_cents INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE payments ADD retry_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE payments ADD next_retry_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        // Drop legacy columns
        $this->addSql('ALTER TABLE payments DROP COLUMN customer_id');
        $this->addSql('ALTER TABLE payments DROP COLUMN gateway_reference');
        $this->addSql('ALTER TABLE payments DROP COLUMN captured_amount_minor');
        $this->addSql('ALTER TABLE payments DROP COLUMN refunded_amount_minor');
        $this->addSql('ALTER TABLE payments DROP COLUMN metadata');
        $this->addSql('ALTER TABLE payments DROP COLUMN failure_message');
        $this->addSql('ALTER TABLE payments DROP COLUMN authorized_at');
        $this->addSql('ALTER TABLE payments DROP COLUMN captured_at');
        $this->addSql('ALTER TABLE payments DROP COLUMN cancelled_at');
        // Drop defaults on existing columns (Doctrine manages these)
        $this->addSql('ALTER TABLE payments ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE payments ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE payments ALTER updated_at DROP DEFAULT');
        // Remove DEFAULT from gateway after backfill
        $this->addSql('ALTER TABLE payments ALTER gateway DROP DEFAULT');
        // Drop legacy indexes (reference dropped columns or wrong naming)
        $this->addSql('DROP INDEX IF EXISTS idx_payments_tenant_customer');
        $this->addSql('DROP INDEX IF EXISTS idx_payments_tenant_order');
        $this->addSql('DROP INDEX IF EXISTS idx_payments_idempotency');
        // Drop legacy FK constraints (Doctrine manages relations differently)
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT IF EXISTS fk_payment_order');
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT IF EXISTS fk_payment_tenant');
        // Add new indexes
        $this->addSql('CREATE INDEX idx_payments_status ON payments (status)');
        $this->addSql('CREATE INDEX idx_payments_gateway ON payments (gateway)');
        $this->addSql('CREATE INDEX idx_payments_retry ON payments (status, next_retry_at, retry_count)');
        $this->addSql('CREATE INDEX idx_payments_order_id ON payments (order_id)');

        // ── invoices ──
        // Drop check constraints that reference columns being dropped
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS chk_invoice_country_code_valid');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS chk_invoice_currency_valid');
        // Consolidate billing address into single encrypted JSON column
        $this->addSql('ALTER TABLE invoices ADD billing_address TEXT NOT NULL DEFAULT \'{}\'');
        $this->addSql('ALTER TABLE invoices ADD subtotal_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        $this->addSql('ALTER TABLE invoices ADD tax_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        $this->addSql('ALTER TABLE invoices ADD total_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        // Drop legacy billing columns
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_name');
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_address_line1');
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_address_line2');
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_city');
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_postal_code');
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_country');
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_vat_number');
        $this->addSql('ALTER TABLE invoices DROP COLUMN currency');
        $this->addSql('ALTER TABLE invoices DROP COLUMN pdf_generated_at');
        // Adjust nullability & defaults
        $this->addSql('ALTER TABLE invoices ALTER invoice_number DROP NOT NULL');
        $this->addSql('ALTER TABLE invoices ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices ALTER tax_breakdown DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices ALTER tax_breakdown SET NOT NULL');
        $this->addSql('ALTER TABLE invoices ALTER is_reverse_charge DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices ALTER updated_at DROP DEFAULT');
        // Remove defaults from new columns
        $this->addSql('ALTER TABLE invoices ALTER billing_address DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices ALTER subtotal_currency DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices ALTER tax_currency DROP DEFAULT');
        $this->addSql('ALTER TABLE invoices ALTER total_currency DROP DEFAULT');
        // Drop legacy FK constraints
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS fk_invoice_tenant');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS fk_invoice_credited');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS fk_invoice_order');
        // Drop legacy indexes (tenant-prefixed, Doctrine uses simpler names)
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_tenant_order');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_tenant_issue_date');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_tenant_due_date');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_created_at');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_overdue');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS uq_invoice_number_tenant');
        // Add new indexes
        $this->addSql('CREATE INDEX idx_invoices_customer_id ON invoices (customer_id)');
        $this->addSql('CREATE INDEX idx_invoices_status ON invoices (status)');
        $this->addSql('CREATE INDEX idx_invoices_issue_date ON invoices (issue_date)');
        $this->addSql('CREATE INDEX idx_invoices_due_date ON invoices (due_date)');
        $this->addSql('CREATE INDEX idx_invoices_order_id ON invoices (order_id)');
        $this->addSql('ALTER INDEX idx_invoices_tenant RENAME TO idx_invoices_tenant_id');

        // ── invoice_lines ──
        // Drop check constraints that reference columns being dropped/renamed
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT IF EXISTS chk_invoice_line_unit_price_valid');
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT IF EXISTS chk_invoice_line_total_valid');
        // Drop FK constraint (Doctrine manages via ManyToOne)
        $this->addSql('ALTER TABLE invoice_lines DROP CONSTRAINT IF EXISTS fk_invoice_line_tenant');
        // Rename unit_price -> unit_price_amount
        $this->addSql('ALTER TABLE invoice_lines RENAME COLUMN unit_price TO unit_price_amount');
        // Add new currency columns
        $this->addSql('ALTER TABLE invoice_lines ADD unit_price_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        $this->addSql('ALTER TABLE invoice_lines ADD tax_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        $this->addSql('ALTER TABLE invoice_lines ADD line_total_amount INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE invoice_lines ADD line_total_currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        // Drop legacy columns (keep tenant_id for RLS!)
        $this->addSql('ALTER TABLE invoice_lines DROP COLUMN line_total');
        $this->addSql('ALTER TABLE invoice_lines DROP COLUMN created_at');
        // Adjust types & defaults
        $this->addSql('ALTER TABLE invoice_lines ALTER tax_rate TYPE DOUBLE PRECISION USING tax_rate::double precision');
        $this->addSql('ALTER TABLE invoice_lines ALTER tax_rate DROP DEFAULT');
        $this->addSql('ALTER TABLE invoice_lines ALTER tax_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE invoice_lines ALTER position DROP DEFAULT');
        // Remove defaults from new columns
        $this->addSql('ALTER TABLE invoice_lines ALTER unit_price_currency DROP DEFAULT');
        $this->addSql('ALTER TABLE invoice_lines ALTER tax_currency DROP DEFAULT');
        $this->addSql('ALTER TABLE invoice_lines ALTER line_total_amount DROP DEFAULT');
        $this->addSql('ALTER TABLE invoice_lines ALTER line_total_currency DROP DEFAULT');
        // Drop legacy indexes
        $this->addSql('DROP INDEX IF EXISTS idx_invoice_lines_product');
        $this->addSql('DROP INDEX IF EXISTS idx_invoice_lines_sku');
        $this->addSql('DROP INDEX IF EXISTS idx_invoice_lines_tenant');
        $this->addSql('DROP INDEX IF EXISTS idx_invoice_lines_invoice_position');
        $this->addSql('DROP INDEX IF EXISTS idx_invoice_lines_tenant_invoice');
        // Add new indexes
        $this->addSql('CREATE INDEX idx_invoice_lines_product_id ON invoice_lines (product_id)');
        $this->addSql('CREATE INDEX idx_invoice_lines_invoice_id ON invoice_lines (invoice_id)');

        // ── product_reviews ──
        // Drop FK constraints
        $this->addSql('ALTER TABLE product_reviews DROP CONSTRAINT IF EXISTS fk_product_reviews_product');
        $this->addSql('ALTER TABLE product_reviews DROP CONSTRAINT IF EXISTS fk_product_reviews_tenant');
        // Add new columns
        $this->addSql('ALTER TABLE product_reviews ADD customer_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_reviews ADD status VARCHAR(20) NOT NULL DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE product_reviews ADD is_verified_purchase BOOLEAN NOT NULL DEFAULT false');
        // Drop legacy columns
        $this->addSql('ALTER TABLE product_reviews DROP COLUMN is_verified');
        $this->addSql('ALTER TABLE product_reviews DROP COLUMN is_approved');
        // Adjust types & defaults
        $this->addSql('ALTER TABLE product_reviews ALTER rating TYPE SMALLINT');
        $this->addSql('ALTER TABLE product_reviews ALTER created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE product_reviews ALTER updated_at DROP DEFAULT');
        // Remove defaults from new columns
        $this->addSql('ALTER TABLE product_reviews ALTER status DROP DEFAULT');
        $this->addSql('ALTER TABLE product_reviews ALTER is_verified_purchase DROP DEFAULT');
        // Drop legacy indexes
        $this->addSql('DROP INDEX IF EXISTS idx_product_reviews_tenant_approved');
        $this->addSql('DROP INDEX IF EXISTS idx_product_reviews_created_at');
        $this->addSql('DROP INDEX IF EXISTS idx_product_reviews_rating');
        $this->addSql('DROP INDEX IF EXISTS idx_product_reviews_tenant_product');
        // Add new indexes
        $this->addSql('CREATE INDEX idx_reviews_status ON product_reviews (status)');
        $this->addSql('CREATE INDEX idx_reviews_product_status ON product_reviews (product_id, status)');
        $this->addSql('ALTER INDEX idx_product_reviews_product_id RENAME TO idx_reviews_product');
        $this->addSql('ALTER INDEX idx_product_reviews_tenant_id RENAME TO idx_reviews_tenant');
        $this->addSql('ALTER INDEX idx_product_reviews_customer_id RENAME TO idx_reviews_customer');
    }

    public function down(Schema $schema): void
    {
        // ── invoice_sequences: recreate with RLS ──
        $this->addSql('CREATE TABLE invoice_sequences (tenant_id UUID NOT NULL, year INT NOT NULL, current_sequence INT DEFAULT 0 NOT NULL, PRIMARY KEY (tenant_id, year))');
        $this->addSql('ALTER TABLE invoice_sequences ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE invoice_sequences FORCE ROW LEVEL SECURITY');
        $this->addSql("CREATE POLICY tenant_isolation ON invoice_sequences USING ((current_setting('app.bypass_rls', true) = 'true') OR (tenant_id::text = current_setting('app.tenant_id', true)))");

        // ── payments: reverse ──
        $this->addSql('ALTER TABLE payments RENAME COLUMN amount_in_cents TO amount_minor');
        $this->addSql('ALTER TABLE payments RENAME COLUMN error_code TO failure_code');
        $this->addSql('ALTER TABLE payments ADD customer_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD gateway_reference TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD captured_amount_minor INT DEFAULT 0');
        $this->addSql('ALTER TABLE payments ADD refunded_amount_minor INT DEFAULT 0');
        $this->addSql('ALTER TABLE payments ADD metadata JSONB DEFAULT \'{}\'');
        $this->addSql('ALTER TABLE payments ADD failure_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD authorized_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD captured_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD cancelled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE payments DROP COLUMN gateway');
        $this->addSql('ALTER TABLE payments DROP COLUMN gateway_transaction_id');
        $this->addSql('ALTER TABLE payments DROP COLUMN error_message');
        $this->addSql('ALTER TABLE payments DROP COLUMN refunded_amount_in_cents');
        $this->addSql('ALTER TABLE payments DROP COLUMN retry_count');
        $this->addSql('ALTER TABLE payments DROP COLUMN next_retry_at');
        $this->addSql('ALTER TABLE payments ALTER status SET DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE payments ALTER created_at SET DEFAULT now()');
        $this->addSql('ALTER TABLE payments ALTER updated_at SET DEFAULT now()');
        $this->addSql('DROP INDEX IF EXISTS idx_payments_status');
        $this->addSql('DROP INDEX IF EXISTS idx_payments_gateway');
        $this->addSql('DROP INDEX IF EXISTS idx_payments_retry');
        $this->addSql('DROP INDEX IF EXISTS idx_payments_order_id');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT fk_payment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_payments_tenant_customer ON payments (tenant_id, customer_id)');
        $this->addSql('CREATE INDEX idx_payments_tenant_order ON payments (tenant_id, order_id)');
        $this->addSql('CREATE UNIQUE INDEX idx_payments_idempotency ON payments (tenant_id, idempotency_key) WHERE (idempotency_key IS NOT NULL)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT chk_payment_amounts CHECK (amount_minor > 0 AND captured_amount_minor >= 0 AND refunded_amount_minor >= 0 AND refunded_amount_minor <= captured_amount_minor)');

        // ── invoices: reverse ──
        $this->addSql('ALTER TABLE invoices ADD billing_name TEXT NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE invoices ADD billing_address_line1 TEXT NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE invoices ADD billing_address_line2 TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD billing_city TEXT NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE invoices ADD billing_postal_code TEXT NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE invoices ADD billing_country VARCHAR(2) NOT NULL DEFAULT \'XX\'');
        $this->addSql('ALTER TABLE invoices ADD billing_vat_number TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD currency VARCHAR(3) NOT NULL DEFAULT \'EUR\'');
        $this->addSql('ALTER TABLE invoices ADD pdf_generated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices DROP COLUMN billing_address');
        $this->addSql('ALTER TABLE invoices DROP COLUMN subtotal_currency');
        $this->addSql('ALTER TABLE invoices DROP COLUMN tax_currency');
        $this->addSql('ALTER TABLE invoices DROP COLUMN total_currency');
        $this->addSql('ALTER TABLE invoices ALTER invoice_number SET NOT NULL');
        $this->addSql('ALTER TABLE invoices ALTER status SET DEFAULT \'draft\'');
        $this->addSql('ALTER TABLE invoices ALTER tax_breakdown SET DEFAULT \'{}\'');
        $this->addSql('ALTER TABLE invoices ALTER tax_breakdown DROP NOT NULL');
        $this->addSql('ALTER TABLE invoices ALTER is_reverse_charge SET DEFAULT false');
        $this->addSql('ALTER TABLE invoices ALTER created_at SET DEFAULT now()');
        $this->addSql('ALTER TABLE invoices ALTER updated_at SET DEFAULT now()');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_credited FOREIGN KEY (credited_invoice_id) REFERENCES invoices (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoice_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_customer_id');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_status');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_issue_date');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_due_date');
        $this->addSql('DROP INDEX IF EXISTS idx_invoices_order_id');
        $this->addSql('ALTER INDEX idx_invoices_tenant_id RENAME TO idx_invoices_tenant');
        $this->addSql('CREATE INDEX idx_invoices_tenant_order ON invoices (tenant_id, order_id)');
        $this->addSql('CREATE INDEX idx_invoices_tenant_issue_date ON invoices (tenant_id, issue_date)');
        $this->addSql('CREATE INDEX idx_invoices_tenant_due_date ON invoices (tenant_id, due_date) WHERE status::text <> ALL (ARRAY[\'paid\'::text, \'cancelled\'::text, \'credited\'::text])');
        $this->addSql('CREATE INDEX idx_invoices_created_at ON invoices (created_at)');
        $this->addSql('CREATE INDEX idx_invoices_overdue ON invoices (tenant_id, due_date) WHERE status::text = ANY (ARRAY[\'issued\'::text, \'partially_paid\'::text]) AND due_date IS NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uq_invoice_number_tenant ON invoices (tenant_id, invoice_number)');
        $this->addSql("ALTER TABLE invoices ADD CONSTRAINT chk_invoice_country_code_valid CHECK (billing_country::text ~ '^[A-Z]{2}$')");
        $this->addSql("ALTER TABLE invoices ADD CONSTRAINT chk_invoice_currency_valid CHECK (currency::text ~ '^[A-Z]{3}$')");

        // ── invoice_lines: reverse ──
        $this->addSql('ALTER TABLE invoice_lines RENAME COLUMN unit_price_amount TO unit_price');
        $this->addSql('ALTER TABLE invoice_lines ADD line_total INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE invoice_lines ADD created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT now()');
        $this->addSql('ALTER TABLE invoice_lines DROP COLUMN unit_price_currency');
        $this->addSql('ALTER TABLE invoice_lines DROP COLUMN tax_currency');
        $this->addSql('ALTER TABLE invoice_lines DROP COLUMN line_total_amount');
        $this->addSql('ALTER TABLE invoice_lines DROP COLUMN line_total_currency');
        $this->addSql('ALTER TABLE invoice_lines ALTER tax_rate TYPE NUMERIC(5, 2) USING tax_rate::numeric(5,2)');
        $this->addSql('ALTER TABLE invoice_lines ALTER tax_rate SET DEFAULT 0');
        $this->addSql('ALTER TABLE invoice_lines ALTER tax_amount SET DEFAULT 0');
        $this->addSql('ALTER TABLE invoice_lines ALTER position SET DEFAULT 0');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT fk_invoice_line_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX IF EXISTS idx_invoice_lines_product_id');
        $this->addSql('DROP INDEX IF EXISTS idx_invoice_lines_invoice_id');
        $this->addSql('CREATE INDEX idx_invoice_lines_product ON invoice_lines (product_id) WHERE product_id IS NOT NULL');
        $this->addSql('CREATE INDEX idx_invoice_lines_sku ON invoice_lines (sku) WHERE sku IS NOT NULL');
        $this->addSql('CREATE INDEX idx_invoice_lines_tenant ON invoice_lines (tenant_id)');
        $this->addSql('CREATE INDEX idx_invoice_lines_invoice_position ON invoice_lines (invoice_id, position)');
        $this->addSql('CREATE INDEX idx_invoice_lines_tenant_invoice ON invoice_lines (tenant_id, invoice_id)');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT chk_invoice_line_unit_price_valid CHECK (unit_price >= 0)');
        $this->addSql('ALTER TABLE invoice_lines ADD CONSTRAINT chk_invoice_line_total_valid CHECK (line_total = (quantity * unit_price))');

        // ── product_reviews: reverse ──
        $this->addSql('ALTER TABLE product_reviews ADD is_verified BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE product_reviews ADD is_approved BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE product_reviews DROP COLUMN customer_name');
        $this->addSql('ALTER TABLE product_reviews DROP COLUMN status');
        $this->addSql('ALTER TABLE product_reviews DROP COLUMN is_verified_purchase');
        $this->addSql('ALTER TABLE product_reviews ALTER rating TYPE INT');
        $this->addSql('ALTER TABLE product_reviews ALTER created_at SET DEFAULT now()');
        $this->addSql('ALTER TABLE product_reviews ALTER updated_at SET DEFAULT now()');
        $this->addSql('ALTER TABLE product_reviews ADD CONSTRAINT fk_product_reviews_product FOREIGN KEY (product_id) REFERENCES catalog_products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_reviews ADD CONSTRAINT fk_product_reviews_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_status');
        $this->addSql('DROP INDEX IF EXISTS idx_reviews_product_status');
        $this->addSql('ALTER INDEX idx_reviews_product RENAME TO idx_product_reviews_product_id');
        $this->addSql('ALTER INDEX idx_reviews_tenant RENAME TO idx_product_reviews_tenant_id');
        $this->addSql('ALTER INDEX idx_reviews_customer RENAME TO idx_product_reviews_customer_id');
        $this->addSql('CREATE INDEX idx_product_reviews_tenant_approved ON product_reviews (tenant_id, is_approved)');
        $this->addSql('CREATE INDEX idx_product_reviews_created_at ON product_reviews (created_at)');
        $this->addSql('CREATE INDEX idx_product_reviews_rating ON product_reviews (rating)');
        $this->addSql('CREATE INDEX idx_product_reviews_tenant_product ON product_reviews (tenant_id, product_id)');
    }
}
