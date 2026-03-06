<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create missing tables (flash_sales, transactions, audit_log, messenger_messages),
 * drop orphaned empty tables, and add RLS policies for tenant-scoped tables.
 */
final class Version20260305090000_SyncSchema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create flash_sales, transactions, audit_log, messenger_messages; drop orphaned tables; add RLS';
    }

    public function up(Schema $schema): void
    {
        // ========================================
        // DROP truly orphaned tables FIRST (verified empty + no code references)
        // Must happen before CREATE to avoid index name conflicts
        // (payment_transactions has idx_transactions_* indexes)
        // NOTE: invoice_sequences, shipments, i18n_backfill_tracking are kept — still referenced in code
        // ========================================
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT IF EXISTS fk_transaction_tenant');
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT IF EXISTS fk_transaction_payment');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS fk_refund_order');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS fk_refund_payment');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT IF EXISTS fk_refund_tenant');
        $this->addSql('ALTER TABLE vat_validations DROP CONSTRAINT IF EXISTS fk_vat_validations_tenant');
        $this->addSql('DROP TABLE payment_transactions');
        $this->addSql('DROP TABLE refunds');
        $this->addSql('DROP TABLE vat_validations');

        // ========================================
        // CREATE new tables
        // ========================================

        // flash_sales (tenant-scoped)
        $this->addSql('CREATE TABLE flash_sales (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, product_ids JSON NOT NULL, discount_type VARCHAR(20) NOT NULL, discount_value DOUBLE PRECISION NOT NULL, start_time TIMESTAMP(0) WITH TIME ZONE NOT NULL, end_time TIMESTAMP(0) WITH TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_flash_sales_tenant_id ON flash_sales (tenant_id)');
        $this->addSql('CREATE INDEX idx_flash_sales_status ON flash_sales (status)');
        $this->addSql('CREATE INDEX idx_flash_sales_start_time ON flash_sales (start_time)');
        $this->addSql('CREATE INDEX idx_flash_sales_end_time ON flash_sales (end_time)');
        $this->addSql('CREATE INDEX idx_flash_sales_tenant_status ON flash_sales (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_flash_sales_created_at ON flash_sales (created_at)');

        // transactions (payment child — no tenant_id directly, linked via payment)
        $this->addSql('CREATE TABLE transactions (id VARCHAR(36) NOT NULL, payment_id VARCHAR(36) NOT NULL, type VARCHAR(20) NOT NULL, amount_in_cents INT NOT NULL, currency VARCHAR(3) NOT NULL, gateway_transaction_id VARCHAR(255) NOT NULL, gateway_response TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, error_code VARCHAR(100) DEFAULT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_transactions_payment_id ON transactions (payment_id)');
        $this->addSql('CREATE INDEX idx_transactions_type ON transactions (type)');
        $this->addSql('CREATE INDEX idx_transactions_status ON transactions (status)');
        $this->addSql('CREATE INDEX idx_transactions_gateway ON transactions (gateway_transaction_id)');
        $this->addSql('CREATE INDEX idx_transactions_created_at ON transactions (created_at)');

        // audit_log (tenant-scoped)
        $this->addSql('CREATE TABLE audit_log (id VARCHAR(36) NOT NULL, tenant_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) DEFAULT NULL, action_type VARCHAR(50) NOT NULL, resource_type VARCHAR(50) NOT NULL, resource_id VARCHAR(255) NOT NULL, metadata JSON NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent TEXT DEFAULT NULL, occurred_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_log_tenant_id ON audit_log (tenant_id)');
        $this->addSql('CREATE INDEX idx_audit_log_user_id ON audit_log (user_id)');
        $this->addSql('CREATE INDEX idx_audit_log_action_type ON audit_log (action_type)');
        $this->addSql('CREATE INDEX idx_audit_log_resource_type ON audit_log (resource_type)');
        $this->addSql('CREATE INDEX idx_audit_log_resource_id ON audit_log (resource_id)');
        $this->addSql('CREATE INDEX idx_audit_log_occurred_at ON audit_log (occurred_at)');

        // messenger_messages (Symfony Messenger transport table — no tenant)
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');

        // ========================================
        // RLS policies for tenant-scoped tables
        // ========================================
        $this->addSql('ALTER TABLE flash_sales ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE flash_sales FORCE ROW LEVEL SECURITY');
        $this->addSql("CREATE POLICY flash_sales_tenant_isolation ON flash_sales USING (tenant_id::text = current_setting('app.tenant_id', true))");

        $this->addSql('ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE audit_log FORCE ROW LEVEL SECURITY');
        $this->addSql("CREATE POLICY audit_log_tenant_isolation ON audit_log USING (tenant_id::text = current_setting('app.tenant_id', true))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS flash_sales');
        $this->addSql('DROP TABLE IF EXISTS transactions');
        $this->addSql('DROP TABLE IF EXISTS audit_log');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        // Legacy tables are not recreated in down() — they were orphaned
    }
}
