/**
 * K6 Smoke Test - Quick API Health Check
 *
 * Validates all critical API endpoints are responding correctly
 * with minimal load (1 VU). Run BEFORE full load tests.
 *
 * Usage:
 *   k6 run tests/Performance/k6/smoke_test.js
 *   k6 run -e BASE_URL=http://staging:8000 tests/Performance/k6/smoke_test.js
 */

import { group, sleep, check } from 'k6';
import { apiGet, apiPost, authenticate, parseResponse, generateTextSummary } from './helpers.js';
import { thresholds } from './config.js';

export const options = {
    vus: 1,
    duration: '1m',
    thresholds: thresholds.smoke,
};

export default function () {
    // 1. Public endpoints
    group('Smoke - Public Endpoints', function () {
        let res = apiGet('/product_entities', { name: 'smoke: products' });
        check(res, {
            'products - 200': (r) => r.status === 200,
            'products - has members': (r) => {
                const { members } = parseResponse(r);
                return members !== undefined;
            },
        });

        res = apiGet('/category_entities', { name: 'smoke: categories' });
        check(res, { 'categories - 200': (r) => r.status === 200 });

        res = apiGet('/storefront/featured-products', { name: 'smoke: featured' });
        check(res, { 'featured products - 2xx': (r) => r.status < 300 });

        res = apiGet('/storefront/home-categories', { name: 'smoke: home categories' });
        check(res, { 'home categories - 2xx': (r) => r.status < 300 });

        res = apiGet('/warehouses', { name: 'smoke: warehouses' });
        check(res, { 'warehouses - 200': (r) => r.status === 200 });

        res = apiGet('/price_lists', { name: 'smoke: price lists' });
        check(res, { 'price lists - 200': (r) => r.status === 200 });

        res = apiGet('/stock-items', { name: 'smoke: stock items' });
        check(res, { 'stock items - 200': (r) => r.status === 200 });

        res = apiGet('/tax_rules', { name: 'smoke: tax rules' });
        check(res, { 'tax rules - 200': (r) => r.status === 200 });

        res = apiGet('/tenants', { name: 'smoke: tenants' });
        check(res, { 'tenants - 200': (r) => r.status === 200 });

        res = apiGet('/locales', { name: 'smoke: locales' });
        check(res, { 'locales - 2xx': (r) => r.status < 300 });

        sleep(1);
    });

    // 2. Search endpoints
    group('Smoke - Search', function () {
        let res = apiGet('/search/products?q=laptop', { type: 'search', name: 'smoke: search' });
        check(res, { 'search - 2xx': (r) => r.status < 300 });

        res = apiGet('/search/autocomplete?q=lap', { type: 'search', name: 'smoke: autocomplete' });
        check(res, { 'autocomplete - 2xx': (r) => r.status < 300 });

        sleep(1);
    });

    // 3. Authentication
    group('Smoke - Authentication', function () {
        const auth = authenticate();
        check(auth, { 'auth - got token': (a) => a !== null && a.token !== undefined });

        if (auth && auth.token) {
            let res = apiGet('/profile', { token: auth.token, name: 'smoke: profile' });
            check(res, { 'profile - 2xx': (r) => r.status < 300 });

            res = apiGet('/orders', { token: auth.token, name: 'smoke: orders' });
            check(res, { 'orders - 2xx': (r) => r.status < 300 });

            res = apiGet('/invoices', { token: auth.token, name: 'smoke: invoices' });
            check(res, { 'invoices - 2xx': (r) => r.status < 300 });

            res = apiGet('/notifications', { token: auth.token, name: 'smoke: notifications' });
            check(res, { 'notifications - 2xx': (r) => r.status < 300 });

            res = apiGet('/customers', { token: auth.token, name: 'smoke: customers' });
            check(res, { 'customers - 2xx': (r) => r.status < 300 });
        }

        sleep(1);
    });

    // 4. Cart endpoints
    group('Smoke - Cart', function () {
        let res = apiGet('/cart', { name: 'smoke: cart' });
        check(res, { 'cart - responds': (r) => r.status < 500 });

        sleep(1);
    });

    // 5. Storefront endpoints
    group('Smoke - Storefront', function () {
        let res = apiGet('/storefront/reviews', { name: 'smoke: reviews' });
        check(res, { 'reviews - 2xx': (r) => r.status < 300 });

        sleep(1);
    });

    // 6. Monitoring / health
    group('Smoke - Monitoring', function () {
        let res = apiGet('/monitoring/health', { name: 'smoke: health' });
        check(res, { 'health - responds': (r) => r.status < 500 });

        sleep(1);
    });
}

export function handleSummary(data) {
    const passed = data.metrics.checks ? data.metrics.checks.values.passes : 0;
    const failed = data.metrics.checks ? data.metrics.checks.values.fails : 0;
    const total = passed + failed;

    console.log('\n=== Smoke Test Results ===');
    console.log(`Checks: ${passed}/${total} passed (${total > 0 ? (passed / total * 100).toFixed(1) : 0}%)`);

    if (failed === 0) {
        console.log('  All endpoints healthy. Ready for load testing.');
    } else {
        console.log(`  ${failed} check(s) failed. Fix issues before load testing.`);
    }

    return {
        'stdout': generateTextSummary(data),
        'reports/smoke-test.json': JSON.stringify(data, null, 2),
    };
}
