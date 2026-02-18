<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251031104108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE IF EXISTS catalog_sku_sequences');
        $this->addSql('DROP TABLE IF EXISTS i18n_backfill_tracking');

        // Conditional: Only alter catalog_configurable_products if it exists
        // (This table is created in Version20251117100000, but this migration runs before it)
        $this->addSql('DO $$ BEGIN
            IF EXISTS (SELECT FROM pg_tables WHERE schemaname = \'public\' AND tablename = \'catalog_configurable_products\') THEN
                ALTER TABLE catalog_configurable_products ALTER id TYPE UUID;
                ALTER TABLE catalog_configurable_products ALTER product_id TYPE UUID;
                ALTER TABLE catalog_configurable_products ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE;
                ALTER TABLE catalog_configurable_products ALTER created_at DROP DEFAULT;
                ALTER TABLE catalog_configurable_products ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE;
                ALTER TABLE catalog_configurable_products ALTER updated_at DROP DEFAULT;
                COMMENT ON COLUMN catalog_configurable_products.created_at IS \'(DC2Type:datetime_immutable)\';
                COMMENT ON COLUMN catalog_configurable_products.updated_at IS \'(DC2Type:datetime_immutable)\';
            END IF;
        END $$');

        // Conditional: Only add FK if tables exist AND constraint doesn't exist
        $this->addSql('DO $$ BEGIN
            IF EXISTS (SELECT FROM pg_tables WHERE schemaname = \'public\' AND tablename = \'catalog_product_options\')
               AND NOT EXISTS (SELECT FROM pg_constraint WHERE conname = \'fk_7fcee8982905368\') THEN
                ALTER TABLE catalog_product_options ADD CONSTRAINT FK_7FCEE8982905368 FOREIGN KEY (configurable_product_id) REFERENCES catalog_configurable_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
            END IF;
        END $$');

        $this->addSql('DO $$ BEGIN
            IF EXISTS (SELECT FROM pg_tables WHERE schemaname = \'public\' AND tablename = \'catalog_product_variants\')
               AND NOT EXISTS (SELECT FROM pg_constraint WHERE conname = \'fk_3e3fa5062905368\') THEN
                ALTER TABLE catalog_product_variants ADD CONSTRAINT FK_3E3FA5062905368 FOREIGN KEY (configurable_product_id) REFERENCES catalog_configurable_products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
            END IF;
        END $$');

        $this->addSql('DO $$ BEGIN
            IF EXISTS (SELECT FROM pg_tables WHERE schemaname = \'public\' AND tablename = \'media_thumbnails\')
               AND EXISTS (SELECT FROM pg_tables WHERE schemaname = \'public\' AND tablename = \'media_images\')
               AND NOT EXISTS (SELECT FROM pg_constraint WHERE conname = \'fk_588111f43da5256d\') THEN
                ALTER TABLE media_thumbnails ADD CONSTRAINT FK_588111F43DA5256D FOREIGN KEY (image_id) REFERENCES media_images (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE;
            END IF;
        END $$');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE TABLE IF NOT EXISTS catalog_sku_sequences (tenant_id UUID NOT NULL, last_value BIGINT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(tenant_id))');
        $this->addSql('CREATE TABLE i18n_backfill_tracking (id INT NOT NULL, entity_type VARCHAR(50) NOT NULL, tenant_id UUID NOT NULL, last_processed_id UUID DEFAULT NULL, total_count INT DEFAULT 0, processed_count INT DEFAULT 0, status VARCHAR(20) DEFAULT \'pending\', started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX i18n_backfill_tracking_entity_type_tenant_id_key ON i18n_backfill_tracking (entity_type, tenant_id)');
        $this->addSql('CREATE INDEX idx_backfill_entity_tenant ON i18n_backfill_tracking (entity_type, tenant_id)');
        $this->addSql('CREATE INDEX idx_backfill_status ON i18n_backfill_tracking (status)');
        $this->addSql('ALTER TABLE catalog_product_variants DROP CONSTRAINT FK_3E3FA5062905368');
        $this->addSql('ALTER TABLE catalog_product_options DROP CONSTRAINT FK_7FCEE8982905368');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER id TYPE UUID');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER product_id TYPE UUID');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER product_id TYPE UUID');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER updated_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE catalog_configurable_products ALTER updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('COMMENT ON COLUMN catalog_configurable_products.created_at IS NULL');
        $this->addSql('COMMENT ON COLUMN catalog_configurable_products.updated_at IS NULL');
        $this->addSql('ALTER TABLE media_thumbnails DROP CONSTRAINT FK_588111F43DA5256D');
    }
}
