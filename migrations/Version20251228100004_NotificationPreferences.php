<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add notification preferences columns to customers table.
 */
final class Version20251228100004_NotificationPreferences extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification preference columns to customers table';
    }

    public function up(Schema $schema): void
    {
        // Add notification preference columns
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_order_updates BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_shipping_updates BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_promotional_offers BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_price_drop_alerts BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_back_in_stock_alerts BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_security_alerts BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_newsletter_weekly BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE customers ADD COLUMN notif_prefer_sms BOOLEAN NOT NULL DEFAULT FALSE');

        // Add comment for documentation
        $this->addSql('COMMENT ON COLUMN customers.notif_order_updates IS \'Order updates notification (cannot be disabled - transactional)\'');
        $this->addSql('COMMENT ON COLUMN customers.notif_shipping_updates IS \'Shipping updates notification\'');
        $this->addSql('COMMENT ON COLUMN customers.notif_promotional_offers IS \'Promotional offers notification (requires marketing consent)\'');
        $this->addSql('COMMENT ON COLUMN customers.notif_price_drop_alerts IS \'Price drop alerts notification\'');
        $this->addSql('COMMENT ON COLUMN customers.notif_back_in_stock_alerts IS \'Back in stock alerts notification\'');
        $this->addSql('COMMENT ON COLUMN customers.notif_security_alerts IS \'Security alerts notification (cannot be disabled - legal requirement)\'');
        $this->addSql('COMMENT ON COLUMN customers.notif_newsletter_weekly IS \'Weekly newsletter subscription\'');
        $this->addSql('COMMENT ON COLUMN customers.notif_prefer_sms IS \'Prefer SMS over email (requires phone number)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove notification preference columns
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_order_updates');
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_shipping_updates');
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_promotional_offers');
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_price_drop_alerts');
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_back_in_stock_alerts');
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_security_alerts');
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_newsletter_weekly');
        $this->addSql('ALTER TABLE customers DROP COLUMN notif_prefer_sms');
    }
}
