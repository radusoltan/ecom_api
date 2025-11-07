# Prometheus & Grafana Setup Guide

**Target Audience**: DevOps, SRE, System Administrators
**Date**: January 16, 2025
**Application**: E-Commerce Platform - Observability & Monitoring

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Prometheus Setup](#prometheus-setup)
4. [Grafana Setup](#grafana-setup)
5. [Available Metrics](#available-metrics)
6. [Dashboard Examples](#dashboard-examples)
7. [Alerting Rules](#alerting-rules)
8. [Troubleshooting](#troubleshooting)

---

## Overview

The e-commerce platform exposes Prometheus-compatible metrics at `/metrics` endpoint for comprehensive monitoring and observability.

### Key Features

- ✅ Real-time application metrics
- ✅ Multi-tenant monitoring (per-tenant labels)
- ✅ SLO tracking (p95 latency < 200ms)
- ✅ Business metrics (orders, payments, conversions)
- ✅ Technical metrics (API latency, error rates)

### Architecture

```
┌─────────────────┐
│   Application   │
│   /metrics      │──┐
└─────────────────┘  │
                     │ Scrape every 15s
┌─────────────────┐  │
│   Prometheus    │←─┘
│   Port 9090     │
└────────┬────────┘
         │ Data source
┌────────▼────────┐
│    Grafana      │
│   Port 3000     │
└─────────────────┘
```

---

## Prerequisites

### System Requirements

- **OS**: Ubuntu 20.04+ / Debian 11+ / RHEL 8+
- **RAM**: Minimum 2GB for Prometheus + Grafana
- **Disk**: 50GB for metrics retention (30 days)
- **Network**: Access to application server port 80/443

### Required Software

```bash
# Install Prometheus
cd /tmp
wget https://github.com/prometheus/prometheus/releases/download/v2.48.0/prometheus-2.48.0.linux-amd64.tar.gz
tar -xvf prometheus-2.48.0.linux-amd64.tar.gz
sudo mv prometheus-2.48.0.linux-amd64 /opt/prometheus
sudo useradd --no-create-home --shell /bin/false prometheus
sudo chown -R prometheus:prometheus /opt/prometheus

# Install Grafana
sudo apt-get install -y software-properties-common
sudo add-apt-repository "deb https://packages.grafana.com/oss/deb stable main"
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
sudo apt-get update
sudo apt-get install grafana
```

---

## Prometheus Setup

### 1. Configuration

Create `/opt/prometheus/prometheus.yml`:

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s
  external_labels:
    environment: 'production'
    cluster: 'ecommerce-platform'

scrape_configs:
  # E-Commerce Platform Metrics
  - job_name: 'ecommerce-backend'
    metrics_path: '/metrics'
    scheme: 'https' # or 'http' for development
    static_configs:
      - targets:
          - 'api.example.com:443' # Replace with your domain
    relabel_configs:
      - source_labels: [__address__]
        target_label: instance
      - source_labels: [__address__]
        target_label: __address__
        replacement: 'api.example.com:443'

  # Multi-instance setup (if running multiple app servers)
  - job_name: 'ecommerce-backend-cluster'
    metrics_path: '/metrics'
    static_configs:
      - targets:
          - 'app-server-1:8000'
          - 'app-server-2:8000'
          - 'app-server-3:8000'
    labels:
      environment: 'production'

  # Prometheus self-monitoring
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  # RabbitMQ monitoring (if RabbitMQ Prometheus plugin enabled)
  - job_name: 'rabbitmq'
    static_configs:
      - targets: ['localhost:15692']
```

### 2. Create Systemd Service

Create `/etc/systemd/system/prometheus.service`:

```ini
[Unit]
Description=Prometheus
Wants=network-online.target
After=network-online.target

[Service]
User=prometheus
Group=prometheus
Type=simple
ExecStart=/opt/prometheus/prometheus \
    --config.file=/opt/prometheus/prometheus.yml \
    --storage.tsdb.path=/opt/prometheus/data \
    --web.console.templates=/opt/prometheus/consoles \
    --web.console.libraries=/opt/prometheus/console_libraries \
    --storage.tsdb.retention.time=30d

Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### 3. Start Prometheus

```bash
# Create data directory
sudo mkdir -p /opt/prometheus/data
sudo chown prometheus:prometheus /opt/prometheus/data

# Enable and start service
sudo systemctl daemon-reload
sudo systemctl enable prometheus
sudo systemctl start prometheus

# Check status
sudo systemctl status prometheus

# Access Prometheus UI
# http://localhost:9090
```

---

## Grafana Setup

### 1. Start Grafana

```bash
sudo systemctl enable grafana-server
sudo systemctl start grafana-server
sudo systemctl status grafana-server

# Access Grafana UI
# http://localhost:3000
# Default credentials: admin/admin
```

### 2. Add Prometheus Data Source

1. Login to Grafana (http://localhost:3000)
2. Navigate to **Configuration → Data Sources**
3. Click **Add data source**
4. Select **Prometheus**
5. Configure:
   - **Name**: Prometheus
   - **URL**: http://localhost:9090
   - **Access**: Server (default)
6. Click **Save & Test**

### 3. Import E-Commerce Dashboard

Create dashboard JSON file: `/tmp/ecommerce-dashboard.json`

```json
{
  "dashboard": {
    "title": "E-Commerce Platform - Order & Payment Monitoring",
    "panels": [
      {
        "title": "Orders Placed (Total)",
        "targets": [
          {
            "expr": "sum(rate(order_placed_total[5m])) by (tenant_id)"
          }
        ],
        "type": "graph"
      },
      {
        "title": "API Request Latency (p95)",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, sum(rate(api_request_duration_seconds_bucket[5m])) by (le, path))"
          }
        ],
        "type": "graph"
      },
      {
        "title": "Payment Success Rate",
        "targets": [
          {
            "expr": "sum(rate(payment_total{status=\"captured\"}[5m])) / sum(rate(payment_total[5m])) * 100"
          }
        ],
        "type": "stat"
      }
    ]
  }
}
```

Import via:
- **Dashboards → Import → Upload JSON file**

---

## Available Metrics

### Order Metrics

| Metric Name | Type | Labels | Description |
|-------------|------|--------|-------------|
| `order_placed_total` | Counter | tenant_id, status | Total orders placed |
| `order_duration_seconds` | Histogram | tenant_id | Order processing duration |
| `order_status_change_total` | Counter | tenant_id, from_status, to_status | Status transitions |

### Payment Metrics

| Metric Name | Type | Labels | Description |
|-------------|------|--------|-------------|
| `payment_total` | Counter | tenant_id, status, gateway | Total payments |
| `payment_failed_total` | Counter | tenant_id, gateway | Failed payments |
| `payment_refunded_total` | Counter | tenant_id, gateway | Refunded payments |
| `payment_duration_seconds` | Histogram | tenant_id, gateway | Payment processing time |

### API Metrics

| Metric Name | Type | Labels | Description |
|-------------|------|--------|-------------|
| `api_requests_total` | Counter | method, path, status, tenant_id | Total API requests |
| `api_request_duration_seconds` | Histogram | method, path, tenant_id | API request latency |
| `rate_limit_hits_total` | Counter | tenant_id | Rate limit violations |

---

## Dashboard Examples

### PromQL Queries

**1. Orders per minute (by tenant):**
```promql
sum(rate(order_placed_total[1m])) by (tenant_id)
```

**2. API p95 latency:**
```promql
histogram_quantile(0.95, sum(rate(api_request_duration_seconds_bucket[5m])) by (le, path))
```

**3. Payment success rate:**
```promql
sum(rate(payment_total{status="captured"}[5m])) / sum(rate(payment_total[5m])) * 100
```

**4. Order status distribution:**
```promql
sum(order_status_change_total) by (to_status)
```

**5. API error rate (4xx/5xx):**
```promql
sum(rate(api_requests_total{status=~"4..|5.."}[5m])) by (status)
```

**6. SLO Compliance (% requests < 200ms):**
```promql
sum(rate(api_request_duration_seconds_bucket{le="0.2"}[5m])) / sum(rate(api_request_duration_seconds_count[5m])) * 100
```

---

## Alerting Rules

Create `/opt/prometheus/rules/ecommerce.yml`:

```yaml
groups:
  - name: ecommerce_alerts
    interval: 30s
    rules:
      # SLO Violation: p95 latency > 200ms
      - alert: HighAPILatency
        expr: histogram_quantile(0.95, sum(rate(api_request_duration_seconds_bucket[5m])) by (le)) > 0.2
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "API p95 latency above 200ms"
          description: "API p95 latency is {{ $value }}s (SLO: <200ms)"

      # High payment failure rate
      - alert: HighPaymentFailureRate
        expr: (sum(rate(payment_failed_total[5m])) / sum(rate(payment_total[5m]))) > 0.05
        for: 10m
        labels:
          severity: critical
        annotations:
          summary: "Payment failure rate above 5%"
          description: "{{ $value | humanizePercentage }} of payments are failing"

      # Rate limit abuse
      - alert: ExcessiveRateLimitHits
        expr: sum(rate(rate_limit_hits_total[5m])) by (tenant_id) > 10
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Tenant {{ $labels.tenant_id }} hitting rate limits"
          description: "{{ $value }} rate limit hits per second"

      # Low order volume (possible outage)
      - alert: LowOrderVolume
        expr: sum(rate(order_placed_total[30m])) < 1
        for: 30m
        labels:
          severity: warning
        annotations:
          summary: "Order volume below threshold"
          description: "Less than 1 order per minute for 30 minutes"
```

Update `prometheus.yml` to include rules:
```yaml
rule_files:
  - "/opt/prometheus/rules/*.yml"
```

Reload Prometheus:
```bash
sudo systemctl reload prometheus
```

---

## Troubleshooting

### Problem: Metrics Endpoint Returns 404

**Symptoms**: `/metrics` endpoint not accessible

**Solutions**:
```bash
# Check if route is registered
php bin/console debug:router | grep metrics

# Clear cache
php bin/console cache:clear

# Verify MetricsController is registered
php bin/console debug:container MetricsCollector
```

### Problem: Prometheus Not Scraping

**Symptoms**: No data in Prometheus

**Solutions**:
```bash
# Check Prometheus targets status
# http://localhost:9090/targets

# Test metrics endpoint manually
curl https://api.example.com/metrics

# Check Prometheus logs
sudo journalctl -u prometheus -f

# Verify firewall allows connections
sudo ufw allow 9090/tcp
```

### Problem: High Memory Usage

**Symptoms**: Prometheus consuming excessive RAM

**Solutions**:
```bash
# Reduce retention period in prometheus.yml
--storage.tsdb.retention.time=15d  # Instead of 30d

# Reduce scrape frequency
scrape_interval: 30s  # Instead of 15s

# Monitor storage size
du -sh /opt/prometheus/data
```

---

## Performance Targets

| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| API p95 latency | < 200ms | > 300ms |
| API p99 latency | < 500ms | > 1s |
| Payment success rate | > 98% | < 95% |
| Order placement success | > 99% | < 97% |
| API error rate (5xx) | < 0.1% | > 1% |
| Rate limit hits | < 5/min/tenant | > 10/min/tenant |

---

## Security Best Practices

1. **Firewall Protection**:
   ```bash
   # Only allow monitoring servers to access /metrics
   sudo ufw allow from 10.0.1.0/24 to any port 443
   ```

2. **Authentication** (Optional):
   Add basic auth to `/metrics` endpoint in Nginx:
   ```nginx
   location /metrics {
       auth_basic "Metrics";
       auth_basic_user_file /etc/nginx/.htpasswd;
       proxy_pass http://backend;
   }
   ```

3. **Separate Monitoring Port** (Recommended):
   Expose metrics on internal port 9091:
   ```yaml
   # config/routes/metrics.yaml
   metrics:
       path: /metrics
       controller: App\Shared\Infrastructure\Http\Controller\MetricsController::metrics
       defaults:
           _port: 9091
   ```

---

## Support

For issues or questions:
- **DevOps Team**: devops@example.com
- **Monitoring Alerts**: #monitoring-alerts Slack channel
- **On-Call**: See PagerDuty rotation

---

**Last Updated**: January 16, 2025
**Version**: 1.0
**Maintained By**: DevOps Team
