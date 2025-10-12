/**
 * K6 Smoke Test - Quick API Health Check
 *
 * Validates basic API functionality with minimal load.
 * Run this before full load tests to ensure endpoints are working.
 *
 * Usage:
 *   k6 run tests/Performance/k6/smoke_test.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';

export const options = {
    vus: 1,              // 1 virtual user
    duration: '30s',     // Run for 30 seconds
    thresholds: {
        'http_req_duration': ['p(95)<500'],  // Relaxed threshold for smoke test
        'http_req_failed': ['rate<0.01'],    // < 1% errors
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
const TENANT_ID = __ENV.TENANT_ID || 'fd2dffaf-48dc-4966-98a2-4549386209b9';

const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/ld+json',
    'X-Tenant-ID': TENANT_ID,
};

export default function () {
    group('Smoke Test - Critical Endpoints', function () {
        // 1. Products endpoint (actual route: /api/product_entities)
        let response = http.get(`${BASE_URL}/api/product_entities`, { headers });
        check(response, {
            'GET /api/product_entities - status 200': (r) => r.status === 200,
            'GET /api/product_entities - has member array': (r) => {
                try {
                    const body = JSON.parse(r.body);
                    return body['hydra:member'] !== undefined || body['member'] !== undefined;
                } catch (e) {
                    return false;
                }
            },
        });

        // 2. Categories endpoint (actual route: /api/category_entities)
        response = http.get(`${BASE_URL}/api/category_entities`, { headers });
        check(response, {
            'GET /api/category_entities - status 200': (r) => r.status === 200,
        });

        // 3. Tenants endpoint
        response = http.get(`${BASE_URL}/api/tenants`, { headers });
        check(response, {
            'GET /api/tenants - status 200': (r) => r.status === 200,
        });

        // 4. Warehouses endpoint
        response = http.get(`${BASE_URL}/api/warehouses`, { headers });
        check(response, {
            'GET /api/warehouses - status 200': (r) => r.status === 200,
        });

        // 5. Price Lists endpoint
        response = http.get(`${BASE_URL}/api/price_lists`, { headers });
        check(response, {
            'GET /api/price_lists - status 200': (r) => r.status === 200,
        });

        sleep(1);
    });
}

export function handleSummary(data) {
    const passed = data.metrics.checks.values.passes;
    const failed = data.metrics.checks.values.fails;
    const total = passed + failed;
    const passRate = (passed / total * 100).toFixed(2);

    console.log('\n=== Smoke Test Results ===');
    console.log(`Checks: ${passed}/${total} passed (${passRate}%)`);
    console.log(`Duration: ${data.state.testRunDurationMs}ms`);
    console.log(`Requests: ${data.metrics.http_reqs.values.count}`);
    console.log(`Failed: ${data.metrics.http_req_failed.values.count}`);

    if (failed === 0) {
        console.log('✓ All smoke tests passed! Ready for load testing.');
    } else {
        console.log('✗ Some smoke tests failed. Fix issues before load testing.');
    }

    return {
        'stdout': '',
        'smoke-test-results.json': JSON.stringify(data, null, 2),
    };
}
