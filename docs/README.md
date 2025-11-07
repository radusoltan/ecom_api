# Backend Documentation

Multi-tenant e-commerce platform built with **DDD/CQRS/Hexagonal Architecture**.

**Version**: 1.0
**Last Updated**: November 6, 2025
**Architecture**: DDD/CQRS/Hexagonal (Multi-tenant)

---

## Quick Links

- 🔌 **API Documentation**: http://localhost:8001/api/docs (OpenAPI/Swagger)
- 🎮 **GraphQL Playground**: http://localhost:8001/api/graphql
- 📊 **Monitoring**: http://localhost:3002 (Grafana), http://localhost:9090 (Prometheus)

---

## Documentation Structure

### 📖 API Documentation (`api/`)

Technical API specifications and integration guides.

| Document | Description | Audience |
|----------|-------------|----------|
| [**API Hub**](api/README.md) | API overview and general information | All |
| [**Cart API**](api/cart.md) | Shopping cart management | Backend, Frontend |
| [**Media API**](api/media.md) | Image uploads, thumbnails, multi-tenant media | Backend, Frontend |
| [**Order API**](api/order.md) | Order management, fulfillment | Backend, Frontend |
| [**Storefront API**](api/storefront.md) | Public-facing catalog API | Frontend |

**When to use**: Integrating with REST APIs, understanding request/response formats

---

### 🏗️ Architecture & Patterns (`architecture/`)

DDD patterns, architecture decisions, and design principles.

| Document | Description | Audience |
|----------|-------------|----------|
| [**DDD Patterns Summary**](architecture/ddd-patterns-summary.md) | Comprehensive DDD pattern catalog | All developers |

**When to use**: Understanding architecture, implementing new features

---

### 📋 Business Requirements (`business/`)

Product requirements and business rules.

| Document | Description | Audience |
|----------|-------------|----------|
| [**Product Requirements Document**](business/PRD.md) | Complete business rules and use cases | All developers |

**When to use**: Understanding business logic, implementing features

---

### 🔌 Integration (`integration/`)

Framework integrations and external service connections.

| Document | Description | Audience |
|----------|-------------|----------|
| [**DDD & Symfony Integration**](integration/ddd-symfony-integration.md) | Dual-model pattern, Doctrine integration | Backend |
| [**Payment Integration**](integration/payment-integration.md) | Stripe, PayPal, 2Checkout setup | Backend, DevOps |

**When to use**: Integrating with frameworks or external services

---

### ⚙️ DevOps & Infrastructure (`devops/`)

Infrastructure setup, monitoring, and operational procedures.

| Document | Description | Audience |
|----------|-------------|----------|
| [**Monitoring**](devops/monitoring.md) | Prometheus, Grafana, RabbitMQ operational guide | DevOps, Backend |
| [**Prometheus & Grafana Setup**](devops/prometheus-grafana.md) | Initial monitoring stack installation | DevOps |
| [**RabbitMQ Worker Setup**](devops/rabbitmq-worker.md) | Async message processing setup | DevOps, Backend |

**When to use**: Setting up infrastructure, troubleshooting production issues

---

### 🎯 Developer Guides (`guides/`)

Practical guides for common development tasks.

| Document | Description | Audience |
|----------|-------------|----------|
| [**Creating a New Aggregate**](guides/new-aggregate.md) | Step-by-step guide for DDD aggregates | Backend |
| [**Testing Guide**](guides/testing-guide.md) | PHPUnit, integration tests, test strategy | Backend |
| [**Observability Quickstart**](guides/observability-quickstart.md) | Getting started with metrics and monitoring | All developers |

**When to use**: Learning platform conventions, writing tests, debugging

---

### 🚢 Deployment (`deployment/`)

Deployment checklists and procedures.

| Document | Description | Audience |
|----------|-------------|----------|
| [**Production Checklist**](deployment/production-checklist.md) | Production deployment steps | DevOps, Tech Lead |

**When to use**: Deploying to staging/production

---

## Core Concepts

### Multi-Tenancy

**All operations are tenant-isolated:**
- Header: `X-Tenant-ID: {uuid}`
- Database: PostgreSQL Row-Level Security (RLS)
- Cache: Redis namespacing `{tenant_id}:*`
- Search: Elasticsearch per-tenant indices

### DDD Architecture

```
src/{Context}/
├── Domain/          # Pure business logic (framework-free)
│   ├── Model/       # Aggregates, entities, value objects
│   ├── Repository/  # Repository interfaces (ports)
│   └── Event/       # Domain events
├── Application/     # Use cases & orchestration
│   ├── Command/     # Write operations (CQRS)
│   └── Query/       # Read operations (CQRS)
└── Infrastructure/  # Framework & external dependencies
    ├── Persistence/ # Doctrine entities & repositories
    ├── ApiPlatform/ # API resources & state processors
    └── EventSubscriber/  # Domain event handlers
```

**Bounded Contexts**: Tenant, Catalog, Order, Inventory, Pricing, Customer, Payment, Tax, Returns, Notifications

See [`CLAUDE.md`](../CLAUDE.md) for detailed architecture patterns and implementation guides.

---

## Getting Started

### For New Developers

1. **Architecture Overview**:
   - Read [`CLAUDE.md`](../CLAUDE.md) (project architecture, patterns, do's and don'ts)
   - Review [DDD Patterns Summary](architecture/ddd-patterns-summary.md)
   - Review [Testing Guide](guides/testing-guide.md)
   - Understand multi-tenancy concepts

2. **Setup Local Environment**:
   - Follow [Monitoring Setup](devops/prometheus-grafana.md)
   - Configure [RabbitMQ Workers](devops/rabbitmq-worker.md)
   - Run `composer install && php bin/console doctrine:migrations:migrate`

