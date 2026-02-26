/**
 * Inventory Scenario
 *
 * Simulates stock availability checks and warehouse lookups.
 * Traffic weight: 3%
 */

import { group, sleep, check } from 'k6';
import { apiGet, apiPost, parseResponse, randomItem, randomInt } from '../helpers.js';

/**
 * Check stock availability
 */
export function checkStock() {
    group('Inventory - Check Stock', function () {
        // First get a product
        const productsRes = apiGet('/product_entities?itemsPerPage=5', {
            name: 'inventory: GET /product_entities',
        });

        const { members } = parseResponse(productsRes);
        const product = randomItem(members);

        if (product && product.id) {
            const response = apiPost('/stock/check', {
                productId: product.id,
                quantity: randomInt(1, 5),
            }, {
                name: 'POST /stock/check',
            });

            check(response, {
                'stock check - responds': (r) => r.status < 500,
            });
        }

        sleep(randomInt(1, 2));
    });
}

/**
 * View stock items
 */
export function viewStockItems() {
    group('Inventory - Stock Items', function () {
        const response = apiGet('/stock-items?itemsPerPage=20', {
            name: 'GET /stock-items',
        });

        check(response, {
            'stock items - status 2xx': (r) => r.status >= 200 && r.status < 300,
        });

        sleep(randomInt(1, 2));
    });
}

/**
 * View warehouses
 */
export function viewWarehouses() {
    group('Inventory - Warehouses', function () {
        const response = apiGet('/warehouses', {
            name: 'GET /warehouses',
        });

        check(response, {
            'warehouses - status 2xx': (r) => r.status >= 200 && r.status < 300,
        });

        const { members } = parseResponse(response);
        if (members.length > 0) {
            const warehouse = randomItem(members);
            if (warehouse && warehouse.id) {
                apiGet(`/warehouses/${warehouse.id}`, {
                    name: 'GET /warehouses/{id}',
                });
            }
        }

        sleep(randomInt(1, 2));
    });
}

/**
 * Combined inventory flow
 */
export default function inventoryCheck() {
    const rand = Math.random();

    if (rand < 0.4) {
        checkStock();
    } else if (rand < 0.7) {
        viewStockItems();
    } else {
        viewWarehouses();
    }
}
