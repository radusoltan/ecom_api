<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add covering index for dashboard recent-orders query.
 * Eliminates full-scan + sort for ORDER BY created_at DESC LIMIT 5.
 */
final class Version20260225100001_DashboardCoveringIndex extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add covering index on orders(tenant_id, created_at DESC) for dashboard queries';
    }

    public function up(Schema $schema): void
    {
        // Note: In production, use CREATE INDEX CONCURRENTLY (outside a transaction)
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_orders_tenant_created_covering
                ON orders (tenant_id, created_at DESC)
                INCLUDE (id, status, customer_email)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_orders_tenant_created_covering');
    }
}
