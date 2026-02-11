<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 4: Add warehouse_id column to stock_reservations table.
 *
 * This column tracks which warehouse the stock is reserved from,
 * enabling multi-warehouse inventory management.
 */
final class Version20251127054300_StockReservationWarehouseId extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add warehouse_id column to stock_reservations table for multi-warehouse support';
    }

    public function up(Schema $schema): void
    {
        // Check if column already exists before adding
        $this->addSql("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM information_schema.columns
                    WHERE table_name = 'stock_reservations' AND column_name = 'warehouse_id'
                ) THEN
                    ALTER TABLE stock_reservations ADD warehouse_id VARCHAR(36) DEFAULT '00000000-0000-0000-0000-000000000000';
                    UPDATE stock_reservations SET warehouse_id = '00000000-0000-0000-0000-000000000000' WHERE warehouse_id IS NULL;
                    ALTER TABLE stock_reservations ALTER COLUMN warehouse_id SET NOT NULL;
                    ALTER TABLE stock_reservations ALTER COLUMN warehouse_id DROP DEFAULT;
                END IF;
            END $$;
        ");

        // Create index if it doesn't exist
        $this->addSql("
            CREATE INDEX IF NOT EXISTS idx_reservation_warehouse ON stock_reservations (warehouse_id)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_reservation_warehouse');
        $this->addSql('ALTER TABLE stock_reservations DROP COLUMN IF EXISTS warehouse_id');
    }
}
