<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create data_export_requests table for GDPR-compliant data exports.
 *
 * Business Requirements:
 * - Track customer data export requests (GDPR Article 15)
 * - Support rate limiting (1 request per 24 hours per customer)
 * - Secure download tokens for file access
 * - 24-hour expiration window for downloads
 * - Multi-tenant isolation with PostgreSQL RLS
 *
 * Table: data_export_requests
 * - id: UUID v7 (primary key)
 * - customer_id: UUID (foreign key to customers)
 * - tenant_id: UUID (for multi-tenancy)
 * - status: enum (pending, processing, ready, expired, failed)
 * - file_path: path to generated JSON file
 * - download_token: secure random token for downloads
 * - expires_at: when the download expires (24h after ready)
 * - created_at: request timestamp
 * - completed_at: when processing finished
 * - error_message: error details if failed
 */
final class Version20251228100001_DataExportRequests extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create data_export_requests table for GDPR-compliant customer data exports with RLS';
    }

    public function up(Schema $schema): void
    {
        // Create data_export_requests table
        $this->addSql("
            CREATE TABLE IF NOT EXISTS data_export_requests (
                id VARCHAR(36) PRIMARY KEY,
                customer_id VARCHAR(36) NOT NULL,
                tenant_id VARCHAR(36) NOT NULL,
                status VARCHAR(20) NOT NULL,
                file_path VARCHAR(500),
                download_token VARCHAR(100),
                expires_at TIMESTAMP WITH TIME ZONE,
                created_at TIMESTAMP WITH TIME ZONE NOT NULL,
                completed_at TIMESTAMP WITH TIME ZONE,
                error_message TEXT,

                CONSTRAINT fk_data_export_requests_customer
                    FOREIGN KEY (customer_id)
                    REFERENCES customers(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_data_export_requests_tenant
                    FOREIGN KEY (tenant_id)
                    REFERENCES tenants(id)
                    ON DELETE CASCADE,

                CONSTRAINT chk_data_export_status
                    CHECK (status IN ('pending', 'processing', 'ready', 'expired', 'failed'))
            )
        ");

        // Create indexes for performance
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_data_export_requests_tenant_id ON data_export_requests(tenant_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_data_export_requests_customer_id ON data_export_requests(customer_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_data_export_requests_status ON data_export_requests(status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_data_export_requests_created_at ON data_export_requests(created_at)');

        // Composite indexes for common queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_data_export_requests_tenant_customer ON data_export_requests(tenant_id, customer_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_data_export_requests_tenant_status ON data_export_requests(tenant_id, status)');

        // Add table comment
        $this->addSql("COMMENT ON TABLE data_export_requests IS 'GDPR-compliant customer data export requests with 24h download window'");

        // Enable Row-Level Security for multi-tenant isolation
        $this->addSql("
            DO $$
            BEGIN
                -- Enable RLS on table
                ALTER TABLE data_export_requests ENABLE ROW LEVEL SECURITY;

                -- Force RLS even for table owner (ecom_admin)
                ALTER TABLE data_export_requests FORCE ROW LEVEL SECURITY;

                -- Create policy for tenant isolation
                IF NOT EXISTS (SELECT 1 FROM pg_policies WHERE tablename = 'data_export_requests' AND policyname = 'tenant_isolation') THEN
                    CREATE POLICY tenant_isolation ON data_export_requests
                        FOR ALL
                        USING (tenant_id = current_setting('app.tenant_id', true)::uuid);
                END IF;

                -- Grant necessary permissions
                GRANT ALL ON data_export_requests TO ecom_admin;
            END $$;
        ");
    }

    public function down(Schema $schema): void
    {
        // Disable RLS before dropping table
        $this->addSql("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'data_export_requests') THEN
                    ALTER TABLE data_export_requests DISABLE ROW LEVEL SECURITY;
                END IF;
            END $$;
        ");

        // Drop table (indexes and constraints are dropped automatically)
        $this->addSql('DROP TABLE IF EXISTS data_export_requests CASCADE');
    }
}
