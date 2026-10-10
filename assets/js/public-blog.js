/**
 * Infinity Blog - Public Blog Service
 * Sourced from /api/v1/posts, /api/v1/posts/{id}, /api/v1/categories
 */

import { apiRequest } from './api-client.js';

export const publicBlogService = {
    async getPosts(params = {}) {
        const query = new URLSearchParams();
        if (params.q) query.set('q', params.q);
        if (params.category_id) query.set('category_id', params.category_id);
        if (params.page) query.set('page', params.page);
        if (params.per_page) query.set('per_page', params.per_page);

        const qs = query.toString();
        const url = `/posts${qs ? '?' + qs : ''}`;
        return await apiRequest(url, { method: 'GET' });
    },

    async getPost(id) {
        return await apiRequest(`/posts/${id}`, { method: 'GET' });
    },

    async getCategories() {
        return await apiRequest('/categories', { method: 'GET' });
    }
};
