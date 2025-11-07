# Sprint 6 Production Deployment Checklist

**Sprint**: Sprint 6 - Multi-Tenancy Hardening & Observability
**Status**: Ready for deployment
**Date**: 2025-10-16

---

## Pre-Deployment Checklist

### 1. Code Review ✅
- [x] All Sprint 6 code reviewed and approved
- [x] Security tests passing (TenantIsolationRLSTest.php)
- [x] No merge conflicts
- [x] Code follows project conventions

### 2. Environment Verification
- [ ] Production database accessible
- [ ] RabbitMQ available for Messenger
- [ ] Redis/APCu available for metrics storage
- [ ] Prometheus server configured
- [ ] Grafana server configured

### 3. Configuration Files
- [ ] `.env.prod` configured with correct values
- [ ] `DATABASE_URL` points to production database
- [ ] `MESSENGER_TRANSPORT_DSN` configured
- [ ] `PROM_METRICS_DSN` set to `apcu` or `redis://...`

---

## Deployment Steps

### Phase 1: Database Migration

#### 1.1 Backup Database
```bash
# Create backup before RLS migration
pg_dump -h prod-db-host -U ecom_admin -d ecom > backup_pre_rls_$(date +%Y%m%d_%H%M%S).sql

# Verify backup
ls -lh backup_pre_rls_*.sql
```

**Checklist**:
- [ ] Backup created successfully
- [ ] Backup file size reasonable (not 0 bytes)
- [ ] Backup stored securely (offsite)

#### 1.2 Test Migration in Staging
```bash
# Apply migration in staging first
ssh staging-server
cd /var/www/app
php bin/console doctrine:migrations:migrate --no-interaction

# Verify RLS enabled
psql -h staging-db -U ecom_admin -d ecom -c "
  SELECT tablename, rowsecurity
  FROM pg_tables
  WHERE schemaname = 'public' AND tablename LIKE 'catalog_%'
"
```

**Expected output**:
```
    tablename       | rowsecurity
--------------------+-------------
 catalog_products   | t
 catalog_categories | t
 media_images       | t
 media_thumbnails   | t
```

**Checklist**:
- [ ] Staging migration successful
- [ ] RLS enabled on all 4 tables
- [ ] Staging application still functional
- [ ] No errors in staging logs

#### 1.3 Run Production Migration

**Maintenance window required**: ~5 minutes

```bash
# SSH to production
ssh production-server
cd /var/www/app

# Enable maintenance mode
php bin/console maintenance:enable

# Apply migration
php bin/console doctrine:migrations:migrate --no-interaction

# Verify RLS
psql -h prod-db -U ecom_admin -d ecom -c "
  SELECT tablename, rowsecurity
  FROM pg_tables
  WHERE schemaname = 'public' AND tablename LIKE 'catalog_%'
"

# Disable maintenance mode
php bin/console maintenance:disable
```

**Checklist**:
- [ ] Production migration successful
- [ ] RLS enabled verified
- [ ] Application responds normally
- [ ] No database errors

### Phase 2: Application Deployment

#### 2.1 Deploy Code
```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Clear cache
php bin/console cache:clear --env=prod --no-debug
php bin/console cache:warmup --env=prod --no-debug

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm
```

**Checklist**:
- [ ] Code pulled successfully
- [ ] Dependencies installed
- [ ] Cache cleared and warmed
- [ ] PHP-FPM restarted

#### 2.2 Verify Application
```bash
# Check metrics endpoint
curl https://api.yoursite.com/metrics | head -20

# Should see Prometheus metrics
```

**Checklist**:
- [ ] `/metrics` endpoint responds
- [ ] API endpoints respond normally
- [ ] No errors in application logs

### Phase 3: Observability Stack

#### 3.1 Configure Prometheus

**File**: `/etc/prometheus/prometheus.yml`

Add scrape config:
```yaml
scrape_configs:
  - job_name: 'ecommerce-backend'
    static_configs:
      - targets: ['prod-backend:8000']
    metrics_path: '/metrics'
    scrape_interval: 15s
```

Copy alert rules:
```bash
sudo cp /path/to/backend/config/prometheus/alerts.yml /etc/prometheus/
sudo chown prometheus:prometheus /etc/prometheus/alerts.yml
```

Update prometheus.yml to include alerts:
```yaml
rule_files:
  - 'alerts.yml'
```

Reload Prometheus:
```bash
sudo systemctl reload prometheus
# Or: curl -X POST http://localhost:9090/-/reload
```

