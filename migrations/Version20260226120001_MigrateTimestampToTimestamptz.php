<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migrate 98 TIMESTAMP WITHOUT TIME ZONE columns to TIMESTAMPTZ across 41 tables.
 * Excludes doctrine_migration_versions.executed_at (Doctrine system table).
 */
final class Version20260226120001_MigrateTimestampToTimestamptz extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert all TIMESTAMP WITHOUT TIME ZONE columns to TIMESTAMPTZ (98 columns, 41 tables)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE bundle_items
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE carts
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_categories
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_configurable_products
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_product_option_values
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_product_options
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_product_variants
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_products
                ALTER COLUMN created_at              TYPE TIMESTAMPTZ USING created_at              AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at              TYPE TIMESTAMPTZ USING updated_at              AT TIME ZONE 'UTC',
                ALTER COLUMN downloadable_expires_at TYPE TIMESTAMPTZ USING downloadable_expires_at AT TIME ZONE 'UTC',
                ALTER COLUMN subscription_trial_end  TYPE TIMESTAMPTZ USING subscription_trial_end  AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE consent_history
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE consents
                ALTER COLUMN created_at   TYPE TIMESTAMPTZ USING created_at   AT TIME ZONE 'UTC',
                ALTER COLUMN granted_at   TYPE TIMESTAMPTZ USING granted_at   AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at   TYPE TIMESTAMPTZ USING updated_at   AT TIME ZONE 'UTC',
                ALTER COLUMN withdrawn_at TYPE TIMESTAMPTZ USING withdrawn_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE customer_addresses
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE customers
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE data_subject_requests
                ALTER COLUMN completed_at TYPE TIMESTAMPTZ USING completed_at AT TIME ZONE 'UTC',
                ALTER COLUMN created_at   TYPE TIMESTAMPTZ USING created_at   AT TIME ZONE 'UTC',
                ALTER COLUMN deadline     TYPE TIMESTAMPTZ USING deadline     AT TIME ZONE 'UTC',
                ALTER COLUMN submitted_at TYPE TIMESTAMPTZ USING submitted_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at   TYPE TIMESTAMPTZ USING updated_at   AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE deletion_requests
                ALTER COLUMN completed_at  TYPE TIMESTAMPTZ USING completed_at  AT TIME ZONE 'UTC',
                ALTER COLUMN confirmed_at  TYPE TIMESTAMPTZ USING confirmed_at  AT TIME ZONE 'UTC',
                ALTER COLUMN created_at    TYPE TIMESTAMPTZ USING created_at    AT TIME ZONE 'UTC',
                ALTER COLUMN scheduled_for TYPE TIMESTAMPTZ USING scheduled_for AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE fulfillments
                ALTER COLUMN assigned_at        TYPE TIMESTAMPTZ USING assigned_at        AT TIME ZONE 'UTC',
                ALTER COLUMN cancelled_at       TYPE TIMESTAMPTZ USING cancelled_at       AT TIME ZONE 'UTC',
                ALTER COLUMN created_at         TYPE TIMESTAMPTZ USING created_at         AT TIME ZONE 'UTC',
                ALTER COLUMN delivered_at       TYPE TIMESTAMPTZ USING delivered_at       AT TIME ZONE 'UTC',
                ALTER COLUMN failed_at          TYPE TIMESTAMPTZ USING failed_at          AT TIME ZONE 'UTC',
                ALTER COLUMN packing_started_at TYPE TIMESTAMPTZ USING packing_started_at AT TIME ZONE 'UTC',
                ALTER COLUMN picking_started_at TYPE TIMESTAMPTZ USING picking_started_at AT TIME ZONE 'UTC',
                ALTER COLUMN shipped_at         TYPE TIMESTAMPTZ USING shipped_at         AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at         TYPE TIMESTAMPTZ USING updated_at         AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE i18n_backfill_tracking
                ALTER COLUMN completed_at TYPE TIMESTAMPTZ USING completed_at AT TIME ZONE 'UTC',
                ALTER COLUMN started_at   TYPE TIMESTAMPTZ USING started_at   AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE loyalty_point_transactions
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN expires_at TYPE TIMESTAMPTZ USING expires_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE loyalty_programs
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE loyalty_tiers
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE media_images
                ALTER COLUMN deleted_at  TYPE TIMESTAMPTZ USING deleted_at  AT TIME ZONE 'UTC',
                ALTER COLUMN uploaded_at TYPE TIMESTAMPTZ USING uploaded_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE media_thumbnails
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE notifications
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN failed_at  TYPE TIMESTAMPTZ USING failed_at  AT TIME ZONE 'UTC',
                ALTER COLUMN sent_at    TYPE TIMESTAMPTZ USING sent_at    AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE orders
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE password_reset_tokens
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN expires_at TYPE TIMESTAMPTZ USING expires_at AT TIME ZONE 'UTC',
                ALTER COLUMN used_at    TYPE TIMESTAMPTZ USING used_at    AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE payment_transactions
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE payment_webhook_events
                ALTER COLUMN processed_at TYPE TIMESTAMPTZ USING processed_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE payments
                ALTER COLUMN authorized_at TYPE TIMESTAMPTZ USING authorized_at AT TIME ZONE 'UTC',
                ALTER COLUMN cancelled_at  TYPE TIMESTAMPTZ USING cancelled_at  AT TIME ZONE 'UTC',
                ALTER COLUMN captured_at   TYPE TIMESTAMPTZ USING captured_at   AT TIME ZONE 'UTC',
                ALTER COLUMN created_at    TYPE TIMESTAMPTZ USING created_at    AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at    TYPE TIMESTAMPTZ USING updated_at    AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE price_history
                ALTER COLUMN changed_at TYPE TIMESTAMPTZ USING changed_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE price_lists
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC',
                ALTER COLUMN valid_from TYPE TIMESTAMPTZ USING valid_from AT TIME ZONE 'UTC',
                ALTER COLUMN valid_to   TYPE TIMESTAMPTZ USING valid_to   AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE product_reviews
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE promotions
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC',
                ALTER COLUMN valid_from TYPE TIMESTAMPTZ USING valid_from AT TIME ZONE 'UTC',
                ALTER COLUMN valid_to   TYPE TIMESTAMPTZ USING valid_to   AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE refresh_tokens
                ALTER COLUMN valid TYPE TIMESTAMPTZ USING valid AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE refunds
                ALTER COLUMN created_at   TYPE TIMESTAMPTZ USING created_at   AT TIME ZONE 'UTC',
                ALTER COLUMN processed_at TYPE TIMESTAMPTZ USING processed_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at   TYPE TIMESTAMPTZ USING updated_at   AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE return_requests
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE stock_items
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE stock_reservations
                ALTER COLUMN expires_at  TYPE TIMESTAMPTZ USING expires_at  AT TIME ZONE 'UTC',
                ALTER COLUMN released_at TYPE TIMESTAMPTZ USING released_at AT TIME ZONE 'UTC',
                ALTER COLUMN reserved_at TYPE TIMESTAMPTZ USING reserved_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE tenants
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE users
                ALTER COLUMN created_at     TYPE TIMESTAMPTZ USING created_at     AT TIME ZONE 'UTC',
                ALTER COLUMN mfa_enabled_at TYPE TIMESTAMPTZ USING mfa_enabled_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE warehouses
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE wishlists
                ALTER COLUMN created_at TYPE TIMESTAMPTZ USING created_at AT TIME ZONE 'UTC',
                ALTER COLUMN updated_at TYPE TIMESTAMPTZ USING updated_at AT TIME ZONE 'UTC'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $tables = [
            'bundle_items' => ['created_at', 'updated_at'],
            'carts' => ['created_at', 'updated_at'],
            'catalog_categories' => ['created_at', 'updated_at'],
            'catalog_configurable_products' => ['created_at', 'updated_at'],
            'catalog_product_option_values' => ['created_at'],
            'catalog_product_options' => ['created_at'],
            'catalog_product_variants' => ['created_at', 'updated_at'],
            'catalog_products' => ['created_at', 'updated_at', 'downloadable_expires_at', 'subscription_trial_end'],
            'consent_history' => ['created_at'],
            'consents' => ['created_at', 'granted_at', 'updated_at', 'withdrawn_at'],
            'customer_addresses' => ['created_at', 'updated_at'],
            'customers' => ['created_at', 'updated_at'],
            'data_subject_requests' => ['completed_at', 'created_at', 'deadline', 'submitted_at', 'updated_at'],
            'deletion_requests' => ['completed_at', 'confirmed_at', 'created_at', 'scheduled_for'],
            'fulfillments' => ['assigned_at', 'cancelled_at', 'created_at', 'delivered_at', 'failed_at', 'packing_started_at', 'picking_started_at', 'shipped_at', 'updated_at'],
            'i18n_backfill_tracking' => ['completed_at', 'started_at'],
            'loyalty_point_transactions' => ['created_at', 'expires_at'],
            'loyalty_programs' => ['created_at', 'updated_at'],
            'loyalty_tiers' => ['created_at'],
            'media_images' => ['deleted_at', 'uploaded_at'],
            'media_thumbnails' => ['created_at'],
            'notifications' => ['created_at', 'failed_at', 'sent_at', 'updated_at'],
            'orders' => ['created_at', 'updated_at'],
            'password_reset_tokens' => ['created_at', 'expires_at', 'used_at'],
            'payment_transactions' => ['created_at'],
            'payment_webhook_events' => ['processed_at'],
            'payments' => ['authorized_at', 'cancelled_at', 'captured_at', 'created_at', 'updated_at'],
            'price_history' => ['changed_at'],
            'price_lists' => ['created_at', 'updated_at', 'valid_from', 'valid_to'],
            'product_reviews' => ['created_at', 'updated_at'],
            'promotions' => ['created_at', 'updated_at', 'valid_from', 'valid_to'],
            'refresh_tokens' => ['valid'],
            'refunds' => ['created_at', 'processed_at', 'updated_at'],
            'return_requests' => ['created_at', 'updated_at'],
            'stock_items' => ['created_at', 'updated_at'],
            'stock_reservations' => ['expires_at', 'released_at', 'reserved_at'],
            'tenants' => ['created_at'],
            'users' => ['created_at', 'mfa_enabled_at'],
            'warehouses' => ['created_at', 'updated_at'],
            'wishlists' => ['created_at', 'updated_at'],
        ];

        foreach ($tables as $table => $columns) {
            $alterClauses = implode(",\n                ", array_map(
                fn(string $col) => "ALTER COLUMN {$col} TYPE TIMESTAMP WITHOUT TIME ZONE USING {$col} AT TIME ZONE 'UTC'",
                $columns
            ));
            $this->addSql("ALTER TABLE {$table}\n                {$alterClauses}");
        }
    }
}
