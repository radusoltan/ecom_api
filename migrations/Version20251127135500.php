<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Create notifications table with RLS policy for multi-tenancy.
 */
final class Version20251127135500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notifications table with RLS policy for multi-tenancy';
    }

    public function up(Schema $schema): void
    {
        // Create notifications table
        $this->addSql('CREATE TABLE notifications (
            id VARCHAR(26) NOT NULL,
            tenant_id UUID NOT NULL,
            type VARCHAR(20) NOT NULL,
            recipient_email VARCHAR(255) DEFAULT NULL,
            recipient_phone VARCHAR(20) DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(20) NOT NULL,
            attempt_count INT NOT NULL DEFAULT 0,
            sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            failed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            failure_reason TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        // Create indexes for performance
        $this->addSql('CREATE INDEX idx_notifications_tenant_id ON notifications (tenant_id)');
        $this->addSql('CREATE INDEX idx_notifications_status ON notifications (status)');
        $this->addSql('CREATE INDEX idx_notifications_type ON notifications (type)');
        $this->addSql('CREATE INDEX idx_notifications_recipient_email ON notifications (recipient_email)');
        $this->addSql('CREATE INDEX idx_notifications_tenant_status ON notifications (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_notifications_created_at ON notifications (created_at)');

        // Add column comments for datetime_immutable
        $this->addSql('COMMENT ON COLUMN notifications.sent_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifications.failed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifications.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN notifications.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Enable Row-Level Security (RLS) for multi-tenancy
        $this->addSql('ALTER TABLE notifications ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE notifications FORCE ROW LEVEL SECURITY');

        // Create RLS policy for tenant isolation
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_policy ON notifications');
        $this->addSql('CREATE POLICY tenant_isolation_policy ON notifications
            USING (tenant_id::text = current_setting(\'app.tenant_id\', true))
            WITH CHECK (tenant_id::text = current_setting(\'app.tenant_id\', true))
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop RLS policy before dropping table
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_policy ON notifications');

        // Drop table
        $this->addSql('DROP TABLE notifications');
    }
}
