/**
 * Infinity Blog - Author Posts Service
 * Handles author post operations, publication states, and AI summarization.
 */

import { apiRequest } from './api-client.js';

export const authorPostService = {
    async getAuthorPosts(params = {}) {
        const query = new URLSearchParams();
        if (params.status) query.set('status', params.status);
        if (params.category_id) query.set('category_id', params.category_id);
        if (params.q) query.set('q', params.q);
        if (params.sort) query.set('sort', params.sort);
        if (params.page) query.set('page', params.page);
        if (params.per_page) query.set('per_page', params.per_page);

        const qs = query.toString();
        const url = `/author/posts${qs ? '?' + qs : ''}`;
        return await apiRequest(url, { method: 'GET' });
    },

    async getPost(id) {
        return await apiRequest(`/author/posts/${id}`, { method: 'GET' });
    },

    async createPost(data) {
        return await apiRequest('/author/posts', {
            method: 'POST',
            body: data,
        });
    },

    async updatePost(id, data) {
        return await apiRequest(`/author/posts/${id}`, {
            method: 'PATCH',
            body: data,
        });
    },

    async publishPost(id) {
        return await apiRequest(`/author/posts/${id}/publish`, {
            method: 'POST',
        });
    },

    async unpublishPost(id) {
        return await apiRequest(`/author/posts/${id}/unpublish`, {
            method: 'POST',
        });
    },

    async deletePost(id) {
        return await apiRequest(`/author/posts/${id}`, {
            method: 'DELETE',
        });
    },

    async generateSummary(id) {
        return await apiRequest(`/author/posts/${id}/summary`, {
            method: 'POST',
        });
    },

    async updateProfile(data) {
        return await apiRequest('/author/profile', {
            method: 'PATCH',
            body: data,
        });
    },

    async uploadImage(file) {
        const formData = new FormData();
        formData.append('image', file);
        return await apiRequest('/author/upload-image', {
            method: 'POST',
            body: formData,
        });
    },

    async bulkDelete(postIds) {
        return await apiRequest('/author/posts/bulk-delete', {
            method: 'POST',
            body: { post_ids: postIds },
        });
    }
};
