<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add discount_currency column to promotions and flash_sales tables.
 * Supports storing the currency for fixed-amount discounts (brick/money migration).
 */
final class Version20260311104637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add discount_currency column to promotions and flash_sales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE promotions ADD discount_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE flash_sales ADD discount_currency VARCHAR(3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE promotions DROP discount_currency');
        $this->addSql('ALTER TABLE flash_sales DROP discount_currency');
    }
}
