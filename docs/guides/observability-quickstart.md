# Observability Quick Start Guide

This guide helps you quickly set up and use the observability stack implemented in Sprint 6.

## Prerequisites

- PostgreSQL 16+ (with RLS enabled)
- Prometheus running
- Grafana running
- Symfony application running

## 1. Verify Metrics Endpoint

Check that the metrics endpoint is accessible:

```bash
curl http://localhost:8000/metrics
```

**Expected output**:
```
# HELP ecom_catalog_product_created_total Total number of products created
# TYPE ecom_catalog_product_created_total counter
ecom_catalog_product_created_total{tenant="550e8400-e29b-41d4-a716-446655440000"} 0

# HELP ecom_catalog_api_latency_seconds Catalog API request latency in seconds
# TYPE ecom_catalog_api_latency_seconds histogram
...
```

If you see metrics, the endpoint is working! ✅

## 2. Configure Prometheus

### Option 1: Docker Compose

Add to your `docker-compose.yml`:

```yaml
services:
  prometheus:
    image: prom/prometheus:latest
    ports:
      - "9090:9090"
    volumes:
      - ./config/prometheus:/etc/prometheus
      - prometheus-data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.console.libraries=/usr/share/prometheus/console_libraries'
      - '--web.console.templates=/usr/share/prometheus/consoles'

volumes:
  prometheus-data:
```

Create `config/prometheus/prometheus.yml`:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - 'alerts.yml'

scrape_configs:
  - job_name: 'ecommerce-backend'
    static_configs:
      - targets: ['backend:8000']  # Or host.docker.internal:8000 from Docker
    metrics_path: '/metrics'
```

Start Prometheus:
```bash
docker-compose up -d prometheus
```

### Option 2: Local Installation

Edit `/etc/prometheus/prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'ecommerce-backend'
    static_configs:
      - targets: ['localhost:8000']
    metrics_path: '/metrics'
```

Reload Prometheus:
```bash
# Send SIGHUP
sudo kill -HUP $(pidof prometheus)

# Or use API
curl -X POST http://localhost:9090/-/reload
```

## 3. Verify Prometheus Scraping

1. Open Prometheus UI: http://localhost:9090
2. Go to **Status** → **Targets**
3. Check that `ecommerce-backend` is **UP** ✅

If DOWN, check:
- Backend is running on correct port
- Metrics endpoint is accessible
- No firewall blocking

## 4. Test Metrics in Prometheus

Run some queries in Prometheus UI (http://localhost:9090/graph):

### Query 1: Total Products Created
```promql
ecom_catalog_product_created_total
```

### Query 2: API Request Rate (requests/sec)
```promql
rate(ecom_catalog_api_latency_seconds_count[5m])
```

### Query 3: API Latency p95
```promql
histogram_quantile(0.95, rate(ecom_catalog_api_latency_seconds_bucket[5m]))
```

### Query 4: Cache Hit Ratio
```promql
ecom_catalog_cache_hit_ratio
```

If queries return results, Prometheus is working! ✅

## 5. Import Grafana Dashboards

### Option 1: Manual Import (Easiest)

1. Open Grafana: http://localhost:3000 (default: admin/admin)
2. Click **+** → **Import dashboard**
3. Click **Upload JSON file**
4. Select `backend/config/grafana/catalog-overview-dashboard.json`
5. Click **Import**
6. Repeat for `search-health-dashboard.json`

### Option 2: Provisioning (Automated)

Create `/etc/grafana/provisioning/dashboards/ecommerce.yaml`:

```yaml
apiVersion: 1

providers:
  - name: 'E-Commerce Platform'
    orgId: 1
    folder: 'E-Commerce'
    type: file
    disableDeletion: false
    updateIntervalSeconds: 30
    allowUiUpdates: true
    options:
      path: /path/to/backend/config/grafana
```

Restart Grafana:
```bash
sudo systemctl restart grafana-server
# Or docker-compose restart grafana
```

### Option 3: API Import

```bash
# Set Grafana credentials
GRAFANA_URL="http://localhost:3000"
GRAFANA_USER="admin"
GRAFANA_PASS="admin"

# Import Catalog Overview Dashboard
curl -X POST "$GRAFANA_URL/api/dashboards/db" \
  -u "$GRAFANA_USER:$GRAFANA_PASS" \
  -H "Content-Type: application/json" \
  -d @backend/config/grafana/catalog-overview-dashboard.json

# Import Search Health Dashboard
curl -X POST "$GRAFANA_URL/api/dashboards/db" \
  -u "$GRAFANA_USER:$GRAFANA_PASS" \
  -H "Content-Type: application/json" \
  -d @backend/config/grafana/search-health-dashboard.json
```

## 6. View Dashboards

1. Open Grafana: http://localhost:3000
2. Go to **Dashboards** → **Browse**
3. Find **Catalog Overview** or **Search Health**
4. Click to open

You should see:
- API request rate graphs
- Latency percentiles (p50/p95/p99)
- Product/Category/Image counters
- Cache hit ratio gauge

## 7. Generate Test Traffic

To see metrics in action, generate some API requests:

```bash
# Create a test product
TENANT_ID="550e8400-e29b-41d4-a716-446655440000"

curl -X POST http://localhost:8000/api/products \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: $TENANT_ID" \
  -d '{
    "sku": "TEST-001",
    "name": "Test Product",
    "price": {
      "amount": 1999,
      "currency": "USD"
    }
  }'

