<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227100001_ForceRLSOnTenantScopedTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Force Row Level Security on 12 tenant-scoped tables that have RLS enabled but not forced';
    }

    public function up(Schema $schema): void
    {
        $tables = [
            'consent_history',
            'deletion_requests',
            'invoice_lines',
            'invoice_sequences',
            'invoices',
            'loyalty_point_transactions',
            'loyalty_programs',
            'loyalty_tiers',
            'payment_transactions',
            'payments',
            'price_history',
            'refunds',
        ];

        foreach ($tables as $table) {
            $this->addSql(sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table));
        }
    }

    public function down(Schema $schema): void
    {
        $tables = [
            'consent_history',
            'deletion_requests',
            'invoice_lines',
            'invoice_sequences',
            'invoices',
            'loyalty_point_transactions',
            'loyalty_programs',
            'loyalty_tiers',
            'payment_transactions',
            'payments',
            'price_history',
            'refunds',
        ];

        foreach ($tables as $table) {
            $this->addSql(sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $table));
        }
    }
}
