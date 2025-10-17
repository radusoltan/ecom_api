# Grafana Dashboards Configuration

This directory contains Grafana dashboard configurations for the E-Commerce Platform.

## Available Dashboards

### 1. Catalog Overview Dashboard
**File**: `catalog-overview-dashboard.json`

Provides comprehensive visibility into catalog operations:
- **API Request Rate**: Real-time request rates per tenant and route
- **API Latency**: p50/p95/p99 latency percentiles
- **Products Created**: Total products created per tenant
- **Categories Created**: Total categories created per tenant
- **Images Uploaded**: Total images uploaded per tenant
- **Thumbnail Generation Time**: p50/p95/p99 thumbnail generation latency
- **Cache Hit Ratio**: Current cache performance gauge

**Alerts**:
- Slow thumbnail generation (>5s p99)

### 2. Search Health Dashboard
**File**: `search-health-dashboard.json`

Focuses on cache performance and search readiness:
- **Cache Hit Ratio Over Time**: Historical cache performance
- **Current Cache Hit Ratio**: Real-time cache efficiency per tenant
- **Cache Performance by Tenant**: Table view of top/bottom performers
- **Product API Request Rate**: Product endpoint usage
- **Category API Request Rate**: Category endpoint usage

**Alerts**:
- Low cache hit ratio (<70%)

**Future**: Will include Elasticsearch metrics when search is implemented.

## Installation

### Option 1: Manual Import

1. Open Grafana UI (default: http://localhost:3000)
2. Login (default credentials: admin/admin)
3. Navigate to **Dashboards** → **Import**
4. Copy-paste the JSON content from each file
5. Click **Load** → **Import**

### Option 2: Provisioning (Automated)

Add to your Grafana provisioning configuration:

```yaml
# /etc/grafana/provisioning/dashboards/ecommerce.yaml
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

Then restart Grafana:
```bash
systemctl restart grafana-server
```

### Option 3: Docker Compose

If using Docker, mount this directory as a volume:

```yaml
# docker-compose.yml
services:
  grafana:
    image: grafana/grafana:latest
    volumes:
      - ./backend/config/grafana:/etc/grafana/provisioning/dashboards:ro
      - ./backend/config/prometheus:/etc/prometheus:ro
```

## Prometheus Configuration

Ensure Prometheus is configured to scrape the application metrics:

```yaml
# prometheus.yml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

rule_files:
  - '/etc/prometheus/alerts.yml'

scrape_configs:
  - job_name: 'ecommerce-backend'
    static_configs:
      - targets: ['backend:8000']  # Or your actual backend host
    metrics_path: '/metrics'
```

## Available Metrics

### Counters
- `ecom_catalog_image_upload_total{tenant}`: Total image uploads
- `ecom_catalog_product_created_total{tenant}`: Total products created
- `ecom_catalog_category_created_total{tenant}`: Total categories created

### Histograms
- `ecom_catalog_api_latency_seconds{tenant,route}`: API request latency
- `ecom_thumbnail_generation_seconds{tenant}`: Thumbnail generation time

### Gauges
- `ecom_catalog_cache_hit_ratio{tenant}`: Cache hit ratio (0.0 to 1.0)

## Alert Rules

Alert rules are defined in `../prometheus/alerts.yml`:

| Alert | Severity | Threshold | Description |
|-------|----------|-----------|-------------|
| HighErrorRate | Critical | >5% | Error rate exceeds threshold |
| LowCacheHit | Warning | <70% | Cache hit ratio too low |
| SlowThumbnails | Warning | >5s (p95) | Thumbnail generation slow |
| HighApiLatency | Warning | >500ms (p95) | API response slow |
| RLSMismatch | Critical | N/A | Potential security issue |
| CatalogServiceDown | Critical | 2min | Service not responding |
| ExcessiveProductCreation | Warning | >100/5m | Potential abuse |
| ImageUploadSpike | Warning | 5x baseline | Anomaly detection |
| LowDiskSpace | Warning | <15% | Storage capacity issue |
| DatabasePoolExhaustion | Critical | >80% | Connection pool full |

## Customization

### Adding New Panels

1. Open the dashboard in Grafana UI
2. Click **Add panel**
3. Configure query, visualization, and options
4. Click **Save dashboard**
5. Export JSON via **Share** → **Export** → **Save to file**
6. Replace the JSON file in this directory

### Modifying Alerts

Edit the `../prometheus/alerts.yml` file and reload Prometheus:

```bash
# Send SIGHUP to reload configuration
kill -HUP $(pidof prometheus)

# Or use Prometheus API
curl -X POST http://localhost:9090/-/reload
```

## Troubleshooting

### No data in dashboards

1. Check Prometheus is scraping the backend:
   ```bash
   curl http://localhost:9090/api/v1/targets
   ```

2. Verify metrics endpoint is working:
   ```bash
   curl http://backend:8000/metrics
   ```

3. Check for metrics in Prometheus:
   ```bash
   curl 'http://localhost:9090/api/v1/query?query=ecom_catalog_product_created_total'
   ```

### Dashboards not loading

1. Check Grafana logs:
   ```bash
   tail -f /var/log/grafana/grafana.log
   ```

2. Verify JSON syntax:
   ```bash
   jq . catalog-overview-dashboard.json
   ```

3. Check Grafana data source configuration (should point to Prometheus)

### Alerts not firing

1. Verify alert rules are loaded in Prometheus:
   ```bash
   curl http://localhost:9090/api/v1/rules
   ```

2. Check Alertmanager is configured:
   ```bash
   curl http://localhost:9093/api/v1/status
   ```

3. Test alert query manually in Prometheus UI

## Best Practices

1. **Use templating variables** for tenant filtering
2. **Set appropriate time ranges** (default: last 6 hours)
3. **Configure alert notifications** (Slack, email, PagerDuty)
4. **Regular dashboard reviews** to ensure relevance
5. **Document custom queries** with comments
6. **Version control dashboards** (this repository)
7. **Test alerts** before deploying to production

## Related Documentation

- [Sprint 6 Completion Report](../../docs/sprints/SPRINT_6_COMPLETION_REPORT.md)
- [Prometheus Official Docs](https://prometheus.io/docs/)
- [Grafana Documentation](https://grafana.com/docs/)
- [Alert Rule Best Practices](https://prometheus.io/docs/practices/alerting/)
