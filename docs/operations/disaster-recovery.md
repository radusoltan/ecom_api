# Disaster Recovery Plan

## Overview

This document defines recovery procedures for the e-commerce platform components.
Recovery targets are sourced from PRD §6.3 and Appendix G.

## Recovery Targets

| Component   | RPO (Data Loss) | RTO (Downtime) |
|-------------|-----------------|----------------|
| PostgreSQL  | 15 minutes      | 2 hours        |
| Redis       | 15 minutes      | 15 minutes     |
| Application | 0 (code in Git) | 30 minutes     |

## Backup Schedule

| Backup Type               | Frequency                             | Retention     |
|---------------------------|---------------------------------------|---------------|
| PostgreSQL WAL archiving  | Continuous (streaming, ~15 min lag)   | 7 days        |
| PostgreSQL nightly        | Daily 2:00 AM (base snapshot)         | 7 days        |
| PostgreSQL weekly         | Sunday 2:00 AM                        | 4 weeks       |
| PostgreSQL monthly        | 1st of month                          | 3 months      |
| Redis RDB                 | Every 15 minutes                      | 7 days        |
| Data retention cleanup    | Daily 3:00 AM                         | N/A (cleanup) |

**PostgreSQL RPO of 15 minutes** is achieved through continuous WAL archiving. Daily snapshots
alone would yield up to 24h of data loss. WAL archiving must be configured and verified before
production deployment (see `scripts/backup/wal_archive_setup.sh`).

## PostgreSQL Recovery

### Full Restore from Backup

```bash
# 1. List available backups
ls -lht /var/www/ecom_api/var/backups/postgresql/daily/

# 2. Verify backup integrity
pg_restore --list <backup_file>

# 3. Restore (preserves existing DB)
./scripts/backup/pg_restore.sh <backup_file>

# 4. Restore with drop+recreate (DESTRUCTIVE)
./scripts/backup/pg_restore.sh <backup_file> --drop-create

# 5. Restore to different database (for testing)
./scripts/backup/pg_restore.sh <backup_file> --target-db ecom_restore_test
```

### Point-in-Time Recovery (PITR)

Requires WAL archiving to be configured. See `scripts/backup/wal_archive_setup.sh`.

```bash
# 1. Restore latest base backup
./scripts/backup/pg_restore.sh <latest_backup>

# 2. Configure recovery target
echo "recovery_target_time = '2026-02-26 14:30:00'" >> /etc/postgresql/17/main/postgresql.conf

# 2b. Point restore_command at the WAL archive directory
echo "restore_command = 'cp /var/www/ecom_api/var/backups/postgresql/wal/%f %p'" \
    >> /etc/postgresql/17/main/postgresql.conf

# 3. Create recovery signal
touch /var/lib/postgresql/17/main/recovery.signal

# 4. Restart PostgreSQL
sudo pg_ctlcluster 17 main restart

# 5. PostgreSQL replays WAL to target time, then pauses
# 6. Verify data, then:
psql -c "SELECT pg_wal_replay_resume();"
```

## Redis Recovery

```bash
# 1. Stop Redis
sudo systemctl stop redis

# 2. Copy backup to Redis data directory
cp /var/www/ecom_api/var/backups/redis/redis_YYYYMMDD_HHMMSS.rdb /var/lib/redis/dump.rdb

# 3. Set ownership
sudo chown redis:redis /var/lib/redis/dump.rdb

# 4. Start Redis
sudo systemctl start redis

# 5. Verify
redis-cli ping
redis-cli dbsize
```

Note: Redis is used for caching. Full data loss is recoverable -- cache rebuilds automatically from PostgreSQL on cache miss.

## Application Recovery

```bash
# 1. Pull latest code
cd /var/www/ecom_api && git pull

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# 4. Clear caches
php bin/console cache:clear --env=prod
redis-cli FLUSHDB

# 5. Frontend rebuild
cd /var/www/ecom_storefront && pnpm install && pnpm build
cd /var/www/ecom_admin && pnpm install && pnpm build
```

## Multi-Tenant Considerations

- RLS policies are included in pg_dump backups
- After restore, verify RLS with: `SELECT * FROM pg_policies;`
- FORCE RLS is re-applied by `pg_restore.sh` automatically
- Test tenant isolation after restore:
  ```sql
  SET app.tenant_id = '00000000-0000-4000-8000-000000000001';
  SELECT count(*) FROM orders; -- Should only show tenant's orders
  ```

## Testing Schedule

| Test Type                | Frequency  | Responsible     |
|--------------------------|------------|-----------------|
| WAL archive verification | Daily      | Automated       |
| Backup verification      | Daily      | Automated       |
| Restore to test DB     | Monthly    | DevOps          |
| Full DR simulation     | Quarterly  | DevOps + Dev    |
| PITR test              | Quarterly  | DevOps          |

## Contacts

| Role               | Name           | Contact          |
|--------------------|----------------|------------------|
| DevOps Lead        | [TBD]          | [TBD]            |
| Database Admin     | [TBD]          | [TBD]            |
| Application Lead   | [TBD]          | [TBD]            |
| Incident Manager   | [TBD]          | [TBD]            |

## Runbook Checklist

- [ ] Identify scope of failure (DB, Redis, app, infrastructure)
- [ ] Notify stakeholders
- [ ] Determine RPO -- what is the latest successfully archived WAL segment? (target: within 15 min of failure)
- [ ] Confirm total downtime is within 2h RTO; escalate immediately if approaching limit
- [ ] Execute appropriate recovery procedure
- [ ] Verify data integrity and tenant isolation
- [ ] Monitor application logs for errors
- [ ] Document incident and update procedures if needed
