<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226140002_CreateFeatureFlagsTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_feature_flags table with RLS policy';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "CREATE TABLE tenant_feature_flags (
                id VARCHAR(36) NOT NULL,
                tenant_id VARCHAR(36) NOT NULL,
                feature_name VARCHAR(100) NOT NULL,
                enabled BOOLEAN NOT NULL DEFAULT false,
                configuration JSONB NOT NULL,
                description TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )"
        );

        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_feature ON tenant_feature_flags (tenant_id, feature_name)');
        $this->addSql('CREATE INDEX idx_feature_flags_tenant ON tenant_feature_flags (tenant_id)');

        $this->addSql('ALTER TABLE tenant_feature_flags ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE tenant_feature_flags FORCE ROW LEVEL SECURITY');

        $this->addSql(
            "CREATE POLICY feature_flags_tenant_isolation ON tenant_feature_flags
                FOR ALL
                USING (tenant_id::text = current_setting('app.tenant_id', true))
                WITH CHECK (tenant_id::text = current_setting('app.tenant_id', true))"
        );

        $this->addSql('CREATE POLICY feature_flags_admin_bypass ON tenant_feature_flags TO ecom_admin USING (true) WITH CHECK (true)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP POLICY IF EXISTS feature_flags_admin_bypass ON tenant_feature_flags');
        $this->addSql('DROP POLICY IF EXISTS feature_flags_tenant_isolation ON tenant_feature_flags');
        $this->addSql('DROP TABLE IF EXISTS tenant_feature_flags');
    }
}
