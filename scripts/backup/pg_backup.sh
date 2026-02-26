#!/bin/bash
# =============================================================================
# PostgreSQL Backup Script -- E-Commerce Platform
# =============================================================================
# Usage:  ./pg_backup.sh [--type daily|weekly|monthly] [--dry-run]
# Exit:   0=success, 1=backup failed, 2=verify failed, 3=config error
# =============================================================================

set -euo pipefail

PG_HOST="${PG_HOST:-localhost}"
PG_PORT="${PG_PORT:-5432}"
PG_DB="${PG_DB:-ecommerce}"
PG_USER="${PG_USER:-ecommerce}"
export PGPASSWORD="${PG_PASS:-ecom_secret_2026}"

BACKUP_BASE_DIR="${BACKUP_DIR:-/var/www/ecom_api/var/backups/postgresql}"
LOG_FILE="${LOG_FILE:-/var/www/ecom_api/var/log/backup.log}"

RETENTION_DAILY="${RETENTION_DAILY:-7}"
RETENTION_WEEKLY="${RETENTION_WEEKLY:-4}"
RETENTION_MONTHLY="${RETENTION_MONTHLY:-3}"

BACKUP_TYPE="daily"
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --type)    BACKUP_TYPE="$2"; shift 2 ;;
        --dry-run) DRY_RUN=true; shift ;;
        *) echo "Unknown option: $1" >&2; exit 3 ;;
    esac
done

if [[ ! "$BACKUP_TYPE" =~ ^(daily|weekly|monthly)$ ]]; then
    echo "Invalid backup type '${BACKUP_TYPE}'." >&2; exit 3
fi

log() {
    local level="$1"; shift
    local ts; ts=$(date '+%Y-%m-%d %H:%M:%S')
    local line="[${ts}] [${level}] $*"
    echo "$line"
    echo "$line" >> "$LOG_FILE"
}

check_prerequisites() {
    for cmd in pg_dump pg_restore pg_isready; do
        command -v "$cmd" &>/dev/null || { log "ERROR" "Missing: $cmd"; exit 3; }
    done
    mkdir -p "${BACKUP_BASE_DIR}/daily" "${BACKUP_BASE_DIR}/weekly" "${BACKUP_BASE_DIR}/monthly"
    mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null || true
    touch "$LOG_FILE" 2>/dev/null || { echo "Cannot write log: $LOG_FILE" >&2; exit 3; }
    pg_isready -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DB" -q 2>/dev/null || {
        log "ERROR" "PostgreSQL not reachable at ${PG_HOST}:${PG_PORT}"; exit 3
    }
}

run_backup() {
    local type="$1"
    local ts; ts=$(date '+%Y%m%d_%H%M%S')
    local filename="${PG_DB}_${type}_${ts}.dump"
    local backup_path="${BACKUP_BASE_DIR}/${type}/${filename}"

    log "INFO" "Starting ${type} backup: ${filename}"

    if [[ "$DRY_RUN" == true ]]; then
        log "INFO" "[DRY-RUN] Would create: ${backup_path}"
        echo "DRY_RUN"; return 0
    fi

    local start; start=$(date +%s)
    pg_dump -h "$PG_HOST" -p "$PG_PORT" -U "$PG_USER" -d "$PG_DB" \
        --format=custom --compress=9 --no-password --file="$backup_path" 2>>"$LOG_FILE" || {
        log "ERROR" "pg_dump failed"; rm -f "$backup_path"; return 1
    }
    local elapsed=$(( $(date +%s) - start ))
    local size; size=$(du -sh "$backup_path" 2>/dev/null | cut -f1)
    log "INFO" "Backup completed in ${elapsed}s, size: ${size}"
    echo "$backup_path"
}

verify_backup() {
    local path="$1"
    log "INFO" "Verifying: $(basename "$path")"
    [[ -f "$path" ]] || { log "ERROR" "File not found: $path"; return 2; }
    local sz; sz=$(stat -c%s "$path" 2>/dev/null || echo 0)
    [[ "$sz" -gt 0 ]] || { log "ERROR" "File empty: $path"; return 2; }
    local toc
    toc=$(pg_restore --list "$path" 2>&1) || { log "ERROR" "TOC read failed: $path"; return 2; }
    local cnt; cnt=$(echo "$toc" | grep -c '^[0-9]' || true)
    log "INFO" "Verified OK -- ${cnt} objects, size: $(du -sh "$path" | cut -f1)"
}

apply_retention() {
    local type="$1" keep="$2" dir="${BACKUP_BASE_DIR}/${type}"
    log "INFO" "Retention ${type}: keep last ${keep}"
    local files=()
    while IFS= read -r -d '' f; do files+=("$f"); done < <(
        find "$dir" -maxdepth 1 -name "${PG_DB}_${type}_*.dump" -printf '%T@ %p\0' 2>/dev/null \
        | sort -zrn | awk -v RS='\0' -v ORS='\0' '{print $2}')
    local total=${#files[@]} removed=0
    for (( i=keep; i<total; i++ )); do
        if [[ "$DRY_RUN" == false ]]; then
            log "INFO" "Removing: $(basename "${files[$i]}")"; rm -f "${files[$i]}"; removed=$((removed+1))
        else
            log "INFO" "[DRY-RUN] Would remove: ${files[$i]}"
        fi
    done
    log "INFO" "Retained: $((total-removed)), removed: ${removed}"
}

main() {
    log "INFO" "========================================================"
    log "INFO" "PostgreSQL Backup -- type=${BACKUP_TYPE} dry_run=${DRY_RUN} db=${PG_DB}@${PG_HOST}:${PG_PORT}"
    check_prerequisites

    local backup_file
    backup_file=$(run_backup "$BACKUP_TYPE") || { log "ERROR" "Backup failed"; exit 1; }

    if [[ "$DRY_RUN" == false ]]; then
        verify_backup "$backup_file" || { log "ERROR" "Verify failed"; exit 2; }
    fi

    case "$BACKUP_TYPE" in
        daily)   apply_retention daily   "$RETENTION_DAILY" ;;
        weekly)  apply_retention weekly  "$RETENTION_WEEKLY" ;;
        monthly) apply_retention monthly "$RETENTION_MONTHLY" ;;
    esac

    log "INFO" "Backup completed successfully"
    log "INFO" "========================================================"
    exit 0
}

main "$@"
