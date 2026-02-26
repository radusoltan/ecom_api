/**
 * Reviews & Wishlist Scenario
 *
 * Simulates users viewing/submitting reviews and managing wishlists.
 * Traffic weight: 2%
 */

import { group, sleep, check } from 'k6';
import { authenticate, apiGet, apiPost, parseResponse, randomItem, randomInt } from '../helpers.js';

/**
 * View product reviews
 */
export function viewProductReviews() {
    group('Reviews - View', function () {
        const response = apiGet('/storefront/reviews?itemsPerPage=10', {
            name: 'GET /storefront/reviews',
        });

        check(response, {
            'reviews - status 2xx': (r) => r.status >= 200 && r.status < 300,
        });

        sleep(randomInt(1, 2));
    });
}

/**
 * View review stats for a product
 */
export function viewReviewStats() {
    group('Reviews - Stats', function () {
        // Get products first
        const productsRes = apiGet('/product_entities?itemsPerPage=5', {
            name: 'reviews: GET /product_entities',
        });

        const { members } = parseResponse(productsRes);
        const product = randomItem(members);

        if (product && product.id) {
            apiGet(`/storefront/products/${product.id}/reviews/stats`, {
                name: 'GET /products/{id}/reviews/stats',
            });

            apiGet(`/storefront/products/${product.id}/reviews/rating-distribution`, {
                name: 'GET /products/{id}/reviews/rating-distribution',
            });
        }

        sleep(randomInt(1, 2));
    });
}

/**
 * Wishlist operations (authenticated)
 */
export function wishlistOperations() {
    group('Wishlist - Operations', function () {
        const auth = authenticate();
        if (!auth || !auth.token) return;

        // View wishlist
        const response = apiGet('/storefront/wishlist', {
            token: auth.token,
            name: 'GET /storefront/wishlist',
        });

        check(response, {
            'wishlist - status 2xx': (r) => r.status >= 200 && r.status < 400,
        });

        // Get wishlist count
        apiGet('/storefront/wishlist/count', {
            token: auth.token,
            name: 'GET /storefront/wishlist/count',
        });

        sleep(randomInt(1, 2));
    });
}

/**
 * Combined reviews/wishlist flow
 */
export default function reviewsWishlist() {
    const rand = Math.random();

    if (rand < 0.4) {
        viewProductReviews();
    } else if (rand < 0.7) {
        viewReviewStats();
    } else {
        wishlistOperations();
    }
}
