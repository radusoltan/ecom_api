<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add payment retry fields
 *
 * Adds columns for automatic payment retry functionality:
 * - error_code: Normalized error code for retry decision logic
 * - retry_count: Number of retry attempts made (0-indexed)
 * - next_retry_at: Scheduled timestamp for next retry attempt
 *
 * Index: Optimizes queries for finding payments due for retry
 */
final class Version20251126140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment retry fields (error_code, retry_count, next_retry_at) and index for retry queries';
    }

    public function up(Schema $schema): void
    {
        // Add error_code column (nullable, max 100 chars)
        $this->addSql('DO $$ BEGIN
            IF NOT EXISTS (
                SELECT FROM information_schema.columns
                WHERE table_schema = \'public\'
                AND table_name = \'payments\'
                AND column_name = \'error_code\'
            ) THEN
                ALTER TABLE payments ADD COLUMN error_code VARCHAR(100) DEFAULT NULL;
            END IF;
        END $$');

        // Add retry_count column (integer, default 0, not null)
        $this->addSql('DO $$ BEGIN
            IF NOT EXISTS (
                SELECT FROM information_schema.columns
                WHERE table_schema = \'public\'
                AND table_name = \'payments\'
                AND column_name = \'retry_count\'
            ) THEN
                ALTER TABLE payments ADD COLUMN retry_count INTEGER NOT NULL DEFAULT 0;
            END IF;
        END $$');

        // Add next_retry_at column (nullable timestamp)
        $this->addSql('DO $$ BEGIN
            IF NOT EXISTS (
                SELECT FROM information_schema.columns
                WHERE table_schema = \'public\'
                AND table_name = \'payments\'
                AND column_name = \'next_retry_at\'
            ) THEN
                ALTER TABLE payments ADD COLUMN next_retry_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL;
            END IF;
        END $$');

        // Add Doctrine type comment for next_retry_at
        $this->addSql('COMMENT ON COLUMN payments.next_retry_at IS \'(DC2Type:datetime_immutable)\'');

        // Create composite index for finding payments due for retry
        // Index pattern: (status, next_retry_at, retry_count)
        // Optimizes queries: WHERE status = 'failed' AND next_retry_at <= NOW() AND retry_count < max
        $this->addSql('DO $$ BEGIN
            IF NOT EXISTS (
                SELECT FROM pg_indexes
                WHERE schemaname = \'public\'
                AND tablename = \'payments\'
                AND indexname = \'idx_payments_retry\'
            ) THEN
                CREATE INDEX idx_payments_retry ON payments (status, next_retry_at, retry_count);
            END IF;
        END $$');
    }

    public function down(Schema $schema): void
    {
        // Drop index
        $this->addSql('DROP INDEX IF EXISTS idx_payments_retry');

        // Drop columns
        $this->addSql('ALTER TABLE payments DROP COLUMN IF EXISTS error_code');
        $this->addSql('ALTER TABLE payments DROP COLUMN IF EXISTS retry_count');
        $this->addSql('ALTER TABLE payments DROP COLUMN IF EXISTS next_retry_at');
    }
}
