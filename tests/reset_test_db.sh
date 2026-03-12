#!/bin/bash
set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_USER="${DB_USER:-ecommerce}"
DB_PASSWORD="${DB_PASSWORD:-ecom_secret_2026}"
TEST_DB_NAME="${TEST_DB_NAME:-ecom_test}"
SOURCE_DB_NAME="${SOURCE_DB_NAME:-ecommerce}"

echo "Resetting test database..."
cd "$PROJECT_ROOT"
APP_ENV=test php bin/console doctrine:database:drop --force --if-exists
sleep 1
APP_ENV=test php bin/console doctrine:database:create
sleep 1

if ! APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction; then
    echo "Migration bootstrap failed, cloning schema from ${SOURCE_DB_NAME}..."
    APP_ENV=test php bin/console doctrine:database:drop --force --if-exists
    sleep 1
    APP_ENV=test php bin/console doctrine:database:create
    sleep 1
    PGPASSWORD="$DB_PASSWORD" pg_dump \
        --schema-only \
        --no-owner \
        --no-privileges \
        -h "$DB_HOST" \
        -U "$DB_USER" \
        "$SOURCE_DB_NAME" \
        | PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$TEST_DB_NAME"
fi

echo "Creating default test tenant..."

# Create tenant in a transaction with RLS context
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$TEST_DB_NAME" << 'PGSQL'
BEGIN;
SET LOCAL app.tenant_id = '00000000-0000-4000-8000-000000000001';
INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales)
VALUES ('00000000-0000-4000-8000-000000000001', 'Test Tenant',
        'test@example.com', 'active', NOW(), 'test-tenant', 'en', '["en"]')
ON CONFLICT (id) DO NOTHING;
COMMIT;
PGSQL

echo "Verifying tenant creation..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -U "$DB_USER" -d "$TEST_DB_NAME" << 'PGSQL'
BEGIN;
SET LOCAL app.tenant_id = '00000000-0000-4000-8000-000000000001';
SELECT id, name, owner_email, status FROM tenants;
COMMIT;
PGSQL

echo ""
echo "Test database reset complete!"
echo "Default test tenant ID: 00000000-0000-4000-8000-000000000001"
