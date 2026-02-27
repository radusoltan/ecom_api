<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227100002_GrantEcomAdminToEcommerceUser extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant ecom_admin role to ecommerce user so SET ROLE works in DataRetentionCommand';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('GRANT ecom_admin TO ecommerce');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('REVOKE ecom_admin FROM ecommerce');
    }
}
