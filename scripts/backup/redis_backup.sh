#!/bin/bash
# =============================================================================
# Redis Backup Script -- E-Commerce Platform
# =============================================================================
# Usage:  ./redis_backup.sh [--dry-run]
# =============================================================================

set -euo pipefail

REDIS_CLI="${REDIS_CLI:-redis-cli}"
BACKUP_DIR="${BACKUP_DIR:-/var/www/ecom_api/var/backups/redis}"
LOG_FILE="${LOG_FILE:-/var/www/ecom_api/var/log/backup.log}"
RETENTION="${RETENTION:-7}"
DRY_RUN=false

[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=true

log() {
    local level="$1"; shift
    local ts; ts=$(date '+%Y-%m-%d %H:%M:%S')
    local line="[${ts}] [${level}] $*"
    echo "$line"
    echo "$line" >> "$LOG_FILE" 2>/dev/null || true
}

mkdir -p "$BACKUP_DIR"

# Check Redis is up
$REDIS_CLI ping > /dev/null 2>&1 || { log "ERROR" "Redis not reachable"; exit 1; }

# Get Redis data directory
REDIS_DIR=$($REDIS_CLI config get dir | tail -1)
REDIS_DBFILE=$($REDIS_CLI config get dbfilename | tail -1)
RDB_PATH="${REDIS_DIR}/${REDIS_DBFILE}"

log "INFO" "Redis backup starting"
log "INFO" "RDB source: ${RDB_PATH}"

if [[ "$DRY_RUN" == true ]]; then
    log "INFO" "[DRY-RUN] Would trigger BGSAVE and copy ${RDB_PATH}"
    exit 0
fi

# Trigger background save
LAST_SAVE=$($REDIS_CLI lastsave)
$REDIS_CLI bgsave > /dev/null 2>&1

# Wait for BGSAVE to complete (max 120s)
WAITED=0
while [[ $($REDIS_CLI lastsave) == "$LAST_SAVE" ]]; do
    sleep 1
    WAITED=$((WAITED + 1))
    if [[ $WAITED -ge 120 ]]; then
        log "ERROR" "BGSAVE timed out after 120s"; exit 1
    fi
done
log "INFO" "BGSAVE completed in ${WAITED}s"

# Copy RDB file
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
DEST="${BACKUP_DIR}/redis_${TIMESTAMP}.rdb"
cp "$RDB_PATH" "$DEST"

# Verify size
SIZE=$(stat -c%s "$DEST" 2>/dev/null || echo 0)
if [[ "$SIZE" -eq 0 ]]; then
    log "ERROR" "Backup file is empty"; rm -f "$DEST"; exit 1
fi
log "INFO" "Backed up: $(basename "$DEST") ($(du -sh "$DEST" | cut -f1))"

# Retention: keep last N
FILES=()
while IFS= read -r -d '' f; do FILES+=("$f"); done < <(
    find "$BACKUP_DIR" -maxdepth 1 -name "redis_*.rdb" -printf '%T@ %p\0' 2>/dev/null \
    | sort -zrn | awk -v RS='\0' -v ORS='\0' '{print $2}')

TOTAL=${#FILES[@]}
for (( i=RETENTION; i<TOTAL; i++ )); do
    log "INFO" "Removing old: $(basename "${FILES[$i]}")"
    rm -f "${FILES[$i]}"
done

log "INFO" "Redis backup completed"
