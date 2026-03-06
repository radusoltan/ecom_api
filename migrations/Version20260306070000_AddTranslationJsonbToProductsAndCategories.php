<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add JSONB translation columns to catalog_products and catalog_categories.
 * These store inline translations as {locale: text} maps, complementing the
 * existing Gedmo/ext_translations approach.
 */
final class Version20260306070000_AddTranslationJsonbToProductsAndCategories extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name_translations, description_translations JSONB columns to catalog_products and catalog_categories';
    }

    public function up(Schema $schema): void
    {
        // catalog_products
        $this->addSql('ALTER TABLE catalog_products ADD COLUMN IF NOT EXISTS name_translations JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD COLUMN IF NOT EXISTS description_translations JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD COLUMN IF NOT EXISTS short_description_translations JSONB DEFAULT NULL');

        // catalog_categories
        $this->addSql('ALTER TABLE catalog_categories ADD COLUMN IF NOT EXISTS name_translations JSONB DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_categories ADD COLUMN IF NOT EXISTS description_translations JSONB DEFAULT NULL');

        // GIN indexes for efficient JSONB queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_products_name_tr_gin ON catalog_products USING GIN (name_translations)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_products_desc_tr_gin ON catalog_products USING GIN (description_translations)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_categories_name_tr_gin ON catalog_categories USING GIN (name_translations)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_categories_desc_tr_gin ON catalog_categories USING GIN (description_translations)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_products_name_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_products_desc_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_categories_name_tr_gin');
        $this->addSql('DROP INDEX IF EXISTS idx_categories_desc_tr_gin');

        $this->addSql('ALTER TABLE catalog_products DROP COLUMN IF EXISTS name_translations');
        $this->addSql('ALTER TABLE catalog_products DROP COLUMN IF EXISTS description_translations');
        $this->addSql('ALTER TABLE catalog_products DROP COLUMN IF EXISTS short_description_translations');
        $this->addSql('ALTER TABLE catalog_categories DROP COLUMN IF EXISTS name_translations');
        $this->addSql('ALTER TABLE catalog_categories DROP COLUMN IF EXISTS description_translations');
    }
}
