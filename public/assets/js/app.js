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

  // ==========================================================================
  // Application-Wide Theme System (Light, Dark, Device Mode)
  // ==========================================================================
  const ThemeManager = {
    STORAGE_KEY: 'ib_theme_preference',
    _initialized: false,

    getPreference() {
      try {
        const val = localStorage.getItem(this.STORAGE_KEY);
        if (val === 'device') {
          // Normalize legacy device setting to light
          try { localStorage.setItem(this.STORAGE_KEY, 'light'); } catch (e) {}
          return 'light';
        }
        if (val === 'dark' || val === 'light') {
          return val;
        }
      } catch (e) {}
      return 'light'; // Strict Light Mode default for all new visitors
    },

    resolveTheme(preference) {
      return preference === 'dark' ? 'dark' : 'light';
    },

    toggle() {
      const current = this.getPreference();
      const next = (current === 'dark') ? 'light' : 'dark';
      this.setPreference(next);
      return next;
    },

    apply(preference, forcedEffectiveTheme = null, notify = true) {
      let validPref = (preference === 'dark') ? 'dark' : 'light';
      // If legacy device passed in, normalize to light
      if (preference === 'device') {
        validPref = 'light';
        try { localStorage.setItem(this.STORAGE_KEY, 'light'); } catch (e) {}
      }
      const theme = forcedEffectiveTheme || this.resolveTheme(validPref);

      // 1. Root DOM attributes
      document.documentElement.setAttribute('data-theme', theme);
      document.documentElement.setAttribute('data-theme-preference', validPref);
      if (theme === 'dark') {
        document.documentElement.classList.add('theme-dark');
      } else {
        document.documentElement.classList.remove('theme-dark');
      }

      // 2. Meta theme-color for browser chrome
      const metaThemeColor = document.querySelector('meta[name="theme-color"]');
      if (metaThemeColor) {
        metaThemeColor.setAttribute('content', theme === 'dark' ? '#070d18' : '#3b82f6');
      }

      // 3. Header Toggle Button Icon and Accessible Label
      const currentIconEl = document.getElementById('header-theme-current-icon');
      const toggleBtn = document.getElementById('header-theme-toggle-btn');
      if (currentIconEl) {
        if (validPref === 'dark') {
          currentIconEl.innerHTML = '<svg class="theme-svg-moon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
        } else {
          currentIconEl.innerHTML = '<svg class="theme-svg-sun" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>';
        }
      }
      if (toggleBtn) {
        const nextActionLabel = (validPref === 'dark') ? 'Switch to Light Mode' : 'Switch to Dark Mode';
        toggleBtn.title = nextActionLabel;
        toggleBtn.setAttribute('aria-label', nextActionLabel);
        toggleBtn.removeAttribute('aria-haspopup');
        toggleBtn.removeAttribute('aria-expanded');
      }

      // 4. Header Popover Dropdown Radio Items (if present)
      document.querySelectorAll('.theme-option-btn').forEach(btn => {
        const btnPref = btn.getAttribute('data-theme-value');
        const isSelected = btnPref === validPref;
        btn.classList.toggle('active', isSelected);
        btn.setAttribute('aria-checked', isSelected ? 'true' : 'false');
      });

      // 5. Segmented Buttons (User Account Menu and Mobile Navigation Drawer)
      document.querySelectorAll('.theme-segmented-btn').forEach(btn => {
        const btnPref = btn.getAttribute('data-theme-value');
        const isSelected = btnPref === validPref;
        btn.classList.toggle('active', isSelected);
        btn.setAttribute('aria-checked', isSelected ? 'true' : 'false');
      });

      // 6. Broadcast Custom Event
      if (notify) {
        try {
          window.dispatchEvent(new CustomEvent('ib:themechange', {
            detail: { theme, preference: validPref }
          }));
        } catch (e) {}
      }
    },

    setPreference(preference) {
      let validPref = (preference === 'dark') ? 'dark' : 'light';
      if (preference === 'device') {
        validPref = 'light';
      }
      try {
        localStorage.setItem(this.STORAGE_KEY, validPref);
      } catch (e) {}
      this.apply(validPref, null, true);
    },

    init() {
      if (this._initialized) {
        // Re-apply in case DOM elements were newly mounted
        this.apply(this.getPreference(), null, false);
        return;
      }
      this._initialized = true;

      const pref = this.getPreference();
      this.apply(pref, null, false);

      // Direct Header Theme Toggle Button Interaction
      const toggleBtn = document.getElementById('header-theme-toggle-btn');
      if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.toggle();
        });
      }

      // Bind remaining segmented buttons (user account dropdown and mobile drawer)
      document.querySelectorAll('.theme-option-btn, .theme-segmented-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const newPref = btn.getAttribute('data-theme-value');
          if (newPref) {
            this.setPreference(newPref);
          }
        });
      });
    }
  };

  // Expose ThemeManager aliases for compatibility
  ThemeManager.setTheme = function(theme) {
    return this.setPreference(theme);
  };
  ThemeManager.toggleTheme = function() {
    return this.toggle();
  };

  // Expose immediately so page scripts can access anytime
  window.ThemeManager = ThemeManager;
  window.InfinityBlog = window.BlogCMS = {
    getCsrfToken,
    ensureCsrfToken,
    showAlert,
    ThemeManager
  };

  // Initialize Theme Manager immediately if DOM is ready, or on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ThemeManager.init());
  } else {
    ThemeManager.init();
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

    // Unified Search Experience: header search + hero search synchronization & keyboard shortcut
    function initUnifiedSearch() {
      const navInput = document.getElementById('nav-search-input');
      const heroInput = document.getElementById('hero-search-input');
      const heroClearBtn = document.getElementById('hero-search-clear');

      function syncInputs(val, source) {
        if (source !== navInput && navInput) navInput.value = val;
        if (source !== heroInput && heroInput) heroInput.value = val;
        if (heroClearBtn) {
          heroClearBtn.style.display = val.trim() ? 'block' : 'none';
        }
      }

      if (navInput) {
        navInput.addEventListener('input', () => syncInputs(navInput.value, navInput));
      }

      if (heroInput) {
        heroInput.addEventListener('input', () => syncInputs(heroInput.value, heroInput));
      }

      if (heroClearBtn && heroInput) {
        heroClearBtn.addEventListener('click', (e) => {
          e.preventDefault();
          heroInput.value = '';
          syncInputs('', heroClearBtn);
          heroInput.focus();
          if (new URLSearchParams(window.location.search).has('search')) {
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.pathname + (url.search ? url.search : '');
          }
        });
      }

      // Keyboard shortcut: Press '/' to focus search when not in an input/textarea
      document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) && !e.ctrlKey && !e.metaKey) {
          e.preventDefault();
          const target = heroInput || navInput;
          if (target) {
            target.focus();
            target.select();
          }
        }
      });
    }
    initUnifiedSearch();

    // Navigation Dropdown Controls (More, Admin, User Profile, etc.)
    const navDropdowns = document.querySelectorAll('.nav-dropdown');

    function closeAllNavDropdowns() {
      navDropdowns.forEach(dropdown => {
        if (dropdown.classList.contains('open')) {
          dropdown.classList.remove('open');
          const btn = dropdown.querySelector('.nav-dropdown-btn');
          if (btn) {
            btn.setAttribute('aria-expanded', 'false');
          }
        }
      });
    }

    // Retain legacy closeAdminDropdown reference for backward compatibility
    function closeAdminDropdown() {
      closeAllNavDropdowns();
    }

    if (navDropdowns.length > 0) {
      navDropdowns.forEach(dropdown => {
        const btn = dropdown.querySelector('.nav-dropdown-btn');
        if (!btn) return;

        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const wasOpen = dropdown.classList.contains('open');
          closeAllNavDropdowns();
          if (!wasOpen) {
            dropdown.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
          }
        });
      });

      document.addEventListener('click', (e) => {
        if (!e.target.closest('.nav-dropdown')) {
          closeAllNavDropdowns();
        }
      });
    }

    // Specific Full Accessibility Support for User Profile Dropdown Menu
    const userDropdown = document.getElementById('user-nav-dropdown');
    const userBtn = document.getElementById('user-nav-dropdown-btn');
    const userMenu = document.getElementById('user-nav-dropdown-menu');

    if (userDropdown && userBtn && userMenu) {
      const getMenuItems = () => Array.from(userMenu.querySelectorAll('a[role="menuitem"]'));

      userBtn.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === ' ' || e.key === 'Enter') {
          e.preventDefault();
          closeAllNavDropdowns();
          userDropdown.classList.add('open');
          userBtn.setAttribute('aria-expanded', 'true');
          const items = getMenuItems();
          if (items.length > 0) items[0].focus();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          closeAllNavDropdowns();
          userDropdown.classList.add('open');
          userBtn.setAttribute('aria-expanded', 'true');
          const items = getMenuItems();
          if (items.length > 0) items[items.length - 1].focus();
        }
      });

      userMenu.addEventListener('keydown', (e) => {
        const items = getMenuItems();
        const currentIndex = items.indexOf(document.activeElement);

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          const nextIndex = (currentIndex + 1) % items.length;
          items[nextIndex].focus();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          const prevIndex = (currentIndex - 1 + items.length) % items.length;
          items[prevIndex].focus();
        } else if (e.key === 'Home') {
          e.preventDefault();
          if (items.length > 0) items[0].focus();
        } else if (e.key === 'End') {
          e.preventDefault();
          if (items.length > 0) items[items.length - 1].focus();
        } else if (e.key === 'Escape') {
          e.preventDefault();
          userDropdown.classList.remove('open');
          userBtn.setAttribute('aria-expanded', 'false');
          userBtn.focus();
        } else if (e.key === 'Tab') {
          userDropdown.classList.remove('open');
          userBtn.setAttribute('aria-expanded', 'false');
        }
      });

      // Close menu when any link inside is clicked
      userMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
          userDropdown.classList.remove('open');
          userBtn.setAttribute('aria-expanded', 'false');
        });
      });
    }

    // Mobile Navigation Drawer Controls (Left Off-Canvas)
    const mobileToggle = document.getElementById('mobile-nav-toggle');
    const mobileDrawer = document.getElementById('mobile-nav-drawer');
    const mobileBackdrop = document.getElementById('mobile-nav-backdrop');
    const mobileCloseBtn = document.getElementById('mobile-nav-close-btn');

    function openMobileDrawer() {
      if (!mobileDrawer) return;
      mobileDrawer.classList.add('open');
      mobileDrawer.setAttribute('aria-hidden', 'false');
      if (mobileToggle) mobileToggle.setAttribute('aria-expanded', 'true');
      if (mobileBackdrop) mobileBackdrop.classList.add('open');
      document.body.classList.add('drawer-open');

      const mobileSearch = mobileDrawer.querySelector('.mobile-search-input');
      setTimeout(() => {
        if (mobileCloseBtn) {
          mobileCloseBtn.focus();
        } else if (mobileSearch) {
          mobileSearch.focus();
        }
      }, 50);
    }

    function closeMobileDrawer() {
      if (!mobileDrawer || !mobileDrawer.classList.contains('open')) return;
      const hadFocusInside = mobileDrawer.contains(document.activeElement);
      mobileDrawer.classList.remove('open');
      mobileDrawer.setAttribute('aria-hidden', 'true');
      if (mobileBackdrop) mobileBackdrop.classList.remove('open');
      document.body.classList.remove('drawer-open');
      if (mobileToggle) {
        mobileToggle.setAttribute('aria-expanded', 'false');
        if (hadFocusInside) {
          mobileToggle.focus();
        }
      }
    }

    if (mobileToggle && mobileDrawer) {
      mobileToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        if (mobileDrawer.classList.contains('open')) {
          closeMobileDrawer();
        } else {
          openMobileDrawer();
        }
      });

      if (mobileCloseBtn) {
        mobileCloseBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          closeMobileDrawer();
        });
      }

      if (mobileBackdrop) {
        mobileBackdrop.addEventListener('click', (e) => {
          e.stopPropagation();
          closeMobileDrawer();
        });
      }

      // Close mobile drawer on Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobileDrawer.classList.contains('open')) {
          closeMobileDrawer();
        }
      });

      // Close mobile drawer if clicking outside (safety fallback)
      document.addEventListener('click', (e) => {
        if (mobileDrawer.classList.contains('open') &&
            !mobileDrawer.contains(e.target) &&
            !mobileToggle.contains(e.target)) {
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

    // Header User Account Menu Dropdown Controls
    const headerUserBtn = document.getElementById('header-user-menu-btn');
    const headerUserDropdown = document.getElementById('header-user-dropdown');
    if (headerUserBtn && headerUserDropdown) {
      headerUserBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = headerUserDropdown.style.display === 'flex';
        headerUserDropdown.style.display = isOpen ? 'none' : 'flex';
        headerUserBtn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      });

      document.addEventListener('click', (e) => {
        if (headerUserDropdown.style.display === 'flex' && !headerUserDropdown.contains(e.target) && !headerUserBtn.contains(e.target)) {
          headerUserDropdown.style.display = 'none';
          headerUserBtn.setAttribute('aria-expanded', 'false');
        }
      });

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && headerUserDropdown.style.display === 'flex') {
          headerUserDropdown.style.display = 'none';
          headerUserBtn.setAttribute('aria-expanded', 'false');
          headerUserBtn.focus();
        }
      });
    }

    // Header Global Search Clear Button Sync
    const headerSearchInput = document.getElementById('header-search-input');
    const headerSearchClear = document.getElementById('header-search-clear');
    if (headerSearchInput && headerSearchClear) {
      headerSearchInput.addEventListener('input', () => {
        headerSearchClear.style.display = headerSearchInput.value.trim() ? 'flex' : 'none';
      });
      headerSearchClear.addEventListener('click', () => {
        headerSearchInput.value = '';
        headerSearchClear.style.display = 'none';
        headerSearchInput.focus();
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
    function checkUserLoggedIn() {
      return document.body.getAttribute('data-user-logged-in') === '1' ||
             !!document.querySelector('.user-profile-link') ||
             !!document.querySelector('.sidebar-user-info') ||
             !!document.getElementById('header-user-menu-btn');
    }

    // Social Follow / Unfollow AJAX Handler
    document.querySelectorAll('.btn-follow-toggle').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const authorId = btn.getAttribute('data-author-id');
        if (!authorId) return;

        if (!checkUserLoggedIn()) {
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

    // Community Join AJAX Handler for Sidebar & Community Cards
    document.querySelectorAll('.btn-community-join').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        const slug = btn.getAttribute('data-slug');
        if (!slug) return;

        const isMember = btn.classList.contains('joined') || btn.getAttribute('data-is-member') === '1';
        if (isMember) {
          window.location.href = `/community/${encodeURIComponent(slug)}`;
          return;
        }

        if (!checkUserLoggedIn()) {
          window.location.href = `/login?redirect=${encodeURIComponent('/community/' + slug)}`;
          return;
        }

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.textContent = 'Joining...';

        try {
          const csrf = await ensureCsrfToken();
          const res = await fetch(`/api/chat/rooms/${encodeURIComponent(slug)}/join`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': csrf
            }
          });
          const data = await res.json().catch(() => null);
          if (res.ok && data?.data) {
            btn.classList.add('joined');
            btn.setAttribute('data-is-member', '1');
            btn.innerHTML = '<span>✓</span> Joined';
            btn.title = 'You are a member of this community. Click to view discussions and posts.';

            document.querySelectorAll(`.community-member-count[data-slug="${slug}"]`).forEach(countEl => {
              const newCount = data.data.member_count;
              if (newCount !== undefined) {
                countEl.textContent = `${newCount} ${newCount === 1 ? 'member' : 'members'}`;
              } else {
                let cur = parseInt(countEl.textContent, 10) || 0;
                countEl.textContent = `${cur + 1} members`;
              }
            });
            showAlert(data?.data?.message || 'You have joined this community!', 'success');
            // Take the user directly into that specific community discussion/chat
            window.location.href = `/community/${encodeURIComponent(slug)}`;
            return;
          } else {
            const errorMsg = data?.error?.message || 'Could not join community.';
            showAlert(errorMsg, 'error');
            btn.innerHTML = originalText;
          }
        } catch (err) {
          showAlert('Network error while joining community.', 'error');
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
      const moreBadge = document.getElementById('more-unread-badge');
      if (!navBadge && !mobBadge && !moreBadge) return;

      try {
        const res = await fetch('/api/messages/unread-count', {
          headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
          const data = await res.json();
          const count = data.data?.unread_count || 0;
          [navBadge, mobBadge, moreBadge].forEach(badge => {
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

    if (checkUserLoggedIn()) {
      updateUnreadCount();
      setInterval(updateUnreadCount, 15000);
    }

    // =========================================================================
    // Share Dialog (Followed Contacts DM + WhatsApp & Social Sharing)
    // =========================================================================
    let lastFocusedElement = null;
    let selectedContactIds = new Set();
    let followedContactsCache = null;

    function escapeHtml(str) {
      if (!str) return '';
      return String(str).replace(/[&<>"']/g, function(m) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
      });
    }

    window.openShareModal = function (url, title, postId) {
      const modal = document.getElementById('share-article-modal');
      const input = document.getElementById('share-modal-url-input');
      const waBtn = document.getElementById('shareWhatsAppBtn');
      const titlePreview = document.getElementById('shareModalArticleTitle');

      const targetUrl = url || window.location.href;
      const targetTitle = title || document.title;
      window.currentSharePostId = postId || null;

      if (input) input.value = targetUrl;
      if (waBtn) {
        waBtn.href = 'https://wa.me/?text=' + encodeURIComponent(targetTitle + '\n' + targetUrl);
      }
      if (titlePreview) {
        titlePreview.textContent = targetTitle;
      }
      const sendBtn = document.getElementById('shareSendDmBtn');
      if (sendBtn && postId) {
        sendBtn.setAttribute('onclick', `sendArticleDirectMessage(${postId})`);
      }

      if (modal) {
        lastFocusedElement = document.activeElement;
        modal.style.display = 'flex';
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';

        // Load followed contacts if user is authenticated and list container is present
        const listEl = document.getElementById('shareFollowingList');
        if (listEl) {
          loadShareContacts();
        }

        // Accessibility: focus on close button
        const closeBtn = modal.querySelector('.alert-close-btn');
        if (closeBtn) closeBtn.focus();
      }
    };

    window.closeShareModal = function () {
      const modal = document.getElementById('share-article-modal');
      if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('open');
      }
      document.body.style.overflow = '';
      const tooltip = document.getElementById('share-copy-tooltip');
      if (tooltip) tooltip.style.display = 'none';
      const feedback = document.getElementById('shareDmFeedback');
      if (feedback) { feedback.textContent = ''; feedback.style.color = ''; }

      if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }
    };

    // Close share modal on outside click
    document.addEventListener('click', function (e) {
      const modal = document.getElementById('share-article-modal');
      if (modal && modal.classList.contains('open') && e.target === modal) {
        closeShareModal();
      }
    });

    async function loadShareContacts() {
      const listEl = document.getElementById('shareFollowingList');
      if (!listEl) return;

      if (followedContactsCache === null) {
        listEl.innerHTML = '<div style="text-align: center; padding: 14px; color: var(--text-muted); font-size: 0.85rem;">Loading contacts...</div>';
        try {
          const res = await fetch('/api/me/following', { headers: { 'Accept': 'application/json' } });
          if (res.ok) {
            const json = await res.json();
            followedContactsCache = json.data?.following || [];
          } else {
            followedContactsCache = [];
          }
        } catch (e) {
          followedContactsCache = [];
        }
      }

      renderShareContacts(followedContactsCache);
    }

    function renderShareContacts(contacts) {
      const listEl = document.getElementById('shareFollowingList');
      if (!listEl) return;

      if (!contacts || contacts.length === 0) {
        listEl.innerHTML = '<div style="text-align: center; padding: 14px; color: var(--text-muted); font-size: 0.85rem;">You are not following any authors yet.</div>';
        return;
      }

      listEl.innerHTML = '';
      contacts.forEach(user => {
        const item = document.createElement('div');
        item.className = 'share-contact-item' + (selectedContactIds.has(user.id) ? ' selected' : '');
        item.setAttribute('role', 'checkbox');
        item.setAttribute('aria-checked', selectedContactIds.has(user.id) ? 'true' : 'false');
        item.tabIndex = 0;

        const avatarInitial = (user.display_name || user.username || 'A').charAt(0).toUpperCase();
        const badgeHtml = user.has_author_badge ? '<span class="badge-author-earned" style="margin-left: 4px; font-size: 0.65rem; padding: 1px 6px;"><span class="badge-author-icon">✍</span> Author</span>' : '';

        item.innerHTML = `
          <div style="display: flex; align-items: center; gap: 9px; min-width: 0;">
            <div style="width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #818cf8, #4f46e5); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; flex-shrink: 0;">
              ${avatarInitial}
            </div>
            <div style="min-width: 0; overflow: hidden;">
              <div style="font-size: 0.84rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <span>${escapeHtml(user.display_name || user.username)}</span>
                ${badgeHtml}
              </div>
              <div style="font-size: 0.74rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                @${escapeHtml(user.username)}
              </div>
            </div>
          </div>
          <div class="share-contact-checkbox">
            ${selectedContactIds.has(user.id) ? '✓' : ''}
          </div>
        `;

        function toggleSelect() {
          if (selectedContactIds.has(user.id)) {
            selectedContactIds.delete(user.id);
            item.classList.remove('selected');
            item.setAttribute('aria-checked', 'false');
            item.querySelector('.share-contact-checkbox').textContent = '';
          } else {
            selectedContactIds.add(user.id);
            item.classList.add('selected');
            item.setAttribute('aria-checked', 'true');
            item.querySelector('.share-contact-checkbox').textContent = '✓';
          }
          updateShareSelectedCount();
        }

        item.addEventListener('click', toggleSelect);
        item.addEventListener('keydown', (e) => {
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            toggleSelect();
          }
        });

        listEl.appendChild(item);
      });
    }

    function updateShareSelectedCount() {
      const countEl = document.getElementById('shareSelectedCount');
      const sendBtn = document.getElementById('shareSendDmBtn');
      const count = selectedContactIds.size;
      if (countEl) countEl.textContent = count + ' selected';
      if (sendBtn) sendBtn.disabled = (count === 0);
    }

    // Filter contacts search
    document.addEventListener('input', function (e) {
      if (e.target && e.target.id === 'shareFollowingSearch') {
        const query = e.target.value.trim().toLowerCase();
        if (!followedContactsCache) return;
        const filtered = followedContactsCache.filter(u =>
          (u.username && u.username.toLowerCase().includes(query)) ||
          (u.display_name && u.display_name.toLowerCase().includes(query))
        );
        renderShareContacts(filtered);
      }
    });

    window.sendArticleDirectMessage = async function (postId) {
      const sendBtn = document.getElementById('shareSendDmBtn');
      const feedback = document.getElementById('shareDmFeedback');
      const noteInput = document.getElementById('shareDmNote');
      const urlInput = document.getElementById('share-modal-url-input');

      let targetPostId = postId || window.currentSharePostId;
      if (!targetPostId && urlInput && urlInput.value) {
        const m = urlInput.value.match(/\/article\/(\d+)/);
        if (m) targetPostId = parseInt(m[1], 10);
      }

      if (selectedContactIds.size === 0) return;

      if (sendBtn) sendBtn.disabled = true;
      if (feedback) {
        feedback.textContent = 'Sending story...';
        feedback.style.color = '#4f46e5';
      }

      try {
        const csrf = await ensureCsrfToken();
        const res = await fetch('/api/share/direct-message', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({
            post_id: targetPostId,
            recipient_ids: Array.from(selectedContactIds),
            message: noteInput ? noteInput.value.trim() : '',
            post_url: urlInput ? urlInput.value.trim() : ''
          })
        });

        const json = await res.json();
        if (res.ok && json.data?.success) {
          const sentCount = json.data.sent_count || selectedContactIds.size;
          if (feedback) {
            feedback.textContent = `✓ Successfully sent to ${sentCount} contact(s)!`;
            feedback.style.color = '#16a34a';
          }
          selectedContactIds.clear();
          updateShareSelectedCount();
          if (noteInput) noteInput.value = '';
          setTimeout(() => {
            closeShareModal();
          }, 1500);
        } else {
          const msg = json.error?.message || 'Failed to send share message.';
          if (feedback) {
            feedback.textContent = msg;
            feedback.style.color = '#ef4444';
          }
          if (sendBtn) sendBtn.disabled = false;
        }
      } catch (e) {
        if (feedback) {
          feedback.textContent = 'Network error while sending.';
          feedback.style.color = '#ef4444';
        }
        if (sendBtn) sendBtn.disabled = false;
      }
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
      openShareModal(url, title);
    };

    // =========================================================================
    // Mention Autocomplete Controller (@username discovery)
    // =========================================================================
    function setupMentionAutocomplete(inputEl) {
      if (!inputEl) return;

      let dropdown = inputEl.parentElement ? inputEl.parentElement.querySelector('.mention-autocomplete-dropdown') : null;
      if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.className = 'mention-autocomplete-dropdown';
        if (inputEl.parentElement) {
          inputEl.parentElement.appendChild(dropdown);
        } else {
          document.body.appendChild(dropdown);
        }
      }

      let activeIndex = -1;
      let currentUsers = [];
      let mentionQuery = null;
      let mentionStartIndex = -1;
      let debounceTimer = null;

      function closeDropdown() {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        activeIndex = -1;
        currentUsers = [];
        mentionQuery = null;
        mentionStartIndex = -1;
      }

      function insertMention(user) {
        if (!user || mentionStartIndex < 0) return;
        const val = inputEl.value;
        const cursor = inputEl.selectionStart;
        const before = val.slice(0, mentionStartIndex);
        const after = val.slice(cursor);
        const handle = user.username;
        const replacement = '@' + handle + ' ';
        inputEl.value = before + replacement + after;
        const newCursor = before.length + replacement.length;
        inputEl.setSelectionRange(newCursor, newCursor);
        inputEl.focus();
        closeDropdown();
      }

      async function fetchUsers(query) {
        try {
          const res = await fetch('/api/users/mention-search?q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json' }
          });
          if (!res.ok) return [];
          const json = await res.json();
          return json.data?.users || [];
        } catch (e) {
          return [];
        }
      }

      function renderDropdown(users) {
        currentUsers = users;
        activeIndex = 0;
        dropdown.innerHTML = '';

        if (!users || users.length === 0) {
          closeDropdown();
          return;
        }

        users.forEach((user, idx) => {
          const item = document.createElement('div');
          item.className = 'mention-dropdown-item' + (idx === 0 ? ' active' : '');
          const initial = (user.display_name || user.username || 'U').charAt(0).toUpperCase();
          const badgeHtml = user.has_author_badge ? '<span class="badge-author-earned" style="font-size: 0.65rem; padding: 1px 6px;"><span class="badge-author-icon">✍</span> Author</span>' : '';

          item.innerHTML = `
            <div class="mention-item-avatar">${initial}</div>
            <div class="mention-item-info">
              <div class="mention-item-name">
                <span>${escapeHtml(user.display_name || user.username)}</span>
                ${badgeHtml}
              </div>
              <div class="mention-item-handle">@${escapeHtml(user.username)}</div>
            </div>
          `;

          item.addEventListener('mousedown', (e) => {
            e.preventDefault();
            insertMention(user);
          });

          dropdown.appendChild(item);
        });

        // Position dropdown relative to input
        dropdown.style.display = 'block';
        dropdown.style.bottom = '100%';
        dropdown.style.top = 'auto';
        dropdown.style.left = '0';
        dropdown.style.marginBottom = '6px';
      }

      function handleInput() {
        const val = inputEl.value;
        const cursor = inputEl.selectionStart;
        const textBeforeCursor = val.slice(0, cursor);

        const match = textBeforeCursor.match(/(?:^|\s)@([a-zA-Z0-9_\.]*)$/);
        if (!match) {
          closeDropdown();
          return;
        }

        mentionQuery = match[1];
        mentionStartIndex = cursor - match[1].length - 1;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(async () => {
          const users = await fetchUsers(mentionQuery);
          renderDropdown(users);
        }, 150);
      }

      inputEl.addEventListener('input', handleInput);

      inputEl.addEventListener('keydown', function (e) {
        if (dropdown.style.display === 'block' && currentUsers.length > 0) {
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = (activeIndex + 1) % currentUsers.length;
            updateActiveItem();
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = (activeIndex - 1 + currentUsers.length) % currentUsers.length;
            updateActiveItem();
          } else if (e.key === 'Enter' || e.key === 'Tab') {
            if (activeIndex >= 0 && activeIndex < currentUsers.length) {
              e.preventDefault();
              insertMention(currentUsers[activeIndex]);
            }
          } else if (e.key === 'Escape') {
            e.preventDefault();
            closeDropdown();
          }
        }
      });

      function updateActiveItem() {
        const items = dropdown.querySelectorAll('.mention-dropdown-item');
        items.forEach((it, idx) => {
          if (idx === activeIndex) {
            it.classList.add('active');
            it.scrollIntoView({ block: 'nearest' });
          } else {
            it.classList.remove('active');
          }
        });
      }

      inputEl.addEventListener('blur', function () {
        setTimeout(closeDropdown, 200);
      });
    }

    function initMentions() {
      const commentInput = document.getElementById('commentContentInput');
      if (commentInput) setupMentionAutocomplete(commentInput);

      const chatInput = document.getElementById('chatInputMessage');
      if (chatInput) setupMentionAutocomplete(chatInput);
    }

    // Notification Center Controls
    function initNotifications() {
      const notifBtn = document.getElementById('nav-notif-btn');
      const notifDropdown = document.getElementById('nav-notif-dropdown');
      const notifBadge = document.getElementById('nav-notif-badge');
      const notifList = document.getElementById('nav-notif-list');
      const notifMarkAll = document.getElementById('nav-notif-mark-all');
      const mobileDrawerNotifLink = document.getElementById('mobile-drawer-notif-link');
      const mobileDrawerNotifBadge = document.getElementById('mobile-drawer-notif-badge');
      const sidebarNotifBtn = document.getElementById('sidebar-notif-btn');
      const sidebarNotifBadge = document.getElementById('sidebar-notif-badge');

      if (!notifBtn && !mobileDrawerNotifLink && !sidebarNotifBtn) return;

      function updateBadges(count) {
        const text = count > 99 ? '99+' : String(count);
        const show = count > 0;
        if (notifBadge) {
          notifBadge.textContent = text;
          notifBadge.style.display = show ? 'inline-flex' : 'none';
        }
        if (mobileDrawerNotifBadge) {
          mobileDrawerNotifBadge.textContent = text;
          mobileDrawerNotifBadge.style.display = show ? 'inline-flex' : 'none';
        }
        if (sidebarNotifBadge) {
          sidebarNotifBadge.textContent = text;
          sidebarNotifBadge.style.display = show ? 'inline-flex' : 'none';
        }
      }

      function formatTimeAgo(dateStr) {
        try {
          const d = new Date(dateStr.replace(' ', 'T') + 'Z');
          const now = new Date();
          const diffSec = Math.floor((now.getTime() - d.getTime()) / 1000);
          if (diffSec < 60) return 'Just now';
          if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
          if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`;
          if (diffSec < 604800) return `${Math.floor(diffSec / 86400)}d ago`;
          return d.toLocaleDateString();
        } catch {
          return dateStr;
        }
      }

      function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
      }

      async function fetchUnreadCount() {
        try {
          const res = await fetch('/api/notifications/count', {
            headers: { 'Accept': 'application/json' }
          });
          if (res.ok) {
            const data = await res.json();
            const count = data.data?.unread_count ?? 0;
            updateBadges(count);
          }
        } catch (e) {
          // Silent polling error
        }
      }

      async function loadNotifications() {
        if (!notifList) return;
        notifList.innerHTML = '<div style="padding: 24px 16px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">Loading notifications...</div>';
        try {
          const res = await fetch('/api/notifications?limit=25', {
            headers: { 'Accept': 'application/json' }
          });
          if (!res.ok) throw new Error('Failed to load');
          const json = await res.json();
          const items = json.data?.notifications || [];
          const count = json.data?.unread_count ?? 0;
          updateBadges(count);

          if (items.length === 0) {
            notifList.innerHTML = '<div class="nav-notif-empty">No notifications yet</div>';
            return;
          }

          notifList.innerHTML = '';
          items.forEach(item => {
            const el = document.createElement('div');
            el.className = `nav-notif-item ${item.is_read ? '' : 'unread'}`;
            el.dataset.id = item.id;
            el.dataset.url = item.target_url || '/';

            const initial = (item.actor_name || 'U').charAt(0).toUpperCase();
            const avatarHtml = item.actor_avatar
              ? `<img src="${escapeHtml(item.actor_avatar)}" class="nav-notif-actor-avatar" alt="${escapeHtml(item.actor_name)}" onerror="this.outerHTML='<div class=\\\'nav-notif-avatar-fallback\\\'>${initial}</div>'">`
              : `<div class="nav-notif-avatar-fallback">${initial}</div>`;

            el.innerHTML = `
              ${avatarHtml}
              <div class="nav-notif-content">
                <div class="nav-notif-msg">${escapeHtml(item.message)}</div>
                <div class="nav-notif-time">${formatTimeAgo(item.created_at)}</div>
              </div>
              <button type="button" class="nav-notif-delete-btn" title="Dismiss" aria-label="Dismiss notification">&times;</button>
            `;

            // Click on item to navigate & mark read
            el.addEventListener('click', async (e) => {
              if (e.target.closest('.nav-notif-delete-btn')) return;
              if (!item.is_read) {
                const csrf = await ensureCsrfToken();
                fetch(`/api/notifications/${item.id}/read`, {
                  method: 'POST',
                  headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
                }).catch(() => {});
              }
              closeDropdown();
              window.location.href = item.target_url || '/';
            });

            // Delete / dismiss button
            const delBtn = el.querySelector('.nav-notif-delete-btn');
            if (delBtn) {
              delBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const csrf = await ensureCsrfToken();
                try {
                  const delRes = await fetch(`/api/notifications/${item.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
                  });
                  if (delRes.ok) {
                    el.remove();
                    if (!item.is_read) {
                      fetchUnreadCount();
                    }
                    if (notifList.children.length === 0) {
                      notifList.innerHTML = '<div class="nav-notif-empty">No notifications yet</div>';
                    }
                  }
                } catch {}
              });
            }

            notifList.appendChild(el);
          });
        } catch (err) {
          notifList.innerHTML = '<div style="padding: 24px 16px; text-align: center; color: #ef4444; font-size: 0.85rem;">Failed to load notifications.</div>';
        }
      }

      function openDropdown() {
        if (!notifDropdown) return;
        closeAllNavDropdowns();
        notifDropdown.style.display = 'flex';
        notifBtn?.setAttribute('aria-expanded', 'true');
        notifBtn?.classList.add('active');
        sidebarNotifBtn?.setAttribute('aria-expanded', 'true');
        sidebarNotifBtn?.classList.add('active');
        loadNotifications();
      }

      function closeDropdown() {
        if (!notifDropdown) return;
        notifDropdown.style.display = 'none';
        notifBtn?.setAttribute('aria-expanded', 'false');
        notifBtn?.classList.remove('active');
        sidebarNotifBtn?.setAttribute('aria-expanded', 'false');
        sidebarNotifBtn?.classList.remove('active');
      }

      function toggleDropdown() {
        if (!notifDropdown) return;
        if (notifDropdown.style.display === 'flex') {
          closeDropdown();
        } else {
          openDropdown();
        }
      }

      if (notifBtn) {
        notifBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          toggleDropdown();
        });
      }

      if (sidebarNotifBtn) {
        sidebarNotifBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          toggleDropdown();
        });
      }

      if (mobileDrawerNotifLink) {
        mobileDrawerNotifLink.addEventListener('click', (e) => {
          e.preventDefault();
          closeMobileDrawer();
          toggleDropdown();
        });
      }

      if (notifMarkAll) {
        notifMarkAll.addEventListener('click', async (e) => {
          e.stopPropagation();
          const csrf = await ensureCsrfToken();
          try {
            const res = await fetch('/api/notifications/read-all', {
              method: 'POST',
              headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' }
            });
            if (res.ok) {
              updateBadges(0);
              notifList.querySelectorAll('.nav-notif-item.unread').forEach(el => {
                el.classList.remove('unread');
              });
            }
          } catch {}
        });
      }

      // Close notification dropdown when clicking outside
      document.addEventListener('click', (e) => {
        if (notifDropdown && !notifDropdown.contains(e.target) && !notifBtn?.contains(e.target) && !sidebarNotifBtn?.contains(e.target)) {
          closeDropdown();
        }
      });

      // Escape key handler
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && notifDropdown?.style.display === 'flex') {
          closeDropdown();
        }
      });

      // Fetch initial count & set interval
      fetchUnreadCount();
      setInterval(fetchUnreadCount, 30000);
    }
    initNotifications();

    // =========================================================================
    // Community Feed Controller (Single Composer, Scores, Bookmarks, Menus, Reporting)
    // =========================================================================
    let attachedComposerImageUrl = null;

    window.toggleFeedComposer = function (open) {
      const card = document.getElementById('feed-composer-card');
      const tray = document.getElementById('composer-expanded-tray');
      const textInput = document.getElementById('composer-text-input');
      if (!tray) return;

      if (open) {
        card?.classList.add('is-expanded');
        tray.style.display = 'flex';
        if (textInput) {
          textInput.focus();
        }
      } else {
        card?.classList.remove('is-expanded');
        tray.style.display = 'none';
        if (textInput) {
          textInput.value = '';
          textInput.style.height = '46px';
        }
        removeComposerAttachedImage();
      }
    };

    window.handleComposerImageUpload = async function (input) {
      if (!input || !input.files || input.files.length === 0) return;
      const file = input.files[0];
      const validTypes = ['image/jpeg', 'image/png', 'image/webp'];

      if (!validTypes.includes(file.type)) {
        showAlert('Please upload a valid image file (JPEG, PNG, or WebP).', 'error');
        input.value = '';
        return;
      }

      if (file.size > 10 * 1024 * 1024) {
        showAlert('Image file size must be less than 10MB.', 'error');
        input.value = '';
        return;
      }

      const attachBtn = document.getElementById('composer-attach-btn');
      if (attachBtn) {
        attachBtn.disabled = true;
        attachBtn.innerHTML = `<span>⏳</span> Uploading...`;
      }

      try {
        const csrf = await ensureCsrfToken();
        const formData = new FormData();
        formData.append('image', file);

        const res = await fetch('/upload/inline-image', {
          method: 'POST',
          headers: {
            'X-CSRF-Token': csrf,
            'Accept': 'application/json'
          },
          body: formData
        });

        const json = await res.json();
        if (res.ok && json.data?.url) {
          attachedComposerImageUrl = json.data.url;
          const previewImg = document.getElementById('composer-image-preview');
          const previewTray = document.getElementById('composer-image-preview-tray');
          if (previewImg && previewTray) {
            previewImg.src = json.data.url;
            previewTray.style.display = 'block';
          }
          toggleFeedComposer(true);
        } else {
          showAlert(json.error?.message || 'Failed to upload attached image.', 'error');
          input.value = '';
        }
      } catch (err) {
        showAlert('Network error occurred during image upload.', 'error');
        input.value = '';
      } finally {
        if (attachBtn) {
          attachBtn.disabled = false;
          attachBtn.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path></svg> <span>Add Image</span>`;
        }
      }
    };

    window.removeComposerAttachedImage = function () {
      attachedComposerImageUrl = null;
      const previewImg = document.getElementById('composer-image-preview');
      const previewTray = document.getElementById('composer-image-preview-tray');
      const fileInput = document.getElementById('composer-image-file-input');
      if (previewTray) previewTray.style.display = 'none';
      if (previewImg) previewImg.src = '';
      if (fileInput) fileInput.value = '';
    };

    window.submitQuickPost = async function (e) {
      if (e) e.preventDefault();
      const submitBtn = document.getElementById('composer-submit-btn');
      const textInput = document.getElementById('composer-text-input');
      const catSelect = document.getElementById('composer-category-select');

      let rawText = textInput?.value?.trim() || '';
      if (attachedComposerImageUrl) {
        rawText = rawText ? `${rawText}\n\n![Embedded Image](${attachedComposerImageUrl})` : `![Embedded Image](${attachedComposerImageUrl})`;
      }

      const categoryId = catSelect ? parseInt(catSelect.value, 10) : 1;

      if (!rawText) {
        showAlert('Please enter your idea or attach an image.', 'error');
        textInput?.focus();
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Publishing...';
      }

      try {
        const csrf = await ensureCsrfToken();
        const res = await fetch('/api/feed/quick-post', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({
            content: rawText,
            category_id: categoryId
          })
        });

        const data = await res.json();
        if (res.ok && data.data?.post_id) {
          showAlert('Post published successfully!', 'success');
          toggleFeedComposer(false);
          setTimeout(() => {
            window.location.href = '/?published=1';
          }, 800);
        } else {
          showAlert(data.error?.message || 'Failed to publish post.', 'error');
        }
      } catch (err) {
        showAlert('Network error occurred while publishing.', 'error');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Publish Idea ✦';
        }
      }
    };

    // Global Report Post Handlers
    window.openReportPostModal = function (postId, postTitle) {
      const modal = document.getElementById('report-article-modal');
      if (!modal) return;

      const targetInput = document.getElementById('reportTargetPostId');
      if (targetInput) {
        targetInput.value = postId ? String(postId) : (window.__currentArticleId ? String(window.__currentArticleId) : '');
      }

      const titleEl = document.getElementById('reportModalTitle');
      const subtitleEl = document.getElementById('reportModalSubtitle');
      if (titleEl) {
        titleEl.textContent = 'Report Content';
      }
      if (subtitleEl) {
        subtitleEl.textContent = postTitle ? `Reporting: "${postTitle}"` : 'Help keep Infinity Blog safe, trustworthy, and constructive.';
      }

      const form = document.getElementById('reportPostForm');
      if (form) form.reset();

      const counter = document.getElementById('reportCharCounter');
      if (counter) {
        counter.textContent = '0 / 500';
        counter.style.color = 'var(--text-muted)';
      }

      const feedback = document.getElementById('reportFormFeedback');
      if (feedback) {
        feedback.style.display = 'none';
        feedback.textContent = '';
      }

      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';

      const reasonSelect = document.getElementById('reportReasonSelect');
      if (reasonSelect) {
        reasonSelect.focus();
      }
    };

    window.closeReportPostModal = function () {
      const modal = document.getElementById('report-article-modal');
      if (!modal) return;
      modal.style.display = 'none';
      document.body.style.overflow = '';
      const form = document.getElementById('reportPostForm');
      if (form) form.reset();
      const feedback = document.getElementById('reportFormFeedback');
      if (feedback) {
        feedback.style.display = 'none';
        feedback.textContent = '';
      }
    };

    window.updateReportCharCount = function (textarea) {
      const counter = document.getElementById('reportCharCounter');
      if (counter && textarea) {
        const len = textarea.value.length;
        counter.textContent = `${len} / 500`;
        if (len > 450) {
          counter.style.color = '#ef4444';
        } else {
          counter.style.color = 'var(--text-muted)';
        }
      }
    };

    window.handleReportPostSubmit = async function (event) {
      if (event) event.preventDefault();
      const btn = document.getElementById('reportSubmitBtn');
      const feedback = document.getElementById('reportFormFeedback');
      const reason = document.getElementById('reportReasonSelect')?.value;
      const explanation = document.getElementById('reportExplanationTextarea')?.value || '';
      const targetInput = document.getElementById('reportTargetPostId');
      const postId = targetInput?.value || window.__currentArticleId;

      if (!reason) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.style.border = '1px solid #fecaca';
          feedback.textContent = 'Please select a reason for reporting.';
        }
        return;
      }

      if (!postId) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.style.border = '1px solid #fecaca';
          feedback.textContent = 'Unable to identify post ID to report.';
        }
        return;
      }

      if (explanation.length > 500) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.style.border = '1px solid #fecaca';
          feedback.textContent = 'Explanation must not exceed 500 characters.';
        }
        return;
      }

      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Submitting...';
      }

      try {
        const csrf = await ensureCsrfToken();
        const res = await fetch(`/api/posts/${postId}/report`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf || ''
          },
          body: JSON.stringify({
            reason: reason,
            explanation: explanation
          })
        });

        const data = await res.json();
        if (res.ok) {
          if (feedback) {
            feedback.style.display = 'block';
            feedback.style.background = '#ecfdf5';
            feedback.style.color = '#065f46';
            feedback.style.border = '1px solid #a7f3d0';
            feedback.textContent = 'Thank you. Your report has been submitted for moderation review.';
          }
          setTimeout(() => {
            closeReportPostModal();
            showAlert('Thank you. The post has been flagged for moderation.', 'success');
          }, 1200);
        } else {
          if (feedback) {
            feedback.style.display = 'block';
            feedback.style.background = '#fef2f2';
            feedback.style.color = '#991b1b';
            feedback.style.border = '1px solid #fecaca';
            feedback.textContent = data.error?.message || 'Could not submit report.';
          }
        }
      } catch (err) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.style.border = '1px solid #fecaca';
          feedback.textContent = 'Network error occurred while submitting report.';
        }
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Submit Report';
        }
      }
    };

    window.openReportReviewModal = function (reviewId) {
      const modal = document.getElementById('report-review-modal');
      if (!modal) return;
      const targetInput = document.getElementById('reportTargetReviewId');
      if (targetInput) targetInput.value = reviewId ? String(reviewId) : '';
      const form = document.getElementById('reportReviewForm');
      if (form) form.reset();
      const feedback = document.getElementById('reportReviewFormFeedback');
      if (feedback) {
        feedback.style.display = 'none';
        feedback.textContent = '';
      }
      const counter = document.getElementById('reportReviewCharCounter');
      if (counter) counter.textContent = '0 / 500';

      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
      const reasonSelect = document.getElementById('reportReviewReasonSelect');
      if (reasonSelect) reasonSelect.focus();
    };

    window.closeReportReviewModal = function () {
      const modal = document.getElementById('report-review-modal');
      if (!modal) return;
      modal.style.display = 'none';
      document.body.style.overflow = '';
      const form = document.getElementById('reportReviewForm');
      if (form) form.reset();
      const feedback = document.getElementById('reportReviewFormFeedback');
      if (feedback) {
        feedback.style.display = 'none';
        feedback.textContent = '';
      }
    };

    window.updateReportReviewCharCount = function (textarea) {
      const counter = document.getElementById('reportReviewCharCounter');
      if (counter && textarea) {
        const len = textarea.value.length;
        counter.textContent = `${len} / 500`;
        counter.style.color = len > 450 ? '#ef4444' : 'var(--text-muted)';
      }
    };

    window.handleReportReviewSubmit = async function (event) {
      if (event) event.preventDefault();
      const btn = document.getElementById('reportReviewSubmitBtn');
      const feedback = document.getElementById('reportReviewFormFeedback');
      const reason = document.getElementById('reportReviewReasonSelect')?.value;
      const explanation = document.getElementById('reportReviewExplanationTextarea')?.value || '';
      const reviewId = document.getElementById('reportTargetReviewId')?.value;

      if (!reason) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.textContent = 'Please select a reason for reporting.';
        }
        return;
      }

      if (!reviewId) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.textContent = 'Unable to identify review ID to report.';
        }
        return;
      }

      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Submitting...';
      }

      try {
        const csrf = await ensureCsrfToken();
        const res = await fetch(`/api/reviews/${reviewId}/report`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf || ''
          },
          body: JSON.stringify({ reason, explanation })
        });

        const data = await res.json();
        if (res.ok) {
          if (feedback) {
            feedback.style.display = 'block';
            feedback.style.background = '#ecfdf5';
            feedback.style.color = '#065f46';
            feedback.textContent = 'Thank you. Your report has been submitted for moderation review.';
          }
          setTimeout(() => {
            closeReportReviewModal();
            showAlert('Review flagged for moderation. Thank you for keeping our community safe.', 'success');
          }, 1200);
        } else {
          if (feedback) {
            feedback.style.display = 'block';
            feedback.style.background = '#fef2f2';
            feedback.style.color = '#991b1b';
            feedback.textContent = data.error?.message || 'Could not submit report.';
          }
        }
      } catch (err) {
        if (feedback) {
          feedback.style.display = 'block';
          feedback.style.background = '#fef2f2';
          feedback.style.color = '#991b1b';
          feedback.textContent = 'Network error occurred while submitting report.';
        }
      } finally {
        if (btn) {
          btn.disabled = false;
          btn.textContent = 'Submit Report';
        }
      }
    };

    window.handleFeedScore = async function (e, postId, scoreVal) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      const card = document.querySelector(`.feed-card[data-post-id="${postId}"]`);
      const likeBtn = card?.querySelector('.btn-feed-like');
      const dislikeBtn = card?.querySelector('.btn-feed-dislike');
      const countEl = card?.querySelector('.like-counter');

      try {
        const csrf = await ensureCsrfToken();
        const res = await fetch(`/posts/${postId}/score`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ score: scoreVal })
        });

        if (res.status === 401) {
          window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
          return;
        }

        const data = await res.json();
        if (res.ok && data.data) {
          const userScore = data.data.user_score;
          if (likeBtn) {
            if (userScore === 5) {
              likeBtn.classList.add('active');
              likeBtn.querySelector('svg')?.setAttribute('fill', 'currentColor');
            } else {
              likeBtn.classList.remove('active');
              likeBtn.querySelector('svg')?.setAttribute('fill', 'none');
            }
          }
          if (dislikeBtn) {
            if (userScore === 1) {
              dislikeBtn.classList.add('active');
              dislikeBtn.querySelector('svg')?.setAttribute('fill', 'currentColor');
            } else {
              dislikeBtn.classList.remove('active');
              dislikeBtn.querySelector('svg')?.setAttribute('fill', 'none');
            }
          }
          if (countEl && typeof data.data.count !== 'undefined') {
            countEl.textContent = data.data.count;
          }
        } else {
          showAlert(data.error?.message || 'Could not update reaction.', 'error');
        }
      } catch (err) {
        showAlert('Network error updating reaction.', 'error');
      }
    };

    window.toggleFeedBookmark = async function (e, postId) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      const card = document.querySelector(`.feed-card[data-post-id="${postId}"]`);
      const bookmarkBtn = card?.querySelector('.btn-feed-bookmark');
      const label = bookmarkBtn?.querySelector('.bookmark-label');
      const isCurrentlySaved = bookmarkBtn ? bookmarkBtn.classList.contains('active') : false;
      const intendedAction = isCurrentlySaved ? 'unsave' : 'save';

      if (bookmarkBtn) {
        bookmarkBtn.disabled = true;
      }

      try {
        const csrf = await ensureCsrfToken();
        const res = await fetch(`/api/library/bookmark/${postId}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({ action: intendedAction })
        });

        if (res.status === 401) {
          window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
          return;
        }

        const data = await res.json();
        if (res.ok && data.data) {
          const isBookmarked = Boolean(data.data.is_bookmarked ?? data.data.bookmarked ?? data.data.saved);
          if (bookmarkBtn) {
            if (isBookmarked) {
              bookmarkBtn.classList.add('active');
              bookmarkBtn.querySelector('svg')?.setAttribute('fill', 'currentColor');
              bookmarkBtn.setAttribute('title', 'Remove bookmark');
              if (label) label.textContent = 'Saved';
            } else {
              bookmarkBtn.classList.remove('active');
              bookmarkBtn.querySelector('svg')?.setAttribute('fill', 'none');
              bookmarkBtn.setAttribute('title', 'Bookmark story');
              if (label) label.textContent = 'Save';
            }
          }

          // Also synchronize the 3-dots dropdown menu item if open/present
          const menuItem = card?.querySelector('.feed-menu-item[onclick*="toggleFeedBookmark"]');
          if (menuItem) {
            menuItem.innerHTML = '<span>🔖</span> ' + (isBookmarked ? 'Remove bookmark' : 'Bookmark story');
          }

          showAlert(isBookmarked ? 'Article added to your reading library.' : 'Article removed from your reading library.', 'success');
        } else {
          showAlert(data.error?.message || 'Could not update bookmark.', 'error');
        }
      } catch (err) {
        showAlert('Network error updating bookmark.', 'error');
      } finally {
        if (bookmarkBtn) {
          bookmarkBtn.disabled = false;
        }
      }
    };

    window.toggleFeedCardMenu = function (e, postId) {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      const menu = document.getElementById(`feed-menu-${postId}`);
      if (!menu) return;
      const isOpen = menu.style.display === 'flex' || menu.style.display === 'block';

      // Close all other open card menus
      document.querySelectorAll('.feed-menu-dropdown').forEach(m => {
        m.style.display = 'none';
      });

      if (!isOpen) {
        menu.style.display = 'flex';
      }
    };

    // Close any feed-card menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.feed-card-menu')) {
        document.querySelectorAll('.feed-menu-dropdown').forEach(m => {
          m.style.display = 'none';
        });
      }
    });

    // Feed and Article Card Click Navigation:
    // Clicking anywhere on a feed or article card (background, title, excerpt, thumbnail, or padding)
    // navigates to the article detail page.
    // Child controls (buttons, author links, category tags, reaction bars, dropdown menus, inputs)
    // and active text selections do NOT trigger card-level navigation.
    const CARD_INTERACTIVE_SELECTOR = [
      'button',
      'input',
      'textarea',
      'select',
      'label',
      '.feed-card-actions',
      '.feed-action-btn',
      '.feed-card-menu',
      '.feed-menu-dropdown',
      '.feed-menu-btn',
      '.feed-menu-item',
      '.dropdown',
      '.dropdown-menu',
      '.dropdown-item',
      '.feed-author-avatar-link',
      '.feed-author-name',
      '.feed-card-category-tag',
      '.article-author-info',
      '.article-author-info a',
      '.badge-category',
      'a[href*="/author/"]',
      'a[href*="category="]',
      'a[href*="remix="]',
      'a[href*="#comments"]',
      'a[href*="/edit"]',
      'a.btn',
      '.feed-comments-btn',
      '.comments-stat-trigger',
      '[data-open-comments]',
      '.engagement-comments-count-val',
      '.feed-comment-counter',
      '.user-avatar',
      '[onclick]'
    ].join(', ');

    function getCardNavigationUrl(card) {
      if (!card) return null;
      const articleUrl = card.getAttribute('data-article-url');
      if (articleUrl) return articleUrl;
      const postId = card.getAttribute('data-post-id');
      if (postId) return `/article/${postId}`;
      return null;
    }

    function shouldIgnoreCardEvent(e, card) {
      // 1. Text selection in progress (user is copying/highlighting text)
      const selection = window.getSelection ? window.getSelection().toString().trim() : '';
      if (selection.length > 0) return true;

      // 2. Interactive control clicked (buttons, menus, author/category links, etc.)
      const interactiveEl = e.target.closest(CARD_INTERACTIVE_SELECTOR);
      if (interactiveEl && card.contains(interactiveEl) && interactiveEl !== card) {
        return true;
      }

      // 3. Explicit link clicked within card (e.g. title link or thumbnail link)
      const link = e.target.closest('a');
      if (link && card.contains(link)) {
        // Native link handles navigation
        return true;
      }

      return false;
    }

    // Left click & modifier click delegation
    document.addEventListener('click', (e) => {
      const card = e.target.closest('.feed-card, .article-card[data-article-url], .article-card[data-post-id]');
      if (!card) return;

      if (shouldIgnoreCardEvent(e, card)) return;

      const url = getCardNavigationUrl(card);
      if (!url) return;

      if (e.metaKey || e.ctrlKey || e.shiftKey) {
        window.open(url, '_blank', 'noopener,noreferrer');
      } else {
        window.location.href = url;
      }
    });

    // Middle click (auxclick button 1) delegation
    document.addEventListener('auxclick', (e) => {
      if (e.button !== 1) return;
      const card = e.target.closest('.feed-card, .article-card[data-article-url], .article-card[data-post-id]');
      if (!card) return;

      if (shouldIgnoreCardEvent(e, card)) return;

      const url = getCardNavigationUrl(card);
      if (url) {
        window.open(url, '_blank', 'noopener,noreferrer');
      }
    });

    // Keyboard navigation: Enter on a focused card opens the article
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const activeEl = document.activeElement;
        if (!activeEl) return;
        if (activeEl.matches && activeEl.matches('.feed-card, .article-card[data-article-url], .article-card[data-post-id]')) {
          const url = getCardNavigationUrl(activeEl);
          if (url) {
            e.preventDefault();
            if (e.metaKey || e.ctrlKey) {
              window.open(url, '_blank', 'noopener,noreferrer');
            } else {
              window.location.href = url;
            }
          }
        }
      }
    });

    // Escape key closes modals, dropdowns, and mobile drawer
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        if (typeof window.closeDeleteModal === 'function') {
          window.closeDeleteModal();
        }
        if (typeof window.closeShareModal === 'function') {
          window.closeShareModal();
        }
        if (typeof window.closeReportReviewModal === 'function') {
          window.closeReportReviewModal();
        }
        if (typeof window.closeReportPostModal === 'function') {
          window.closeReportPostModal();
        }
        document.querySelectorAll('.feed-menu-dropdown').forEach(m => {
          m.style.display = 'none';
        });
        closeAllNavDropdowns();
        closeMobileDrawer();
      }
    });

    // Initialize @mention autocompletion for comments and chat
    initMentions();
  });

  window.InfinityBlog = window.BlogCMS = {
    getCsrfToken,
    ensureCsrfToken,
    showAlert,
    ThemeManager
  };
})();
