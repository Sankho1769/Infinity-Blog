/**
 * Infinity Blog Client Controller
 * Pure vanilla JavaScript with CSRF handling, modals, mobile navigation, and AJAX interactions
 */

(function () {
  'use strict';

  // Read CSRF token from meta tag or cookie
  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  // Update CSRF token across page
  function updateCsrfToken(newToken) {
    if (!newToken) return;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', newToken);
    document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
      input.value = newToken;
    });
  }

  // Fetch CSRF token from API if missing
  async function ensureCsrfToken() {
    let token = getCsrfToken();
    if (token) return token;
    try {
      const res = await fetch('/auth/csrf', { headers: { 'Accept': 'application/json' } });
      if (res.ok) {
        const json = await res.json();
        token = json.data?.csrf_token || '';
        updateCsrfToken(token);
      }
    } catch (e) {
      console.error('Failed to retrieve CSRF token:', e);
    }
    return token;
  }

  // Show Toast Alert Banner
  function showAlert(message, type = 'error', container = null) {
    const alertBox = document.createElement('div');
    alertBox.className = `alert-banner alert-${type === 'success' ? 'success' : 'error'}`;
    alertBox.innerHTML = `<span>${escapeHtml(message)}</span>`;

    const target = container || document.querySelector('.flash-messages') || document.querySelector('main');
    if (target) {
      target.prepend(alertBox);
      setTimeout(() => {
        alertBox.style.opacity = '0';
        alertBox.style.transition = 'opacity 0.4s ease';
        setTimeout(() => alertBox.remove(), 400);
      }, 5000);
    }
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Password Visibility Toggle & Setup
  document.addEventListener('DOMContentLoaded', () => {
    // Clean transient query parameters from URL so feedback alerts never persist on refresh or subsequent visits
    try {
      const url = new URL(window.location.href);
      const transients = ['logged_out', 'deleted', 'created', 'updated', 'published'];
      let changed = false;
      for (const p of transients) {
        if (url.searchParams.has(p)) {
          url.searchParams.delete(p);
          changed = true;
        }
      }
      if (changed) {
        const cleanUrl = url.pathname + (url.search ? url.search : '') + url.hash;
        window.history.replaceState({}, document.title, cleanUrl);
      }
    } catch (e) {
      // Safe fallback if history API is restricted
    }

    // Auto-dismiss static flash alert banners after 5 seconds
    document.querySelectorAll('.alert-banner').forEach(banner => {
      setTimeout(() => {
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(-6px)';
        banner.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        setTimeout(() => banner.remove(), 400);
      }, 5000);
    });

    document.querySelectorAll('.input-toggle-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const input = btn.closest('.input-with-icon')?.querySelector('input');
        if (!input) return;
        if (input.type === 'password') {
          input.type = 'text';
          btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
        } else {
          input.type = 'password';
          btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        }
      });
    });

    // Delete Modal Setup
    const deleteModal = document.getElementById('delete-confirm-modal');
    let deleteTargetUrl = null;

    window.openDeleteModal = function (url, postTitle) {
      deleteTargetUrl = url;
      const modal = document.getElementById('delete-confirm-modal');
      const titleElem = document.getElementById('delete-modal-post-title');
      if (titleElem) titleElem.textContent = postTitle ? `"${postTitle}"` : 'this post';
      if (modal) modal.classList.add('open');
    };

    window.closeDeleteModal = function () {
      deleteTargetUrl = null;
      const modal = document.getElementById('delete-confirm-modal');
      if (modal) modal.classList.remove('open');
    };

    const confirmDeleteBtn = document.getElementById('modal-confirm-delete-btn');
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener('click', async () => {
        if (!deleteTargetUrl) return;
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.textContent = 'Deleting...';
        try {
          const csrf = await ensureCsrfToken();
          const res = await fetch(deleteTargetUrl, {
            method: 'DELETE',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': csrf
            },
            body: JSON.stringify({ confirm: true })
          });
          const data = await res.json();
          if (res.ok) {
            window.location.href = '/posts/manage?deleted=1';
          } else {
            showAlert(data.error?.message || 'Failed to delete post.', 'error');
            closeDeleteModal();
          }
        } catch (err) {
          showAlert('Network error occurred.', 'error');
          closeDeleteModal();
        } finally {
          confirmDeleteBtn.disabled = false;
          confirmDeleteBtn.textContent = 'Delete Post';
        }
      });
    }

    // AI Summary Trigger in Post Editor
    const summaryBtn = document.getElementById('generate-summary-btn');
    if (summaryBtn) {
      summaryBtn.addEventListener('click', async () => {
        const postId = summaryBtn.getAttribute('data-post-id');
        if (!postId) return;
        summaryBtn.disabled = true;
        summaryBtn.textContent = 'Generating AI Summary...';
        try {
          const csrf = await ensureCsrfToken();
          const res = await fetch(`/author/posts/${postId}/summary`, {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'X-CSRF-Token': csrf
            }
          });
          const data = await res.json();
          if (res.ok) {
            showAlert('AI Summary generated successfully! Reloading...', 'success');
            setTimeout(() => window.location.reload(), 1200);
          } else {
            showAlert(data.error?.message || 'AI Summarization unavailable.', 'error');
          }
        } catch (err) {
          showAlert('Could not generate summary.', 'error');
        } finally {
          summaryBtn.disabled = false;
          summaryBtn.textContent = 'Generate AI Summary';
        }
      });
    }

    // Escape key closes modals
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        window.closeDeleteModal();
      }
    });

    // Mobile Navigation Drawer Toggle
    const mobileToggle = document.getElementById('mobile-nav-toggle');
    const mobileDrawer = document.getElementById('mobile-nav-drawer');
    if (mobileToggle && mobileDrawer) {
      mobileToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = mobileDrawer.classList.contains('open');
        if (isOpen) {
          mobileDrawer.classList.remove('open');
          mobileDrawer.style.display = 'none';
          mobileToggle.setAttribute('aria-expanded', 'false');
        } else {
          mobileDrawer.classList.add('open');
          mobileDrawer.style.display = 'block';
          mobileToggle.setAttribute('aria-expanded', 'true');
        }
      });

      // Close mobile drawer if clicking outside
      document.addEventListener('click', (e) => {
        if (!mobileDrawer.contains(e.target) && !mobileToggle.contains(e.target)) {
          if (mobileDrawer.classList.contains('open')) {
            mobileDrawer.classList.remove('open');
            mobileDrawer.style.display = 'none';
            mobileToggle.setAttribute('aria-expanded', 'false');
          }
        }
      });
    }
  });

  window.InfinityBlog = window.BlogCMS = {
    getCsrfToken,
    ensureCsrfToken,
    showAlert
  };
})();
