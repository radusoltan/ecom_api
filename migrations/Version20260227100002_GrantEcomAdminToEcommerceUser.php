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
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ecom_admin')
                    AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ecommerce') THEN
                    BEGIN
                        EXECUTE 'GRANT ecom_admin TO ecommerce';
                    EXCEPTION
                        WHEN insufficient_privilege THEN
                            RAISE NOTICE 'Skipping GRANT ecom_admin TO ecommerce due to insufficient privilege';
                    END;
                END IF;
            END
            $$;
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ecom_admin')
                    AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'ecommerce') THEN
                    BEGIN
                        EXECUTE 'REVOKE ecom_admin FROM ecommerce';
                    EXCEPTION
                        WHEN insufficient_privilege THEN
                            RAISE NOTICE 'Skipping REVOKE ecom_admin FROM ecommerce due to insufficient privilege';
                    END;
                END IF;
            END
            $$;
        SQL);
    }
}
