<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260304070000_AddUserPreferredLocale extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add preferred_locale column to users table for locale negotiation step 3';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN preferred_locale VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN preferred_locale');
    }
}
