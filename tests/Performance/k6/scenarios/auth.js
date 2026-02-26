/**
 * Authentication Scenario
 *
 * Simulates user login, token refresh, and registration flows.
 * Traffic weight: 10%
 */

import http from 'k6/http';
import { group, sleep, check } from 'k6';
import { authenticate, authHeaders, apiGet, apiPost, errorRate, authDuration } from '../helpers.js';
import { BASE_URL, TENANT_ID, AUTH_EMAIL, AUTH_PASSWORD, defaultHeaders } from '../config.js';

/**
 * Login flow
 */
export function loginFlow() {
    group('Auth - Login', function () {
        const auth = authenticate();

        check(auth, {
            'login - got token': (a) => a !== null && a.token !== undefined,
        });

        if (auth && auth.token) {
            // Access protected endpoint with token
            const response = apiGet('/profile', {
                token: auth.token,
                name: 'GET /profile (authenticated)',
            });

            check(response, {
                'profile - status 2xx': (r) => r.status >= 200 && r.status < 300,
            });
        }

        sleep(randomSleep(1, 3));
    });
}

/**
 * Token refresh flow
 */
export function tokenRefreshFlow() {
    group('Auth - Token Refresh', function () {
        const auth = authenticate();

        if (auth && auth.refreshToken) {
            const response = http.post(
                `${BASE_URL}/api/v1/auth/token/refresh`,
                JSON.stringify({ refreshToken: auth.refreshToken }),
                {
                    headers: defaultHeaders,
                    tags: { type: 'auth', name: 'POST /auth/token/refresh' },
                }
            );

            authDuration.add(response.timings.duration);

            check(response, {
                'token refresh - status 2xx': (r) => r.status >= 200 && r.status < 300,
            });
        }

        sleep(randomSleep(1, 2));
    });
}

/**
 * Registration flow (generates unique emails)
 */
export function registrationFlow() {
    group('Auth - Register', function () {
        const uniqueId = `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;

        const response = apiPost('/auth/register', {
            email: `loadtest_${uniqueId}@example.com`,
            password: 'LoadTest123!',
            username: `loadtest_${uniqueId}`,
            firstName: 'Load',
            lastName: 'Tester',
        }, {
            type: 'auth',
            name: 'POST /auth/register',
        });

        authDuration.add(response.timings.duration);

        check(response, {
            'register - status 2xx or 422': (r) => r.status < 500,
        });

        sleep(randomSleep(2, 5));
    });
}

function randomSleep(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Combined authentication flow
 */
export default function authenticationScenario() {
    const rand = Math.random();

    if (rand < 0.6) {
        loginFlow();
    } else if (rand < 0.85) {
        tokenRefreshFlow();
    } else {
        registrationFlow();
    }
}
