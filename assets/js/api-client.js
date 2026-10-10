/**
 * Infinity Blog - Reusable Centralized API Client
 * Follows FRONTEND_BACKEND_INTEGRATION_GUIDE.md & API_SPEC.md
 */

import { CONFIG } from './config.js';

let cachedCsrfToken = null;

export function setCachedCsrfToken(token) {
    cachedCsrfToken = token;
}

export function getCachedCsrfToken() {
    return cachedCsrfToken;
}

export async function fetchCsrfToken() {
    try {
        const response = await fetch(`${CONFIG.API_BASE}/auth/csrf`, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        });
        if (response.ok) {
            const result = await response.json();
            cachedCsrfToken = result?.data?.csrf_token || null;
            return cachedCsrfToken;
        }
    } catch (err) {
        // Safe failover
    }
    return null;
}

export async function apiRequest(path, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    const headers = new Headers(options.headers || {});
    
    headers.set('Accept', 'application/json');

    if (options.body !== undefined && !(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    // Attach CSRF token on state-changing operations
    if (['POST', 'PATCH', 'PUT', 'DELETE'].includes(method)) {
        if (!cachedCsrfToken && path !== '/auth/login') {
            await fetchCsrfToken();
        }
        if (cachedCsrfToken) {
            headers.set('X-CSRF-Token', cachedCsrfToken);
        }
    }

    let body = options.body;
    if (body !== undefined && typeof body === 'object' && !(body instanceof FormData)) {
        body = JSON.stringify(body);
    }

    const requestUrl = path.startsWith('http') ? path : `${CONFIG.API_BASE}${path}`;

    try {
        const response = await fetch(requestUrl, {
            ...options,
            method,
            headers,
            body,
            credentials: 'include',
        });

        const contentType = response.headers.get('content-type') || '';
        let result = null;

        if (response.status !== 204 && contentType.includes('application/json')) {
            try {
                result = await response.json();
            } catch (jsonErr) {
                result = null;
            }
        }

        // Cache refreshed CSRF token if returned in response data
        if (result?.data?.csrf_token) {
            cachedCsrfToken = result.data.csrf_token;
        }

        if (!response.ok) {
            const err = new Error(result?.error?.message || `HTTP error ${response.status}`);
            err.status = response.status;
            err.code = result?.error?.code || 'REQUEST_FAILED';
            err.fields = result?.error?.fields || {};
            err.requestId = result?.request_id || response.headers.get('x-request-id') || null;
            throw err;
        }

        return result;
    } catch (error) {
        if (!error.status) {
            // Network or CORS failure
            const networkError = new Error('Network error or server unreachable. Please verify connection.');
            networkError.status = 0;
            networkError.code = 'NETWORK_ERROR';
            networkError.fields = {};
            throw networkError;
        }
        throw error;
    }
}
