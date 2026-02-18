<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Encryption at Rest: Add blind index columns and widen PII columns for encrypted storage.
 *
 * Encrypted fields use sodium_crypto_secretbox (XSalsa20-Poly1305) producing base64-encoded
 * ciphertext that is significantly larger than plaintext. Columns are widened to TEXT.
 *
 * Blind indexes (HMAC-SHA256, 64-char hex) enable searching encrypted fields without decryption.
 * Unique constraints are migrated from plaintext columns to blind index columns.
 */
final class Version20260218100001_EncryptionAtRest extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add blind index columns and widen PII fields for encryption at rest';
    }

    public function up(Schema $schema): void
    {
        // === CUSTOMERS TABLE ===
        // Add blind index column for email lookups
        $this->addSql('ALTER TABLE customers ADD COLUMN email_blind_index VARCHAR(64)');

        // Widen PII columns to TEXT for encrypted data
        $this->addSql('ALTER TABLE customers ALTER COLUMN email TYPE TEXT');
        $this->addSql('ALTER TABLE customers ALTER COLUMN first_name TYPE TEXT');
        $this->addSql('ALTER TABLE customers ALTER COLUMN last_name TYPE TEXT');
        $this->addSql('ALTER TABLE customers ALTER COLUMN phone_number TYPE TEXT');

        // Drop old email index and unique constraint (can't work on encrypted data)
        $this->addSql('DROP INDEX IF EXISTS idx_62534e21e7927c74'); // email index
        $this->addSql('ALTER TABLE customers DROP CONSTRAINT IF EXISTS unique_email_per_tenant');

        // Create new indexes on blind index column
        $this->addSql('CREATE INDEX idx_customers_email_bi ON customers (email_blind_index)');
        $this->addSql('CREATE UNIQUE INDEX unique_email_per_tenant_bi ON customers (tenant_id, email_blind_index)');

        // === USERS TABLE ===
        // Add blind index columns
        $this->addSql('ALTER TABLE users ADD COLUMN email_blind_index VARCHAR(64)');
        $this->addSql('ALTER TABLE users ADD COLUMN username_blind_index VARCHAR(64)');

        // Widen columns to TEXT
        $this->addSql('ALTER TABLE users ALTER COLUMN email TYPE TEXT');
        $this->addSql('ALTER TABLE users ALTER COLUMN username TYPE TEXT');

        // Drop old unique indexes
        $this->addSql('DROP INDEX IF EXISTS uniq_1483a5e9e7927c74'); // email unique
        $this->addSql('DROP INDEX IF EXISTS uniq_1483a5e9f85e0677'); // username unique

        // Create new unique indexes on blind index columns
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email_bi ON users (email_blind_index)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_username_bi ON users (username_blind_index)');

        // === ORDERS TABLE ===
        // Add blind index for customer_email
        $this->addSql('ALTER TABLE orders ADD COLUMN customer_email_blind_index VARCHAR(64)');

        // Widen customer_email to TEXT
        $this->addSql('ALTER TABLE orders ALTER COLUMN customer_email TYPE TEXT');

        // Widen address JSON columns to TEXT for encrypted storage
        $this->addSql('ALTER TABLE orders ALTER COLUMN shipping_address TYPE TEXT');
        $this->addSql('ALTER TABLE orders ALTER COLUMN billing_address TYPE TEXT');

        // Drop old customer_email indexes
        $this->addSql('DROP INDEX IF EXISTS idx_orders_customer_created');
        $this->addSql('DROP INDEX IF EXISTS idx_orders_tenant_customer');

        // Create new indexes on blind index
        $this->addSql('CREATE INDEX idx_orders_customer_created_bi ON orders (customer_email_blind_index, created_at)');
        $this->addSql('CREATE INDEX idx_orders_tenant_customer_bi ON orders (tenant_id, customer_email_blind_index)');

        // === CUSTOMER_ADDRESSES TABLE ===
        // Widen PII columns to TEXT
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN street TYPE TEXT');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN street2 TYPE TEXT');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN city TYPE TEXT');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN state TYPE TEXT');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN postal_code TYPE TEXT');

        // === INVOICES TABLE ===
        // Widen billing PII columns to TEXT
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_name TYPE TEXT');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_address_line1 TYPE TEXT');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_address_line2 TYPE TEXT');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_city TYPE TEXT');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_postal_code TYPE TEXT');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_vat_number TYPE TEXT');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN notes TYPE TEXT');

        // === PAYMENTS TABLE ===
        // Widen PII-adjacent columns to TEXT
        $this->addSql('ALTER TABLE payments ALTER COLUMN gateway_reference TYPE TEXT');
        $this->addSql('ALTER TABLE payments ALTER COLUMN failure_message TYPE TEXT');

        // Drop and recreate gateway_reference index (now TEXT)
        $this->addSql('DROP INDEX IF EXISTS idx_payments_gateway_reference');
        // gateway_reference index can't work on encrypted data — remove it

        // === CONSENTS TABLE ===
        // Widen PII columns to TEXT
        $this->addSql('ALTER TABLE consents ALTER COLUMN ip_address TYPE TEXT');
        $this->addSql('ALTER TABLE consents ALTER COLUMN user_agent TYPE TEXT');

        // === DATA_SUBJECT_REQUESTS TABLE ===
        // Widen PII columns to TEXT
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN reason TYPE TEXT');
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN review_notes TYPE TEXT');
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN rejection_reason TYPE TEXT');
        // export_data is already json type, will use encrypted_json Doctrine type
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN export_data TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        // === DATA_SUBJECT_REQUESTS ===
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN export_data TYPE json USING export_data::json');
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN rejection_reason TYPE text');
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN review_notes TYPE text');
        $this->addSql('ALTER TABLE data_subject_requests ALTER COLUMN reason TYPE text');

        // === CONSENTS ===
        $this->addSql('ALTER TABLE consents ALTER COLUMN user_agent TYPE VARCHAR(500)');
        $this->addSql('ALTER TABLE consents ALTER COLUMN ip_address TYPE VARCHAR(45)');

        // === PAYMENTS ===
        $this->addSql('ALTER TABLE payments ALTER COLUMN failure_message TYPE text');
        $this->addSql('ALTER TABLE payments ALTER COLUMN gateway_reference TYPE VARCHAR(255)');
        $this->addSql('CREATE INDEX idx_payments_gateway_reference ON payments (gateway_reference) WHERE gateway_reference IS NOT NULL');

        // === INVOICES ===
        $this->addSql('ALTER TABLE invoices ALTER COLUMN notes TYPE text');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_vat_number TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_postal_code TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_city TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_address_line2 TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_address_line1 TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE invoices ALTER COLUMN billing_name TYPE VARCHAR(255)');

        // === CUSTOMER_ADDRESSES ===
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN postal_code TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN state TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN city TYPE VARCHAR(100)');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN street2 TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE customer_addresses ALTER COLUMN street TYPE VARCHAR(255)');

        // === ORDERS ===
        $this->addSql('DROP INDEX IF EXISTS idx_orders_tenant_customer_bi');
        $this->addSql('DROP INDEX IF EXISTS idx_orders_customer_created_bi');
        $this->addSql('ALTER TABLE orders ALTER COLUMN billing_address TYPE json USING billing_address::json');
        $this->addSql('ALTER TABLE orders ALTER COLUMN shipping_address TYPE json USING shipping_address::json');
        $this->addSql('ALTER TABLE orders ALTER COLUMN customer_email TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE orders DROP COLUMN customer_email_blind_index');
        $this->addSql('CREATE INDEX idx_orders_customer_created ON orders (customer_email, created_at)');
        $this->addSql('CREATE INDEX idx_orders_tenant_customer ON orders (tenant_id, customer_email)');

        // === USERS ===
        $this->addSql('DROP INDEX IF EXISTS uniq_users_username_bi');
        $this->addSql('DROP INDEX IF EXISTS uniq_users_email_bi');
        $this->addSql('ALTER TABLE users ALTER COLUMN username TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE users ALTER COLUMN email TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE users DROP COLUMN username_blind_index');
        $this->addSql('ALTER TABLE users DROP COLUMN email_blind_index');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9e7927c74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX uniq_1483a5e9f85e0677 ON users (username)');

        // === CUSTOMERS ===
        $this->addSql('DROP INDEX IF EXISTS unique_email_per_tenant_bi');
        $this->addSql('DROP INDEX IF EXISTS idx_customers_email_bi');
        $this->addSql('ALTER TABLE customers ALTER COLUMN phone_number TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE customers ALTER COLUMN last_name TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE customers ALTER COLUMN first_name TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE customers ALTER COLUMN email TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE customers DROP COLUMN email_blind_index');
        $this->addSql('CREATE INDEX idx_62534e21e7927c74 ON customers (email)');
        $this->addSql('ALTER TABLE customers ADD CONSTRAINT unique_email_per_tenant UNIQUE (tenant_id, email)');
    }
}
