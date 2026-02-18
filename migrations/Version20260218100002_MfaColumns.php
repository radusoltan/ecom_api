<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add MFA (Multi-Factor Authentication) columns to users table.
 *
 * - mfa_enabled: boolean flag
 * - totp_secret: encrypted TOTP secret key
 * - backup_codes: encrypted JSON array of bcrypt-hashed backup codes
 * - mfa_enabled_at: timestamp when MFA was activated
 */
final class Version20260218100002_MfaColumns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add MFA columns (totp_secret, backup_codes, mfa_enabled, mfa_enabled_at) to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE users ADD COLUMN totp_secret TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN backup_codes TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD COLUMN mfa_enabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN users.mfa_enabled_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN mfa_enabled');
        $this->addSql('ALTER TABLE users DROP COLUMN totp_secret');
        $this->addSql('ALTER TABLE users DROP COLUMN backup_codes');
        $this->addSql('ALTER TABLE users DROP COLUMN mfa_enabled_at');
    }
}
