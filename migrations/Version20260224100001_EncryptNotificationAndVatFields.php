<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Alter columns to support encrypted data (TEXT type needed for base64-encoded ciphertext).
 *
 * - notifications.recipient_email: VARCHAR(255) -> TEXT
 * - notifications.recipient_phone: VARCHAR(20) -> TEXT
 * - orders.vat_number: VARCHAR(20) -> TEXT
 * - Drop idx_notifications_recipient_email (can't index encrypted columns)
 */
final class Version20260224100001_EncryptNotificationAndVatFields extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert notification PII and order VAT number columns to TEXT for encryption at rest';
    }

    public function up(Schema $schema): void
    {
        // Drop the plaintext index on recipient_email (encrypted columns can't be indexed)
        $this->addSql('DROP INDEX IF EXISTS idx_notifications_recipient_email');

        // Widen columns to TEXT to hold base64-encoded ciphertext
        $this->addSql('ALTER TABLE notifications ALTER COLUMN recipient_email TYPE TEXT');
        $this->addSql('ALTER TABLE notifications ALTER COLUMN recipient_phone TYPE TEXT');
        $this->addSql('ALTER TABLE orders ALTER COLUMN vat_number TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        // Reverse column types (data will be truncated if encrypted)
        $this->addSql('ALTER TABLE notifications ALTER COLUMN recipient_email TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE notifications ALTER COLUMN recipient_phone TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE orders ALTER COLUMN vat_number TYPE VARCHAR(20)');

        // Recreate the plaintext index
        $this->addSql('CREATE INDEX idx_notifications_recipient_email ON notifications (recipient_email)');
    }
}
