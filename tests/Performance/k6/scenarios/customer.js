/**
 * Customer Profile Scenario
 *
 * Simulates authenticated users viewing profiles, orders, addresses.
 * Traffic weight: 5%
 */

import { group, sleep, check } from 'k6';
import { authenticate, apiGet, parseResponse, randomItem, randomInt } from '../helpers.js';

/**
 * View profile and addresses
 */
export function viewProfile(token) {
    group('Customer - View Profile', function () {
        const response = apiGet('/profile', {
            token: token,
            name: 'GET /profile',
        });

        check(response, {
            'profile - status 2xx': (r) => r.status >= 200 && r.status < 300,
        });

        // View addresses
        apiGet('/profile/addresses', {
            token: token,
            name: 'GET /profile/addresses',
        });

        sleep(randomInt(1, 3));
    });
}

/**
 * View order history
 */
export function viewOrders(token) {
    group('Customer - View Orders', function () {
        const response = apiGet('/orders?itemsPerPage=10', {
            token: token,
            name: 'GET /orders',
        });

        const { members } = parseResponse(response);

        if (members.length > 0) {
            const order = randomItem(members);
            if (order && order.id) {
                apiGet(`/orders/${order.id}`, {
                    token: token,
                    name: 'GET /orders/{id}',
                });
            }
        }

        sleep(randomInt(1, 3));
    });
}

/**
 * View invoices
 */
export function viewInvoices(token) {
    group('Customer - View Invoices', function () {
        const response = apiGet('/invoices?itemsPerPage=10', {
            token: token,
            name: 'GET /invoices',
        });

        check(response, {
            'invoices - status 2xx': (r) => r.status >= 200 && r.status < 300,
        });

        sleep(randomInt(1, 2));
    });
}

/**
 * Combined customer profile flow
 */
export default function customerProfile() {
    const auth = authenticate();
    if (!auth || !auth.token) return;

    const rand = Math.random();

    if (rand < 0.4) {
        viewProfile(auth.token);
    } else if (rand < 0.75) {
        viewOrders(auth.token);
    } else {
        viewInvoices(auth.token);
    }
}
