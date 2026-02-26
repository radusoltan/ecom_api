/**
 * K6 Stress Test - Push Beyond Limits
 *
 * Progressively increases load to find the system's breaking point.
 * Ramps from 100 to 500 VUs to identify at what point the system degrades.
 *
 * Usage:
 *   k6 run tests/Performance/k6/stress_test.js
 *
 * What to look for:
 *   - At what VU count does p95 exceed 200ms?
 *   - At what VU count does error rate exceed 1%?
 *   - Does the system recover after load decreases?
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
    stages: profiles.stress.stages,
    thresholds: {
        ...thresholds.stress,
        'error_rate': ['rate<0.05'],         // 5% error rate acceptable under stress
        'api_duration': ['p(95)<500'],       // Relaxed latency target
        'search_duration': ['p(95)<300'],
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
        'reports/stress-test.json': JSON.stringify(data, null, 2),
        'reports/stress-test.html': generateHtmlReport(data, 'Stress Test Report'),
    };
}
