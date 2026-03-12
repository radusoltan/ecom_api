# Sprint 3 Performance Analysis Report

**Date**: 2026-03-10
**Sprint**: Sprint 3 (TSK-23)
**Environment**: nginx 1.24 + php-fpm 8.5 (WSL2), PostgreSQL 17, Redis 7
**Comparison Reference**: `PERFORMANCE_BASELINE_2026-03-09.md` (Sprint 2)
**Data Volume**: ~336 products, 170 orders, 72 customers per tenant (vs 0 in Sprint 2)

---

## 1. Comparison: Sprint 2 vs Sprint 3

| Endpoint | Sprint 2 Avg | Sprint 3 Avg | Sprint 3 p95 | Change (%) | Status |
|---|---|---|---|---|---|
| POST /api/login_check | 415ms | 396.69ms | 399.64ms | -4.41% | **PASS** |
| GET /api/v1/products | 47ms | 430.02ms | 483.29ms | +814.94% | **FAIL** |
| GET /api/v1/categories | 27ms | 34.88ms | 36.54ms | +29.19% | **PASS** |
| GET /api/v1/orders | 48ms | 281.64ms | 326.77ms | +486.75% | **FAIL** |
| GET /api/v1/customers | 50ms | 67.12ms | 68.87ms | +34.24% | **PASS** |
| GET /api/v1/invoices | 62ms | 87.84ms | 98.78ms | +41.68% | **PASS** |
| GET /api/v1/promotions | 48ms | 51.94ms | 71.51ms | +8.21% | **PASS** |

> **Note**: Sprint 2 was tested with an empty database. Sprint 3 tests use a realistic dataset (~1,000 records total). The sharp increase in Products and Orders latency confirms that the current implementation is highly sensitive to dataset size.

---

## 2. Improvement Analysis

### Positives
- **Login latency**: Improved by 4.4%. Moving from the PHP built-in server to nginx + php-fpm has slightly reduced the overhead of the security stack, despite the expensive bcrypt hashing.
- **Lightweight collections**: Categories, Customers, Invoices, and Promotions remain well under the 100ms target, showing good baseline performance for simple reads.

### Negatives
- **Product Listing Performance**: 430ms is unacceptable for a list of 10 items from a pool of 336. This indicates likely N+1 query patterns or lack of proper indexing for the catalog context.
- **Order Listing Performance**: 281ms is also above the 200ms target. Orders involve multi-table joins (customer, address, items) which are currently not optimized.

---

## 3. Cache Effectiveness

| Metric | Sprint 2 | Sprint 3 |
|---|---|---|
| Cache Configuration | Filesystem (Default) | Redis (Configured, not fully active) |
| Avg Iteration 1 | N/A | ~410ms (Products) |
| Avg Iteration 2-5 | N/A | ~435ms (Products) |
| **Estimated Hit Rate** | **0%** | **0%** |

**Analysis**:
The raw data shows no significant delta between the first and subsequent requests (Iteration 1 is sometimes faster than later ones). This confirms that **Application-level caching (Layer 2) is NOT yet active** for these endpoints. 
`ADR-013` was accepted on 2026-03-10 to address this, but implementation was likely not part of the collected raw data.

---

## 4. Load Test Analysis (GET /api/v1/products)

Throughput remains flat regardless of concurrency, indicating a **complete serialization of requests**.

| Concurrency | Throughput | Mean Latency (per request) | p95 Latency |
|---|---|---|---|
| 10 | 5.92 req/s | 1689ms | 1835ms |
| 50 | 6.06 req/s | 8250ms | 8784ms |
| 100 | 5.87 req/s | 17041ms | 18094ms |

### Key Findings:
1. **Serialization Bottleneck**: The throughput is fixed at ~6 req/s. This suggests that only one PHP-FPM worker is effectively processing requests at a time, or there is a global lock (possibly in the shared database connection or session management).
2. **Linear Latency Growth**: Latency increases linearly with concurrency ($T \approx Concurrency \times 170ms$). This confirms that the system is queueing requests rather than processing them in parallel.
3. **Target Miss**: The p95 target of <200ms is missed by **90x** under load.

---

## 5. Remaining Bottlenecks

1. **Database Sequential Scans**: Reconfirming the Sprint 2 warning — tables like `catalog_product_variants` likely still use sequential scans, which become noticeable at 300+ records.
2. **N+1 Queries**: High latency in Products listing suggests that related data (media, pricing, options) is being fetched per-product instead of using joined queries or DTO projections.
3. **Single-threaded Execution**: The load test throughput suggests a misconfiguration in the PHP-FPM pool or a bottleneck in the Nginx/FPM communication layer.
4. **Missing App-Layer Cache**: Redis is available but `CachedCollectionProvider` is disabled (as per ADR-013).

---

## 6. Recommendations for Sprint 4

### P0: Optimization & Parallelism
1. **Fix PHP-FPM Parallelism**: Audit `php-fpm.conf` and `www.conf`. Ensure `pm.max_children` is > 1 and `pm.start_servers` > 5. Investigate potential resource locks preventing parallel execution.
2. **Implement ADR-013 Layer 2**: Activate `CachedCollectionProvider` using the `cache.api_collections` Redis pool. Targeting >90% hit rate for Product/Category listings.
3. **Database Index Audit**: Add missing indexes identified in `PERFORMANCE_BASELINE_2026-03-09.md` (Product Options/Variants).

### P1: Query Refactoring
1. **Doctrine Projections**: Replace full entity hydration with DTO projections for all listing endpoints (`GET /api/v1/...`).
2. **Eager Loading**: Explicitly join Media and Pricing data in the Product list query to eliminate N+1 issues.

### P2: Infrastructure
1. **FrankenPHP Evaluation**: Consider switching from Nginx + FPM to FrankenPHP (Go-based server) for better performance and easier parallel request handling.
2. **PgBouncer**: Implement connection pooling to reduce DB connection overhead during high concurrency.

---
*Report generated by Gemini CLI for TSK-23.*
