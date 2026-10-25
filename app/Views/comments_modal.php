<?php
declare(strict_types=1);
?>
<!-- Threaded Discussion Panel / Bottom Sheet Modal -->
<div id="discussionModalBackdrop" class="discussion-backdrop" style="display: none;" onclick="handleDiscussionBackdropClick(event)">
  <div id="discussionDrawer" class="discussion-drawer glass-panel" role="dialog" aria-modal="true" aria-labelledby="discussionDrawerHeading" onclick="event.stopPropagation()">
    <!-- Mobile Grab / Drag Handle -->
    <div class="discussion-drag-handle" aria-hidden="true"></div>

    <!-- Header -->
    <div class="discussion-header">
      <button type="button" class="discussion-header-btn discussion-back-btn" onclick="closeCommentsDrawer()" aria-label="Go back or close" title="Close">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="19" y1="12" x2="5" y2="12"></line>
          <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
      </button>

      <div class="discussion-header-title-wrap">
        <h3 id="discussionDrawerHeading" class="discussion-drawer-heading">Replies</h3>
        <span id="discussionCountBadge" class="discussion-count-badge">0</span>
      </div>

      <button type="button" class="discussion-header-btn discussion-close-btn" onclick="closeCommentsDrawer()" aria-label="Close" title="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>

    <!-- Post Context Banner -->
    <div id="discussionPostContext" class="discussion-post-context" style="display: none;">
      <span class="context-label">Discussion on:</span>
      <a id="discussionPostLink" href="#" class="context-title" target="_blank" rel="noopener"></a>
    </div>

    <!-- Discussion Stream (Scrollable) -->
    <div id="discussionStream" class="discussion-stream">
      <!-- Loading Skeleton / Spinner -->
      <div id="discussionLoading" class="discussion-loading">
        <div class="discussion-spinner"></div>
        <span>Loading discussion...</span>
      </div>

      <!-- Empty State -->
      <div id="discussionEmptyState" class="discussion-empty-state" style="display: none;">
        <div class="empty-icon">💬</div>
        <h4>No comments yet</h4>
        <p>Be the first to share your thoughts on this story!</p>
      </div>

      <!-- Comments Tree Container -->
      <div id="discussionCommentsTree" class="discussion-comments-tree"></div>
    </div>

    <!-- Docked Footer Composer -->
    <div id="discussionComposerArea" class="discussion-composer-area">
      <!-- Replying To Banner -->
      <div id="discussionReplyingBanner" class="discussion-replying-banner" style="display: none;">
        <div class="replying-text">
          <span>Replying to</span>
          <strong id="discussionReplyingHandle">@user</strong>
        </div>
        <button type="button" class="discussion-cancel-reply-btn" onclick="cancelDiscussionReply()" aria-label="Cancel reply">
          ✕ Cancel
        </button>
      </div>

      <!-- Authenticated Form -->
      <div id="discussionAuthComposer" class="discussion-auth-composer">
        <div class="composer-avatar-slot" id="discussionViewerAvatar">
          <div class="default-composer-avatar">💬</div>
        </div>
        <div class="composer-input-wrap">
          <textarea id="discussionComposerInput"
                    class="discussion-textarea"
                    placeholder="Add a reply..."
                    rows="1"
                    maxlength="3000"
                    onkeydown="handleDiscussionInputKeydown(event)"></textarea>
        </div>
        <button type="button"
                id="discussionComposerSendBtn"
                class="discussion-send-btn"
                onclick="submitDiscussionComment()"
                aria-label="Send reply">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </button>
      </div>

      <!-- Guest Prompt -->
      <div id="discussionGuestComposer" class="discussion-guest-composer" style="display: none;">
        <span class="guest-msg">Sign in to join the conversation</span>
        <a id="discussionGuestLoginLink" href="/login" class="btn btn-primary btn-sm btn-pill">Sign In</a>
      </div>
    </div>
  </div>
</div>

<!-- Report Comment Modal -->
<div id="commentReportModal" class="comment-report-modal-backdrop" style="display: none;" onclick="closeCommentReportModal(event)">
  <div class="comment-report-modal glass-panel" onclick="event.stopPropagation()">
    <div class="report-modal-header">
      <h4>Report Comment</h4>
      <button type="button" class="close-report-btn" onclick="closeCommentReportModal()">✕</button>
    </div>
    <form id="commentReportForm" onsubmit="submitCommentReport(event)">
      <input type="hidden" id="reportCommentId" value="">
      <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 12px;">Help us understand why this comment violates community standards:</p>
      <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px;">
        <label class="report-radio-label">
          <input type="radio" name="reportReason" value="Spam or advertising" checked>
          <span>Spam or advertising</span>
        </label>
        <label class="report-radio-label">
          <input type="radio" name="reportReason" value="Harassment or hate speech">
          <span>Harassment or hate speech</span>
        </label>
        <label class="report-radio-label">
          <input type="radio" name="reportReason" value="Misinformation or harmful content">
          <span>Misinformation or harmful content</span>
        </label>
        <label class="report-radio-label">
          <input type="radio" name="reportReason" value="Inappropriate or offensive">
          <span>Inappropriate or offensive</span>
        </label>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-secondary btn-sm btn-pill" onclick="closeCommentReportModal()">Cancel</button>
        <button type="submit" class="btn btn-danger btn-sm btn-pill" id="submitReportBtn">Submit Report</button>
      </div>
    </form>
  </div>
</div>
