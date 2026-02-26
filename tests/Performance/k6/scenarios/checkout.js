/**
 * Checkout Flow Scenario
 *
 * Simulates the full e-commerce checkout: browse -> add to cart -> checkout -> payment.
 * Traffic weight: 5%
 * Target: p95 < 500ms (full flow)
 */

import { group, sleep, check } from 'k6';
import { authenticate, apiGet, apiPost, parseResponse, randomItem, randomInt, checkoutDuration, errorRate } from '../helpers.js';
import { TENANT_ID } from '../config.js';

/**
 * Full checkout flow (browse -> cart -> checkout)
 */
export default function checkoutFlow() {
    let auth = null;

    group('Checkout - Full Flow', function () {
        // Step 1: Authenticate
        group('Step 1: Login', function () {
            auth = authenticate();

            check(auth, {
                'checkout login - got token': (a) => a !== null,
            });

            sleep(0.5);
        });

        if (!auth || !auth.token) return;

        // Step 2: Browse products
        let productId = null;
        group('Step 2: Browse Products', function () {
            const response = apiGet('/product_entities?itemsPerPage=10', {
                token: auth.token,
                name: 'checkout: GET /product_entities',
            });

            const { members } = parseResponse(response);
            const product = randomItem(members);
            if (product) {
                productId = product.id;

                // View product details
                apiGet(`/product_entities/${productId}`, {
                    token: auth.token,
                    name: 'checkout: GET /product_entities/{id}',
                });
            }

            sleep(randomInt(1, 2));
        });

        // Step 3: Add to cart
        group('Step 3: Add to Cart', function () {
            const payload = {
                tenantId: TENANT_ID,
                productId: productId || '01JMCF0H1ZZZZZZZZZZZZZZ001',
                quantity: randomInt(1, 2),
                unitPriceAmount: randomInt(1999, 4999),
                unitPriceCurrency: 'USD',
            };

            const response = apiPost('/cart/items', payload, {
                token: auth.token,
                type: 'checkout',
                name: 'checkout: POST /cart/items',
            });

            check(response, {
                'add to cart - status 2xx': (r) => r.status >= 200 && r.status < 300,
            });

            sleep(0.5);
        });

        // Step 4: View cart / calculate pricing
        group('Step 4: Review Cart', function () {
            apiGet('/cart', {
                token: auth.token,
                type: 'checkout',
                name: 'checkout: GET /cart',
            });

            sleep(randomInt(1, 3));
        });

        // Step 5: Tax estimation
        group('Step 5: Tax Estimate', function () {
            apiGet('/cart/tax-estimate', {
                token: auth.token,
                type: 'checkout',
                name: 'checkout: GET /cart/tax-estimate',
            });

            sleep(0.5);
        });

        // Step 6: Place order via checkout
        group('Step 6: Checkout', function () {
            const uniqueId = `${Date.now()}_${Math.random().toString(36).slice(2, 6)}`;

            const checkoutPayload = {
                customerEmail: `checkout_${uniqueId}@example.com`,
                shippingAddress: {
                    street: '123 Load Test St',
                    city: 'New York',
                    state: 'NY',
                    postalCode: '10001',
                    country: 'US',
                },
                billingAddress: {
                    street: '123 Load Test St',
                    city: 'New York',
                    state: 'NY',
                    postalCode: '10001',
                    country: 'US',
                },
            };

            const response = apiPost('/checkout', checkoutPayload, {
                token: auth.token,
                type: 'checkout',
                name: 'checkout: POST /checkout',
            });

            checkoutDuration.add(response.timings.duration);

            check(response, {
                'checkout - responds (no 5xx)': (r) => r.status < 500,
                'checkout - response < 500ms': (r) => r.timings.duration < 500,
            });

            sleep(1);
        });
    });
}
