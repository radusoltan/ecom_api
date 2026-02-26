/**
 * Product Search Scenario
 *
 * Simulates users searching products via Elasticsearch.
 * Traffic weight: 20%
 * Target: p95 < 100ms
 */

import { group, sleep, check } from 'k6';
import { apiGet, parseResponse, randomItem, randomInt, errorRate, searchDuration } from '../helpers.js';

const SEARCH_TERMS = ['laptop', 'phone', 'tablet', 'monitor', 'keyboard', 'mouse', 'headphones', 'camera', 'wireless', 'bluetooth'];
const AUTOCOMPLETE_TERMS = ['lap', 'pho', 'tab', 'mon', 'key', 'mou', 'hea', 'cam', 'wir', 'blu'];

/**
 * Full text search
 */
export function fullTextSearch() {
    group('Search - Full Text', function () {
        const term = randomItem(SEARCH_TERMS);

        const response = apiGet(`/search/products?q=${term}`, {
            type: 'search',
            name: 'GET /search/products',
        });

        searchDuration.add(response.timings.duration);

        check(response, {
            'search - status 2xx': (r) => r.status >= 200 && r.status < 300,
            'search - response < 100ms': (r) => r.timings.duration < 100,
        });

        sleep(randomInt(1, 3));
    });
}

/**
 * Autocomplete search (fast, frequent)
 */
export function autocompleteSearch() {
    group('Search - Autocomplete', function () {
        const term = randomItem(AUTOCOMPLETE_TERMS);

        const response = apiGet(`/search/autocomplete?q=${term}`, {
            type: 'search',
            name: 'GET /search/autocomplete',
        });

        searchDuration.add(response.timings.duration);

        check(response, {
            'autocomplete - status 2xx': (r) => r.status >= 200 && r.status < 300,
            'autocomplete - response < 50ms': (r) => r.timings.duration < 50,
        });

        sleep(0.3);
    });
}

/**
 * Search with filters (category, price range)
 */
export function filteredSearch() {
    group('Search - Filtered', function () {
        const term = randomItem(SEARCH_TERMS);
        const minPrice = randomInt(10, 100) * 100;
        const maxPrice = minPrice + randomInt(50, 500) * 100;

        const response = apiGet(`/product_entities?name=${term}&price[gte]=${minPrice}&price[lte]=${maxPrice}`, {
            type: 'search',
            name: 'GET /product_entities?filters',
        });

        searchDuration.add(response.timings.duration);

        check(response, {
            'filtered search - status 2xx': (r) => r.status >= 200 && r.status < 300,
        });

        sleep(randomInt(1, 2));
    });
}

/**
 * Combined search flow
 */
export default function productSearch() {
    const rand = Math.random();

    if (rand < 0.4) {
        fullTextSearch();
    } else if (rand < 0.7) {
        autocompleteSearch();
    } else {
        filteredSearch();
    }
}
