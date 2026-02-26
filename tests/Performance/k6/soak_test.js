/**
 * K6 Soak Test - Extended Duration Load
 *
 * Runs moderate load (50 VUs) for 30+ minutes to detect:
 * - Memory leaks
 * - Connection pool exhaustion
 * - Database connection accumulation
 * - Cache degradation over time
 * - Gradual performance degradation
 *
 * Usage:
 *   k6 run tests/Performance/k6/soak_test.js
 *   k6 run -e SOAK_DURATION=60m tests/Performance/k6/soak_test.js
 *
 * What to look for:
 *   - Does p95 latency increase over time?
 *   - Does error rate increase over time?
 *   - Are there periodic spikes (GC, cron jobs)?
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

const SOAK_DURATION = __ENV.SOAK_DURATION || '30m';

export const options = {
    stages: [
        { duration: '2m', target: 50 },
        { duration: SOAK_DURATION, target: 50 },
        { duration: '2m', target: 0 },
    ],
    thresholds: {
        ...thresholds.soak,
        'error_rate': ['rate<0.001'],
        'api_duration': ['p(95)<300'],
        'search_duration': ['p(95)<150'],
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
        'reports/soak-test.json': JSON.stringify(data, null, 2),
        'reports/soak-test.html': generateHtmlReport(data, 'Soak Test Report (Extended Duration)'),
    };
}
