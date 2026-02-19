<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create payment_webhook_events table for webhook deduplication.
 *
 * Prevents duplicate processing of webhook events from payment gateways.
 * Each (gateway, external_event_id) pair is unique - if a webhook is received
 * twice (e.g. Stripe retry on timeout), the second is acknowledged without processing.
 */
final class Version20260219100002_CreatePaymentWebhookEventsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payment_webhook_events table with RLS for webhook deduplication';
    }

    public function up(Schema $schema): void
    {
        // Create the table
        $this->addSql("
            CREATE TABLE IF NOT EXISTS payment_webhook_events (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                gateway VARCHAR(50) NOT NULL,
                external_event_id VARCHAR(255) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                processed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                payload_hash VARCHAR(64) DEFAULT NULL,
                CONSTRAINT pk_payment_webhook_events PRIMARY KEY (id),
                CONSTRAINT fk_payment_webhook_events_tenant FOREIGN KEY (tenant_id)
                    REFERENCES tenants(id) ON DELETE CASCADE
            )
        ");

        // Unique index: (gateway, external_event_id) - core deduplication constraint
        $this->addSql("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_payment_webhook_events_dedup
            ON payment_webhook_events (gateway, external_event_id)
        ");

        // Tenant isolation index (CRITICAL for RLS performance)
        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_payment_webhook_events_tenant_id
            ON payment_webhook_events (tenant_id)
        ");

        // Cleanup index: processed_at for efficient deletion of old entries
        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_payment_webhook_events_processed_at
            ON payment_webhook_events (processed_at)
        ");

        // Enable Row Level Security
        $this->addSql('ALTER TABLE payment_webhook_events ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE payment_webhook_events FORCE ROW LEVEL SECURITY');

        // RLS policy using COALESCE pattern (same as payments table).
        // Webhooks arrive from external gateways WITHOUT tenant context (no X-Tenant-ID header).
        // COALESCE allows queries to succeed without tenant context while still enforcing
        // tenant isolation when context IS set.
        $this->addSql("
            CREATE POLICY tenant_isolation ON payment_webhook_events
                FOR ALL
                USING (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
                WITH CHECK (tenant_id = COALESCE(NULLIF(current_setting('app.tenant_id', true), '')::UUID, tenant_id))
        ");

        // Grant access to the application user
        $this->addSql('GRANT SELECT, INSERT, DELETE ON payment_webhook_events TO ecommerce');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation ON payment_webhook_events');
        $this->addSql('DROP TABLE IF EXISTS payment_webhook_events');
    }
}
