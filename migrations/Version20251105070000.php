<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for Wishlist feature
 * Creates wishlists table with multi-tenant support
 */
final class Version20251105070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates wishlists table for customer wishlist management';
    }

    public function up(Schema $schema): void
    {
        // Only create table if it doesn't exist
        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = 'public' AND tablename = 'wishlists') THEN
                    CREATE TABLE wishlists (
                        id UUID NOT NULL,
                        customer_id VARCHAR(255) NOT NULL,
                        tenant_id UUID NOT NULL,
                        items JSON NOT NULL,
                        created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                        updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                        PRIMARY KEY(id)
                    );

                    -- Add unique constraint - one wishlist per customer per tenant
                    CREATE UNIQUE INDEX UNIQ_wishlists_customer_tenant ON wishlists (customer_id, tenant_id);

                    -- Add index for customer lookups
                    CREATE INDEX IDX_wishlists_customer_id ON wishlists (customer_id);

                    -- Add index for tenant isolation
                    CREATE INDEX IDX_wishlists_tenant_id ON wishlists (tenant_id);

                    -- Add comment for items column
                    COMMENT ON COLUMN wishlists.items IS '(DC2Type:json)';

                    -- Add comments for timestamp columns
                    COMMENT ON COLUMN wishlists.created_at IS '(DC2Type:datetime_immutable)';
                    COMMENT ON COLUMN wishlists.updated_at IS '(DC2Type:datetime_immutable)';
                END IF;
            END $$;
        ");
    }

    public function down(Schema $schema): void
    {
        // Drop wishlists table
        $this->addSql('DROP TABLE wishlists');
    }
}