3. **Explore APIs**:
   - Open http://localhost:8000/api/docs (OpenAPI)
   - Try [Storefront API](api/storefront.md) examples
   - Test with Postman/Insomnia

4. **Write Your First Feature**:
   - Follow [Creating a New Aggregate](guides/new-aggregate.md) guide
   - Write tests first (TDD) - see [Testing Guide](guides/testing-guide.md)
   - Use `symfony console make:` commands for scaffolding

### For Frontend Developers

**Start Here**:
1. [API Hub](api/README.md) - API overview
2. [Storefront API](api/storefront.md) - Public catalog API
3. [Order API](api/order.md) - Order placement & tracking
4. [Cart API](api/cart.md) - Shopping cart management

**API Base URL**: `http://localhost:8000/api`

**Required Headers**:
```http
X-Tenant-ID: {tenant-uuid}
Content-Type: application/json
Authorization: Bearer {jwt-token}
```

### For DevOps Engineers

**Start Here**:
1. [Monitoring](devops/monitoring.md) - Complete observability stack
2. [Deployment Checklist](deployment/production-checklist.md)
3. [RabbitMQ Worker Setup](devops/rabbitmq-worker.md)

**Key Services**:
- **Symfony API**: Port 8000
- **PostgreSQL**: Port 5432
- **Redis**: Port 6379
- **RabbitMQ**: Ports 5672, 15672
- **Elasticsearch**: Port 9200
- **Prometheus**: Port 9090
- **Grafana**: Port 3002

---

## Common Tasks

### Add New API Endpoint

1. Create domain model in `{Context}/Domain/Model/`
2. Create Doctrine entity in `{Context}/Infrastructure/Persistence/Doctrine/Entity/`
3. Create API resource in `{Context}/Infrastructure/ApiPlatform/Resource/`
4. Create state processor in `{Context}/Infrastructure/ApiPlatform/State/`
5. Write tests in `tests/Functional/Api/`

See [Creating a New Aggregate](guides/new-aggregate.md) guide

### Configure New Payment Gateway

1. Read [Payment Integration Guide](integration/payment-integration.md)
2. Implement `PaymentGatewayInterface` in `src/Payment/Infrastructure/Gateway/`
3. Add credentials to `.env`
4. Update `PaymentGatewayFactory`
5. Add test script to `scripts/`

### Add Custom Metrics

1. Create metrics collector in `{Context}/Infrastructure/Metrics/`
2. Register in `services.yaml`
3. Expose via Prometheus endpoint
4. Create Grafana dashboard
5. Define alert rules in `config/prometheus/alerts.yml`

See [Monitoring Guide](devops/monitoring.md)

### Debug Production Issue

1. Check **Grafana Dashboards**: http://localhost:3002
2. Query **Prometheus**: http://localhost:9090
3. View **RabbitMQ Queues**: http://localhost:15672
4. Check **Logs**: `tail -f var/log/*.log`
5. Review **Sentry/Error Tracking** (if configured)

---

## Best Practices

### Documentation

- **Keep docs updated**: Update docs when code changes
- **Link to code**: Reference file paths and line numbers
- **Add examples**: Show actual API requests/responses
- **Version docs**: Track major changes with dates

### API Design

- **RESTful**: Follow REST principles
- **Versioning**: Use `/api/v1/` for breaking changes
- **Pagination**: Always paginate collections
- **Filtering**: Support `?tenant={id}`, `?status={value}`
- **Errors**: Return RFC 7807 Problem Details

### Testing

- **Unit tests**: Domain models only (no framework)
- **Integration tests**: Repositories with real database
- **Functional tests**: Full HTTP request/response cycle
- **Coverage target**: ≥80% global, ≥90% critical paths

### Deployment

- **Migrations first**: Run migrations before code deploy
- **Zero-downtime**: Use blue-green or rolling deployments
- **Health checks**: Monitor `/health` endpoint
- **Rollback plan**: Always have rollback procedure

---

## Support

### Internal Resources

- **Architecture**: [`CLAUDE.md`](../CLAUDE.md)
- **API Docs**: http://localhost:8001/api/docs
- **Monitoring**: http://localhost:3002 (Grafana)
- **Issue Tracker**: GitHub Issues

### External Resources

- **Symfony**: https://symfony.com/doc/current/
- **API Platform**: https://api-platform.com/docs/
- **Doctrine**: https://www.doctrine-project.org/projects/doctrine-orm/en/current/
- **PostgreSQL**: https://www.postgresql.org/docs/
- **Prometheus**: https://prometheus.io/docs/

---

## Contributing

### Documentation Updates

1. Keep this README.md updated when adding/removing docs
2. Follow existing document structure
3. Use clear, concise language
4. Add code examples where helpful
5. Update "Last Updated" dates

### Document Template

For new documentation, use this structure:

```markdown
# Document Title

**Version**: 1.0
**Last Updated**: YYYY-MM-DD
**Audience**: [Backend/Frontend/DevOps/All]

## Overview
Brief description...

## Table of Contents
1. [Section 1](#section-1)
2. [Section 2](#section-2)

## Content sections...

## Support
Links to related docs...

---
**Document maintained by**: [Team Name]
**Last reviewed**: [Date]
```

---

## Document Statistics

- **Total Documents**: ~50+
- **Architecture**: 1
- **Business**: 1
- **API Docs**: 5
- **Integration**: 2
- **DevOps Guides**: 3
- **Developer Guides**: 3
- **Deployment**: 1
- **Privacy**: 1
- **Reference**: ~30+ (Symfony, API Platform, Libraries)

---

**Documentation maintained by**: Engineering Team
**Last full audit**: November 6, 2025
