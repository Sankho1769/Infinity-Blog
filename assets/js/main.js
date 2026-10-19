/**
 * Infinity Blog - Global Client Interactions & Shell Manager
 * Handles responsive nav, session restoration, toasts, and modals.
 */

import { authService } from './auth.js';

export function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${escapeHtml(message)}</span>
        <button type="button" style="background:none;border:none;cursor:pointer;font-size:1.1rem;margin-left:8px;color:#94a3b8;">&times;</button>
    `;

    toast.querySelector('button').onclick = () => toast.remove();
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

export function showConfirmModal(title, message, confirmText = 'Confirm', onConfirm = () => {}) {
    let modalOverlay = document.getElementById('global-confirm-modal');
    if (!modalOverlay) {
        modalOverlay = document.createElement('div');
        modalOverlay.id = 'global-confirm-modal';
        modalOverlay.className = 'modal-overlay';
        modalOverlay.innerHTML = `
            <div class="modal-card">
                <div class="modal-header">
                    <h3 class="modal-title" id="confirm-modal-title">Confirm Action</h3>
                    <button class="modal-close" id="confirm-modal-close">&times;</button>
                </div>
                <p id="confirm-modal-message" style="color:var(--color-text-muted);font-size:0.95rem;margin-bottom:1.5rem;"></p>
                <div class="modal-actions">
                    <button class="btn btn-secondary" id="confirm-modal-cancel">Cancel</button>
                    <button class="btn btn-danger" id="confirm-modal-btn">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(modalOverlay);
    }

    const titleEl = document.getElementById('confirm-modal-title');
    const msgEl = document.getElementById('confirm-modal-message');
    const confirmBtn = document.getElementById('confirm-modal-btn');
    const cancelBtn = document.getElementById('confirm-modal-cancel');
    const closeBtn = document.getElementById('confirm-modal-close');

    titleEl.textContent = title;
    msgEl.textContent = message;
    confirmBtn.textContent = confirmText;

    const closeModal = () => modalOverlay.classList.remove('active');

    cancelBtn.onclick = closeModal;
    closeBtn.onclick = closeModal;
    confirmBtn.onclick = async () => {
        closeModal();
        await onConfirm();
    };

    modalOverlay.classList.add('active');
}

export function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

export async function initAppShell() {
    const user = await authService.getCurrentUser();
    const navMenu = document.querySelector('.nav-menu');
    const headerActions = document.querySelector('.header-actions');

    const currentPath = window.location.pathname;

    if (navMenu) {
        let navHtml = `
            <li><a href="index.html" class="nav-link ${currentPath.endsWith('index.html') || currentPath.endsWith('/') ? 'active' : ''}">Home</a></li>
            <li><a href="categories.html" class="nav-link ${currentPath.includes('categories.html') ? 'active' : ''}">Categories</a></li>
            <li><a href="authors.html" class="nav-link ${currentPath.includes('authors.html') ? 'active' : ''}">Authors</a></li>
            <li><a href="index.html#about" class="nav-link">About</a></li>
        `;

        if (user) {
            navHtml += `
                <li><a href="dashboard.html" class="nav-link ${currentPath.includes('dashboard.html') ? 'active' : ''}">Dashboard</a></li>
                <li><a href="posts.html" class="nav-link ${currentPath.includes('posts.html') ? 'active' : ''}">Posts</a></li>
                <li><a href="create-post.html" class="nav-link ${currentPath.includes('create-post.html') ? 'active' : ''}">Create Post</a></li>
            `;
            if (user.role === 'admin') {
                navHtml += `<li><a href="admin.html" class="nav-link ${currentPath.includes('admin.html') ? 'active' : ''}">Admin Panel</a></li>`;
            }
        }

        navMenu.innerHTML = navHtml;
    }

    if (headerActions) {
        if (user) {
            headerActions.innerHTML = `
                <div style="display:flex;align-items:center;gap:10px;">
                    <a href="profile.html" class="user-profile-pill" title="View & Edit Profile" style="text-decoration:none;display:flex;align-items:center;gap:6px;padding:4px 8px;border-radius:var(--radius-sm);background:rgba(37,99,235,0.06);transition:background 0.15s ease;">
                        <span class="badge ${user.role === 'admin' ? 'badge-admin' : 'badge-published'}">${escapeHtml(user.role)}</span>
                        <span style="font-size:0.9rem;font-weight:600;color:var(--color-text-main);">${escapeHtml(user.display_name)}</span>
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" id="global-logout-btn">Logout</button>
                </div>
            `;
            const logoutBtn = document.getElementById('global-logout-btn');
            if (logoutBtn) {
                logoutBtn.onclick = async () => {
                    try {
                        logoutBtn.disabled = true;
                        await authService.logout();
                        showToast('Logged out successfully.', 'info');
                        setTimeout(() => {
                            window.location.href = 'index.html';
                        }, 500);
                    } catch (err) {
                        showToast('Logout encountered an issue.', 'danger');
                    }
                };
            }
        } else {
            headerActions.innerHTML = `
                <a href="signup.html" class="btn btn-secondary btn-sm" style="margin-right:6px;">Sign Up</a>
                <a href="login.html" class="btn btn-primary btn-sm">Sign In</a>
            `;
        }
    }
}

// Auto-run when DOM is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAppShell);
} else {
    initAppShell();
}
