#!/bin/bash
set -e
echo "Resetting test database..."
cd /var/www/new_ecom/backend
APP_ENV=test php bin/console doctrine:database:drop --force --if-exists
sleep 1
APP_ENV=test php bin/console doctrine:database:create
sleep 1
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction
echo "Creating default test tenant..."

# Create tenant in a transaction with RLS context
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom_test << 'PGSQL'
BEGIN;
SET LOCAL app.tenant_id = '00000000-0000-4000-8000-000000000001';
INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales)
VALUES ('00000000-0000-4000-8000-000000000001', 'Test Tenant',
        'test@example.com', 'active', NOW(), 'test-tenant', 'en', '["en"]')
ON CONFLICT (id) DO NOTHING;
COMMIT;
PGSQL

echo "Verifying tenant creation..."
PGPASSWORD=sr324395 psql -h 127.0.0.1 -U ecom_admin -d ecom_test << 'PGSQL'
BEGIN;
SET LOCAL app.tenant_id = '00000000-0000-4000-8000-000000000001';
SELECT id, name, owner_email, status FROM tenants;
COMMIT;
PGSQL

echo ""
echo "Test database reset complete!"
echo "Default test tenant ID: 00000000-0000-4000-8000-000000000001"
