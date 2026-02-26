/**
 * K6 Load Test - Standard Load Profile
 *
 * Full load test with realistic traffic distribution across all scenarios.
 * Validates PRD performance targets under expected production load.
 *
 * Usage:
 *   k6 run tests/Performance/k6/load_test.js
 *   k6 run -e BASE_URL=http://staging:8000 tests/Performance/k6/load_test.js
 *   k6 run --out json=reports/load-test-results.json tests/Performance/k6/load_test.js
 *
 * Performance Targets (PRD Section 9.1):
 *   - API p95 < 200ms
 *   - Search p95 < 100ms
 *   - Error rate < 0.1%
 *   - Checkout p95 < 500ms
 */

import { sleep } from 'k6';
import { thresholds, profiles, scenarioWeights } from './config.js';
import { selectWeightedScenario, generateTextSummary, generateHtmlReport } from './helpers.js';
import catalogBrowsing from './scenarios/catalog.js';
import productSearch from './scenarios/search.js';
import cartOperations from './scenarios/cart.js';
import authenticationScenario from './scenarios/auth.js';
import checkoutFlow from './scenarios/checkout.js';
import customerProfile from './scenarios/customer.js';
import inventoryCheck from './scenarios/inventory.js';
import reviewsWishlist from './scenarios/reviews.js';

export const options = {
    stages: profiles.load.stages,
    thresholds: {
        ...thresholds.api,
        'error_rate': ['rate<0.01'],
        'api_duration': ['p(95)<200'],
        'search_duration': ['p(95)<100'],
        'checkout_duration': ['p(95)<500'],
    },
};

const scenarios = [
    { weight: scenarioWeights.catalogBrowsing, fn: catalogBrowsing },
    { weight: scenarioWeights.productSearch, fn: productSearch },
    { weight: scenarioWeights.cartOperations, fn: cartOperations },
    { weight: scenarioWeights.authentication, fn: authenticationScenario },
    { weight: scenarioWeights.checkoutFlow, fn: checkoutFlow },
    { weight: scenarioWeights.customerProfile, fn: customerProfile },
    { weight: scenarioWeights.inventoryCheck, fn: inventoryCheck },
    { weight: scenarioWeights.reviewsWishlist, fn: reviewsWishlist },
];

export default function () {
    const scenarioFn = selectWeightedScenario(scenarios);
    scenarioFn();
}

export function handleSummary(data) {
    return {
        'stdout': generateTextSummary(data),
        'reports/load-test.json': JSON.stringify(data, null, 2),
        'reports/load-test.html': generateHtmlReport(data, 'Load Test Report'),
    };
}
