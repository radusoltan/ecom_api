<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * GDPR Account Deletion Requests Migration.
 *
 * Creates deletion_requests table with RLS for multi-tenancy.
 */
final class Version20251228100002_DeletionRequests extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create deletion_requests table for GDPR-compliant account deletion (Right to be Forgotten)';
    }

    public function up(Schema $schema): void
    {
        // Create deletion_requests table
        $this->addSql('
            CREATE TABLE deletion_requests (
                id UUID NOT NULL,
                customer_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                status VARCHAR(20) NOT NULL,
                reason TEXT DEFAULT NULL,
                hold_reason TEXT DEFAULT NULL,
                scheduled_for TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        ');

        $this->addSql('COMMENT ON COLUMN deletion_requests.scheduled_for IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN deletion_requests.confirmed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN deletion_requests.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN deletion_requests.created_at IS \'(DC2Type:datetime_immutable)\'');

        // Create indexes
        $this->addSql('CREATE INDEX idx_deletion_requests_tenant_id ON deletion_requests (tenant_id)');
        $this->addSql('CREATE INDEX idx_deletion_requests_customer_id ON deletion_requests (customer_id)');
        $this->addSql('CREATE INDEX idx_deletion_requests_status ON deletion_requests (status)');
        $this->addSql('CREATE INDEX idx_deletion_requests_scheduled_for ON deletion_requests (scheduled_for)');

        // Enable Row-Level Security
        $this->addSql('ALTER TABLE deletion_requests ENABLE ROW LEVEL SECURITY');

        // Create RLS policy for tenant isolation
        $this->addSql('
            CREATE POLICY deletion_requests_tenant_isolation ON deletion_requests
            USING (tenant_id::text = current_setting(\'app.tenant_id\', true))
        ');

        // Grant permissions
        $this->addSql('GRANT SELECT, INSERT, UPDATE, DELETE ON deletion_requests TO ecom_admin');
    }

    public function down(Schema $schema): void
    {
        // Drop RLS policy
        $this->addSql('DROP POLICY IF EXISTS deletion_requests_tenant_isolation ON deletion_requests');

        // Drop table
        $this->addSql('DROP TABLE IF EXISTS deletion_requests');
    }
}
