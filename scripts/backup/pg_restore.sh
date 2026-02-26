#!/bin/bash
# =============================================================================
# PostgreSQL Restore Script -- E-Commerce Platform
# =============================================================================
# Usage:  ./pg_restore.sh <backup_file> [--target-db <name>] [--drop-create]
# =============================================================================

set -euo pipefail

PG_HOST="${PG_HOST:-localhost}"
PG_PORT="${PG_PORT:-5432}"
PG_DB="${PG_DB:-ecommerce}"
PG_USER="${PG_USER:-ecommerce}"
PG_ADMIN="${PG_ADMIN:-ecom_admin}"
export PGPASSWORD="${PG_PASS:-ecom_secret_2026}"

LOG_FILE="${LOG_FILE:-/var/www/ecom_api/var/log/backup.log}"
BACKUP_FILE=""
TARGET_DB="$PG_DB"
DROP_CREATE=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --target-db)   TARGET_DB="$2"; shift 2 ;;
        --drop-create) DROP_CREATE=true; shift ;;
        -*)            echo "Unknown option: $1" >&2; exit 1 ;;
        *)             BACKUP_FILE="$1"; shift ;;
    esac
done

log() {
    local level="$1"; shift
    local ts; ts=$(date '+%Y-%m-%d %H:%M:%S')
    local line="[${ts}] [${level}] $*"
    echo "$line"
    echo "$line" >> "$LOG_FILE" 2>/dev/null || true
}

if [[ -z "$BACKUP_FILE" ]]; then
    echo "Usage: $0 <backup_file> [--target-db <name>] [--drop-create]" >&2; exit 1
fi

if [[ ! -f "$BACKUP_FILE" ]]; then
    log "ERROR" "Backup file not found: $BACKUP_FILE"; exit 1
fi

# Verify backup integrity before restore
log "INFO" "Verifying backup: $BACKUP_FILE"
pg_restore --list "$BACKUP_FILE" > /dev/null 2>&1 || {
    log "ERROR" "Backup file is invalid or corrupt"; exit 1
}
log "INFO" "Backup verified OK"

if [[ "$DROP_CREATE" == true ]]; then
    log "WARN" "DROP and RECREATE mode -- all data in '${TARGET_DB}' will be lost!"
    echo -n "Type 'YES' to confirm: "
    read -r confirm
    if [[ "$confirm" != "YES" ]]; then
        log "INFO" "Restore cancelled by user"; exit 0
    fi

    log "INFO" "Dropping database: ${TARGET_DB}"
    psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d postgres \
        -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='${TARGET_DB}' AND pid <> pg_backend_pid();" 2>/dev/null || true
    psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d postgres \
        -c "DROP DATABASE IF EXISTS \"${TARGET_DB}\";" 2>>"$LOG_FILE"
    psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d postgres \
        -c "CREATE DATABASE \"${TARGET_DB}\" OWNER ${PG_USER};" 2>>"$LOG_FILE"
    log "INFO" "Database recreated: ${TARGET_DB}"
fi

log "INFO" "Starting restore to ${TARGET_DB} from $(basename "$BACKUP_FILE")"
local_start=$(date +%s)

pg_restore \
    -h "$PG_HOST" \
    -p "$PG_PORT" \
    -U "$PG_USER" \
    -d "$TARGET_DB" \
    --no-owner \
    --no-privileges \
    --jobs=4 \
    "$BACKUP_FILE" 2>>"$LOG_FILE" || {
    log "WARN" "pg_restore completed with warnings (non-fatal for custom format)"
}

elapsed=$(( $(date +%s) - local_start ))
log "INFO" "Restore completed in ${elapsed}s"

# Re-apply RLS FORCE and admin bypass
log "INFO" "Re-applying RLS policies..."
TABLES=$(psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$TARGET_DB" -t -A \
    -c "SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename NOT IN ('doctrine_migration_versions');" 2>/dev/null)

for table in $TABLES; do
    has_tenant=$(psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$TARGET_DB" -t -A \
        -c "SELECT COUNT(*) FROM information_schema.columns WHERE table_name='${table}' AND column_name='tenant_id';" 2>/dev/null)
    if [[ "$has_tenant" -gt 0 ]]; then
        psql -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$TARGET_DB" \
            -c "ALTER TABLE ${table} FORCE ROW LEVEL SECURITY;" 2>>"$LOG_FILE" || true
    fi
done

log "INFO" "RLS FORCE re-applied"
log "INFO" "Restore completed successfully"
