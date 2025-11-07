<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add translation quota and usage tracking to tenants table.
 *
 * Business Rules:
 * - translation_quota: Maximum number of translations per month (default: 10,000)
 * - translation_usage: Current usage count (default: 0)
 */
final class Version20251107082832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add translation quota and usage columns to tenants table';
    }

    public function up(Schema $schema): void
    {
        // Add translation quota tracking columns
        $this->addSql('ALTER TABLE tenants ADD translation_quota INT DEFAULT 10000 NOT NULL');
        $this->addSql('ALTER TABLE tenants ADD translation_usage INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tenants ALTER default_locale DROP DEFAULT');
        $this->addSql('ALTER TABLE tenants ALTER enabled_locales DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // Remove translation quota tracking columns
        $this->addSql('ALTER TABLE tenants DROP translation_quota');
        $this->addSql('ALTER TABLE tenants DROP translation_usage');
        $this->addSql('ALTER TABLE tenants ALTER default_locale SET DEFAULT \'en\'');
        $this->addSql('ALTER TABLE tenants ALTER enabled_locales SET DEFAULT \'["en"]\'');
    }
}