**Checklist**:
- [ ] Prometheus configuration updated
- [ ] Alert rules copied
- [ ] Prometheus reloaded successfully
- [ ] Target shows as UP in Prometheus UI

#### 3.2 Verify Prometheus Scraping

1. Open Prometheus UI: https://prometheus.yoursite.com
2. Go to **Status** → **Targets**
3. Check `ecommerce-backend` is **UP** ✅

Run test queries:
```promql
# Should return data
ecom_catalog_product_created_total
rate(ecom_catalog_api_latency_seconds_count[5m])
```

**Checklist**:
- [ ] Prometheus scraping backend successfully
- [ ] Test queries return data
- [ ] No scrape errors in Prometheus logs

#### 3.3 Import Grafana Dashboards

**Option A: API Import** (recommended for automation)
```bash
GRAFANA_URL="https://grafana.yoursite.com"
GRAFANA_API_KEY="your-api-key"

curl -X POST "$GRAFANA_URL/api/dashboards/db" \
  -H "Authorization: Bearer $GRAFANA_API_KEY" \
  -H "Content-Type: application/json" \
  -d @config/grafana/catalog-overview-dashboard.json

curl -X POST "$GRAFANA_URL/api/dashboards/db" \
  -H "Authorization: Bearer $GRAFANA_API_KEY" \
  -H "Content-Type: application/json" \
  -d @config/grafana/search-health-dashboard.json
```

**Option B: Manual Import**
1. Login to Grafana
2. Click + → Import
3. Upload JSON files

**Checklist**:
- [ ] Catalog Overview dashboard imported
- [ ] Search Health dashboard imported
- [ ] Dashboards show data (wait 1-2 min for scraping)
- [ ] Tenant variable works

#### 3.4 Configure Alertmanager

**File**: `/etc/alertmanager/alertmanager.yml`

```yaml
global:
  smtp_smarthost: 'smtp.company.com:587'
  smtp_from: 'alerts@company.com'
  smtp_auth_username: 'alerts@company.com'
  smtp_auth_password: 'your-password'

route:
  group_by: ['alertname', 'tenant']
  group_wait: 30s
  group_interval: 5m
  repeat_interval: 4h
  receiver: 'devops-team'
  routes:
    - match:
        severity: critical
      receiver: 'pagerduty'
    - match:
        severity: warning
      receiver: 'slack'

receivers:
  - name: 'devops-team'
    email_configs:
      - to: 'devops@company.com'

  - name: 'slack'
    slack_configs:
      - api_url: 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL'
        channel: '#alerts-ecommerce'
        title: 'E-Commerce Alert'

  - name: 'pagerduty'
    pagerduty_configs:
      - service_key: 'your-pagerduty-integration-key'
```

Reload Alertmanager:
```bash
sudo systemctl reload alertmanager
```

**Checklist**:
- [ ] Alertmanager configured
- [ ] Email notifications configured
- [ ] Slack integration configured (if applicable)
- [ ] PagerDuty integration configured (if applicable)
- [ ] Test alert sent successfully

#### 3.5 Test Alert Flow

Trigger a test alert:
```bash
# Generate artificial load to trigger HighApiLatency alert
for i in {1..1000}; do
  curl https://api.yoursite.com/api/products?limit=100 \
    -H "X-Tenant-ID: test-tenant-id" &
done
wait
```

**Checklist**:
- [ ] Alert triggered in Prometheus
- [ ] Alert visible in Alertmanager
- [ ] Notification received (email/Slack/PagerDuty)
- [ ] Alert auto-resolves when condition clears

### Phase 4: Verification & Monitoring

#### 4.1 Run Security Tests

```bash
# Run RLS tests in production (read-only, safe)
php bin/console doctrine:fixtures:load --env=test --no-interaction
vendor/bin/phpunit tests/Integration/Security/TenantIsolationRLSTest.php
```

**Expected**: All tests pass ✅

**Checklist**:
- [ ] All 8 RLS tests passing
- [ ] No cross-tenant data access detected
- [ ] RLS policies enforcing isolation

#### 4.2 Monitor for 1 Hour

Watch dashboards and metrics for any anomalies:

1. **Grafana - Catalog Overview**:
   - [ ] Request rate looks normal
   - [ ] Latency within acceptable range (p95 < 500ms)
   - [ ] No error rate spikes
   - [ ] Cache hit ratio stable

2. **Grafana - Search Health**:
   - [ ] Cache hit ratio > 70%
   - [ ] No performance degradation

3. **Prometheus Alerts**:
   - [ ] No critical alerts firing
   - [ ] Warning alerts (if any) are expected

