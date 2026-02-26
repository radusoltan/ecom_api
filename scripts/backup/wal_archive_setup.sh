#!/bin/bash
# =============================================================================
# WAL Archiving Setup -- E-Commerce Platform
# =============================================================================
# This script checks and configures WAL archiving for point-in-time recovery.
# Run once during initial setup or after PostgreSQL major upgrades.
# =============================================================================

set -euo pipefail

WAL_DIR="${WAL_DIR:-/var/www/ecom_api/var/backups/wal}"
PG_HOST="${PG_HOST:-localhost}"
PG_PORT="${PG_PORT:-5432}"
PG_USER="${PG_USER:-ecommerce}"
export PGPASSWORD="${PG_PASS:-ecom_secret_2026}"

echo "=== WAL Archiving Setup ==="
echo ""

# Create WAL archive directory
mkdir -p "$WAL_DIR"
echo "[OK] WAL archive directory: $WAL_DIR"

# Check current settings
echo ""
echo "--- Current PostgreSQL Settings ---"
psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d ecommerce -t -A \
    -c "SELECT name || ' = ' || setting FROM pg_settings WHERE name IN ('archive_mode', 'archive_command', 'wal_level', 'max_wal_senders') ORDER BY name;" 2>/dev/null || {
    echo "WARNING: Could not query PostgreSQL settings"
}

echo ""
echo "=== Required postgresql.conf Changes ==="
echo ""
echo "Add or update these lines in your postgresql.conf:"
echo ""
echo "  wal_level = replica"
echo "  archive_mode = on"
echo "  archive_command = 'cp %p ${WAL_DIR}/%f'"
echo "  max_wal_senders = 3"
echo ""
echo "After editing, restart PostgreSQL:"
echo "  sudo systemctl restart postgresql"
echo "  # or on WSL2: sudo pg_ctlcluster 17 main restart"
echo ""
echo "=== Point-in-Time Recovery ==="
echo ""
echo "To restore to a specific point in time:"
echo "  1. Restore the latest base backup: ./pg_restore.sh <backup_file>"
echo "  2. Copy WAL files to pg_wal directory"
echo "  3. Create recovery.signal in PostgreSQL data directory"
echo "  4. Set recovery_target_time in postgresql.conf"
echo "  5. Start PostgreSQL -- it will replay WAL up to the target time"
echo ""
echo "See docs/operations/disaster-recovery.md for full procedures."
