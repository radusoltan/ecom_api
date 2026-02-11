<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Payment schema migration
 *
 * Creates three tables for payment processing:
 * - payments: Main payment records with gateway integration
 * - payment_transactions: Audit trail of all payment operations
 * - refunds: Refund requests and processing
 *
 * Business Rules:
 * - All amounts stored in minor units (cents) to avoid floating point issues
 * - Idempotency keys prevent duplicate payment processing
 * - Multi-tenant isolation via RLS policies
 * - Status transitions tracked via timestamps
 * - Gateway responses stored as JSONB for flexibility
 */
final class Version20251128200000_CreatePaymentTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payments, payment_transactions, and refunds tables with RLS';
    }

    public function up(Schema $schema): void
    {
        // ============================================
        // 💳 PAYMENTS TABLE
        // ============================================
        // Main payment records linked to orders
        // Supports: authorization, capture, cancellation flows
        // Payment methods: card, paypal, bank_transfer, etc.
        $this->addSql('
            CREATE TABLE payments (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                order_id UUID NOT NULL,
                customer_id UUID,
                gateway_reference VARCHAR(255),
                method VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT \'pending\',
                amount_minor INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                captured_amount_minor INT DEFAULT 0,
                refunded_amount_minor INT DEFAULT 0,
                metadata JSONB DEFAULT \'{}\',
                failure_code VARCHAR(100),
                failure_message TEXT,
                idempotency_key VARCHAR(255),
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
                authorized_at TIMESTAMP,
                captured_at TIMESTAMP,
                cancelled_at TIMESTAMP,
                CONSTRAINT fk_payment_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
                CONSTRAINT chk_payment_status CHECK (status IN (
                    \'pending\', \'authorized\', \'captured\', \'cancelled\',
                    \'failed\', \'refunded\', \'partially_refunded\'
                )),
                CONSTRAINT chk_payment_method CHECK (method IN (
                    \'card\', \'paypal\', \'bank_transfer\', \'cash_on_delivery\',
                    \'crypto\', \'apple_pay\', \'google_pay\', \'klarna\'
                )),
                CONSTRAINT chk_payment_amounts CHECK (
                    amount_minor > 0
                    AND captured_amount_minor >= 0
                    AND refunded_amount_minor >= 0
                    AND refunded_amount_minor <= captured_amount_minor
                ),
                CONSTRAINT chk_payment_currency CHECK (currency ~ \'^[A-Z]{3}$\')
            )
        ');

        // Indexes for payments - tenant_id MUST be first for RLS efficiency
        $this->addSql('CREATE INDEX idx_payments_tenant_id ON payments(tenant_id)');
        $this->addSql('CREATE INDEX idx_payments_tenant_order ON payments(tenant_id, order_id)');
        $this->addSql('CREATE INDEX idx_payments_tenant_customer ON payments(tenant_id, customer_id)');
        $this->addSql('CREATE INDEX idx_payments_tenant_status ON payments(tenant_id, status)');
        $this->addSql('CREATE INDEX idx_payments_gateway_reference ON payments(gateway_reference) WHERE gateway_reference IS NOT NULL');
        $this->addSql('CREATE INDEX idx_payments_created_at ON payments(created_at DESC)');

        // Unique idempotency constraint per tenant
        $this->addSql('CREATE UNIQUE INDEX idx_payments_idempotency ON payments(tenant_id, idempotency_key) WHERE idempotency_key IS NOT NULL');

        // ============================================
        // 📋 PAYMENT TRANSACTIONS TABLE
        // ============================================
        // Audit trail for all payment operations
        // Types: authorize, capture, cancel, refund
        $this->addSql('
            CREATE TABLE payment_transactions (
                id UUID PRIMARY KEY,
                payment_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                type VARCHAR(50) NOT NULL,
                amount_minor INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                gateway_reference VARCHAR(255),
                status VARCHAR(50) NOT NULL,
                raw_response JSONB,
                error_code VARCHAR(100),
                error_message TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                CONSTRAINT fk_transaction_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
                CONSTRAINT fk_transaction_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT chk_transaction_type CHECK (type IN (
                    \'authorize\', \'capture\', \'cancel\', \'refund\', \'void\'
                )),
                CONSTRAINT chk_transaction_status CHECK (status IN (
                    \'pending\', \'success\', \'failed\'
                )),
                CONSTRAINT chk_transaction_amount CHECK (amount_minor > 0),
                CONSTRAINT chk_transaction_currency CHECK (currency ~ \'^[A-Z]{3}$\')
            )
        ');

        // Indexes for transactions - tenant_id first for RLS
        $this->addSql('CREATE INDEX idx_transactions_tenant_id ON payment_transactions(tenant_id)');
        $this->addSql('CREATE INDEX idx_transactions_tenant_payment ON payment_transactions(tenant_id, payment_id)');
        $this->addSql('CREATE INDEX idx_transactions_type ON payment_transactions(type)');
        $this->addSql('CREATE INDEX idx_transactions_status ON payment_transactions(status)');
        $this->addSql('CREATE INDEX idx_transactions_created_at ON payment_transactions(created_at DESC)');

        // ============================================
        // 💸 REFUNDS TABLE
        // ============================================
        // Refund requests and processing
        // Reasons: customer_request, fraud, defective_product, duplicate, etc.
        $this->addSql('
            CREATE TABLE refunds (
                id UUID PRIMARY KEY,
                tenant_id UUID NOT NULL,
                payment_id UUID NOT NULL,
                order_id UUID NOT NULL,
                gateway_reference VARCHAR(255),
                status VARCHAR(50) NOT NULL DEFAULT \'pending\',
                amount_minor INT NOT NULL,
                currency VARCHAR(3) NOT NULL,
                reason VARCHAR(50) NOT NULL,
                reason_note TEXT,
                requested_by_id UUID,
                approved_by_id UUID,
                processed_at TIMESTAMP,
                failure_code VARCHAR(100),
                failure_message TEXT,
                metadata JSONB DEFAULT \'{}\',
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
                CONSTRAINT fk_refund_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_refund_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT,
                CONSTRAINT fk_refund_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
                CONSTRAINT chk_refund_status CHECK (status IN (
                    \'pending\', \'approved\', \'rejected\', \'processing\',
                    \'completed\', \'failed\'
                )),
                CONSTRAINT chk_refund_reason CHECK (reason IN (
                    \'customer_request\', \'fraud\', \'duplicate\', \'defective_product\',
                    \'not_as_described\', \'wrong_item\', \'damaged\', \'cancelled_order\',
                    \'other\'
                )),
                CONSTRAINT chk_refund_amount CHECK (amount_minor > 0),
                CONSTRAINT chk_refund_currency CHECK (currency ~ \'^[A-Z]{3}$\')
            )
        ');

        // Indexes for refunds - tenant_id first for RLS
        $this->addSql('CREATE INDEX idx_refunds_tenant_id ON refunds(tenant_id)');
        $this->addSql('CREATE INDEX idx_refunds_tenant_payment ON refunds(tenant_id, payment_id)');
        $this->addSql('CREATE INDEX idx_refunds_tenant_order ON refunds(tenant_id, order_id)');
        $this->addSql('CREATE INDEX idx_refunds_tenant_status ON refunds(tenant_id, status)');
        $this->addSql('CREATE INDEX idx_refunds_reason ON refunds(reason)');
        $this->addSql('CREATE INDEX idx_refunds_created_at ON refunds(created_at DESC)');

        // ============================================
        // 🔒 ROW-LEVEL SECURITY (RLS)
        // ============================================
        // Enable RLS on all tables
        $this->addSql('ALTER TABLE payments ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE payment_transactions ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE refunds ENABLE ROW LEVEL SECURITY');

        // Create tenant isolation policies
        // Uses current_setting('app.tenant_id') set by application
        // Fallback to tenant_id column if setting not defined (allows seeding)
        $this->addSql('
            CREATE POLICY tenant_isolation_payments ON payments
            FOR ALL
            USING (tenant_id = COALESCE(NULLIF(current_setting(\'app.tenant_id\', true), \'\')::UUID, tenant_id))
        ');

        $this->addSql('
            CREATE POLICY tenant_isolation_transactions ON payment_transactions
            FOR ALL
            USING (tenant_id = COALESCE(NULLIF(current_setting(\'app.tenant_id\', true), \'\')::UUID, tenant_id))
        ');

        $this->addSql('
            CREATE POLICY tenant_isolation_refunds ON refunds
            FOR ALL
            USING (tenant_id = COALESCE(NULLIF(current_setting(\'app.tenant_id\', true), \'\')::UUID, tenant_id))
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop RLS policies first
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_refunds ON refunds');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_transactions ON payment_transactions');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_payments ON payments');

        // Drop tables in reverse order (foreign key dependencies)
        $this->addSql('DROP TABLE IF EXISTS refunds');
        $this->addSql('DROP TABLE IF EXISTS payment_transactions');
        $this->addSql('DROP TABLE IF EXISTS payments');
    }
}
