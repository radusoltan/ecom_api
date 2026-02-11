<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add abandonment_email_sent column to carts table.
 *
 * This field tracks whether an abandonment email has been sent for a cart,
 * ensuring only one email is sent per abandoned cart.
 */
final class Version20251126120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add abandonment_email_sent column to carts table for cart abandonment tracking';
    }

    public function up(Schema $schema): void
    {
        // Check if column already exists (idempotency)
        $table = $schema->getTable('carts');
        if ($table->hasColumn('abandonment_email_sent')) {
            return;
        }

        $this->addSql('
            ALTER TABLE carts
            ADD COLUMN abandonment_email_sent BOOLEAN NOT NULL DEFAULT FALSE
        ');

        $this->addSql('COMMENT ON COLUMN carts.abandonment_email_sent IS \'Whether abandonment recovery email has been sent for this cart\'');

        // Create index for efficient querying of abandoned carts
        $this->addSql('CREATE INDEX idx_carts_abandonment_email ON carts(abandonment_email_sent, status, updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_carts_abandonment_email');
        $this->addSql('ALTER TABLE carts DROP COLUMN IF EXISTS abandonment_email_sent');
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
