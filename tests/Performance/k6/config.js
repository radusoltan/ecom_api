/**
 * Shared K6 Configuration
 *
 * Central configuration for all k6 test scripts.
 * Override via environment variables:
 *   k6 run -e BASE_URL=http://staging:8000 -e TENANT_ID=xxx script.js
 */

// Environment
export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';
export const TENANT_ID = __ENV.TENANT_ID || '00000000-0000-4000-8000-000000000001';
export const API_PREFIX = '/api/v1';

// Auth credentials (test user)
export const AUTH_EMAIL = __ENV.AUTH_EMAIL || 'admin@example.com';
export const AUTH_PASSWORD = __ENV.AUTH_PASSWORD || 'admin123';

// Default headers (public endpoints)
export const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/ld+json',
    'X-Tenant-ID': TENANT_ID,
};

// Performance thresholds from PRD Section 9.1
export const thresholds = {
    api: {
        'http_req_duration{type:api}': ['p(95)<200'],        // API p95 < 200ms
        'http_req_duration{type:search}': ['p(95)<100'],     // Search p95 < 100ms
        'http_req_duration{type:auth}': ['p(95)<300'],       // Auth p95 < 300ms
        'http_req_duration{type:checkout}': ['p(95)<500'],   // Checkout p95 < 500ms
        'http_req_failed': ['rate<0.001'],                   // Error rate < 0.1%
    },
    smoke: {
        'http_req_duration': ['p(95)<500'],
        'http_req_failed': ['rate<0.01'],
    },
    stress: {
        'http_req_duration': ['p(95)<500'],
        'http_req_failed': ['rate<0.05'],                    // Allow 5% errors under stress
    },
    soak: {
        'http_req_duration': ['p(95)<300'],
        'http_req_failed': ['rate<0.001'],
    },
};

// Load profiles
export const profiles = {
    smoke: {
        vus: 1,
        duration: '30s',
    },
    load: {
        stages: [
            { duration: '1m', target: 50 },     // Ramp up
            { duration: '3m', target: 100 },    // Ramp to peak
            { duration: '5m', target: 100 },    // Sustain peak
            { duration: '2m', target: 200 },    // Spike
            { duration: '2m', target: 100 },    // Scale back
            { duration: '2m', target: 0 },      // Ramp down
        ],
    },
    stress: {
        stages: [
            { duration: '2m', target: 100 },
            { duration: '5m', target: 200 },
            { duration: '2m', target: 300 },
            { duration: '5m', target: 400 },    // Push beyond limits
            { duration: '2m', target: 500 },    // Breaking point
            { duration: '5m', target: 0 },      // Recovery
        ],
    },
    spike: {
        stages: [
            { duration: '30s', target: 10 },    // Baseline
            { duration: '10s', target: 500 },   // Spike!
            { duration: '1m', target: 500 },    // Sustain spike
            { duration: '10s', target: 10 },    // Drop back
            { duration: '2m', target: 10 },     // Recovery
            { duration: '30s', target: 0 },     // Ramp down
        ],
    },
    soak: {
        stages: [
            { duration: '2m', target: 50 },     // Ramp up
            { duration: '30m', target: 50 },    // Sustained load
            { duration: '2m', target: 0 },      // Ramp down
        ],
    },
};

// Scenario weights (traffic distribution)
export const scenarioWeights = {
    catalogBrowsing: 35,     // 35% - Browse products/categories
    productSearch: 20,       // 20% - Search products
    cartOperations: 20,      // 20% - Cart add/update/remove
    authentication: 10,      // 10% - Login/register/token refresh
    checkoutFlow: 5,         // 5%  - Full checkout
    customerProfile: 5,      // 5%  - Profile/addresses/orders
    inventoryCheck: 3,       // 3%  - Stock availability checks
    reviewsWishlist: 2,      // 2%  - Reviews/wishlist
};
