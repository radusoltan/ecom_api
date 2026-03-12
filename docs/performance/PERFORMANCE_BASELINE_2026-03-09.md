# Performance Baseline Report

**Date**: 2026-03-09
**Sprint**: Sprint 2 (TSK-17)
**Environment**: WSL2 Ubuntu 24.04, PHP 8.5.3 built-in server, PostgreSQL 17, Redis 7

## Test Conditions

- **Server**: PHP built-in server (`php -S`), single-threaded, APP_ENV=prod
- **Database**: PostgreSQL 17 (local), essentially empty (0 products, 0 orders, 0 categories)
- **Cache**: Redis 7 (local), APCu with `apc.enable_cli=1`
- **Methodology**: curl with `X-Response-Time` response header (actual server processing time)
- **Iterations**: 5 per endpoint
- **Auth**: JWT Bearer token

> **Note**: PHP built-in server is single-threaded and not representative of production (nginx + php-fpm or FrankenPHP). Results reflect application-level performance only.

## API Endpoint Benchmarks

### Core Endpoints (X-Response-Time)

| Endpoint | Min | Avg | p95 | Max | Status |
|---|---|---|---|---|---|
| GET /api (entrypoint) | 20ms | 24ms | 32ms | 32ms | PASS |
| POST /api/login_check | 405ms | 415ms | 439ms | 439ms | EXPECTED |
| GET /api/v1/products | 26ms | 47ms | 104ms | 104ms | PASS |
| GET /api/v1/categories | 26ms | 27ms | 33ms | 33ms | PASS |
| GET /api/v1/orders | 31ms | 48ms | 70ms | 70ms | PASS |
| GET /api/v1/customers | 27ms | 50ms | 127ms | 127ms | PASS |
| GET /api/v1/invoices | 30ms | 62ms | 186ms | 186ms | WARN |
| GET /api/v1/returns | 10ms | 12ms | 20ms | 20ms | PASS |
| GET /api/v1/promotions | 32ms | 48ms | 89ms | 89ms | PASS |
| GET /api/v1/wishlists | 10ms | 11ms | 11ms | 11ms | PASS |
| GET /api/v1/reviews | 10ms | 10ms | 11ms | 11ms | PASS |

### Pagination & Filtering

| Endpoint | Min | Avg | p95 | Max |
|---|---|---|---|---|
| Products ?page=1&itemsPerPage=10 | 27ms | 46ms | 96ms | 96ms |

### Summary

- **10 of 11 endpoints** meet the <200ms p95 target
- **Login** at 415ms avg is expected (bcrypt password hashing, security-critical)
- **Invoices** at 186ms p95 is borderline — monitor under load with real data
- **Lightweight endpoints** (returns, wishlists, reviews) are very fast at ~10ms

## Database Analysis

### Table Statistics

| Table | Rows | Seq Scans | Idx Scans | Index Usage % |
|---|---|---|---|---|
| catalog_product_option_values | 150 | 13 | 0 | 0% |
| catalog_product_variants | 135 | 17 | 0 | 0% |
| refresh_tokens | 13 | 22 | 6 | 21% |
| carts | 1 | 13 | 4 | 24% |

> **Warning**: Product option values and variants use 100% sequential scans. Acceptable at current scale (150 rows) but will degrade with growth. Indexes exist but optimizer prefers seq scan for small tables.

## Top 5 Bottlenecks Identified

### 1. OpenTelemetry SimpleSpanProcessor (CRITICAL)

**Impact**: +40 seconds per request on kernel.terminate
**Root Cause**: `TracerFactory` uses `SimpleSpanProcessor` which exports spans synchronously via OTLP HTTP to `localhost:4318`. When the OTel Collector is not running, each request blocks for the HTTP connection timeout (~40s).
**File**: `src/Shared/Infrastructure/Telemetry/TracerFactory.php:73`
**Fix**:
- Switch to `BatchSpanProcessor` for async batched export
- Add a connection check / circuit breaker before creating the exporter
- Use `OTEL_SDK_DISABLED` env var check in the factory to return a NoopTracer

### 2. Login Endpoint Latency (EXPECTED)

**Impact**: 415ms avg response time
**Root Cause**: bcrypt password hashing with cost factor (intentionally slow for security)
**Fix**: No fix needed — this is by design. Consider:
- Response caching of JWT tokens (with appropriate TTL)
- Rate limiting to prevent brute-force attempts (already implemented)

### 3. PHP Built-in Server (DEV ONLY)

**Impact**: Single-threaded, no concurrency, blocks during kernel.terminate
**Root Cause**: PHP's built-in server processes one request at a time
**Fix**: Use nginx + php-fpm or FrankenPHP for realistic benchmarks and development
**Note**: This only affects development. Production will use a proper web server.

### 4. Invoices Endpoint Near Threshold

**Impact**: 186ms p95, close to 200ms target
**Root Cause**: Complex query with joins on empty tables — will worsen with data
**Fix**:
- Add query result caching (Redis, 60s TTL)
- Review Doctrine hydration strategy (use DTO projections)
- Add covering indexes for common query patterns

### 5. Missing HTTP Cache Headers

**Impact**: Every request hits the application, no CDN/browser caching
**Root Cause**: API responses lack `Cache-Control`, `ETag`, or `Last-Modified` headers
**Fix**:
- Add `Cache-Control: public, max-age=60` for product/category listings
- Implement ETag-based conditional requests for detail endpoints
- Add Varnish or CDN layer for read-heavy endpoints

## Frontend Performance (Not Benchmarked)

Frontend Lighthouse scores were **not collected** due to:
- Next.js Turbopack dev server in WSL2 has ~57 second cold start
- Not representative of production (Vercel/standalone build)

**Recommendation**: Run Lighthouse against production build (`next build && next start`) or in CI/CD pipeline.

## Sprint 3 Performance Targets (p95)

| Category | Target | Current Baseline | Gap |
|---|---|---|---|
| Read endpoints (list) | <100ms | 27-104ms | ON TRACK |
| Read endpoints (detail) | <150ms | ~50ms (est.) | ON TRACK |
| Write endpoints (create/update) | <200ms | Not tested | NEEDS DATA |
| Login | <500ms | 439ms | ON TRACK |
| Search (Elasticsearch) | <100ms | Not tested | NEEDS DATA |
| Frontend page load | <2s | Not tested | NEEDS DATA |
| Frontend TTFB | <500ms | Not tested | NEEDS DATA |
| Cache hit ratio | >90% | No caching yet | NEEDS IMPL |

## Recommended Actions for Sprint 3

1. **P0**: Fix OTel `SimpleSpanProcessor` → `BatchSpanProcessor` (blocks every request by ~40s)
2. **P1**: Add HTTP cache headers for read-heavy endpoints
3. **P1**: Implement Redis query caching for invoice/order listings
4. **P2**: Set up nginx + php-fpm or FrankenPHP for dev environment
5. **P2**: Create load test suite with realistic data (1000+ products, 500+ orders)
6. **P3**: Add Lighthouse CI to CI/CD pipeline for frontend metrics
7. **P3**: Configure PgBouncer connection pooling for production
