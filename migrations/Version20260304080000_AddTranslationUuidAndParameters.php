<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add translation_uuid (UUID) and parameters (JSONB) columns to ext_translations.
 *
 * Supports Translation Aggregate Root refactoring per PRD §4.7.
 */
final class Version20260304080000_AddTranslationUuidAndParameters extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add translation_uuid and parameters columns to ext_translations for Aggregate Root support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ext_translations ADD COLUMN IF NOT EXISTS translation_uuid UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE ext_translations ADD COLUMN IF NOT EXISTS parameters JSONB DEFAULT \'{}\'');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_ext_translations_uuid ON ext_translations (translation_uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_ext_translations_uuid');
        $this->addSql('ALTER TABLE ext_translations DROP COLUMN IF EXISTS parameters');
        $this->addSql('ALTER TABLE ext_translations DROP COLUMN IF EXISTS translation_uuid');
    }
}
