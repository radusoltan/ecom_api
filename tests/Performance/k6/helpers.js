/**
 * K6 Helper Functions
 *
 * Reusable utilities for all k6 test scripts.
 */

import http from 'k6/http';
import { check } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { BASE_URL, API_PREFIX, TENANT_ID, AUTH_EMAIL, AUTH_PASSWORD, defaultHeaders } from './config.js';

// Custom metrics
export const errorRate = new Rate('error_rate');
export const cacheHitRate = new Rate('cache_hits');
export const apiDuration = new Trend('api_duration', true);
export const searchDuration = new Trend('search_duration', true);
export const authDuration = new Trend('auth_duration', true);
export const checkoutDuration = new Trend('checkout_duration', true);
export const cartDuration = new Trend('cart_duration', true);
export const requestCount = new Counter('request_count');

/**
 * Authenticate and get JWT token
 */
export function authenticate(email, password) {
    const loginUrl = `${BASE_URL}/api/login_check`;
    const payload = JSON.stringify({
        email: email || AUTH_EMAIL,
        password: password || AUTH_PASSWORD,
    });

    const response = http.post(loginUrl, payload, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Tenant-ID': TENANT_ID,
        },
        tags: { type: 'auth', name: 'login' },
    });

    authDuration.add(response.timings.duration);

    if (response.status === 200) {
        try {
            const body = JSON.parse(response.body);
            return {
                token: body.token,
                refreshToken: body.refreshToken || body.refresh_token,
                user: body.user,
            };
        } catch (e) {
            return null;
        }
    }

    return null;
}

/**
 * Get authenticated headers
 */
export function authHeaders(token) {
    return {
        ...defaultHeaders,
        'Authorization': `Bearer ${token}`,
    };
}

/**
 * Build API URL
 */
export function apiUrl(path) {
    if (path.startsWith('/api/v1') || path.startsWith('/api/login')) {
        return `${BASE_URL}${path}`;
    }
    return `${BASE_URL}${API_PREFIX}${path}`;
}

/**
 * Parse JSON-LD or JSON response body
 */
export function parseResponse(response) {
    try {
        const body = JSON.parse(response.body);
        return {
            members: body['hydra:member'] || body['member'] || body.data || [],
            totalItems: body['hydra:totalItems'] || body['totalItems'] || 0,
            raw: body,
        };
    } catch (e) {
        return { members: [], totalItems: 0, raw: null };
    }
}

/**
 * Pick random item from array
 */
export function randomItem(arr) {
    if (!arr || arr.length === 0) return null;
    return arr[Math.floor(Math.random() * arr.length)];
}

/**
 * Pick random integer between min and max (inclusive)
 */
export function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

/**
 * Standard API GET request with metrics tracking
 */
export function apiGet(path, opts = {}) {
    const url = apiUrl(path);
    const headers = opts.token ? authHeaders(opts.token) : defaultHeaders;
    const tags = { type: opts.type || 'api', name: opts.name || path };

    const response = http.get(url, { headers, tags });

    requestCount.add(1);
    apiDuration.add(response.timings.duration);
    errorRate.add(response.status >= 400);
    trackCacheHeaders(response);

    return response;
}

/**
 * Standard API POST request with metrics tracking
 */
export function apiPost(path, body, opts = {}) {
    const url = apiUrl(path);
    const headers = opts.token ? authHeaders(opts.token) : defaultHeaders;
    const tags = { type: opts.type || 'api', name: opts.name || path };

    const response = http.post(url, JSON.stringify(body), { headers, tags });

    requestCount.add(1);
    apiDuration.add(response.timings.duration);
    errorRate.add(response.status >= 400);

    return response;
}

/**
 * Standard API PATCH request with metrics tracking
 */
