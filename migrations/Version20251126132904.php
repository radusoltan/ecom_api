<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251126132904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Create password_reset_tokens table (idempotent)
        $this->addSql('CREATE TABLE IF NOT EXISTS password_reset_tokens (id UUID NOT NULL, user_id UUID NOT NULL, token VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_3967A2165F37A13B ON password_reset_tokens (token)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_prt_token ON password_reset_tokens (token)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_prt_user_id ON password_reset_tokens (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_prt_expires_at ON password_reset_tokens (expires_at)');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.used_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN password_reset_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');

        // Cart improvements (idempotent)
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS FK_BEF484451AD5CDBF');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT FK_BEF484451AD5CDBF FOREIGN KEY (cart_id) REFERENCES carts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE carts ADD COLUMN IF NOT EXISTS abandonment_email_sent BOOLEAN DEFAULT false NOT NULL');

        // Payment retry support (idempotent)
        $this->addSql('ALTER TABLE payments ADD COLUMN IF NOT EXISTS error_code VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE payments ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE payments ADD COLUMN IF NOT EXISTS next_retry_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN payments.next_retry_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
        $this->addSql('ALTER TABLE payments DROP COLUMN IF EXISTS error_code');
        $this->addSql('ALTER TABLE payments DROP COLUMN IF EXISTS retry_count');
        $this->addSql('ALTER TABLE payments DROP COLUMN IF EXISTS next_retry_at');
        $this->addSql('ALTER TABLE carts DROP COLUMN IF EXISTS abandonment_email_sent');
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT IF EXISTS FK_BEF484451AD5CDBF');
    }
}
