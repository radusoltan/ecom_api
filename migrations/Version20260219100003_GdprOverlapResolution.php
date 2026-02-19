<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * GDPR Overlap Resolution Migration.
 *
 * Adds privacy_request_id column to deletion_requests and data_export_requests
 * tables to link Customer GDPR actions to their originating Privacy DSR.
 */
final class Version20260219100003_GdprOverlapResolution extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add privacy_request_id columns to deletion_requests and data_export_requests for Privacy-Customer DSR linking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deletion_requests ADD COLUMN privacy_request_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE data_export_requests ADD COLUMN privacy_request_id VARCHAR(36) DEFAULT NULL');

        $this->addSql('CREATE INDEX idx_deletion_requests_privacy_req ON deletion_requests (privacy_request_id)');
        $this->addSql('CREATE INDEX idx_data_export_requests_privacy_req ON data_export_requests (privacy_request_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_data_export_requests_privacy_req');
        $this->addSql('DROP INDEX IF EXISTS idx_deletion_requests_privacy_req');

        $this->addSql('ALTER TABLE data_export_requests DROP COLUMN IF EXISTS privacy_request_id');
        $this->addSql('ALTER TABLE deletion_requests DROP COLUMN IF EXISTS privacy_request_id');
    }
}
