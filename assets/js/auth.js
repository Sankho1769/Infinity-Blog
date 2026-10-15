/**
 * Infinity Blog - Authentication Service
 * Handles login, logout, session restoration, and role redirection.
 * Complies with rule: Never use localStorage as source of truth for auth.
 */

import { apiRequest, setCachedCsrfToken } from './api-client.js';

let currentUser = null;

export const authService = {
    async login(identifier, password) {
        const response = await apiRequest('/auth/login', {
            method: 'POST',
            body: { identifier, password },
        });

        if (response?.data?.user) {
            currentUser = response.data.user;
            if (response.data.csrf_token) {
                setCachedCsrfToken(response.data.csrf_token);
            }
            return currentUser;
        }
        throw new Error('Unexpected response format from login endpoint.');
    },

    async register(userData) {
        const response = await apiRequest('/auth/register', {
            method: 'POST',
            body: userData,
        });
        return response?.data;
    },

    async logout() {
        try {
            await apiRequest('/auth/logout', { method: 'POST' });
        } finally {
            currentUser = null;
            setCachedCsrfToken(null);
        }
    },

    async getCurrentUser(forceRefresh = false) {
        if (currentUser && !forceRefresh) {
            return currentUser;
        }

        try {
            const response = await apiRequest('/auth/me', { method: 'GET' });
            currentUser = response?.data?.user || null;
            if (response?.data?.csrf_token) {
                setCachedCsrfToken(response.data.csrf_token);
            }
            return currentUser;
        } catch (error) {
            currentUser = null;
            return null;
        }
    },

    getUserRole() {
        return currentUser?.role || null;
    },

    isAuthenticated() {
        return currentUser !== null;
    },

    isAdmin() {
        return currentUser?.role === 'admin';
    },

    isAuthor() {
        return currentUser?.role === 'author' || currentUser?.role === 'admin';
    }
};
