<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add idempotency_key column to payments table (Doctrine ORM schema).
 *
 * The original SQL migration (Version20251128200000) created this column,
 * but the Doctrine ORM entity didn't map it, so it may not exist in the
 * Doctrine-managed schema. This migration ensures the column exists with
 * a partial unique index per tenant for duplicate payment prevention.
 */
final class Version20260219100001_AddPaymentIdempotencyKey extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add idempotency_key column and unique index to payments table';
    }

    public function up(Schema $schema): void
    {
        // Add column only if it doesn't exist (safe for both fresh and existing DBs)
        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'payments' AND column_name = 'idempotency_key'
                ) THEN
                    ALTER TABLE payments ADD COLUMN idempotency_key VARCHAR(255) DEFAULT NULL;
                END IF;
            END $$
        ");

        // Create partial unique index (only on non-NULL values) per tenant
        // This allows multiple payments with NULL idempotency_key
        // Note: idx_payments_idempotency may already exist from the original SQL migration
        $this->addSql("
            CREATE UNIQUE INDEX IF NOT EXISTS idx_payments_idempotency
            ON payments (tenant_id, idempotency_key)
            WHERE idempotency_key IS NOT NULL
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_payments_idempotency');
        $this->addSql('ALTER TABLE payments DROP COLUMN IF EXISTS idempotency_key');
    }
}
