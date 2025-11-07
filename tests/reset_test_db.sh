#!/bin/bash

# Test Database Reset Script
# Drops, recreates, and sets up the test database with migrations and default tenant
# Usage: ./tests/reset_test_db.sh

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}========================================${NC}"
echo -e "${YELLOW}Test Database Reset Script${NC}"
echo -e "${YELLOW}========================================${NC}"
echo ""

# Load environment variables
export APP_ENV=test
source .env 2>/dev/null || true
source .env.test 2>/dev/null || true

# Database credentials (from .env)
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_USER="${DB_USER:-ecom_admin}"
DB_PASSWORD="${DB_PASSWORD:-sr324395}"
DB_NAME="${DB_NAME:-ecom_test}"

# Default test tenant UUID (from tests/bootstrap.php)
DEFAULT_TENANT_ID="00000000-0000-4000-8000-000000000001"

echo -e "${GREEN}[1/6] Database Configuration${NC}"
echo "  Host: $DB_HOST:$DB_PORT"
echo "  User: $DB_USER"
echo "  Database: $DB_NAME"
echo ""

# Step 1: Drop test database if exists
echo -e "${GREEN}[2/6] Dropping test database (if exists)${NC}"
PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d postgres -c "DROP DATABASE IF EXISTS $DB_NAME;" 2>&1 | grep -v "NOTICE" || true
echo "  ✓ Database dropped"
echo ""

# Step 2: Create test database
echo -e "${GREEN}[3/6] Creating test database${NC}"
PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d postgres -c "CREATE DATABASE $DB_NAME OWNER $DB_USER;" 2>&1 | grep -v "NOTICE"
echo "  ✓ Database created"
echo ""

# Step 3: Run migrations
echo -e "${GREEN}[4/6] Running Doctrine migrations${NC}"
symfony console doctrine:migrations:migrate -n --allow-no-migration --env=test 2>&1 | grep -E "\[notice\]|\[OK\]|Successfully migrated"
echo "  ✓ Migrations completed"
echo ""

# Step 4: Verify migrations executed
echo -e "${GREEN}[5/6] Verifying migrations${NC}"
MIGRATION_COUNT=$(symfony console doctrine:migrations:status --env=test 2>/dev/null | grep "Executed" | grep -v "Unavailable" | awk '{print $4}')
echo "  ✓ Executed migrations: $MIGRATION_COUNT"
echo ""

# Step 5: Create default test tenant
echo -e "${GREEN}[6/6] Creating default test tenant${NC}"
PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME <<SQL
-- Set tenant context for RLS
SET app.tenant_id = '$DEFAULT_TENANT_ID';

-- Insert default test tenant (idempotent)
INSERT INTO tenants (id, name, owner_email, status, created_at, slug, default_locale, enabled_locales, translation_quota, translation_usage)
VALUES ('$DEFAULT_TENANT_ID', 'Test Tenant', 'test@example.com', 'active', NOW(), 'test-tenant', 'en', '["en"]', 10000, 0)
ON CONFLICT (id) DO NOTHING;

-- Verify tenant created
SELECT id, name, status FROM tenants WHERE id = '$DEFAULT_TENANT_ID';
SQL

if [ $? -eq 0 ]; then
    echo "  ✓ Default test tenant created (ID: $DEFAULT_TENANT_ID)"
else
    echo -e "  ${RED}✗ Failed to create default test tenant${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Test database reset completed!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "You can now run tests with:"
echo "  vendor/bin/phpunit"
echo ""
