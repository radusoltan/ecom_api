#!/bin/bash
# =============================================================================
# Cron Setup for Automated Backups -- E-Commerce Platform
# =============================================================================
# Usage:  ./cron_setup.sh [--install]
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL=false

[[ "${1:-}" == "--install" ]] && INSTALL=true

CRON_ENTRIES="# E-Commerce Platform Backup Schedule
# Daily PostgreSQL backup at 2:00 AM
0 2 * * * ${SCRIPT_DIR}/pg_backup.sh --type daily >> /var/www/ecom_api/var/log/cron-backup.log 2>&1

# Weekly PostgreSQL backup at 2:00 AM on Sundays
0 2 * * 0 ${SCRIPT_DIR}/pg_backup.sh --type weekly >> /var/www/ecom_api/var/log/cron-backup.log 2>&1

# Monthly PostgreSQL backup at 2:00 AM on the 1st
0 2 1 * * ${SCRIPT_DIR}/pg_backup.sh --type monthly >> /var/www/ecom_api/var/log/cron-backup.log 2>&1

# Daily Redis backup at 2:30 AM
30 2 * * * ${SCRIPT_DIR}/redis_backup.sh >> /var/www/ecom_api/var/log/cron-backup.log 2>&1

# Daily data retention cleanup at 3:00 AM
0 3 * * * cd /var/www/ecom_api && php bin/console app:data:cleanup >> /var/www/ecom_api/var/log/cron-backup.log 2>&1
"

echo "=== Backup Cron Schedule ==="
echo ""
echo "$CRON_ENTRIES"

if [[ "$INSTALL" == true ]]; then
    # Preserve existing crontab, remove old ecommerce entries, add new ones
    EXISTING=$(crontab -l 2>/dev/null | grep -v "ecom_api/scripts/backup\|E-Commerce Platform Backup\|app:data:cleanup" || true)
    echo "${EXISTING}
${CRON_ENTRIES}" | crontab -
    echo "[OK] Crontab updated. Verify with: crontab -l"
else
    echo "To install automatically, run: $0 --install"
    echo "Or copy the entries above and run: crontab -e"
fi
