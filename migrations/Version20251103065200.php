<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for audit_log table
 * Implements comprehensive audit trail for all user actions in the system
 */
final class Version20251103065200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create audit_log table with indexes for efficient querying';
    }

    public function up(Schema $schema): void
    {
        // Create audit_log table
        $this->addSql('CREATE TABLE audit_log (
            id VARCHAR(36) NOT NULL,
            tenant_id VARCHAR(36) NOT NULL,
            user_id VARCHAR(36) DEFAULT NULL,
            action_type VARCHAR(50) NOT NULL,
            resource_type VARCHAR(50) NOT NULL,
            resource_id VARCHAR(255) NOT NULL,
            metadata JSONB DEFAULT \'{}\'::jsonb NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        // Create indexes for efficient querying
        $this->addSql('CREATE INDEX idx_audit_log_tenant_id ON audit_log (tenant_id)');
        $this->addSql('CREATE INDEX idx_audit_log_user_id ON audit_log (user_id)');
        $this->addSql('CREATE INDEX idx_audit_log_action_type ON audit_log (action_type)');
        $this->addSql('CREATE INDEX idx_audit_log_resource_type ON audit_log (resource_type)');
        $this->addSql('CREATE INDEX idx_audit_log_resource_id ON audit_log (resource_id)');
        $this->addSql('CREATE INDEX idx_audit_log_occurred_at ON audit_log (occurred_at)');

        // Add comment to the table
        $this->addSql('COMMENT ON TABLE audit_log IS \'Comprehensive audit trail for all user actions in the system\'');
    }

    public function down(Schema $schema): void
    {
        // Drop indexes first
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_tenant_id');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_user_id');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_action_type');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_resource_type');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_resource_id');
        $this->addSql('DROP INDEX IF EXISTS idx_audit_log_occurred_at');

        // Drop table
        $this->addSql('DROP TABLE IF EXISTS audit_log');
    }
}
