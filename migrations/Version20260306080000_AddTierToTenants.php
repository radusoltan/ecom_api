<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306080000_AddTierToTenants extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tier column to tenants table for resource quota support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS tier VARCHAR(20) NOT NULL DEFAULT 'starter'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP COLUMN IF EXISTS tier');
    }
}
