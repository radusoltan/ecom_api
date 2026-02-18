<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251201090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create media_images and media_thumbnails tables for media management subsystem.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS media_images (
    id UUID NOT NULL,
    tenant_id UUID NOT NULL,
    owner_type VARCHAR(16) NOT NULL CHECK (owner_type IN ('product', 'category', 'user')),
    owner_id UUID NOT NULL,
    original_path TEXT NOT NULL,
    mime_type VARCHAR(128) NOT NULL,
    file_size BIGINT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    alt_text VARCHAR(255) DEFAULT NULL,
    uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_media_images_owner ON media_images (tenant_id, owner_type, owner_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_media_images_tenant_uploaded ON media_images (tenant_id, uploaded_at)');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS media_thumbnails (
    id UUID NOT NULL,
    tenant_id UUID NOT NULL,
    image_id UUID NOT NULL,
    size_label VARCHAR(8) NOT NULL CHECK (size_label IN ('sm', 'md', 'lg', 'xl')),
    path TEXT NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    crop_json JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    PRIMARY KEY(id),
    CONSTRAINT fk_media_thumbnails_image FOREIGN KEY (image_id) REFERENCES media_images (id) ON DELETE CASCADE
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_media_thumbnails_tenant ON media_thumbnails (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_media_thumbnails_image_size ON media_thumbnails (image_id, size_label)');

        // Skip GIN index creation if column type is JSON (not JSONB) from previous migration
        $this->addSql('DO $$ BEGIN
            IF EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_name = \'media_thumbnails\'
                AND column_name = \'crop_json\'
                AND data_type = \'jsonb\'
            ) AND NOT EXISTS (
                SELECT 1 FROM pg_indexes WHERE indexname = \'idx_media_thumbnails_crop\'
            ) THEN
                CREATE INDEX idx_media_thumbnails_crop ON media_thumbnails USING GIN (crop_json jsonb_path_ops);
            END IF;
        END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_media_thumbnails_crop');
        $this->addSql('DROP INDEX IF EXISTS uniq_media_thumbnails_image_size');
        $this->addSql('DROP INDEX IF EXISTS idx_media_thumbnails_tenant');
        $this->addSql('DROP TABLE IF EXISTS media_thumbnails');

        $this->addSql('DROP INDEX IF EXISTS idx_media_images_tenant_uploaded');
        $this->addSql('DROP INDEX IF EXISTS idx_media_images_owner');
        $this->addSql('DROP TABLE IF EXISTS media_images');
    }
}
