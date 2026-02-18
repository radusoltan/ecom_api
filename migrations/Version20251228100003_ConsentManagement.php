<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * GDPR Consent Management Migration.
 *
 * Adds consent columns to customers table and creates consent_history audit trail table.
 */
final class Version20251228100003_ConsentManagement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add GDPR consent management: consent columns in customers table and consent_history audit table with RLS';
    }

    public function up(Schema $schema): void
    {
        // Add consent columns to customers table
        $this->addSql('ALTER TABLE customers ADD consent_marketing_email BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE customers ADD consent_marketing_sms BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE customers ADD consent_third_party BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('ALTER TABLE customers ADD consent_analytics BOOLEAN DEFAULT FALSE NOT NULL');

        // Create consent_history table for audit trail
        $this->addSql('
            CREATE TABLE consent_history (
                id UUID NOT NULL PRIMARY KEY,
                customer_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                consent_type VARCHAR(50) NOT NULL,
                granted BOOLEAN NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_consent_history_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
            )
        ');

        // Add comment for immutability
        $this->addSql("COMMENT ON TABLE consent_history IS 'Immutable audit trail of GDPR consent changes. Records cannot be updated or deleted.'");

        // Create indexes for performance
        $this->addSql('CREATE INDEX idx_consent_history_tenant ON consent_history (tenant_id)');
        $this->addSql('CREATE INDEX idx_consent_history_customer ON consent_history (customer_id)');
        $this->addSql('CREATE INDEX idx_consent_history_consent_type ON consent_history (consent_type)');
        $this->addSql('CREATE INDEX idx_consent_history_created_at ON consent_history (created_at)');

        // Enable Row-Level Security on consent_history
        $this->addSql('ALTER TABLE consent_history ENABLE ROW LEVEL SECURITY');

        // Create RLS policy: users can only access consent history for their tenant
        $this->addSql("
            CREATE POLICY consent_history_tenant_isolation_policy ON consent_history
            FOR ALL
            TO PUBLIC
            USING (tenant_id::text = current_setting('app.tenant_id', true))
        ");

        // Create RLS policy for super admin (bypass tenant isolation)
        $this->addSql("
            CREATE POLICY consent_history_superadmin_policy ON consent_history
            FOR ALL
            TO PUBLIC
            USING (current_setting('app.is_superadmin', TRUE)::boolean = TRUE)
        ");
    }

    public function down(Schema $schema): void
    {
        // Drop RLS policies
        $this->addSql('DROP POLICY IF EXISTS consent_history_superadmin_policy ON consent_history');
        $this->addSql('DROP POLICY IF EXISTS consent_history_tenant_isolation_policy ON consent_history');

        // Disable RLS
        $this->addSql('ALTER TABLE consent_history DISABLE ROW LEVEL SECURITY');

        // Drop consent_history table
        $this->addSql('DROP TABLE IF EXISTS consent_history');

        // Remove consent columns from customers table
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS consent_analytics');
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS consent_third_party');
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS consent_marketing_sms');
        $this->addSql('ALTER TABLE customers DROP COLUMN IF EXISTS consent_marketing_email');
    }
}