export function apiPatch(path, body, opts = {}) {
    const url = apiUrl(path);
    const headers = opts.token
        ? { ...authHeaders(opts.token), 'Content-Type': 'application/merge-patch+json' }
        : { ...defaultHeaders, 'Content-Type': 'application/merge-patch+json' };
    const tags = { type: opts.type || 'api', name: opts.name || path };

    const response = http.patch(url, JSON.stringify(body), { headers, tags });

    requestCount.add(1);
    apiDuration.add(response.timings.duration);
    errorRate.add(response.status >= 400);

    return response;
}

/**
 * Standard API DELETE request with metrics tracking
 */
export function apiDelete(path, opts = {}) {
    const url = apiUrl(path);
    const headers = opts.token ? authHeaders(opts.token) : defaultHeaders;
    const tags = { type: opts.type || 'api', name: opts.name || path };

    const response = http.del(url, null, { headers, tags });

    requestCount.add(1);
    apiDuration.add(response.timings.duration);
    errorRate.add(response.status >= 400);

    return response;
}

/**
 * Track cache headers
 */
function trackCacheHeaders(response) {
    const cacheControl = response.headers['Cache-Control'];
    const etag = response.headers['ETag'];
    cacheHitRate.add(cacheControl && cacheControl.includes('max-age') ? 1 : 0);
}

/**
 * Select weighted scenario
 */
export function selectWeightedScenario(scenarios) {
    const rand = Math.random() * 100;
    let cumulative = 0;

    for (const scenario of scenarios) {
        cumulative += scenario.weight;
        if (rand <= cumulative) {
            return scenario.fn;
        }
    }

    return scenarios[0].fn;
}

/**
 * Generate HTML report
 */
