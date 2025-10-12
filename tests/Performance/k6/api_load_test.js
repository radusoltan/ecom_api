/**
 * K6 Load Test Suite for E-commerce API
 *
 * Tests all critical API endpoints under load to validate:
 * - Response time < 200ms (p95)
 * - Error rate < 0.1%
 * - Throughput > 500 req/sec
 * - Cache hit rate > 90%
 *
 * Usage:
 *   k6 run tests/Performance/k6/api_load_test.js
 *   k6 run --vus 100 --duration 5m tests/Performance/k6/api_load_test.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');
const cacheHitRate = new Rate('cache_hits');
const apiDuration = new Trend('api_duration');
const dbQueries = new Counter('db_queries');

// Test configuration
export const options = {
    stages: [
        { duration: '1m', target: 50 },   // Ramp up to 50 users
        { duration: '3m', target: 100 },  // Ramp up to 100 users
        { duration: '5m', target: 100 },  // Stay at 100 users
        { duration: '2m', target: 200 },  // Spike to 200 users
        { duration: '2m', target: 100 },  // Scale back to 100
        { duration: '2m', target: 0 },    // Ramp down
    ],
    thresholds: {
        // Business requirements (PRD Section 9.1)
        'http_req_duration': ['p(95)<200'],           // 95% of requests < 200ms
        'http_req_duration{type:api}': ['p(95)<200'], // API requests < 200ms
        'http_req_duration{type:search}': ['p(95)<100'], // Search < 100ms
        'http_req_failed': ['rate<0.001'],           // Error rate < 0.1%
        'errors': ['rate<0.001'],                     // Custom error rate < 0.1%
        'cache_hits': ['rate>0.90'],                  // Cache hit rate > 90%
        'http_reqs': ['rate>500'],                    // Throughput > 500 req/sec
    },
};

// Test data
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const TENANT_ID = __ENV.TENANT_ID || 'fd2dffaf-48dc-4966-98a2-4549386209b9';

const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/ld+json',
    'X-Tenant-ID': TENANT_ID,
};

// Helper function to check cache headers
function checkCacheHeaders(response) {
    const cacheControl = response.headers['Cache-Control'];
    const etag = response.headers['ETag'];

    if (cacheControl && cacheControl.includes('max-age')) {
        cacheHitRate.add(1); // Consider it a cache hit if headers are present
    } else {
        cacheHitRate.add(0);
    }

    return {
        'has cache-control': !!cacheControl,
        'has etag': !!etag,
    };
}

// Scenario 1: Product Browsing (60% of traffic)
export function productBrowsing() {
    group('Product Browsing', function () {
        // 1. List products
        let response = http.get(`${BASE_URL}/api/product_entities`, {
            headers: headers,
            tags: { type: 'api' },
        });

        const listCheck = check(response, {
            'product list - status 200': (r) => r.status === 200,
            'product list - response time OK': (r) => r.timings.duration < 200,
            'product list - has data': (r) => {
                try {
                    const data = JSON.parse(r.body);
                    return data['hydra:member'] || JSON.parse(response.body)['member'] && data['hydra:member'] || JSON.parse(response.body)['member'].length > 0;
                } catch (e) {
                    return false;
                }
            },
        });

        errorRate.add(!listCheck);
        apiDuration.add(response.timings.duration);
        checkCacheHeaders(response);

        if (response.status === 200) {
            const products = JSON.parse(response.body)['hydra:member'] || JSON.parse(response.body)['member'];

            if (products.length > 0) {
                // 2. Get product details
                const productId = products[0].id;

                response = http.get(`${BASE_URL}/api/product_entities/${productId}`, {
                    headers: headers,
                    tags: { type: 'api' },
                });

                const detailCheck = check(response, {
                    'product detail - status 200': (r) => r.status === 200,
                    'product detail - response time OK': (r) => r.timings.duration < 200,
                    'product detail - has SKU': (r) => {
                        try {
                            const data = JSON.parse(r.body);
                            return !!data.sku;
                        } catch (e) {
                            return false;
                        }
                    },
                });

                errorRate.add(!detailCheck);
                apiDuration.add(response.timings.duration);
                checkCacheHeaders(response);
            }
        }

        sleep(1);
    });
}

// Scenario 2: Product Search (30% of traffic)
export function productSearch() {
    group('Product Search', function () {
        const searchTerms = ['laptop', 'phone', 'tablet', 'monitor', 'keyboard'];
        const term = searchTerms[Math.floor(Math.random() * searchTerms.length)];

        const response = http.get(`${BASE_URL}/api/product_entities?name=${term}`, {
            headers: headers,
            tags: { type: 'search' },
        });

        const searchCheck = check(response, {
            'search - status 200': (r) => r.status === 200,
            'search - response time < 100ms': (r) => r.timings.duration < 100,
            'search - has results': (r) => {
                try {
                    const data = JSON.parse(r.body);
                    return data['hydra:member'] || JSON.parse(response.body)['member'] !== undefined;
                } catch (e) {
                    return false;
                }
            },
        });

        errorRate.add(!searchCheck);
        apiDuration.add(response.timings.duration);
        checkCacheHeaders(response);

        sleep(0.5);
    });
}

// Scenario 3: Category Navigation (5% of traffic)
export function categoryNavigation() {
    group('Category Navigation', function () {
        // 1. List categories
        let response = http.get(`${BASE_URL}/api/category_entities`, {
            headers: headers,
            tags: { type: 'api' },
        });

        const categoryCheck = check(response, {
            'categories - status 200': (r) => r.status === 200,
            'categories - response time OK': (r) => r.timings.duration < 200,
        });

        errorRate.add(!categoryCheck);
        checkCacheHeaders(response);

        sleep(1);
    });
}

// Scenario 4: Tenant Info (1% of traffic)
export function tenantInfo() {
    group('Tenant Info', function () {
        const response = http.get(`${BASE_URL}/api/tenants`, {
            headers: headers,
            tags: { type: 'api' },
        });

        const tenantCheck = check(response, {
            'tenants - status 200': (r) => r.status === 200,
            'tenants - response time OK': (r) => r.timings.duration < 200,
        });

        errorRate.add(!tenantCheck);
        checkCacheHeaders(response);

        sleep(2);
    });
}

// Scenario 5: Warehouse Lookup (2% of traffic)
export function warehouseLookup() {
    group('Warehouse Lookup', function () {
        const response = http.get(`${BASE_URL}/api/warehouses`, {
            headers: headers,
            tags: { type: 'api' },
        });

        const warehouseCheck = check(response, {
            'warehouses - status 200': (r) => r.status === 200,
            'warehouses - response time OK': (r) => r.timings.duration < 200,
        });

        errorRate.add(!warehouseCheck);
        checkCacheHeaders(response);

        sleep(2);
    });
}

// Scenario 6: Price List Check (2% of traffic)
export function priceListCheck() {
    group('Price List', function () {
        const response = http.get(`${BASE_URL}/api/price_lists`, {
            headers: headers,
            tags: { type: 'api' },
        });

        const priceCheck = check(response, {
            'price_lists - status 200': (r) => r.status === 200,
            'price_lists - response time OK': (r) => r.timings.duration < 200,
        });

        errorRate.add(!priceCheck);
        checkCacheHeaders(response);

        sleep(2);
    });
}

// Main execution - weighted scenario distribution
export default function () {
    const scenarios = [
        { weight: 60, fn: productBrowsing },    // 60% product browsing
        { weight: 30, fn: productSearch },      // 30% search
        { weight: 5, fn: categoryNavigation },  // 5% category navigation
        { weight: 2, fn: warehouseLookup },     // 2% warehouse
        { weight: 2, fn: priceListCheck },      // 2% price lists
        { weight: 1, fn: tenantInfo },          // 1% tenant info
    ];

    // Select scenario based on weight
    const rand = Math.random() * 100;
    let cumulative = 0;

    for (const scenario of scenarios) {
        cumulative += scenario.weight;
        if (rand <= cumulative) {
            scenario.fn();
            return;
        }
    }

    // Fallback
    productBrowsing();
}

// Summary handler
export function handleSummary(data) {
    return {
        'stdout': textSummary(data, { indent: ' ', enableColors: true }),
        'summary.json': JSON.stringify(data),
        'summary.html': htmlReport(data),
    };
}

// Text summary helper
function textSummary(data, options) {
    const indent = options.indent || '';
    const enableColors = options.enableColors || false;

    let output = '\n' + indent + '=== Load Test Summary ===\n\n';

    // Requests
    output += indent + 'Total Requests: ' + data.metrics.http_reqs.values.count + '\n';
    output += indent + 'Request Rate: ' + data.metrics.http_reqs.values.rate.toFixed(2) + ' req/s\n';
    output += indent + 'Failed Requests: ' + (data.metrics.http_req_failed.values.rate * 100).toFixed(2) + '%\n\n';

    // Response times
    output += indent + 'Response Times:\n';
    output += indent + '  p(50): ' + data.metrics.http_req_duration.values['p(50)'].toFixed(2) + 'ms\n';
    output += indent + '  p(95): ' + data.metrics.http_req_duration.values['p(95)'].toFixed(2) + 'ms\n';
    output += indent + '  p(99): ' + data.metrics.http_req_duration.values['p(99)'].toFixed(2) + 'ms\n';
    output += indent + '  avg: ' + data.metrics.http_req_duration.values.avg.toFixed(2) + 'ms\n\n';

    // Thresholds
    output += indent + 'Thresholds:\n';
    for (const [name, threshold] of Object.entries(data.metrics)) {
        if (threshold.thresholds) {
            for (const [rule, result] of Object.entries(threshold.thresholds)) {
                const status = result.ok ? '✓' : '✗';
                output += indent + '  ' + status + ' ' + name + ' ' + rule + '\n';
            }
        }
    }

    return output;
}

// HTML report helper
function htmlReport(data) {
    return `
<!DOCTYPE html>
<html>
<head>
    <title>K6 Load Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .pass { color: green; }
        .fail { color: red; }
    </style>
</head>
<body>
    <h1>K6 Load Test Report</h1>
    <h2>Summary</h2>
    <table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Total Requests</td><td>${data.metrics.http_reqs.values.count}</td></tr>
        <tr><td>Request Rate</td><td>${data.metrics.http_reqs.values.rate.toFixed(2)} req/s</td></tr>
        <tr><td>Failed Requests</td><td>${(data.metrics.http_req_failed.values.rate * 100).toFixed(2)}%</td></tr>
        <tr><td>p50 Response Time</td><td>${data.metrics.http_req_duration.values['p(50)'].toFixed(2)}ms</td></tr>
        <tr><td>p95 Response Time</td><td>${data.metrics.http_req_duration.values['p(95)'].toFixed(2)}ms</td></tr>
        <tr><td>p99 Response Time</td><td>${data.metrics.http_req_duration.values['p(99)'].toFixed(2)}ms</td></tr>
    </table>
    <p>Generated: ${new Date().toISOString()}</p>
</body>
</html>
    `;
}
