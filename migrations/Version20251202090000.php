<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251202090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cover_image column to catalog_categories';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_categories ADD cover_image VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_categories DROP cover_image');
    }
}
