<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224090000_AddMissingOrderVatAndReverseCharge extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing orders.vat_number and orders.is_reverse_charge columns before encryption migrations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD COLUMN IF NOT EXISTS vat_number VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD COLUMN IF NOT EXISTS is_reverse_charge BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP COLUMN IF EXISTS vat_number');
        $this->addSql('ALTER TABLE orders DROP COLUMN IF EXISTS is_reverse_charge');
    }
}
