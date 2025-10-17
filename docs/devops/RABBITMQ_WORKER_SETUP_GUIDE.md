# RabbitMQ Worker Setup Guide

**Target Audience**: DevOps, System Administrators, SRE
**Date**: January 16, 2025
**Application**: E-Commerce Platform - Event-Driven Payment Processing

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Queue Configuration](#queue-configuration)
4. [Worker Setup](#worker-setup)
5. [Supervisor Configuration](#supervisor-configuration)
6. [Monitoring & Alerts](#monitoring--alerts)
7. [Troubleshooting](#troubleshooting)
8. [Production Checklist](#production-checklist)

---

## Overview

The e-commerce platform uses **RabbitMQ** for asynchronous event processing across bounded contexts (Payment, Order, Inventory). This guide covers:

- ✅ Queue setup and configuration
- ✅ Worker process management with Supervisor
- ✅ Monitoring and alerting
- ✅ Performance tuning
- ✅ Disaster recovery

### Architecture

```
┌──────────────┐
│   Payment    │─→ PaymentCaptured ─→ [payment_events] ─→ Worker 1
│   Gateway    │                                             ↓
└──────────────┘                                    UpdateOrderStatus
                                                             ↓
┌──────────────┐                                    Emit OrderPaid
│   Order      │←─ UpdateOrderStatus                        ↓
│   Service    │                                    [order_events] ─→ Worker 2
└──────────────┘                                             ↓
                                                    Start Fulfillment
┌──────────────┐
│  Inventory   │←─ AllocateStock ←─ [inventory_events] ←─ Worker 3
└──────────────┘
```

### Event Queues

| Queue Name | Purpose | Retry Strategy | Priority |
|------------|---------|----------------|----------|
| **payment_events** | Payment lifecycle events | 3 retries, 1s delay, 2x multiplier | High |
| **order_events** | Order status changes, fulfillment | 3 retries, 1s delay, 2x multiplier | High |
| **inventory_events** | Stock allocation, reservations | 5 retries, 2s delay, 2x multiplier | Medium |
| **async** | General async tasks | 3 retries, 1s delay | Low |
| **media_async** | Image processing, thumbnails | 3 retries, 2s delay | Low |
| **failed** | Dead letter queue | N/A | Manual |

---

## Prerequisites

### System Requirements

- **OS**: Ubuntu 20.04+ / Debian 11+ / RHEL 8+
- **PHP**: 8.3+
- **RabbitMQ**: 3.12+
- **Supervisor**: 4.2+
- **Memory**: Minimum 512MB per worker
- **Disk**: 10GB for logs and failed messages

### Required Software

```bash
# Install RabbitMQ
sudo apt-get update
sudo apt-get install -y rabbitmq-server

# Install Supervisor
sudo apt-get install -y supervisor

# Start services
sudo systemctl enable rabbitmq-server
sudo systemctl start rabbitmq-server
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

### RabbitMQ Management Plugin

```bash
# Enable management UI
sudo rabbitmq-plugins enable rabbitmq_management

# Access at: http://localhost:15672
# Default credentials: guest/guest (change in production!)
```

---

## Queue Configuration

### 1. Environment Variables

Update `/var/www/new_ecom/backend/.env`:

```env
# RabbitMQ Connection
MESSENGER_TRANSPORT_DSN=amqp://ecom:sr324395@localhost:5672/%2f/messages

# For production with credentials:
# MESSENGER_TRANSPORT_DSN=amqp://your_user:your_password@rabbitmq.example.com:5672/production
```

### 2. Setup Transports

```bash
cd /var/www/new_ecom/backend

# Create all queues and exchanges
php bin/console messenger:setup-transports

# Expected output:
# ✔ The "async" transport was set up successfully.
# ✔ The "payment_events" transport was set up successfully.
# ✔ The "order_events" transport was set up successfully.
# ✔ The "inventory_events" transport was set up successfully.
# ✔ The "failed" transport was set up successfully.
```

### 3. Verify Queue Creation

```bash
# List all queues
sudo rabbitmqctl list_queues name messages consumers

# Expected output:
# Listing queues for vhost / ...
# async             0       0
# payment_events    0       0
# order_events      0       0
# inventory_events  0       0
# failed            0       0
```

---

## Worker Setup

### Manual Worker Start (Development)

```bash
cd /var/www/new_ecom/backend

# Start payment events worker
php bin/console messenger:consume payment_events -vv

# Start order events worker (separate terminal)
php bin/console messenger:consume order_events -vv

# Start all workers together
php bin/console messenger:consume async payment_events order_events inventory_events -vv
```

**Options**:
- `-vv`: Verbose output (INFO level)
- `-vvv`: Very verbose output (DEBUG level)
- `--limit=1000`: Stop after processing 1000 messages
- `--time-limit=3600`: Stop after 1 hour
- `--memory-limit=128M`: Stop when memory exceeds limit

### Production Worker Setup (Supervisor)

**DON'T run workers manually in production!** Use Supervisor for:
- ✅ Automatic restart on failure
- ✅ Process monitoring
- ✅ Log management
- ✅ Graceful shutdown

---

## Supervisor Configuration

### 1. Create Supervisor Configuration

Create `/etc/supervisor/conf.d/ecommerce-workers.conf`:

```ini
; ============================================
; E-Commerce Platform - RabbitMQ Workers
; ============================================

; Payment Events Worker (High Priority)
[program:ecommerce-payment-worker]
command=php /var/www/new_ecom/backend/bin/console messenger:consume payment_events --time-limit=3600 --memory-limit=256M
process_name=%(program_name)s_%(process_num)02d
numprocs=2
directory=/var/www/new_ecom/backend
user=www-data
autostart=true
autorestart=true
startsecs=10
startretries=3
stdout_logfile=/var/log/supervisor/ecommerce-payment-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
stderr_logfile=/var/log/supervisor/ecommerce-payment-worker-error.log
stderr_logfile_maxbytes=10MB
stderr_logfile_backups=10
stopwaitsecs=30
stopasgroup=true
killasgroup=true
priority=999

; Order Events Worker (High Priority)
[program:ecommerce-order-worker]
command=php /var/www/new_ecom/backend/bin/console messenger:consume order_events --time-limit=3600 --memory-limit=256M
process_name=%(program_name)s_%(process_num)02d
numprocs=2
directory=/var/www/new_ecom/backend
user=www-data
autostart=true
autorestart=true
startsecs=10
startretries=3
stdout_logfile=/var/log/supervisor/ecommerce-order-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
stderr_logfile=/var/www/new_ecom/backend/var/log/supervisor/ecommerce-order-worker-error.log
stderr_logfile_maxbytes=10MB
stderr_logfile_backups=10
stopwaitsecs=30
stopasgroup=true
killasgroup=true
priority=999

; Inventory Events Worker (Medium Priority)
[program:ecommerce-inventory-worker]
command=php /var/www/new_ecom/backend/bin/console messenger:consume inventory_events --time-limit=3600 --memory-limit=256M
process_name=%(program_name)s_%(process_num)02d
numprocs=1
directory=/var/www/new_ecom/backend
user=www-data
autostart=true
autorestart=true
startsecs=10
startretries=3
stdout_logfile=/var/log/supervisor/ecommerce-inventory-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
stderr_logfile=/var/log/supervisor/ecommerce-inventory-worker-error.log
stderr_logfile_maxbytes=10MB
stderr_logfile_backups=10
stopwaitsecs=30
stopasgroup=true
killasgroup=true
priority=500

; General Async Worker (Low Priority)
[program:ecommerce-async-worker]
command=php /var/www/new_ecom/backend/bin/console messenger:consume async --time-limit=3600 --memory-limit=256M
process_name=%(program_name)s_%(process_num)02d
numprocs=1
directory=/var/www/new_ecom/backend
user=www-data
autostart=true
autorestart=true
startsecs=10
startretries=3
stdout_logfile=/var/log/supervisor/ecommerce-async-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
stderr_logfile=/var/log/supervisor/ecommerce-async-worker-error.log
stderr_logfile_maxbytes=10MB
stderr_logfile_backups=10
stopwaitsecs=30
stopasgroup=true
killasgroup=true
priority=100

; Group for easy management
[group:ecommerce-workers]
programs=ecommerce-payment-worker,ecommerce-order-worker,ecommerce-inventory-worker,ecommerce-async-worker
```

### 2. Load Configuration

```bash
# Reload Supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start all workers
sudo supervisorctl start ecommerce-workers:*

# Check status
sudo supervisorctl status
```

### 3. Worker Management Commands

```bash
# Start all workers
sudo supervisorctl start ecommerce-workers:*

# Stop all workers
sudo supervisorctl stop ecommerce-workers:*

# Restart all workers
sudo supervisorctl restart ecommerce-workers:*

# Stop specific worker group
sudo supervisorctl stop ecommerce-payment-worker:*

# View logs
sudo supervisorctl tail -f ecommerce-payment-worker:ecommerce-payment-worker_00 stdout
sudo supervisorctl tail -f ecommerce-payment-worker:ecommerce-payment-worker_00 stderr

# Check worker status
sudo supervisorctl status ecommerce-workers:*
```

### 4. Graceful Shutdown

```bash
# Send SIGTERM to workers (graceful shutdown)
sudo supervisorctl stop ecommerce-workers:*

# Workers will:
# 1. Finish processing current message
# 2. Acknowledge message
# 3. Exit cleanly
```

---

## Monitoring & Alerts

### 1. RabbitMQ Management UI

Access: `http://localhost:15672`

**Key Metrics to Monitor**:
- Queue depth (messages ready)
- Consumer count
- Message rate (in/out)
- Unacknowledged messages
- Memory usage
- Disk space

### 2. Queue Health Check Script

Create `/usr/local/bin/check_rabbitmq_queues.sh`:

```bash
#!/bin/bash
# Check RabbitMQ queue depth and alert if too high

THRESHOLD=1000
ALERT_EMAIL="devops@example.com"

for QUEUE in payment_events order_events inventory_events; do
    DEPTH=$(sudo rabbitmqctl list_queues name messages | grep "^$QUEUE" | awk '{print $2}')

    if [ "$DEPTH" -gt "$THRESHOLD" ]; then
        echo "ALERT: Queue $QUEUE has $DEPTH messages (threshold: $THRESHOLD)" | \
            mail -s "RabbitMQ Alert: High Queue Depth" $ALERT_EMAIL
    fi
done
```

### 3. Cron Job for Monitoring

```bash
# Add to crontab (every 5 minutes)
crontab -e

# Add line:
*/5 * * * * /usr/local/bin/check_rabbitmq_queues.sh
```

### 4. Failed Messages Monitoring

```bash
# Check failed queue
php bin/console messenger:failed:show

# Retry failed messages
php bin/console messenger:failed:retry

# Remove failed messages
php bin/console messenger:failed:remove <id>
```

### 5. Prometheus Metrics (Advanced)

Install RabbitMQ Prometheus exporter:

```bash
# Enable plugin
sudo rabbitmq-plugins enable rabbitmq_prometheus

# Metrics available at:
# http://localhost:15692/metrics
```

---

## Troubleshooting

### Problem: Workers Not Starting

**Symptoms**: Supervisor shows `FATAL` or `BACKOFF` state

**Solutions**:
```bash
# Check Supervisor logs
sudo tail -f /var/log/supervisor/supervisor.log

# Check worker logs
sudo tail -f /var/log/supervisor/ecommerce-payment-worker-error.log

# Common issues:
# - PHP path incorrect: Update 'command=' in config
# - User permissions: Ensure www-data can read /var/www/new_ecom/backend
# - Database connection: Check DATABASE_URL in .env
```

### Problem: Messages Not Being Processed

**Symptoms**: Queue depth keeps growing

**Solutions**:
```bash
# Check if workers are running
sudo supervisorctl status

# Check if workers are connected to RabbitMQ
sudo rabbitmqctl list_consumers

# Manually consume to see errors
cd /var/www/new_ecom/backend
php bin/console messenger:consume payment_events -vvv

# Check application logs
tail -f var/log/dev.log
```

### Problem: Memory Leaks

**Symptoms**: Workers consuming too much memory

**Solutions**:
```bash
# Set memory limit in Supervisor config
--memory-limit=256M

# Set time limit to restart workers periodically
--time-limit=3600  # Restart after 1 hour

# Monitor memory usage
watch -n 1 'ps aux | grep messenger:consume'
```

### Problem: Failed Messages Accumulating

**Symptoms**: `failed` queue has many messages

**Solutions**:
```bash
# View failed messages
php bin/console messenger:failed:show

# Retry all failed messages
php bin/console messenger:failed:retry -vv

# Retry specific message
php bin/console messenger:failed:retry <id>

# Remove unfixable messages
php bin/console messenger:failed:remove <id>
```

---

## Production Checklist

### Pre-Deployment

- [ ] RabbitMQ installed and running
- [ ] Supervisor installed and configured
- [ ] All queues created (`messenger:setup-transports`)
- [ ] Worker configuration file created
- [ ] Log directories exist and are writable
- [ ] Environment variables configured
- [ ] Firewall rules allow RabbitMQ port (5672)

### Post-Deployment

- [ ] Supervisor workers started and running
- [ ] Workers connected to RabbitMQ (check `list_consumers`)
- [ ] Test message sent and consumed successfully
- [ ] Monitoring alerts configured
- [ ] Log rotation configured
- [ ] Failed message handling tested
- [ ] Documentation shared with team

### Performance Tuning

#### Worker Count

```ini
; High traffic: Increase numprocs
numprocs=4  ; 4 parallel workers per queue

; Low traffic: Decrease to save resources
numprocs=1
```

#### Memory Limits

```ini
; Adjust based on message size and complexity
--memory-limit=128M   ; Light messages
--memory-limit=512M   ; Heavy messages (images, reports)
```

#### Time Limits

```ini
; Restart workers periodically to prevent memory leaks
--time-limit=1800   ; Every 30 minutes
--time-limit=3600   ; Every 1 hour (recommended)
--time-limit=7200   ; Every 2 hours
```

### Security

```bash
# Change default RabbitMQ credentials
sudo rabbitmqctl change_password guest NEW_SECURE_PASSWORD

# Create dedicated user for application
sudo rabbitmqctl add_user ecom_app SECURE_PASSWORD
sudo rabbitmqctl set_permissions -p / ecom_app ".*" ".*" ".*"

# Remove guest user (production only)
sudo rabbitmqctl delete_user guest

# Enable SSL/TLS
# Update MESSENGER_TRANSPORT_DSN to use amqps://
```

---

## Additional Resources

### Symfony Messenger Documentation
- [Official Docs](https://symfony.com/doc/current/messenger.html)
- [Messenger Component](https://symfony.com/doc/current/components/messenger.html)

### RabbitMQ Documentation
- [RabbitMQ](https://www.rabbitmq.com/documentation.html)
- [Management Plugin](https://www.rabbitmq.com/management.html)
- [Monitoring](https://www.rabbitmq.com/monitoring.html)

### Supervisor Documentation
- [Supervisor](http://supervisord.org/)
- [Configuration](http://supervisord.org/configuration.html)

### Internal Documentation
- `SPRINT_2_PARTIAL_IMPLEMENTATION.md` - Event architecture overview
- `config/packages/messenger.yaml` - Queue configuration
- `src/Payment/Application/EventSubscriber/` - Event handlers

---

## Support

For issues or questions:
- **DevOps Team**: devops@example.com
- **Development Team**: dev@example.com
- **On-Call**: See PagerDuty rotation

---

**Last Updated**: January 16, 2025
**Version**: 1.0
**Maintained By**: DevOps Team
