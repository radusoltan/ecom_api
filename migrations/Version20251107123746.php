<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251107123746 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription fields to catalog_products table (interval, billing_cycles, setup_fee, trial_end)';
    }

    public function up(Schema $schema): void
    {
        // Add subscription fields to catalog_products
        $this->addSql('ALTER TABLE catalog_products ADD subscription_interval VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD subscription_billing_cycles INT DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD subscription_setup_fee_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD subscription_setup_fee_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE catalog_products ADD subscription_trial_end TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN catalog_products.subscription_trial_end IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove subscription fields from catalog_products
        $this->addSql('ALTER TABLE catalog_products DROP subscription_interval');
        $this->addSql('ALTER TABLE catalog_products DROP subscription_billing_cycles');
        $this->addSql('ALTER TABLE catalog_products DROP subscription_setup_fee_amount');
        $this->addSql('ALTER TABLE catalog_products DROP subscription_setup_fee_currency');
        $this->addSql('ALTER TABLE catalog_products DROP subscription_trial_end');
    }
}
