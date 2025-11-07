# Monitoring & Observability Stack

**Version**: 1.0
**Last Updated**: October 17, 2025
**Status**: ✅ Production Ready

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Components](#components)
4. [Configuration](#configuration)
5. [Metrics & Alerts](#metrics--alerts)
6. [Dashboards](#dashboards)
7. [Operational Guide](#operational-guide)
8. [Troubleshooting](#troubleshooting)

---

## Overview

Enterprise-grade monitoring and observability stack for multi-tenant e-commerce platform.

### Stack Components

| Component | Version | Purpose | Port |
|-----------|---------|---------|------|
| **RabbitMQ** | 3.12+ | Message broker, async processing | 5672, 15672, 15692 |
| **Prometheus** | 3.1.0+ | Metrics collection & alerting | 9090 |
| **Grafana** | 12.2.0+ | Metrics visualization | 3002 |
| **Redis Exporter** | Latest | Redis metrics | 9121 |
| **PostgreSQL Exporter** | Latest | Database metrics | 9187 |

### What Can Be Monitored

**Application Metrics**:
- HTTP request rate and latency (per route, per tenant)
- API endpoint performance
- Error rates (2xx/4xx/5xx responses)
- Catalog operations (product/category/image creation)
- Thumbnail generation performance
- Cache hit ratio

**Infrastructure Metrics**:
- RabbitMQ queue depth and message rates
- PostgreSQL connections and query performance
- Redis memory usage and command rates
- System resources (CPU, memory, disk)

**Business Metrics**:
- Per-tenant product creation rate
- Per-tenant API usage
- Per-tenant cache performance

---

## Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                     Symfony Application                        │
│  ┌────────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ API Controllers│→ │  Messenger   │→ │ RabbitMQ     │     │
│  │                │  │  (Async)     │  │  Queues      │     │
│  └───────┬────────┘  └──────────────┘  └──────────────┘     │
│          │                                                     │
│          ↓                                                     │
│  ┌──────────────────────┐                                     │
│  │ Prometheus Metrics   │  /metrics endpoint                  │
│  │ artprima/prometheus  │  (port 8001)                        │
│  └──────────┬───────────┘                                     │
└─────────────┼───────────────────────────────────────────────┘
              │
              │ HTTP scrape (every 10s)
              ↓
┌─────────────────────────────────────────────────────────────┐
│                        Prometheus                             │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐│
│  │ Metrics Storage│  │  Alert Rules   │  │  Exporters     ││
│  │    (TSDB)      │  │   (10 rules)   │  │  Scrape Jobs   ││
│  └────────────────┘  └────────────────┘  └────────────────┘│
│          │                    │                               │
│          │scrape              │evaluate                       │
│          ↓                    ↓                               │
│  ┌──────────────┐    ┌──────────────┐                       │
│  │ Redis:9121   │    │AlertManager  │ (optional)            │
│  │ PostgreSQL   │    │ (email/slack)│                       │
│  │ :9187        │    └──────────────┘                       │
│  │ RabbitMQ     │                                            │
│  │ :15692       │                                            │
│  └──────────────┘                                            │
└─────────────┬───────────────────────────────────────────────┘
              │
              │ PromQL queries
              ↓
┌─────────────────────────────────────────────────────────────┐
│                         Grafana                               │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐│
│  │   Datasource   │  │   Dashboards   │  │   Alerting     ││
│  │  (Prometheus)  │  │   (8 total)    │  │  (optional)    ││
│  └────────────────┘  └────────────────┘  └────────────────┘│
│                                                               │
│  Web UI: http://localhost:3002 (admin/admin)                 │
└───────────────────────────────────────────────────────────────┘
```

---

## Components

### 1. RabbitMQ Message Broker

**Purpose**: Async message processing, decouples services

**Installation**:
```bash
sudo apt install rabbitmq-server
sudo systemctl enable rabbitmq-server
sudo systemctl start rabbitmq-server
```

**Configuration**:
```bash
# Enable management plugin
sudo rabbitmq-plugins enable rabbitmq_management
sudo rabbitmq-plugins enable rabbitmq_prometheus

# Create user
sudo rabbitmqctl add_user ecom your-password
sudo rabbitmqctl set_user_tags ecom administrator
sudo rabbitmqctl set_permissions -p / ecom ".*" ".*" ".*"
```

**Endpoints**:
- Core AMQP: `amqp://localhost:5672`
- Management UI: http://localhost:15672
- Prometheus Metrics: http://localhost:15692/metrics

**Queues Created**:
- `async` - General async operations
- `media_async` - Media processing operations
- `failed` - Failed messages for retry

---

### 2. Prometheus Metrics Collector

**Purpose**: Scrapes, stores, and queries time-series metrics

**Installation**:
```bash
# Download and install
cd /tmp
wget https://github.com/prometheus/prometheus/releases/download/v3.1.0/prometheus-3.1.0.linux-amd64.tar.gz
tar xzf prometheus-3.1.0.linux-amd64.tar.gz
sudo mv prometheus-3.1.0.linux-amd64 /opt/prometheus

# Create systemd service
sudo nano /etc/systemd/system/prometheus.service
```

**Configuration** (`/etc/prometheus/prometheus.yml`):
```yaml
global:
  scrape_interval: 10s
  evaluation_interval: 10s

alerting:
  alertmanagers:
    - static_configs:
        - targets: []  # Configure AlertManager here

rule_files:
  - "alerts.yml"

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  - job_name: 'symfony_api'
    static_configs:
      - targets: ['localhost:8001']

  - job_name: 'rabbitmq'
    static_configs:
      - targets: ['localhost:15692']

  - job_name: 'postgresql'
    static_configs:
      - targets: ['localhost:9187']

  - job_name: 'redis'
    static_configs:
      - targets: ['localhost:9121']
```

**Alert Rules** (`/etc/prometheus/alerts.yml`):

Copy from project:
```bash
sudo cp /var/www/new_ecom/backend/config/prometheus/alerts.yml /etc/prometheus/
```

Contains 10 alert rules:
- `HighErrorRate` (critical)
- `LowCacheHit` (warning)
- `SlowThumbnails` (warning)
- `HighApiLatency` (warning)
- `RLSMismatch` (critical)
- `CatalogServiceDown` (critical)
- `ExcessiveProductCreation` (warning)
- `ImageUploadSpike` (warning)
- `LowDiskSpace` (warning)
- `DatabasePoolExhaustion` (critical)

**Reload Configuration**:
```bash
curl -X POST http://localhost:9090/-/reload
```

---

### 3. Grafana Visualization

**Purpose**: Dashboards, graphs, alerting UI

**Installation**:
```bash
sudo apt install -y software-properties-common
sudo add-apt-repository "deb https://packages.grafana.com/oss/deb stable main"
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
sudo apt update
sudo apt install grafana
```

**Configuration** (`/etc/grafana/grafana.ini`):
```ini
[server]
http_port = 3002

[plugins]
allow_loading_unsigned_plugins = grafana-metricsdrilldown-app,grafana-lokiexplore-app
```

**Start Service**:
```bash
sudo systemctl enable grafana-server
sudo systemctl start grafana-server
```

**Add Prometheus Datasource**:
```bash
curl -X POST http://localhost:3002/api/datasources \
  -H "Content-Type: application/json" \
  -u admin:admin \
  -d '{
    "name": "Prometheus",
    "type": "prometheus",
    "url": "http://localhost:9090",
    "access": "proxy",
    "isDefault": true
  }'
```

---

### 4. Symfony Integration

**Install Prometheus Bundle**:
```bash
composer require artprima/prometheus-metrics-bundle
```

**Configuration** (`config/packages/prometheus_metrics.yaml`):
```yaml
prometheus_metrics:
    namespace: ecom
    type: apcu
    ignored_routes: []
```

**Metrics Endpoint**: http://localhost:8001/metrics

**Custom Metrics Collectors**:
- `CatalogMetricsSubscriber` - Catalog operations
- `ApiLatencyMiddleware` - API latency tracking

**Messenger Configuration** (`.env`):
```env
MESSENGER_TRANSPORT_DSN="amqp://ecom:password@localhost:5672/%2f/messages"
```

---

## Metrics & Alerts

### Application Metrics Exposed

**Default HTTP Metrics**:
```promql
ecom_http_requests_total{action}
ecom_http_2xx_responses_total{action}
ecom_request_durations_histogram_seconds{action}
```

**Custom Catalog Metrics**:
```promql
ecom_catalog_product_created_total{tenant}
ecom_catalog_category_created_total{tenant}
ecom_catalog_image_upload_total{tenant}
ecom_catalog_api_latency_seconds{tenant,route}
ecom_thumbnail_generation_seconds{tenant}
ecom_catalog_cache_hit_ratio{tenant}
```

### Useful PromQL Queries

**Request Rate (per second)**:
```promql
rate(ecom_http_requests_total[5m])
```

**API Latency p95**:
```promql
histogram_quantile(0.95, rate(ecom_request_durations_histogram_seconds_bucket[5m]))
```

**Error Rate**:
```promql
sum(rate(ecom_http_requests_total{status_code=~"5.."}[5m])) / sum(rate(ecom_http_requests_total[5m]))
```

**Products Created Per Tenant**:
```promql
ecom_catalog_product_created_total
```

**RabbitMQ Queue Depth**:
```promql
rabbitmq_queue_messages_ready
```

---

## Dashboards

### Available Dashboards (Grafana)

1. **Catalog Overview** (Custom)
   - Product/Category creation rates
   - API latency heatmap
   - Cache hit ratio
   - Per-tenant metrics

2. **Search Health** (Custom)
   - Elasticsearch performance
   - Search latency
   - Index health

3. **RabbitMQ Overview** (ID: 10991)
4. **Symfony / PHP** (ID: 5955)
5. **PostgreSQL Exporter** (ID: 9628)
6. **Redis Exporter** (ID: 763)
7. **Prometheus 2.0 Stats** (ID: 3662)

### Import Custom Dashboards

```bash
# From Grafana JSON files
cd /var/www/new_ecom/backend/config/grafana

# Import via UI:
# Grafana → Dashboards → Import → Upload JSON file
# - catalog-overview-dashboard.json
# - search-health-dashboard.json
```

---

## Operational Guide

### Access URLs

| Service | URL | Credentials |
|---------|-----|-------------|
| Symfony API | http://localhost:8001 | - |
| Metrics Endpoint | http://localhost:8001/metrics | - |
| RabbitMQ Management | http://localhost:15672 | ecom/password |
| Prometheus | http://localhost:9090 | - |
| Grafana | http://localhost:3002 | admin/admin |

### Start/Stop Services

```bash
# RabbitMQ
sudo systemctl start rabbitmq-server
sudo systemctl stop rabbitmq-server
sudo systemctl status rabbitmq-server

# Prometheus
sudo systemctl start prometheus
sudo systemctl stop prometheus
sudo systemctl status prometheus

# Grafana
sudo systemctl start grafana-server
sudo systemctl stop grafana-server
sudo systemctl status grafana-server
```

### Start Messenger Consumer

**Foreground** (for testing):
```bash
cd /var/www/new_ecom/backend
php bin/console messenger:consume async -vv
```

**Background**:
```bash
php bin/console messenger:consume async -vv > /var/log/messenger.log 2>&1 &
```

**As Systemd Service** (recommended for production):
```bash
# Create /etc/systemd/system/messenger-worker.service
sudo systemctl enable messenger-worker
sudo systemctl start messenger-worker
```

### View Logs

```bash
# Grafana logs
sudo journalctl -u grafana-server -f

# Prometheus logs
sudo journalctl -u prometheus -f

# RabbitMQ logs
sudo tail -f /var/log/rabbitmq/rabbit@$(hostname).log
```

### Generate Test Metrics

```bash
TENANT_ID="550e8400-e29b-41d4-a716-446655440000"

# Create test products
for i in {1..10}; do
  curl -X POST http://localhost:8001/api/products \
    -H "Content-Type: application/json" \
    -H "X-Tenant-ID: $TENANT_ID" \
    -d "{
      \"sku\": \"TEST-$(date +%s)-$i\",
      \"name\": \"Test Product $i\",
      \"price\": {\"amount\": $((1000 + i * 100)), \"currency\": \"USD\"}
    }"
done
```

Then verify:
1. Prometheus: `ecom_catalog_product_created_total` increments
2. Grafana: Catalog Overview dashboard shows activity
3. RabbitMQ: Check message processing

---

## Troubleshooting

### Prometheus Not Scraping

**Symptoms**: Target shows as DOWN in http://localhost:9090/targets

**Check target is reachable**:
```bash
curl http://localhost:8001/metrics
curl http://localhost:15692/metrics
```

**Check Prometheus logs**:
```bash
sudo journalctl -u prometheus -n 50
```

**Common causes**:
- Service not running
- Port mismatch in `prometheus.yml`
- Firewall blocking localhost connections

**Solution**:
```bash
# Verify prometheus.yml syntax
promtool check config /etc/prometheus/prometheus.yml

# Reload configuration
curl -X POST http://localhost:9090/-/reload
```

---

### Grafana Won't Start

**Symptoms**: Service in restart loop

**Check logs**:
```bash
sudo journalctl -u grafana-server -n 50
```

**Common causes**:
- Plugin signature validation errors
- Port already in use
- Permission issues on `/var/lib/grafana`

**Solution for plugin errors**:
```bash
sudo nano /etc/grafana/grafana.ini

# Add under [plugins]:
allow_loading_unsigned_plugins = grafana-metricsdrilldown-app

sudo systemctl restart grafana-server
```

---

### RabbitMQ Queues Growing

**Symptoms**: Messages accumulating in queues

**Check queue status**:
```bash
curl -u ecom:password http://localhost:15672/api/queues/%2F | python3 -m json.tool
```

**Solution**: Start messenger consumer
```bash
php bin/console messenger:consume async -vv
```

**Check for failed messages**:
```bash
php bin/console messenger:failed:show
php bin/console messenger:failed:retry
```

---

### High Memory Usage

**Check Prometheus TSDB size**:
```bash
du -sh /var/lib/prometheus/
```

**Reduce retention** (edit prometheus.yml):
```yaml
global:
  storage.tsdb.retention.time: 15d  # Default: 15 days
```

---

### Metrics Not Updating

**Check APCu cache**:
```bash
php -r "phpinfo();" | grep apcu
```

If APCu not installed:
```bash
sudo apt install php-apcu
sudo systemctl restart php8.3-fpm  # Adjust PHP version
```

---

## Best Practices

### Production Deployment

1. **Secure Grafana**:
   - Change default admin password
   - Enable HTTPS
   - Configure authentication (LDAP/OAuth)

2. **Configure AlertManager**:
   - Set up email/Slack notifications
   - Define escalation policies
   - Test alert delivery

3. **Backup Grafana Dashboards**:
   ```bash
   # Export dashboards via API
   curl -H "Authorization: Bearer <token>" \
     http://localhost:3002/api/search?type=dash-db | \
     python3 -m json.tool > dashboards_backup.json
   ```

4. **Monitor the Monitors**:
   - Set up uptime checks for Prometheus/Grafana
   - Alert if scrape targets go down
   - Monitor disk space for metrics storage

5. **Tune Scrape Intervals**:
   - High-traffic: 10s intervals
   - Low-traffic: 30s-60s intervals
   - Reduces storage and CPU usage

---

## Support

### Documentation
- **Prometheus**: https://prometheus.io/docs/
- **Grafana**: https://grafana.com/docs/
- **RabbitMQ**: https://www.rabbitmq.com/documentation.html
- **Prometheus Bundle**: https://github.com/artprima/prometheus-metrics-bundle

### Internal
- **API Reference**: `/api/docs`
- **CLAUDE.md**: Project architecture
- **Alert Rules**: `config/prometheus/alerts.yml`

---

**Document maintained by**: DevOps Team
**Last reviewed**: October 17, 2025
