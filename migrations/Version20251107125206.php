<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251107125206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add downloadable file fields to catalog_products table for virtual products';
    }

    public function up(Schema $schema): void
    {
        // Add downloadable file fields for virtual products
        $this->addSql('ALTER TABLE catalog_products ADD downloadable_filename VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD downloadable_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD downloadable_size_bytes BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD downloadable_limit INT DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD downloadable_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN catalog_products.downloadable_expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove downloadable file fields
        $this->addSql('ALTER TABLE catalog_products DROP downloadable_filename');
        $this->addSql('ALTER TABLE catalog_products DROP downloadable_url');
        $this->addSql('ALTER TABLE catalog_products DROP downloadable_size_bytes');
        $this->addSql('ALTER TABLE catalog_products DROP downloadable_limit');
        $this->addSql('ALTER TABLE catalog_products DROP downloadable_expires_at');
    }
}
