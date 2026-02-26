# K6 Load Testing Suite

E-Commerce Platform load testing with [k6](https://k6.io/) v1.6+.

## Quick Start

```bash
# Install k6
curl -s https://dl.k6.io/key.gpg | sudo gpg --dearmor -o /usr/share/keyrings/k6-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update && sudo apt-get install k6

# Run smoke test first
k6 run tests/Performance/k6/smoke_test.js

# Run full load test
k6 run tests/Performance/k6/load_test.js
```

## Test Types

| Script | VUs | Duration | Purpose |
|--------|-----|----------|---------|
| `smoke_test.js` | 1 | 1m | Health check - all endpoints respond correctly |
| `load_test.js` | 50→200 | 15m | Standard load - validate PRD performance targets |
| `stress_test.js` | 100→500 | 21m | Find breaking point - push beyond expected load |
| `spike_test.js` | 10→500 | 4.5m | Flash sale simulation - sudden traffic burst |
| `soak_test.js` | 50 | 34m | Memory leaks & degradation - extended duration |
| `api_load_test.js` | 50→200 | 15m | Legacy - read-only endpoints only |

## Scenarios (Traffic Distribution)

| Scenario | Weight | Endpoints |
|----------|--------|-----------|
| Catalog Browsing | 35% | Products, categories, featured, storefront |
| Product Search | 20% | ES full-text search, autocomplete, filtered |
| Cart Operations | 20% | Add/update/remove items, apply coupon |
| Authentication | 10% | Login, token refresh, registration |
| Checkout Flow | 5% | Full flow: browse → cart → checkout |
| Customer Profile | 5% | Profile, addresses, orders, invoices |
| Inventory Check | 3% | Stock availability, warehouses |
| Reviews/Wishlist | 2% | Product reviews, ratings, wishlist |

## Performance Targets (PRD Section 9.1)

| Metric | Target | Threshold |
|--------|--------|-----------|
| API Response (p95) | < 200ms | `http_req_duration{type:api}: p(95)<200` |
| Search Response (p95) | < 100ms | `http_req_duration{type:search}: p(95)<100` |
| Checkout (p95) | < 500ms | `checkout_duration: p(95)<500` |
| Error Rate | < 0.1% | `http_req_failed: rate<0.001` |

## Configuration

Override via environment variables:

```bash
k6 run -e BASE_URL=http://staging:8000 \
       -e TENANT_ID=00000000-0000-4000-8000-000000000001 \
       -e AUTH_EMAIL=admin@example.com \
       -e AUTH_PASSWORD=admin123 \
       tests/Performance/k6/load_test.js
```

## Reports

Tests generate reports in `reports/` directory:
- `{test-type}.json` - Raw k6 metrics data
- `{test-type}.html` - Visual HTML report

## File Structure

```
k6/
├── config.js              # Shared configuration
├── helpers.js             # Utility functions & custom metrics
├── smoke_test.js          # Quick health check
├── load_test.js           # Standard load test
├── stress_test.js         # Breaking point test
├── spike_test.js          # Flash sale simulation
├── soak_test.js           # Extended duration test
├── api_load_test.js       # Legacy read-only test
├── scenarios/
│   ├── catalog.js         # Product/category browsing
│   ├── search.js          # ES search & autocomplete
│   ├── cart.js            # Cart CRUD operations
│   ├── auth.js            # Authentication flows
│   ├── checkout.js        # Full checkout flow
│   ├── customer.js        # Profile & orders
│   ├── inventory.js       # Stock & warehouses
│   └── reviews.js         # Reviews & wishlist
└── reports/               # Generated test reports
```

## Multi-Tenant Testing

All requests include `X-Tenant-ID` header. Default test tenant:
`00000000-0000-4000-8000-000000000001`

RLS (Row-Level Security) is enforced on all tenant-scoped tables.
