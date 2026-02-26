/**
 * Cart Operations Scenario
 *
 * Simulates users adding items to cart, updating quantities, and removing items.
 * Traffic weight: 20%
 */

import { group, sleep, check } from 'k6';
import { apiGet, apiPost, apiPatch, apiDelete, parseResponse, randomItem, randomInt, cartDuration } from '../helpers.js';
import { TENANT_ID } from '../config.js';

/**
 * Add item to cart
 */
export function addToCart(productId) {
    group('Cart - Add Item', function () {
        const payload = {
            tenantId: TENANT_ID,
            productId: productId || '01JMCF0H1ZZZZZZZZZZZZZZ001',
            quantity: randomInt(1, 3),
            unitPriceAmount: randomInt(999, 9999),
            unitPriceCurrency: 'USD',
        };

        const response = apiPost('/cart/items', payload, {
            type: 'api',
            name: 'POST /cart/items',
        });

        cartDuration.add(response.timings.duration);

        check(response, {
            'add to cart - status 2xx': (r) => r.status >= 200 && r.status < 300,
            'add to cart - response < 200ms': (r) => r.timings.duration < 200,
        });

        sleep(0.5);
    });
}

/**
 * View cart
 */
export function viewCart() {
    group('Cart - View', function () {
        const response = apiGet('/cart', {
            name: 'GET /cart',
        });

        cartDuration.add(response.timings.duration);

        check(response, {
            'view cart - status 2xx': (r) => r.status >= 200 && r.status < 400,
        });

        sleep(randomInt(1, 2));
    });
}

/**
 * Update cart item quantity
 */
export function updateCartQuantity(itemId) {
    if (!itemId) return;

    group('Cart - Update Quantity', function () {
        const response = apiPatch(`/cart/items/${itemId}`, {
            quantity: randomInt(1, 5),
        }, {
            name: 'PATCH /cart/items/{id}',
        });

        cartDuration.add(response.timings.duration);

        check(response, {
            'update cart - status 2xx': (r) => r.status >= 200 && r.status < 300,
        });

        sleep(0.5);
    });
}

/**
 * Apply coupon to cart
 */
export function applyCoupon() {
    group('Cart - Apply Coupon', function () {
        const response = apiPost('/cart/apply-coupon', {
            code: 'SAVE10',
        }, {
            name: 'POST /cart/apply-coupon',
        });

        cartDuration.add(response.timings.duration);

        // Coupon may not exist - just checking the API responds
        check(response, {
            'apply coupon - responds': (r) => r.status < 500,
        });

        sleep(0.5);
    });
}

/**
 * Clear cart
 */
export function clearCart() {
    group('Cart - Clear', function () {
        const response = apiDelete('/cart', {
            name: 'DELETE /cart',
        });

        cartDuration.add(response.timings.duration);
        sleep(0.5);
    });
}

/**
 * Combined cart operations flow
 */
export default function cartOperations() {
    // First get products to add to cart
    const productsRes = apiGet('/product_entities?itemsPerPage=5', {
        name: 'GET /product_entities (for cart)',
    });

    const { members: products } = parseResponse(productsRes);
    const product = randomItem(products);

    const rand = Math.random();

    if (rand < 0.4) {
        // Add to cart
        addToCart(product ? product.id : null);
    } else if (rand < 0.7) {
        // View cart
        viewCart();
    } else if (rand < 0.85) {
        // Apply coupon
        applyCoupon();
    } else {
        // Clear cart
        clearCart();
    }
}
