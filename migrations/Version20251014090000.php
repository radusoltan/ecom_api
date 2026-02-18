<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251014090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add feature flags to products and categories, introduce SKU sequence table, and scope SKU uniqueness per tenant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_products ADD is_featured BOOLEAN DEFAULT false');
        $this->addSql('UPDATE catalog_products SET is_featured = false');
        $this->addSql('ALTER TABLE catalog_products ALTER COLUMN is_featured SET NOT NULL');

        $this->addSql('ALTER TABLE catalog_categories ADD show_on_front BOOLEAN DEFAULT false');
        $this->addSql('UPDATE catalog_categories SET show_on_front = false');
        $this->addSql('ALTER TABLE catalog_categories ALTER COLUMN show_on_front SET NOT NULL');

        $this->addSql('DROP INDEX UNIQ_816D8444F9038C4');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_products_tenant_sku ON catalog_products (tenant_id, sku)');

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_sku_sequences (
                tenant_id UUID NOT NULL,
                last_value BIGINT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(tenant_id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE catalog_sku_sequences');

        $this->addSql('DROP INDEX uniq_catalog_products_tenant_sku');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_816D8444F9038C4 ON catalog_products (sku)');

        $this->addSql('ALTER TABLE catalog_products DROP is_featured');
        $this->addSql('ALTER TABLE catalog_categories DROP show_on_front');
    }
}
