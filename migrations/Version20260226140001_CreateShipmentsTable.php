<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226140001_CreateShipmentsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shipments table with RLS policy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE shipments (
                id VARCHAR(36) NOT NULL,
                tenant_id VARCHAR(36) NOT NULL,
                order_id VARCHAR(36) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                carrier_code VARCHAR(10) DEFAULT NULL,
                tracking_number VARCHAR(100) DEFAULT NULL,
                recipient_name VARCHAR(255) NOT NULL,
                recipient_address JSONB NOT NULL,
                rate_amount INT DEFAULT NULL,
                rate_currency VARCHAR(3) DEFAULT NULL,
                rate_estimated_days INT DEFAULT NULL,
                rate_service_name VARCHAR(100) DEFAULT NULL,
                shipped_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                delivered_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )"
        );

        $this->addSql('CREATE INDEX idx_shipments_tenant_id ON shipments (tenant_id)');
        $this->addSql('CREATE INDEX idx_shipments_order_id ON shipments (tenant_id, order_id)');
        $this->addSql('CREATE INDEX idx_shipments_status ON shipments (tenant_id, status)');
        $this->addSql('CREATE INDEX idx_shipments_tracking ON shipments (tracking_number) WHERE tracking_number IS NOT NULL');

        $this->addSql('ALTER TABLE shipments ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE shipments FORCE ROW LEVEL SECURITY');

        $this->addSql(
            "CREATE POLICY shipments_tenant_isolation ON shipments
                FOR ALL
                USING (tenant_id::text = current_setting('app.tenant_id', true))
                WITH CHECK (tenant_id::text = current_setting('app.tenant_id', true))"
        );

        $this->addSql('CREATE POLICY shipments_admin_bypass ON shipments TO ecom_admin USING (true) WITH CHECK (true)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP POLICY IF EXISTS shipments_admin_bypass ON shipments');
        $this->addSql('DROP POLICY IF EXISTS shipments_tenant_isolation ON shipments');
        $this->addSql('DROP TABLE IF EXISTS shipments');
    }
}