# List products (multiple times to generate traffic)
for i in {1..50}; do
  curl -s http://localhost:8000/api/products \
    -H "X-Tenant-ID: $TENANT_ID" > /dev/null
  echo "Request $i completed"
done
```

**Then check**:
1. Prometheus: `rate(ecom_catalog_api_latency_seconds_count[1m])` should show activity
2. Grafana: Refresh dashboard, should see request rate spike

## 8. Configure Alerts

### Load Alert Rules

Copy alerts to Prometheus config directory:

```bash
cp backend/config/prometheus/alerts.yml /etc/prometheus/
```

Reload Prometheus:
```bash
curl -X POST http://localhost:9090/-/reload
```

### Verify Alerts Loaded

1. Open Prometheus: http://localhost:9090/alerts
2. You should see 10 alerts listed
3. Status should be "Inactive" (green) if no issues

### Test an Alert

Force a slow API response to trigger `HighApiLatency` alert:

```bash
# Add artificial delay in code temporarily
# Or run heavy queries repeatedly

for i in {1..100}; do
  curl http://localhost:8000/api/products?limit=1000 \
    -H "X-Tenant-ID: $TENANT_ID"
done
```

After 5 minutes of slow responses, alert should fire.

## 9. Alertmanager (Optional)

To receive alert notifications, configure Alertmanager:

### Docker Compose

```yaml
services:
  alertmanager:
    image: prom/alertmanager:latest
    ports:
      - "9093:9093"
    volumes:
      - ./config/alertmanager:/etc/alertmanager
    command:
      - '--config.file=/etc/alertmanager/alertmanager.yml'
```

Create `config/alertmanager/alertmanager.yml`:

```yaml
global:
  smtp_smarthost: 'smtp.gmail.com:587'
  smtp_from: 'alerts@yourcompany.com'
  smtp_auth_username: 'your-email@gmail.com'
  smtp_auth_password: 'your-app-password'

route:
  group_by: ['alertname', 'cluster', 'service']
  group_wait: 10s
  group_interval: 10s
  repeat_interval: 12h
  receiver: 'email'

receivers:
  - name: 'email'
    email_configs:
      - to: 'devops@yourcompany.com'
```

Update Prometheus config:

```yaml
alerting:
  alertmanagers:
    - static_configs:
        - targets: ['alertmanager:9093']
```

## 10. Structured Logging (Optional)

### Install Monolog Bundle

```bash
composer require symfony/monolog-bundle
```

### Create Monolog Config

File already prepared at `config/packages/monolog.yaml`.

### Enable JSON Logging

Set environment to production or create `config/packages/prod/monolog.yaml`:

```yaml
monolog:
    handlers:
        main:
            type: stream
            path: php://stderr
            level: debug
            formatter: monolog.formatter.json
```

### View Structured Logs

```bash
# Run in production mode
APP_ENV=prod symfony console cache:clear

# Tail logs
tail -f var/log/prod.log | jq
```

Each log entry will include tenant_id, request_id, etc.

## Troubleshooting

### Metrics endpoint returns 404

**Check routes**:
```bash
symfony console debug:router | grep metrics
```

**Should show**:
```
prometheus_metrics    /metrics
```

If missing, check Prometheus bundle is installed:
```bash
composer show artprima/prometheus-metrics-bundle
```

### Prometheus shows target as DOWN

**Check backend is running**:
```bash
curl http://localhost:8000/metrics
```

**Check Prometheus can reach backend**:
```bash
# From Prometheus container
docker exec -it prometheus wget -O- http://backend:8000/metrics
```

**Check firewall** (if running on separate hosts)

### No data in Grafana dashboards

**Check Prometheus data source**:
1. Grafana → Configuration → Data Sources
2. Should have "Prometheus" data source
3. URL should be: http://prometheus:9090 (or http://localhost:9090)
4. Click "Save & Test" → should show "Data source is working"

**Check Prometheus has data**:
```promql
ecom_catalog_product_created_total
```

If no results in Prometheus, metrics aren't being recorded.

### Alerts not firing

**Check alert rules loaded**:
```bash
curl http://localhost:9090/api/v1/rules
```

**Check alert query manually**:

Copy the `expr` from alert rule and test in Prometheus UI.

**Check alert for condition**:

Alert won't fire unless condition is true for the `for` duration.

## Next Steps

Now that observability is set up:

1. **Monitor dashboards daily** - Look for anomalies
2. **Review alerts weekly** - Tune thresholds as needed
3. **Add custom metrics** - As new features are added
4. **Create runbooks** - Document how to respond to each alert
5. **Set up on-call rotation** - Ensure alerts are acted upon

## Resources

- **Prometheus Docs**: https://prometheus.io/docs/
- **Grafana Docs**: https://grafana.com/docs/
- **PromQL Tutorial**: https://prometheus.io/docs/prometheus/latest/querying/basics/
- **Sprint 6 Report**: `docs/sprints/SPRINT_6_COMPLETION_REPORT.md`
- **Dashboard Guide**: `config/grafana/README.md`

## Support

For issues with observability stack:

1. Check troubleshooting section above
2. Review Sprint 6 completion report
3. Check Prometheus and Grafana logs
4. Verify backend application is running correctly

---

**Quick Start Complete!** 🎉

You now have a production-ready observability stack monitoring your e-commerce platform.