export function generateHtmlReport(data, title) {
    const reqDuration = data.metrics.http_req_duration || {};
    const reqFailed = data.metrics.http_req_failed || {};
    const httpReqs = data.metrics.http_reqs || {};

    const p50 = reqDuration.values ? reqDuration.values['p(50)'] : 0;
    const p95 = reqDuration.values ? reqDuration.values['p(95)'] : 0;
    const p99 = reqDuration.values ? reqDuration.values['p(99)'] : 0;
    const avg = reqDuration.values ? reqDuration.values.avg : 0;
    const totalReqs = httpReqs.values ? httpReqs.values.count : 0;
    const reqRate = httpReqs.values ? httpReqs.values.rate : 0;
    const failRate = reqFailed.values ? (reqFailed.values.rate * 100) : 0;

    let thresholdRows = '';
    for (const [name, metric] of Object.entries(data.metrics)) {
        if (metric.thresholds) {
            for (const [rule, result] of Object.entries(metric.thresholds)) {
                const cls = result.ok ? 'pass' : 'fail';
                const icon = result.ok ? '&#10003;' : '&#10007;';
                thresholdRows += `<tr class="${cls}"><td>${icon}</td><td>${name}</td><td>${rule}</td></tr>`;
            }
        }
    }

    return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>${title || 'K6 Load Test Report'}</title>
<style>
  body{font-family:system-ui,-apple-system,sans-serif;margin:0;padding:20px 40px;background:#f8f9fa;color:#333}
  h1{border-bottom:3px solid #7b2ff7;padding-bottom:10px}
  h2{color:#555;margin-top:30px}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin:20px 0}
  .card{background:#fff;border-radius:8px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,.1)}
  .card .value{font-size:2em;font-weight:700;color:#7b2ff7}
  .card .label{color:#888;font-size:.9em;margin-top:4px}
  table{border-collapse:collapse;width:100%;margin:16px 0;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,.1)}
  th{background:#7b2ff7;color:#fff;padding:12px 16px;text-align:left}
  td{padding:10px 16px;border-bottom:1px solid #eee}
  .pass{color:#22c55e}.fail{color:#ef4444;font-weight:700}
  footer{margin-top:40px;color:#999;font-size:.85em;border-top:1px solid #ddd;padding-top:10px}
</style>
</head>
<body>
<h1>${title || 'K6 Load Test Report'}</h1>
<p>E-Commerce Platform &mdash; Multi-Tenant Load Testing</p>

<div class="cards">
  <div class="card"><div class="value">${totalReqs.toLocaleString()}</div><div class="label">Total Requests</div></div>
  <div class="card"><div class="value">${reqRate.toFixed(1)}/s</div><div class="label">Request Rate</div></div>
  <div class="card"><div class="value">${failRate.toFixed(2)}%</div><div class="label">Error Rate</div></div>
  <div class="card"><div class="value">${p95.toFixed(0)}ms</div><div class="label">p95 Latency</div></div>
</div>

<h2>Response Times</h2>
<table>
  <tr><th>Percentile</th><th>Duration</th></tr>
  <tr><td>p50 (median)</td><td>${p50.toFixed(1)}ms</td></tr>
  <tr><td>p95</td><td>${p95.toFixed(1)}ms</td></tr>
  <tr><td>p99</td><td>${p99.toFixed(1)}ms</td></tr>
  <tr><td>Average</td><td>${avg.toFixed(1)}ms</td></tr>
</table>

<h2>Threshold Results</h2>
<table>
  <tr><th></th><th>Metric</th><th>Threshold</th></tr>
  ${thresholdRows || '<tr><td colspan="3">No thresholds configured</td></tr>'}
</table>

<footer>Generated: ${new Date().toISOString()} | k6 Load Test Suite | E-Commerce Platform</footer>
</body>
</html>`;
}

/**
 * Generate text summary
 */
export function generateTextSummary(data) {
    const reqDuration = data.metrics.http_req_duration || {};
    const reqFailed = data.metrics.http_req_failed || {};
    const httpReqs = data.metrics.http_reqs || {};

    const p50 = reqDuration.values ? reqDuration.values['p(50)'].toFixed(1) : '?';
    const p95 = reqDuration.values ? reqDuration.values['p(95)'].toFixed(1) : '?';
    const p99 = reqDuration.values ? reqDuration.values['p(99)'].toFixed(1) : '?';
    const avg = reqDuration.values ? reqDuration.values.avg.toFixed(1) : '?';
    const totalReqs = httpReqs.values ? httpReqs.values.count : 0;
    const reqRate = httpReqs.values ? httpReqs.values.rate.toFixed(1) : '?';
    const failRate = reqFailed.values ? (reqFailed.values.rate * 100).toFixed(2) : '?';

    let output = '\n╔══════════════════════════════════════════╗\n';
    output += '║       E-Commerce Load Test Results        ║\n';
    output += '╠══════════════════════════════════════════╣\n';
    output += `║  Total Requests:  ${String(totalReqs).padStart(20)}  ║\n`;
    output += `║  Request Rate:    ${String(reqRate + '/s').padStart(20)}  ║\n`;
    output += `║  Error Rate:      ${String(failRate + '%').padStart(20)}  ║\n`;
    output += '╠══════════════════════════════════════════╣\n';
    output += '║  Response Times:                         ║\n';
    output += `║    p50:           ${String(p50 + 'ms').padStart(20)}  ║\n`;
    output += `║    p95:           ${String(p95 + 'ms').padStart(20)}  ║\n`;
    output += `║    p99:           ${String(p99 + 'ms').padStart(20)}  ║\n`;
    output += `║    avg:           ${String(avg + 'ms').padStart(20)}  ║\n`;
    output += '╠══════════════════════════════════════════╣\n';
    output += '║  Thresholds:                             ║\n';

    for (const [name, metric] of Object.entries(data.metrics)) {
        if (metric.thresholds) {
            for (const [rule, result] of Object.entries(metric.thresholds)) {
                const icon = result.ok ? ' PASS' : ' FAIL';
                const line = `${icon} ${name}: ${rule}`;
                output += `║  ${line.padEnd(39)} ║\n`;
            }
        }
    }

    output += '╚══════════════════════════════════════════╝\n';
    return output;
}
