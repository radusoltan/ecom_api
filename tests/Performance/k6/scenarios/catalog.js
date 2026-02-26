/**
 * Catalog Browsing Scenario
 *
 * Simulates users browsing products and categories.
 * Traffic weight: 35%
 */

import { group, sleep } from 'k6';
import { apiGet, parseResponse, randomItem, randomInt, errorRate } from '../helpers.js';

const SEARCH_TERMS = ['laptop', 'phone', 'tablet', 'monitor', 'keyboard', 'mouse', 'headphones', 'camera', 'speaker', 'watch'];

/**
 * Browse products with pagination
 */
export function browseProducts() {
    group('Catalog - Browse Products', function () {
        const page = randomInt(1, 3);
        const response = apiGet(`/product_entities?page=${page}&itemsPerPage=20`, {
            name: 'GET /product_entities',
        });

        const { members } = parseResponse(response);

        if (members.length > 0) {
            // View a random product detail
            const product = randomItem(members);
            if (product && product.id) {
                apiGet(`/product_entities/${product.id}`, {
                    name: 'GET /product_entities/{id}',
                });
            }
        }

        sleep(randomInt(1, 3));
    });
}

/**
 * Browse categories
 */
export function browseCategories() {
    group('Catalog - Browse Categories', function () {
        apiGet('/category_entities', {
            name: 'GET /category_entities',
        });

        sleep(randomInt(1, 2));
    });
}

/**
 * Storefront featured products (homepage)
 */
export function viewFeaturedProducts() {
    group('Catalog - Featured Products', function () {
        apiGet('/storefront/featured-products', {
            name: 'GET /storefront/featured-products',
        });

        apiGet('/storefront/home-categories', {
            name: 'GET /storefront/home-categories',
        });

        sleep(randomInt(1, 3));
    });
}

/**
 * Combined catalog browsing flow
 */
export default function catalogBrowsing() {
    const rand = Math.random();

    if (rand < 0.4) {
        browseProducts();
    } else if (rand < 0.7) {
        viewFeaturedProducts();
    } else {
        browseCategories();
    }
}
