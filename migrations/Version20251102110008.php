<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251102110008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add GDPR Privacy tables: consents and data_subject_requests with indexes for multi-tenant isolation';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE consents (id VARCHAR(26) NOT NULL, tenant_id UUID NOT NULL, customer_id UUID NOT NULL, purpose VARCHAR(50) NOT NULL, is_granted BOOLEAN NOT NULL, ip_address VARCHAR(45) NOT NULL, user_agent VARCHAR(500) NOT NULL, consent_text TEXT NOT NULL, consent_version VARCHAR(20) NOT NULL, granted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, withdrawn_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_consent_customer ON consents (customer_id)');
        $this->addSql('CREATE INDEX idx_consent_tenant ON consents (tenant_id)');
        $this->addSql('CREATE INDEX idx_consent_customer_purpose_granted ON consents (customer_id, purpose, is_granted)');
        $this->addSql('COMMENT ON COLUMN consents.id IS \'(DC2Type:consent_id)\'');
        $this->addSql('COMMENT ON COLUMN consents.tenant_id IS \'(DC2Type:tenant_id)\'');
        $this->addSql('COMMENT ON COLUMN consents.customer_id IS \'(DC2Type:customer_id)\'');
        $this->addSql('COMMENT ON COLUMN consents.purpose IS \'(DC2Type:consent_purpose)\'');
        $this->addSql('COMMENT ON COLUMN consents.granted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN consents.withdrawn_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN consents.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN consents.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE data_subject_requests (id VARCHAR(26) NOT NULL, tenant_id UUID NOT NULL, customer_id UUID NOT NULL, request_type VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, reason TEXT DEFAULT NULL, review_notes TEXT DEFAULT NULL, rejection_reason TEXT DEFAULT NULL, export_data JSON DEFAULT NULL, submitted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deadline TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_extended BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_dsr_customer ON data_subject_requests (customer_id)');
        $this->addSql('CREATE INDEX idx_dsr_tenant ON data_subject_requests (tenant_id)');
        $this->addSql('CREATE INDEX idx_dsr_status ON data_subject_requests (status)');
        $this->addSql('CREATE INDEX idx_dsr_type ON data_subject_requests (request_type)');
        $this->addSql('CREATE INDEX idx_dsr_deadline_status ON data_subject_requests (deadline, status)');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.id IS \'(DC2Type:data_subject_request_id)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.tenant_id IS \'(DC2Type:tenant_id)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.customer_id IS \'(DC2Type:customer_id)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.request_type IS \'(DC2Type:request_type)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.status IS \'(DC2Type:request_status)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.submitted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.deadline IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN data_subject_requests.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE consents');
        $this->addSql('DROP TABLE data_subject_requests');
    }
}
