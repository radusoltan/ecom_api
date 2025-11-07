<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251107080742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add default_locale and enabled_locales columns to tenants table, and add suspended status support';
    }

    public function up(Schema $schema): void
    {
        // Add locale management columns with default values
        $this->addSql('ALTER TABLE tenants ADD default_locale VARCHAR(2) NOT NULL DEFAULT \'en\'');
        $this->addSql('ALTER TABLE tenants ADD enabled_locales JSON NOT NULL DEFAULT \'["en"]\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE tenants DROP default_locale');
        $this->addSql('ALTER TABLE tenants DROP enabled_locales');
    }
}
