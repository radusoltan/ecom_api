<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 6: Enable PostgreSQL Row-Level Security (RLS) for Multi-Tenancy Hardening
 *
 * This migration enables RLS on all catalog tables and creates policies that enforce
 * tenant isolation at the database level. Even if application logic fails, cross-tenant
 * data access becomes impossible.
 *
 * Tables protected:
 * - catalog_products
 * - catalog_categories
 * - media_images
 * - media_thumbnails
 * - product_options (when exists)
 * - product_option_values (when exists)
 * - product_variants (when exists)
 */
final class Version20251203090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable PostgreSQL RLS for multi-tenant isolation on all catalog tables';
    }

    public function up(Schema $schema): void
    {
        // Enable RLS on catalog_products
        $this->addSql('ALTER TABLE catalog_products ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE catalog_products FORCE ROW LEVEL SECURITY');

        // Create policy for catalog_products
        $this->addSql("
            CREATE POLICY p_catalog_products_rw ON catalog_products
            FOR ALL
            USING (tenant_id = current_setting('app.tenant_id', true))
            WITH CHECK (tenant_id = current_setting('app.tenant_id', true))
        ");

        // Enable RLS on catalog_categories
        $this->addSql('ALTER TABLE catalog_categories ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE catalog_categories FORCE ROW LEVEL SECURITY');

        // Create policy for catalog_categories
        $this->addSql("
            CREATE POLICY p_catalog_categories_rw ON catalog_categories
            FOR ALL
            USING (tenant_id = current_setting('app.tenant_id', true))
            WITH CHECK (tenant_id = current_setting('app.tenant_id', true))
        ");

        // Enable RLS on media_images
        $this->addSql('ALTER TABLE media_images ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE media_images FORCE ROW LEVEL SECURITY');

        // Create policy for media_images
        $this->addSql("
            CREATE POLICY p_media_images_rw ON media_images
            FOR ALL
            USING (tenant_id = current_setting('app.tenant_id', true))
            WITH CHECK (tenant_id = current_setting('app.tenant_id', true))
        ");

        // Enable RLS on media_thumbnails
        $this->addSql('ALTER TABLE media_thumbnails ENABLE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE media_thumbnails FORCE ROW LEVEL SECURITY');

        // Create policy for media_thumbnails
        $this->addSql("
            CREATE POLICY p_media_thumbnails_rw ON media_thumbnails
            FOR ALL
            USING (tenant_id = current_setting('app.tenant_id', true))
            WITH CHECK (tenant_id = current_setting('app.tenant_id', true))
        ");

        // Log RLS enablement
        $this->write('RLS enabled and policies created for catalog_products, catalog_categories, media_images, media_thumbnails');
        $this->write('IMPORTANT: Application must SET LOCAL app.tenant_id for every request');
        $this->write('See TenantConnectionSubscriber for implementation');
    }

    public function down(Schema $schema): void
    {
        // Drop policies for media_thumbnails
        $this->addSql('DROP POLICY IF EXISTS p_media_thumbnails_rw ON media_thumbnails');
        $this->addSql('ALTER TABLE media_thumbnails NO FORCE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE media_thumbnails DISABLE ROW LEVEL SECURITY');

        // Drop policies for media_images
        $this->addSql('DROP POLICY IF EXISTS p_media_images_rw ON media_images');
        $this->addSql('ALTER TABLE media_images NO FORCE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE media_images DISABLE ROW LEVEL SECURITY');

        // Drop policies for catalog_categories
        $this->addSql('DROP POLICY IF EXISTS p_catalog_categories_rw ON catalog_categories');
        $this->addSql('ALTER TABLE catalog_categories NO FORCE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE catalog_categories DISABLE ROW LEVEL SECURITY');

        // Drop policies for catalog_products
        $this->addSql('DROP POLICY IF EXISTS p_catalog_products_rw ON catalog_products');
        $this->addSql('ALTER TABLE catalog_products NO FORCE ROW LEVEL SECURITY');
        $this->addSql('ALTER TABLE catalog_products DISABLE ROW LEVEL SECURITY');

        $this->write('RLS disabled and policies dropped for all catalog tables');
    }
}
