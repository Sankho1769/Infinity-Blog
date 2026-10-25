/**
 * Infinity Blog — Threaded Comments and Replies System
 * Handles feed card discussion trigger, mobile claymorphic bottom sheet,
 * desktop glassmorphism drawer, nested thread lines, reactions, and count synchronization.
 */
(function() {
  'use strict';

  let currentPostId = null;
  let activeParentReplyId = null;
  let cachedComments = [];
  let isSubmitting = false;

  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatRelativeTime(dateStr) {
    if (!dateStr) return 'just now';
    const timestamp = new Date(dateStr.replace(/-/g, '/')).getTime();
    if (isNaN(timestamp)) return 'just now';
    const diff = Math.floor((Date.now() - timestamp) / 1000);
    if (diff < 45) return 'just now';
    if (diff < 3600) return Math.max(1, Math.floor(diff / 60)) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    if (diff < 31536000) return Math.floor(diff / 2592000) + 'mo ago';
    return Math.floor(diff / 31536000) + 'y ago';
  }

  // Open Comments Drawer for a specific post
  window.openCommentsDrawer = async function(event, postId) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    if (!postId) return;
    currentPostId = Number(postId);
    activeParentReplyId = null;

    const backdrop = document.getElementById('discussionModalBackdrop');
    const drawer = document.getElementById('discussionDrawer');
    if (!backdrop || !drawer) return;

    // Show backdrop & drawer
    backdrop.style.display = 'flex';
    requestAnimationFrame(() => {
      backdrop.classList.add('is-open');
      drawer.classList.add('is-open');
    });

    // Lock page background scrolling without losing scroll position
    document.body.classList.add('discussion-modal-open');

    // Reset composer state
    cancelDiscussionReply();
    const input = document.getElementById('discussionComposerInput');
    if (input) {
      input.value = '';
      input.style.height = 'auto';
    }

    // Set loading state
    const loading = document.getElementById('discussionLoading');
    const empty = document.getElementById('discussionEmptyState');
    const tree = document.getElementById('discussionCommentsTree');
    const badge = document.getElementById('discussionCountBadge');
    const contextBox = document.getElementById('discussionPostContext');

    if (loading) loading.style.display = 'flex';
    if (empty) empty.style.display = 'none';
    if (tree) tree.innerHTML = '';
    if (badge) badge.textContent = '...';
    if (contextBox) contextBox.style.display = 'none';

    // Fetch comments
    try {
      const resp = await fetch(`/api/posts/${currentPostId}/comments`, {
        headers: {
          'Accept': 'application/json'
        }
      });
      const data = await resp.json();

      if (loading) loading.style.display = 'none';

      if (resp.ok && data && data.data) {
        cachedComments = data.data.data || data.data || [];
        const totalCount = typeof data.data.total_count === 'number'
          ? data.data.total_count
          : (typeof data.total_count === 'number' ? data.total_count : cachedComments.length);

        if (badge) badge.textContent = String(totalCount);

        // Update post context banner
        const postInfo = data.data.post || data.post;
        if (postInfo && contextBox) {
          const postLink = document.getElementById('discussionPostLink');
          if (postLink) {
            postLink.href = `/article/${currentPostId}`;
            postLink.textContent = postInfo.title || 'View article';
          }
          contextBox.style.display = 'flex';
        }

        // Setup composer viewer
        const viewer = data.data.viewer || data.viewer;
        setupComposerViewer(viewer);

        // Render comments
        renderCommentsTree(cachedComments);
      } else {
        if (tree) {
          tree.innerHTML = `<div class="discussion-error-msg">${escapeHtml(data.error?.message || 'Could not load comments.')}</div>`;
        }
      }
    } catch (err) {
      if (loading) loading.style.display = 'none';
      if (tree) {
        tree.innerHTML = '<div class="discussion-error-msg">Failed to connect to discussion server. Please try again.</div>';
      }
    }
  };

  // Close Comments Drawer
  window.closeCommentsDrawer = function(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    const backdrop = document.getElementById('discussionModalBackdrop');
    const drawer = document.getElementById('discussionDrawer');
    if (!backdrop || !drawer) return;

    drawer.classList.remove('is-open');
    backdrop.classList.remove('is-open');

    setTimeout(() => {
      backdrop.style.display = 'none';
      document.body.classList.remove('discussion-modal-open');
    }, 250);
  };

  window.handleDiscussionBackdropClick = function(event) {
    if (event.target && event.target.id === 'discussionModalBackdrop') {
      window.closeCommentsDrawer(event);
    }
  };

  // Keyboard shortcut: Escape closes drawer
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const backdrop = document.getElementById('discussionModalBackdrop');
      if (backdrop && backdrop.style.display !== 'none') {
        window.closeCommentsDrawer();
      }
    }
  });

  // Setup viewer state in composer
  function setupComposerViewer(viewer) {
    const authBox = document.getElementById('discussionAuthComposer');
    const guestBox = document.getElementById('discussionGuestComposer');
    const avatarSlot = document.getElementById('discussionViewerAvatar');

    if (viewer) {
      if (authBox) authBox.style.display = 'flex';
      if (guestBox) guestBox.style.display = 'none';
      if (avatarSlot) {
        if (viewer.avatar_url) {
          avatarSlot.innerHTML = `<img src="${escapeHtml(viewer.avatar_url)}" alt="${escapeHtml(viewer.name)}" class="composer-avatar-img">`;
        } else {
          const initial = viewer.name ? viewer.name.charAt(0).toUpperCase() : 'U';
          avatarSlot.innerHTML = `<div class="composer-avatar-initial">${escapeHtml(initial)}</div>`;
        }
      }
    } else {
      if (authBox) authBox.style.display = 'none';
      if (guestBox) {
        guestBox.style.display = 'flex';
        const loginLink = document.getElementById('discussionGuestLoginLink');
        if (loginLink) {
          loginLink.href = `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
        }
      }
    }
  }

  // Render hierarchical comments tree
  function renderCommentsTree(comments) {
    const tree = document.getElementById('discussionCommentsTree');
    const empty = document.getElementById('discussionEmptyState');
    if (!tree) return;

    if (!comments || comments.length === 0) {
      tree.innerHTML = '';
      if (empty) empty.style.display = 'flex';
      return;
    }

    if (empty) empty.style.display = 'none';

    let html = '';
    for (const comment of comments) {
      html += renderCommentNode(comment, false);
    }
    tree.innerHTML = html;
  }

  // Render single comment node (and its replies recursively)
  function renderCommentNode(comment, isReply) {
    const id = comment.id;
    const authorName = escapeHtml(comment.user_name || 'User');
    const authorHandle = escapeHtml(comment.handle || `@${(comment.user_username || 'user')}`);
    const timeAgo = escapeHtml(comment.formatted_time || formatRelativeTime(comment.created_at));
    const avatarUrl = comment.avatar_url || null;
    const initial = (comment.user_name || 'U').charAt(0).toUpperCase();

    const isPinned = Boolean(comment.is_pinned);
    const isPostAuthor = Boolean(comment.is_author);
    const hasAuthorBadge = Boolean(comment.has_author_badge);
    const isOwn = Boolean(comment.is_own);
    const likes = Number(comment.like_count || 0);
    const dislikes = Number(comment.dislike_count || 0);
    const viewerReaction = comment.viewer_reaction || null;
    const replies = Array.isArray(comment.replies) ? comment.replies : [];
    const replyCount = replies.length;

    let avatarHtml = '';
    if (avatarUrl) {
      avatarHtml = `<img src="${escapeHtml(avatarUrl)}" alt="${authorName}" class="comment-avatar-img">`;
    } else {
      avatarHtml = `<div class="comment-avatar-initial">${escapeHtml(initial)}</div>`;
    }

    let pinnedTag = '';
    if (isPinned) {
      pinnedTag = `
        <div class="comment-pinned-banner">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
            <path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/>
          </svg>
          <span>Pinned by Author</span>
        </div>
      `;
    }

    let authorBadgeHtml = '';
    if (isPostAuthor) {
      authorBadgeHtml = `<span class="comment-role-tag author-tag">Author</span>`;
    } else if (hasAuthorBadge) {
      authorBadgeHtml = `<span class="comment-verified-symbol" title="Recognized Contributor">✦</span>`;
    }

    // Three-dot dropdown options
    let menuItems = '';
    if (isOwn) {
      menuItems += `<button type="button" class="comment-menu-item" onclick="startEditComment(${id})">✏️ Edit</button>`;
      menuItems += `<button type="button" class="comment-menu-item item-delete" onclick="deleteCommentItem(${id})">🗑️ Delete</button>`;
    }
    menuItems += `<button type="button" class="comment-menu-item" onclick="openCommentReportModal(${id})">🚩 Report</button>`;

    // Nested replies markup
    let repliesHtml = '';
    if (replyCount > 0) {
      let childNodesHtml = '';
      for (const child of replies) {
        childNodesHtml += renderCommentNode(child, true);
      }

      repliesHtml = `
        <div class="comment-replies-wrapper" id="replies-wrapper-${id}">
          <button type="button"
                  class="comment-toggle-replies-btn"
                  id="toggle-btn-${id}"
                  onclick="toggleRepliesBlock(${id}, ${replyCount})">
            <span class="reply-toggle-text">${replyCount} ${replyCount === 1 ? 'reply' : 'replies'}</span>
            <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
          </button>
          <div class="comment-replies-list" id="replies-list-${id}" style="display: none;">
            ${childNodesHtml}
          </div>
        </div>
      `;
    }

    return `
      <div class="comment-node ${isReply ? 'comment-reply-item' : 'comment-root-item'} ${isPinned ? 'is-pinned' : ''}" id="comment-node-${id}" data-comment-id="${id}">
        ${pinnedTag}
        <div class="comment-card-inner">
          <!-- Avatar -->
          <a href="/author/${encodeURIComponent(comment.user_username || comment.user_id)}" class="comment-avatar-wrap">
            ${avatarHtml}
          </a>

          <!-- Main Body -->
          <div class="comment-body-wrap">
            <!-- Header Row -->
            <div class="comment-meta-row">
              <div class="comment-author-info">
                <a href="/author/${encodeURIComponent(comment.user_username || comment.user_id)}" class="comment-handle">
                  ${authorHandle}
                </a>
                ${authorBadgeHtml}
                <span class="comment-bullet">·</span>
                <span class="comment-time">${timeAgo}</span>
              </div>

              <!-- Options Menu -->
              <div class="comment-menu-anchor">
                <button type="button" class="comment-dots-btn" onclick="toggleCommentMenu(event, ${id})" aria-label="Comment options">
                  •••
                </button>
                <div class="comment-dropdown-menu glass-panel" id="comment-menu-${id}" style="display: none;">
                  ${menuItems}
                </div>
              </div>
            </div>

            <!-- Content Area -->
            <div class="comment-text-content" id="comment-text-${id}">
              ${comment.rendered_content || escapeHtml(comment.content || '')}
            </div>

            <!-- Edit Box (Hidden by default) -->
            <div class="comment-edit-box" id="comment-edit-box-${id}" style="display: none;">
              <textarea class="comment-edit-textarea" id="comment-edit-input-${id}">${escapeHtml(comment.content || '')}</textarea>
              <div class="comment-edit-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEditComment(${id})">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveEditComment(${id})">Save</button>
              </div>
            </div>

            <!-- Action Bar (Like, Dislike, Reply) -->
            <div class="comment-action-bar">
              <!-- Like -->
              <button type="button"
                      class="comment-action-btn btn-like ${viewerReaction === 'like' ? 'active' : ''}"
                      id="comment-like-${id}"
                      onclick="toggleCommentReaction(event, ${id}, 'like')"
                      title="Like">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="${viewerReaction === 'like' ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                </svg>
                <span class="reaction-count" id="like-count-${id}">${likes > 0 ? likes : ''}</span>
              </button>

              <!-- Dislike -->
              <button type="button"
                      class="comment-action-btn btn-dislike ${viewerReaction === 'dislike' ? 'active' : ''}"
                      id="comment-dislike-${id}"
                      onclick="toggleCommentReaction(event, ${id}, 'dislike')"
                      title="Dislike">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="${viewerReaction === 'dislike' ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path>
                </svg>
              </button>

              <!-- Reply -->
              <button type="button"
                      class="comment-action-btn btn-reply"
                      onclick="initDiscussionReply(${id}, '${escapeHtml(authorHandle)}')"
                      title="Reply">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span>Reply</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Render Threaded Nested Replies -->
        ${repliesHtml}
      </div>
    `;
  }

  // Toggle dropdown options menu on a comment
  window.toggleCommentMenu = function(event, commentId) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    const menu = document.getElementById(`comment-menu-${commentId}`);
    if (!menu) return;

    // Close any other open comment menus first
    document.querySelectorAll('.comment-dropdown-menu').forEach(m => {
      if (m !== menu) m.style.display = 'none';
    });

    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
  };

  // Close menus when clicking outside
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.comment-menu-anchor')) {
      document.querySelectorAll('.comment-dropdown-menu').forEach(m => {
        m.style.display = 'none';
      });
    }
  });

  // Expand / collapse replies block with smooth transition
  window.toggleRepliesBlock = function(commentId, count) {
    const list = document.getElementById(`replies-list-${commentId}`);
    const btn = document.getElementById(`toggle-btn-${commentId}`);
    if (!list || !btn) return;

    const isHidden = list.style.display === 'none';
    if (isHidden) {
      list.style.display = 'block';
      btn.classList.add('is-expanded');
      btn.innerHTML = `
        <span class="reply-toggle-text">Hide replies</span>
        <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="18 15 12 9 6 15"></polyline>
        </svg>
      `;
    } else {
      list.style.display = 'none';
      btn.classList.remove('is-expanded');
      btn.innerHTML = `
        <span class="reply-toggle-text">${count} ${count === 1 ? 'reply' : 'replies'}</span>
        <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      `;
    }
  };

  // Initialize reply mode in bottom composer
  window.initDiscussionReply = function(commentId, authorHandle) {
    activeParentReplyId = commentId;
    const banner = document.getElementById('discussionReplyingBanner');
    const handleEl = document.getElementById('discussionReplyingHandle');
    const input = document.getElementById('discussionComposerInput');

    if (banner) banner.style.display = 'flex';
    if (handleEl) handleEl.textContent = authorHandle || '@user';
    if (input) {
      input.placeholder = `Reply to ${authorHandle || '@user'}...`;
      input.focus();
    }
  };

  // Cancel reply mode
  window.cancelDiscussionReply = function() {
    activeParentReplyId = null;
    const banner = document.getElementById('discussionReplyingBanner');
    const input = document.getElementById('discussionComposerInput');

    if (banner) banner.style.display = 'none';
    if (input) {
      input.placeholder = 'Add a reply...';
    }
  };

  // Submit new comment or reply
  window.submitDiscussionComment = async function() {
    if (!currentPostId || isSubmitting) return;

    const input = document.getElementById('discussionComposerInput');
    const sendBtn = document.getElementById('discussionComposerSendBtn');
    if (!input) return;

    const content = input.value.trim();
    if (!content) {
      input.focus();
      input.classList.add('input-shake');
      setTimeout(() => input.classList.remove('input-shake'), 400);
      return;
    }

    isSubmitting = true;
    if (sendBtn) {
      sendBtn.disabled = true;
      sendBtn.classList.add('loading');
    }

    try {
      const resp = await fetch(`/api/posts/${currentPostId}/comments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify({
          content: content,
          parent_id: activeParentReplyId
        })
      });

      const data = await resp.json();

      if (resp.status === 401) {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
        return;
      }

      if (resp.ok && data && data.data) {
        input.value = '';
        input.style.height = 'auto';

        const newComment = data.data;
        const totalCount = typeof data.total_count === 'number' ? data.total_count : null;

        // Render newly added comment in DOM
        if (activeParentReplyId) {
          // It was a reply: append under parent's replies list
          let wrapper = document.getElementById(`replies-wrapper-${activeParentReplyId}`);
          let list = document.getElementById(`replies-list-${activeParentReplyId}`);

          if (!wrapper) {
            // Create replies wrapper if parent had 0 replies previously
            const parentNode = document.getElementById(`comment-node-${activeParentReplyId}`);
            if (parentNode) {
              const div = document.createElement('div');
              div.className = 'comment-replies-wrapper';
              div.id = `replies-wrapper-${activeParentReplyId}`;
              div.innerHTML = `
                <button type="button" class="comment-toggle-replies-btn is-expanded" id="toggle-btn-${activeParentReplyId}" onclick="toggleRepliesBlock(${activeParentReplyId}, 1)">
                  <span class="reply-toggle-text">Hide replies</span>
                  <svg class="chevron-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"></polyline></svg>
                </button>
                <div class="comment-replies-list" id="replies-list-${activeParentReplyId}" style="display: block;"></div>
              `;
              parentNode.appendChild(div);
              list = div.querySelector('.comment-replies-list');
            }
          }

          if (list) {
            list.style.display = 'block';
            list.insertAdjacentHTML('beforeend', renderCommentNode(newComment, true));
          }
        } else {
          // Top-level comment: prepend to stream
          const tree = document.getElementById('discussionCommentsTree');
          const empty = document.getElementById('discussionEmptyState');
          if (empty) empty.style.display = 'none';

          if (tree) {
            tree.insertAdjacentHTML('afterbegin', renderCommentNode(newComment, false));
          }
        }

        // Reset reply state
        cancelDiscussionReply();

        // Synchronize counts across the page
        updateGlobalCommentCounts(currentPostId, totalCount);
      } else {
        alert(data.error?.message || 'Failed to post comment. Please try again.');
      }
    } catch (err) {
      alert('Network error while posting comment.');
    } finally {
      isSubmitting = false;
      if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.classList.remove('loading');
      }
    }
  };

  // Keyboard shortcut: Ctrl+Enter or Cmd+Enter submits
  window.handleDiscussionInputKeydown = function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      window.submitDiscussionComment();
    }
  };

  // Toggle reaction (Like / Dislike) on a comment
  window.toggleCommentReaction = async function(event, commentId, reaction) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    try {
      const resp = await fetch(`/api/comments/${commentId}/react`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify({ reaction: reaction })
      });

      if (resp.status === 401) {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
        return;
      }

      const res = await resp.json();
      if (resp.ok && res && res.data) {
        const d = res.data;
        const likeBtn = document.getElementById(`comment-like-${commentId}`);
        const dislikeBtn = document.getElementById(`comment-dislike-${commentId}`);
        const countSpan = document.getElementById(`like-count-${commentId}`);

        if (countSpan) {
          countSpan.textContent = d.likes > 0 ? d.likes : '';
        }

        if (likeBtn) {
          if (d.viewer_reaction === 'like') {
            likeBtn.classList.add('active');
            const svg = likeBtn.querySelector('svg');
            if (svg) svg.setAttribute('fill', 'currentColor');
          } else {
            likeBtn.classList.remove('active');
            const svg = likeBtn.querySelector('svg');
            if (svg) svg.setAttribute('fill', 'none');
          }
        }

        if (dislikeBtn) {
          if (d.viewer_reaction === 'dislike') {
            dislikeBtn.classList.add('active');
            const svg = dislikeBtn.querySelector('svg');
            if (svg) svg.setAttribute('fill', 'currentColor');
          } else {
            dislikeBtn.classList.remove('active');
            const svg = dislikeBtn.querySelector('svg');
            if (svg) svg.setAttribute('fill', 'none');
          }
        }
      }
    } catch (err) {
      console.error('Reaction error:', err);
    }
  };

  // Inline comment editing
  window.startEditComment = function(commentId) {
    const textEl = document.getElementById(`comment-text-${commentId}`);
    const editBox = document.getElementById(`comment-edit-box-${commentId}`);
    const menu = document.getElementById(`comment-menu-${commentId}`);

    if (menu) menu.style.display = 'none';
    if (textEl) textEl.style.display = 'none';
    if (editBox) editBox.style.display = 'block';
  };

  window.cancelEditComment = function(commentId) {
    const textEl = document.getElementById(`comment-text-${commentId}`);
    const editBox = document.getElementById(`comment-edit-box-${commentId}`);

    if (textEl) textEl.style.display = 'block';
    if (editBox) editBox.style.display = 'none';
  };

  window.saveEditComment = async function(commentId) {
    const input = document.getElementById(`comment-edit-input-${commentId}`);
    if (!input) return;

    const content = input.value.trim();
    if (!content) return;

    try {
      const resp = await fetch(`/api/comments/${commentId}/edit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify({ content: content })
      });

      const res = await resp.json();
      if (resp.ok) {
        const textEl = document.getElementById(`comment-text-${commentId}`);
        if (textEl) {
          textEl.textContent = content;
          textEl.style.display = 'block';
        }
        window.cancelEditComment(commentId);
      } else {
        alert(res.error?.message || 'Could not update comment.');
      }
    } catch (err) {
      alert('Network error while saving edit.');
    }
  };

  // Delete a comment
  window.deleteCommentItem = async function(commentId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;

    try {
      const resp = await fetch(`/api/comments/${commentId}/delete`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-Token': getCsrfToken()
        }
      });

      if (resp.ok) {
        const node = document.getElementById(`comment-node-${commentId}`);
        if (node) {
          node.remove();
        }
        // Decrement counters
        updateGlobalCommentCounts(currentPostId, null, -1);
      } else {
        alert('Could not delete comment.');
      }
    } catch (err) {
      alert('Network error while deleting comment.');
    }
  };

  // Report modal handlers
  window.openCommentReportModal = function(commentId) {
    const modal = document.getElementById('commentReportModal');
    const input = document.getElementById('reportCommentId');
    if (input) input.value = String(commentId);
    if (modal) modal.style.display = 'flex';
  };

  window.closeCommentReportModal = function() {
    const modal = document.getElementById('commentReportModal');
    if (modal) modal.style.display = 'none';
  };

  window.submitCommentReport = async function(event) {
    if (event) event.preventDefault();
    const commentId = document.getElementById('reportCommentId')?.value;
    const selectedRadio = document.querySelector('input[name="reportReason"]:checked');
    const reason = selectedRadio ? selectedRadio.value : 'Inappropriate content';

    if (!commentId) return;

    try {
      const resp = await fetch(`/api/comments/${commentId}/report`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': getCsrfToken()
        },
        body: JSON.stringify({ reason: reason })
      });

      if (resp.status === 401) {
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname + window.location.search)}`;
        return;
      }

      window.closeCommentReportModal();
      alert('Thank you for reporting. Our moderation team will review this comment.');
    } catch (err) {
      alert('Could not submit report.');
    }
  };

  // Synchronize comment counts across drawer, feed cards, and article page
  function updateGlobalCommentCounts(postId, explicitCount, delta = 1) {
    // 1. Drawer count
    const badge = document.getElementById('discussionCountBadge');
    let newCount = explicitCount;
    if (typeof newCount !== 'number') {
      const current = parseInt(badge ? badge.textContent : '0', 10) || 0;
      newCount = Math.max(0, current + delta);
    }
    if (badge) badge.textContent = String(newCount);

    // 2. Feed card action button pill `[ 💬 X ]`
    document.querySelectorAll(`.feed-comments-btn[data-post-id="${postId}"] .feed-comment-counter`).forEach(el => {
      el.textContent = String(newCount);
    });

    // 3. Feed card engagement summary `X comments`
    document.querySelectorAll(`.engagement-comments-count-val[data-post-id="${postId}"]`).forEach(el => {
      el.textContent = `${newCount} ${newCount === 1 ? 'comment' : 'comments'}`;
    });

    // 4. Article page `#commentsCount`
    const artCount = document.getElementById('commentsCount');
    if (artCount) artCount.textContent = String(newCount);
  }

})();
