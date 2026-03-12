<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix COALESCE RLS policies on 5 tables (P0 security fix).
 *
 * Problem: The COALESCE pattern allows full table access when tenant context is missing:
 *   tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id)
 *   → When app.tenant_id is empty: COALESCE(NULL, tenant_id) = tenant_id → always true → ALL rows returned
 *
 * Fix: Replace with the standard fail-closed ::text pattern used by all other tables:
 *   tenant_id::text = current_setting('app.tenant_id', true)
 *   → When app.tenant_id is empty: tenant_id::text = '' → always false → 0 rows returned
 *
 * Tables affected: payments, invoices, invoice_lines, invoice_sequences, payment_webhook_events
 * Tables NOT affected: payment_transactions (doesn't exist, renamed to transactions), refunds (doesn't exist)
 *
 * @see docs/security/RLS_MIGRATION_DESIGN_SPRINT4.md
 */
final class Version20260310120000_FixCoalesceRLSPolicies extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace dangerous COALESCE RLS policies with fail-closed ::text pattern on 5 payment/invoice tables';
    }

    public function up(Schema $schema): void
    {
        // ──────────────────────────────────────────────────────────────
        // 1. payments (policy: tenant_isolation_payments)
        // ──────────────────────────────────────────────────────────────
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_payments ON payments');
        $this->addSql("
            CREATE POLICY tenant_isolation_payments ON payments
                FOR ALL
                USING (tenant_id::text = current_setting('app.tenant_id', true))
        ");

        // ──────────────────────────────────────────────────────────────
        // 2. invoices (policy: tenant_isolation_invoices)
        // ──────────────────────────────────────────────────────────────
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoices ON invoices');
        $this->addSql("
            CREATE POLICY tenant_isolation_invoices ON invoices
                FOR ALL
                USING (tenant_id::text = current_setting('app.tenant_id', true))
        ");

        // ──────────────────────────────────────────────────────────────
        // 3. invoice_lines (policy: tenant_isolation_invoice_lines)
        // ──────────────────────────────────────────────────────────────
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoice_lines ON invoice_lines');
        $this->addSql("
            CREATE POLICY tenant_isolation_invoice_lines ON invoice_lines
                FOR ALL
                USING (tenant_id::text = current_setting('app.tenant_id', true))
        ");

        // ──────────────────────────────────────────────────────────────
        // 4. invoice_sequences (policy: tenant_isolation_invoice_sequences)
        // ──────────────────────────────────────────────────────────────
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoice_sequences ON invoice_sequences');
        $this->addSql("
            CREATE POLICY tenant_isolation_invoice_sequences ON invoice_sequences
                FOR ALL
                USING (tenant_id::text = current_setting('app.tenant_id', true))
        ");

        // ──────────────────────────────────────────────────────────────
        // 5. payment_webhook_events (policy: tenant_isolation)
        //    NOTE: This table previously had both USING and WITH CHECK.
        //    The new policy uses only USING (PostgreSQL uses USING for
        //    WITH CHECK when not specified), consistent with all other tables.
        // ──────────────────────────────────────────────────────────────
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON payment_webhook_events');
        $this->addSql("
            CREATE POLICY tenant_isolation ON payment_webhook_events
                FOR ALL
                USING (tenant_id::text = current_setting('app.tenant_id', true))
        ");
    }

    public function down(Schema $schema): void
    {
        // Restore the original COALESCE policies (for rollback safety)

        // 1. payments
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_payments ON payments');
        $this->addSql("
            CREATE POLICY tenant_isolation_payments ON payments
                FOR ALL
                USING (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
        ");

        // 2. invoices
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoices ON invoices');
        $this->addSql("
            CREATE POLICY tenant_isolation_invoices ON invoices
                FOR ALL
                USING (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
        ");

        // 3. invoice_lines
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoice_lines ON invoice_lines');
        $this->addSql("
            CREATE POLICY tenant_isolation_invoice_lines ON invoice_lines
                FOR ALL
                USING (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
        ");

        // 4. invoice_sequences
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_invoice_sequences ON invoice_sequences');
        $this->addSql("
            CREATE POLICY tenant_isolation_invoice_sequences ON invoice_sequences
                FOR ALL
                USING (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
        ");

        // 5. payment_webhook_events (restore WITH CHECK too)
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON payment_webhook_events');
        $this->addSql("
            CREATE POLICY tenant_isolation ON payment_webhook_events
                FOR ALL
                USING (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
                WITH CHECK (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
        ");
    }
}
