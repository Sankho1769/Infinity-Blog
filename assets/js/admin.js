/**
 * Infinity Blog - Administrative Service
 * Connects to /api/v1/admin/* endpoints
 */

import { apiRequest } from './api-client.js';

export const adminService = {
    async getStats() {
        return await apiRequest('/admin/stats', { method: 'GET' });
    },

    async getPosts(params = {}) {
        const query = new URLSearchParams();
        if (params.status) query.set('status', params.status);
        if (params.category_id) query.set('category_id', params.category_id);
        if (params.q) query.set('q', params.q);
        if (params.page) query.set('page', params.page);
        if (params.per_page) query.set('per_page', params.per_page);

        const qs = query.toString();
        const url = `/admin/posts${qs ? '?' + qs : ''}`;
        return await apiRequest(url, { method: 'GET' });
    },

    async getCategories() {
        return await apiRequest('/admin/categories', { method: 'GET' });
    },

    async createCategory(data) {
        return await apiRequest('/admin/categories', {
            method: 'POST',
            body: data,
        });
    },

    async updateCategory(id, data) {
        return await apiRequest(`/admin/categories/${id}`, {
            method: 'PATCH',
            body: data,
        });
    },

    async activateCategory(id) {
        return await apiRequest(`/admin/categories/${id}/activate`, {
            method: 'POST',
        });
    },

    async deactivateCategory(id) {
        return await apiRequest(`/admin/categories/${id}/deactivate`, {
            method: 'POST',
        });
    },

    async getUsers() {
        return await apiRequest('/admin/users', { method: 'GET' });
    },

    async createUser(data) {
        return await apiRequest('/admin/users', {
            method: 'POST',
            body: data,
        });
    },

    async activateUser(id) {
        return await apiRequest(`/admin/users/${id}/activate`, {
            method: 'POST',
        });
    },

    async deactivateUser(id) {
        return await apiRequest(`/admin/users/${id}/deactivate`, {
            method: 'POST',
        });
    },

    async getAuditLogs(params = {}) {
        const query = new URLSearchParams();
        if (params.page) query.set('page', params.page);
        if (params.per_page) query.set('per_page', params.per_page);

        const qs = query.toString();
        const url = `/admin/audit-logs${qs ? '?' + qs : ''}`;
        return await apiRequest(url, { method: 'GET' });
    }
};