4. **Application Logs**:
   - [ ] No RLS-related errors
   - [ ] No tenant context errors
   - [ ] No Messenger middleware errors

#### 4.3 Smoke Test

Run end-to-end tests:

```bash
# Test product creation (should record metrics)
TENANT_ID="550e8400-e29b-41d4-a716-446655440000"

curl -X POST https://api.yoursite.com/api/products \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "sku": "SMOKE-TEST-001",
    "name": "Smoke Test Product",
    "price": {"amount": 1000, "currency": "USD"}
  }'

# Verify metrics incremented
curl https://prometheus.yoursite.com/api/v1/query?query=ecom_catalog_product_created_total
```

**Checklist**:
- [ ] Product created successfully
- [ ] Metrics counter incremented
- [ ] Latency histogram recorded
- [ ] No errors in logs

---

## Rollback Plan

If issues occur, rollback in reverse order:

### Emergency Rollback

**If critical issue detected**:

1. **Disable RLS** (immediate):
   ```sql
   ALTER TABLE catalog_products DISABLE ROW LEVEL SECURITY;
   ALTER TABLE catalog_categories DISABLE ROW LEVEL SECURITY;
   ALTER TABLE media_images DISABLE ROW LEVEL SECURITY;
   ALTER TABLE media_thumbnails DISABLE ROW LEVEL SECURITY;
   ```

2. **Rollback migration**:
   ```bash
   php bin/console doctrine:migrations:migrate prev --no-interaction
   ```

3. **Restore code** (if needed):
   ```bash
   git checkout previous-version-tag
   composer install --no-dev --optimize-autoloader
   php bin/console cache:clear
   ```

### Rollback Checklist
- [ ] RLS disabled (if needed)
- [ ] Migration rolled back
- [ ] Code reverted to previous version
- [ ] Application functional
- [ ] Incident report created

---

## Post-Deployment Tasks

### Within 24 Hours

1. **Monitor dashboards closely**:
   - [ ] Check every hour for first 4 hours
   - [ ] Check every 4 hours for rest of day

2. **Review logs**:
   - [ ] Look for RLS-related errors
   - [ ] Check for performance issues
   - [ ] Verify tenant isolation working

3. **Test multi-tenant scenarios**:
   - [ ] Create resources for Tenant A
   - [ ] Verify Tenant B cannot access Tenant A's data
   - [ ] Test async operations maintain context

### Within 1 Week

1. **Tune alert thresholds**:
   - [ ] Review alert firing frequency
   - [ ] Adjust thresholds if too noisy
   - [ ] Add new alerts if gaps found

2. **Create runbooks**:
   - [ ] Document response for each alert
   - [ ] Define escalation procedures
   - [ ] Train team on alert handling

3. **Performance baseline**:
   - [ ] Record p50/p95/p99 latencies
   - [ ] Document normal request rates
   - [ ] Establish SLA targets

### Within 1 Month

1. **Apply RLS to additional tables**:
   - [ ] Order tables (orders, order_items)
   - [ ] Inventory tables (stock_items, reservations)
   - [ ] Customer tables (customers, addresses)
   - [ ] Pricing tables (price_lists, price_rules)

2. **Enhance observability**:
   - [ ] Add business metrics
   - [ ] Create tenant-specific dashboards
   - [ ] Implement distributed tracing

3. **Security audit**:
   - [ ] Penetration testing
   - [ ] RLS bypass attempts
   - [ ] Compliance verification

---

## Sign-off

### Deployment Team

- [ ] **DevOps Lead**: _________________ Date: _______
- [ ] **Backend Lead**: _________________ Date: _______
- [ ] **Security Lead**: _________________ Date: _______
- [ ] **Product Owner**: _________________ Date: _______

### Deployment Summary

**Deployment Date**: _____________
**Deployment Duration**: _______ minutes
**Issues Encountered**: _____________________________________________
**Resolution**: ____________________________________________________

---

## Support Contacts

- **DevOps On-Call**: +1-XXX-XXX-XXXX
- **Backend Team**: backend-team@company.com
- **Security Team**: security@company.com
- **Slack Channel**: #incidents-ecommerce

---

## References

- Sprint 6 Completion Report: `docs/sprints/SPRINT_6_COMPLETION_REPORT.md`
- Observability Quick Start: `docs/guides/observability-quickstart.md`
- Dashboard Guide: `config/grafana/README.md`
- Alert Rules: `config/prometheus/alerts.yml`

---

**Deployment Status**: ⏳ **PENDING**

Mark as ✅ **COMPLETE** when all checklist items are checked.
