/**
 * K6 API Load Test (Legacy - Simplified)
 *
 * Quick load test for read-only API endpoints.
 * For comprehensive testing, use load_test.js instead.
 *
 * Usage:
 *   k6 run tests/Performance/k6/api_load_test.js
 *   k6 run --vus 50 --duration 5m tests/Performance/k6/api_load_test.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const errorRate = new Rate('errors');
const apiDuration = new Trend('api_duration');

export const options = {
    stages: [
        { duration: '1m', target: 50 },
        { duration: '3m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '2m', target: 200 },
        { duration: '2m', target: 100 },
        { duration: '2m', target: 0 },
    ],
    thresholds: {
        'http_req_duration': ['p(95)<200'],
        'http_req_duration{type:api}': ['p(95)<200'],
        'http_req_duration{type:search}': ['p(95)<100'],
        'http_req_failed': ['rate<0.001'],
        'errors': ['rate<0.001'],
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const TENANT_ID = __ENV.TENANT_ID || '00000000-0000-4000-8000-000000000001';

const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/ld+json',
    'X-Tenant-ID': TENANT_ID,
};

function parseMembers(response) {
    try {
        const data = JSON.parse(response.body);
        return data['hydra:member'] || data['member'] || [];
    } catch (e) {
        return [];
    }
}

// Scenario 1: Product Browsing (60%)
function productBrowsing() {
    group('Product Browsing', function () {
        let response = http.get(`${BASE_URL}/api/v1/product_entities`, {
            headers, tags: { type: 'api' },
        });

        const ok = check(response, {
            'product list - 200': (r) => r.status === 200,
            'product list - < 200ms': (r) => r.timings.duration < 200,
        });
        errorRate.add(!ok);
        apiDuration.add(response.timings.duration);

        const products = parseMembers(response);
        if (products.length > 0 && products[0].id) {
            response = http.get(`${BASE_URL}/api/v1/product_entities/${products[0].id}`, {
                headers, tags: { type: 'api' },
            });
            check(response, {
                'product detail - 200': (r) => r.status === 200,
            });
            apiDuration.add(response.timings.duration);
        }

        sleep(1);
    });
}

// Scenario 2: Product Search (30%)
function productSearch() {
    group('Product Search', function () {
        const terms = ['laptop', 'phone', 'tablet', 'monitor', 'keyboard'];
        const term = terms[Math.floor(Math.random() * terms.length)];

        const response = http.get(`${BASE_URL}/api/v1/search/products?q=${term}`, {
            headers, tags: { type: 'search' },
        });

        const ok = check(response, {
            'search - 2xx': (r) => r.status >= 200 && r.status < 300,
            'search - < 100ms': (r) => r.timings.duration < 100,
        });
        errorRate.add(!ok);
        apiDuration.add(response.timings.duration);

        sleep(0.5);
    });
}

// Scenario 3: Category Navigation (5%)
function categoryNavigation() {
    group('Category Navigation', function () {
        const response = http.get(`${BASE_URL}/api/v1/category_entities`, {
            headers, tags: { type: 'api' },
        });
        check(response, { 'categories - 200': (r) => r.status === 200 });
        sleep(1);
    });
}

// Scenario 4: Warehouse Lookup (3%)
function warehouseLookup() {
    group('Warehouse Lookup', function () {
        const response = http.get(`${BASE_URL}/api/v1/warehouses`, {
            headers, tags: { type: 'api' },
        });
        check(response, { 'warehouses - 200': (r) => r.status === 200 });
        sleep(2);
    });
}

// Scenario 5: Price Lists (2%)
function priceListCheck() {
    group('Price Lists', function () {
        const response = http.get(`${BASE_URL}/api/v1/price_lists`, {
            headers, tags: { type: 'api' },
        });
        check(response, { 'price_lists - 200': (r) => r.status === 200 });
        sleep(2);
    });
}

// Weighted scenario selection
export default function () {
    const scenarios = [
        { weight: 60, fn: productBrowsing },
        { weight: 30, fn: productSearch },
        { weight: 5, fn: categoryNavigation },
        { weight: 3, fn: warehouseLookup },
        { weight: 2, fn: priceListCheck },
    ];

    const rand = Math.random() * 100;
    let cumulative = 0;
    for (const s of scenarios) {
        cumulative += s.weight;
        if (rand <= cumulative) { s.fn(); return; }
    }
    productBrowsing();
}
