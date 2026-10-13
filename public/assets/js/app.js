/**
 * Infinity Blog Client Controller
 * Pure vanilla JavaScript with CSRF handling, modals, mobile navigation, and AJAX interactions
 */

(function () {
  'use strict';

  // Read CSRF token from meta tag, hidden input, or cookie
  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content')) return meta.getAttribute('content');
    const input = document.querySelector('input[name="csrf_token"]');
    if (input && input.value) return input.value;
    return '';
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

  // Expose immediately so page scripts can access anytime
  window.InfinityBlog = window.BlogCMS = {
    getCsrfToken,
    ensureCsrfToken,
    showAlert
  };

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

    // Admin Navigation Dropdown Controls
    const adminDropdown = document.getElementById('admin-nav-dropdown');
    const adminDropdownBtn = document.getElementById('admin-nav-dropdown-btn');

    function closeAdminDropdown() {
      if (adminDropdown && adminDropdown.classList.contains('open')) {
        adminDropdown.classList.remove('open');
        if (adminDropdownBtn) {
          adminDropdownBtn.setAttribute('aria-expanded', 'false');
        }
      }
    }

    if (adminDropdown && adminDropdownBtn) {
      adminDropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = adminDropdown.classList.contains('open');
        if (isOpen) {
          closeAdminDropdown();
        } else {
          adminDropdown.classList.add('open');
          adminDropdownBtn.setAttribute('aria-expanded', 'true');
        }
      });

      document.addEventListener('click', (e) => {
        if (!adminDropdown.contains(e.target)) {
          closeAdminDropdown();
        }
      });
    }

    // Mobile Navigation Drawer Controls
    const mobileToggle = document.getElementById('mobile-nav-toggle');
    const mobileDrawer = document.getElementById('mobile-nav-drawer');

    function closeMobileDrawer() {
      if (mobileDrawer && mobileDrawer.classList.contains('open')) {
        mobileDrawer.classList.remove('open');
        mobileDrawer.style.display = 'none';
        if (mobileToggle) {
          mobileToggle.setAttribute('aria-expanded', 'false');
        }
      }
    }

    if (mobileToggle && mobileDrawer) {
      mobileToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = mobileDrawer.classList.contains('open');
        if (isOpen) {
          closeMobileDrawer();
        } else {
          mobileDrawer.classList.add('open');
          mobileDrawer.style.display = 'block';
          mobileToggle.setAttribute('aria-expanded', 'true');
        }
      });

      // Close mobile drawer if clicking outside
      document.addEventListener('click', (e) => {
        if (!mobileDrawer.contains(e.target) && !mobileToggle.contains(e.target)) {
          closeMobileDrawer();
        }
      });

      // Close mobile drawer when clicking any link inside it
      mobileDrawer.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
          closeMobileDrawer();
        });
      });
    }

    // Horizontal Category Carousel Scroll Navigation
    function initCategoryCarousels() {
      document.querySelectorAll('.category-carousel-wrapper').forEach(wrapper => {
        const track = wrapper.querySelector('.category-filter-bar');
        const prevBtn = wrapper.querySelector('.category-scroll-prev');
        const nextBtn = wrapper.querySelector('.category-scroll-next');
        if (!track) return;

        function updateButtons() {
          const maxScroll = track.scrollWidth - track.clientWidth;
          if (prevBtn) {
            if (track.scrollLeft > 6) {
              prevBtn.style.display = 'flex';
              prevBtn.classList.remove('hidden');
              prevBtn.disabled = false;
            } else {
              prevBtn.style.display = 'none';
              prevBtn.classList.add('hidden');
              prevBtn.disabled = true;
            }
          }
          if (nextBtn) {
            if (maxScroll > 6 && track.scrollLeft < maxScroll - 6) {
              nextBtn.style.display = 'flex';
              nextBtn.classList.remove('hidden');
              nextBtn.disabled = false;
            } else {
              nextBtn.style.display = 'none';
              nextBtn.classList.add('hidden');
              nextBtn.disabled = true;
            }
          }
        }

        if (prevBtn) {
          prevBtn.addEventListener('click', (e) => {
            e.preventDefault();
            track.scrollBy({ left: -260, behavior: 'smooth' });
          });
        }

        if (nextBtn) {
          nextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            track.scrollBy({ left: 260, behavior: 'smooth' });
          });
        }

        track.addEventListener('scroll', updateButtons, { passive: true });
        window.addEventListener('resize', updateButtons, { passive: true });

        const activeItem = track.querySelector('.filter-pill.active');
        if (activeItem) {
          try {
            activeItem.scrollIntoView({ inline: 'center', block: 'nearest' });
          } catch (e) {}
        }

        updateButtons();
      });
    }
    initCategoryCarousels();

    // Social Follow / Unfollow AJAX Handler
    document.querySelectorAll('.btn-follow-toggle').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const authorId = btn.getAttribute('data-author-id');
        if (!authorId) return;

        const isLoggedIn = !!document.querySelector('.user-profile-link');
        if (!isLoggedIn) {
          window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname)}`;
          return;
        }

        const isFollowing = btn.classList.contains('following') || btn.getAttribute('data-following') === '1';
        const actionUrl = isFollowing ? `/api/authors/${authorId}/unfollow` : `/api/authors/${authorId}/follow`;

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.textContent = isFollowing ? 'Unfollowing...' : 'Following...';

        try {
          const csrf = await ensureCsrfToken();
          const res = await fetch(actionUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': csrf
            }
          });
          const data = await res.json();
          if (res.ok) {
            const nextFollowing = data.data?.is_following ?? !isFollowing;
            const newCount = data.data?.follower_count;
            if (nextFollowing) {
              btn.classList.add('following');
              btn.setAttribute('data-following', '1');
              btn.innerHTML = '<span>✓</span> Following';
            } else {
              btn.classList.remove('following');
              btn.setAttribute('data-following', '0');
              btn.innerHTML = '<span>+</span> Follow';
            }

            document.querySelectorAll(`.author-follower-count[data-author-id="${authorId}"], #author-follower-count`).forEach(el => {
              if (newCount !== undefined) {
                el.textContent = newCount;
              } else {
                let cur = parseInt(el.textContent, 10) || 0;
                el.textContent = nextFollowing ? cur + 1 : Math.max(0, cur - 1);
              }
            });
          } else {
            showAlert(data.error?.message || 'Could not update follow status.', 'error');
            btn.innerHTML = originalText;
          }
        } catch (err) {
          showAlert('Network error while updating follow status.', 'error');
          btn.innerHTML = originalText;
        } finally {
          btn.disabled = false;
        }
      });
    });

    // Unread Direct Message Badge Poller
    async function updateUnreadCount() {
      const navBadge = document.getElementById('nav-unread-badge');
      const mobBadge = document.getElementById('mobile-unread-badge');
      if (!navBadge && !mobBadge) return;

      try {
        const res = await fetch('/api/messages/unread-count', {
          headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
          const data = await res.json();
          const count = data.data?.unread_count || 0;
          [navBadge, mobBadge].forEach(badge => {
            if (badge) {
              if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
              } else {
                badge.style.display = 'none';
              }
            }
          });
        }
      } catch (e) {}
    }

    if (document.querySelector('.user-profile-link')) {
      updateUnreadCount();
      setInterval(updateUnreadCount, 15000);
    }

    // Share Modal & Copy Link
    window.openShareModal = function (url, title) {
      const modal = document.getElementById('share-article-modal');
      const input = document.getElementById('share-modal-url-input');
      if (input && url) input.value = url;
      if (modal) modal.classList.add('open');
    };

    window.closeShareModal = function () {
      const modal = document.getElementById('share-article-modal');
      if (modal) modal.classList.remove('open');
      const tooltip = document.getElementById('share-copy-tooltip');
      if (tooltip) tooltip.style.display = 'none';
    };

    window.copyShareLink = async function () {
      const input = document.getElementById('share-modal-url-input');
      const tooltip = document.getElementById('share-copy-tooltip');
      if (!input) return;
      try {
        await navigator.clipboard.writeText(input.value);
        if (tooltip) {
          tooltip.style.display = 'inline';
          setTimeout(() => { tooltip.style.display = 'none'; }, 2500);
        }
      } catch (e) {
        input.select();
        document.execCommand('copy');
        if (tooltip) {
          tooltip.style.display = 'inline';
          setTimeout(() => { tooltip.style.display = 'none'; }, 2500);
        }
      }
    };

    window.triggerNativeShare = async function (url, title, text) {
      if (navigator.share) {
        try {
          await navigator.share({ title: title || document.title, text: text || '', url: url || window.location.href });
          return;
        } catch (e) {
          if (e.name !== 'AbortError') openShareModal(url, title);
        }
      } else {
        openShareModal(url, title);
      }
    };

    // Escape key closes modals, dropdowns, and mobile drawer
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (typeof window.closeDeleteModal === 'function') {
          window.closeDeleteModal();
        }
        if (typeof window.closeShareModal === 'function') {
          window.closeShareModal();
        }
        closeAdminDropdown();
        closeMobileDrawer();
      }
    });
  });

  window.InfinityBlog = window.BlogCMS = {
    getCsrfToken,
    ensureCsrfToken,
    showAlert
  };
})();
