/**
 * K6 Spike Test - Sudden Traffic Burst
 *
 * Simulates flash sale or viral event: sudden traffic spike from 10 to 500 VUs.
 * Tests the system's ability to handle sudden load increases and recover.
 *
 * Usage:
 *   k6 run tests/Performance/k6/spike_test.js
 *
 * What to look for:
 *   - How does the system handle the sudden spike?
 *   - How quickly does it recover after the spike?
 *   - Are there any cascading failures?
 */

import { sleep } from 'k6';
import { thresholds, profiles, scenarioWeights } from './config.js';
import { selectWeightedScenario, generateTextSummary, generateHtmlReport } from './helpers.js';
import catalogBrowsing from './scenarios/catalog.js';
import productSearch from './scenarios/search.js';
import cartOperations from './scenarios/cart.js';
import authenticationScenario from './scenarios/auth.js';
import checkoutFlow from './scenarios/checkout.js';
import inventoryCheck from './scenarios/inventory.js';

export const options = {
    stages: profiles.spike.stages,
    thresholds: {
        'http_req_duration': ['p(95)<1000'],  // Very relaxed during spike
        'http_req_failed': ['rate<0.10'],     // Allow 10% errors during spike
        'error_rate': ['rate<0.10'],
    },
};

// Spike test focuses on high-traffic scenarios (catalog + search + cart)
const scenarios = [
    { weight: 40, fn: catalogBrowsing },
    { weight: 25, fn: productSearch },
    { weight: 20, fn: cartOperations },
    { weight: 10, fn: authenticationScenario },
    { weight: 5, fn: inventoryCheck },
];

export default function () {
    const scenarioFn = selectWeightedScenario(scenarios);
    scenarioFn();
}

export function handleSummary(data) {
    return {
        'stdout': generateTextSummary(data),
        'reports/spike-test.json': JSON.stringify(data, null, 2),
        'reports/spike-test.html': generateHtmlReport(data, 'Spike Test Report (Flash Sale Simulation)'),
    };
}
