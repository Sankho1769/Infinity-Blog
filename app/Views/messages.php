<?php
/**
 * Messages View — Infinity Blog
 * Robust Direct Messaging with Content Integrity, Message States, and Live People Search
 */
$currentUserId = (int) ($currentUserId ?? 0);
?>
<!-- Compact Messaging Workspace Topbar -->
<div class="chat-workspace-topbar">
  <div class="chat-type-switcher">
    <a href="/chat" class="chat-switch-pill">
      <span>💬</span> Category Communities
    </a>
    <a href="/messages" class="chat-switch-pill active">
      <span>✉️</span> Direct Messages
    </a>
  </div>
  <div class="chat-workspace-meta">
    <span class="chat-workspace-badge">Private 1-on-1 Messages</span>
  </div>
</div>

<div class="messages-container <?= !empty($activeUser) ? 'has-active-chat' : '' ?>" id="messagesApp">
  <!-- Left Column: Conversations List -->
  <aside class="conversations-sidebar">
    <div class="conversations-header">
      <h2 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-dark);">Conversations</h2>
      <button type="button" class="btn btn-primary btn-pill btn-sm" id="openNewChatModalBtn" style="padding: 4px 12px; font-size: 0.8rem;">
        + New Chat
      </button>
    </div>

    <!-- Quick Filter Search Input -->
    <div class="chat-search-wrap">
      <input type="text" id="conversationSearchInput" placeholder="Filter conversations..." style="width: 100%; padding: 7px 12px; border-radius: var(--radius-pill); border: 1px solid var(--glass-border); font-size: 0.82rem; background: rgba(255, 255, 255, 0.8); outline: none; box-sizing: border-box; color: var(--text-dark);">
    </div>

    <ul class="conversations-list" id="conversationsList">
      <?php if (empty($conversations)): ?>
        <li style="padding: 32px 20px; text-align: center; color: var(--text-muted); font-size: 0.88rem;">
          No conversations yet.<br>Click <strong>+ New Chat</strong> to message an author!
        </li>
      <?php else: ?>
        <?php foreach ($conversations as $c): ?>
          <?php
            $other = $c['other_user'];
            $isActive = !empty($activeUser) && (int) $activeUser['id'] === (int) $other['id'];
            $avatarUrl = $other['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($other['avatar_path'] ?? null);
            $displayName = $other['display_name'] ?? $other['username'];
            $unread = (int) ($c['unread_count'] ?? 0);
          ?>
          <li>
            <a href="/messages/<?= (int) $other['id'] ?>" class="conversation-item <?= $isActive ? 'active' : '' ?>" data-user-id="<?= (int) $other['id'] ?>" data-name="<?= htmlspecialchars(strtolower($displayName . ' ' . $other['username']), ENT_QUOTES, 'UTF-8') ?>">
              <?php if (!empty($avatarUrl)): ?>
                <img src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
                <div style="display: none; width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
                  <?= strtoupper(substr($displayName, 0, 1)) ?>
                </div>
              <?php else: ?>
                <div style="width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
                  <?= strtoupper(substr($displayName, 0, 1)) ?>
                </div>
              <?php endif; ?>

              <div class="conversation-info">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span class="conversation-name"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if (!empty($c['last_message']['created_at'])): ?>
                    <span style="font-size: 0.72rem; color: var(--text-muted);" class="conversation-time"><?= date('M j', strtotime($c['last_message']['created_at'])) ?></span>
                  <?php endif; ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2px;">
                  <span class="conversation-preview">
                    <?= htmlspecialchars(mb_substr($c['last_message']['body'] ?? 'No messages yet', 0, 32), ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <?php if ($unread > 0): ?>
                    <span class="badge-unread-dot"><?= $unread ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </aside>

  <!-- Right Column: Active Conversation Stream -->
  <div class="chat-main-pane">
    <?php if (empty($activeUser)): ?>
      <div class="chat-empty-panel" style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 12px;">💬</div>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-dark); margin: 0 0 8px 0;">Select a Conversation</h3>
        <p style="color: var(--text-muted); max-width: 380px; margin: 0 0 20px 0; font-size: 0.92rem;">
          Choose an existing contact from the left list or search for any Infinity Blog member to start a new chat.
        </p>
        <button type="button" class="btn btn-primary btn-pill" onclick="document.getElementById('openNewChatModalBtn').click()">
          Start New Conversation
        </button>
      </div>
    <?php else: ?>
      <?php
        $activeAvatar = $activeUser['avatar_url'] ?? \InfinityBlog\Posts\ArticleImageHelper::resolveAvatarUrl($activeUser['avatar_path'] ?? null);
        $activeName = $activeUser['display_name'] ?? $activeUser['username'];
      ?>
      <div class="chat-header">
        <!-- Mobile Back to Conversations Button -->
        <a href="/messages" class="chat-back-btn" aria-label="Back to conversations">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        </a>

        <?php if (!empty($activeAvatar)): ?>
          <img src="<?= htmlspecialchars($activeAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
          <div style="display: none; width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
            <?= strtoupper(substr($activeName, 0, 1)) ?>
          </div>
        <?php else: ?>
          <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
            <?= strtoupper(substr($activeName, 0, 1)) ?>
          </div>
        <?php endif; ?>

        <div style="flex: 1; min-width: 0;">
          <h2 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?>
          </h2>
          <span style="font-size: 0.78rem; color: var(--text-muted); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= htmlspecialchars($activeUser['handle'] ?? \InfinityBlog\Support\UserIdentityHelper::formatHandle($activeUser['username'] ?? null, $activeName), ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>

        <a href="/author/<?= (int) $activeUser['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="font-size: 0.8rem; padding: 4px 12px; flex-shrink: 0;">
          View Profile →
        </a>
      </div>

      <!-- Messages Stream with Floating New Messages Indicator -->
      <div class="chat-stream-container">
        <div class="chat-messages-stream" id="chatStream">
          <?php if (empty($threadMessages)): ?>
            <div class="chat-empty-state" style="text-align: center; color: var(--text-muted); margin: auto; padding: 30px;">
              <p style="font-size: 0.95rem; margin-bottom: 4px;">No messages with <?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?> yet.</p>
              <p style="font-size: 0.82rem; margin: 0;">Send a message below to break the ice!</p>
            </div>
          <?php else: ?>
            <?php foreach ($threadMessages as $msg): ?>
              <?php
                $isSentByMe = isset($msg['is_mine']) ? (bool) $msg['is_mine'] : ((int) $msg['sender_id'] === $currentUserId);
                $clientMsgId = $msg['client_msg_id'] ?? null;
              ?>
              <div class="chat-bubble <?= $isSentByMe ? 'sent' : 'received' ?>" data-msg-id="<?= (int) $msg['id'] ?>" <?= $clientMsgId ? 'data-client-msg-id="' . htmlspecialchars($clientMsgId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                <div class="chat-bubble-content"><?= htmlspecialchars($msg['body'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="chat-bubble-meta">
                  <span class="chat-timestamp">
                    <?= date('g:i A', strtotime($msg['created_at'])) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Floating New Messages Pill -->
        <button type="button" class="chat-new-messages-pill" id="newMessagesPill" aria-label="Scroll to new messages">
          <span>↓ New messages</span>
        </button>
      </div>

      <!-- Message Input Form (Firmly Anchored at Bottom) -->
      <form class="chat-input-bar" id="sendMessageForm">
        <textarea class="chat-textarea" id="messageInput" name="body" placeholder="Write a message to <?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?>... (Enter to send, Shift+Enter for new line)" rows="1" required maxlength="5000"></textarea>
        <button type="submit" class="btn btn-primary btn-pill btn-sm" id="sendBtn" style="height: 42px; padding: 0 18px; display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;">
          <span>Send</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- New Chat Modal (Server-Backed Real-Time People Search) -->
<div class="modal-backdrop" id="newChatModal" role="dialog" aria-modal="true" aria-labelledby="newChatModalTitle">
  <div class="modal-card" style="max-width: 480px; width: 92%; max-height: 82vh; display: flex; flex-direction: column; padding: 20px 22px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
      <div>
        <h3 id="newChatModalTitle" style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-dark);">Start New Conversation</h3>
        <p style="margin: 2px 0 0 0; font-size: 0.8rem; color: var(--text-muted);">Search people by display name or @username</p>
      </div>
      <button type="button" class="alert-close-btn" id="closeNewChatModalBtn" aria-label="Close modal" style="font-size: 1.4rem; cursor: pointer; background: none; border: none; color: var(--text-muted); line-height: 1;">×</button>
    </div>

    <!-- Live Search Input Wrapper with Icon and Clear Button -->
    <div style="position: relative; margin-bottom: 12px; display: flex; align-items: center;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 14px; color: var(--text-muted); pointer-events: none;">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="text" id="newChatSearchInput" placeholder="Search by name or @username..." autocomplete="off" style="width: 100%; padding: 9px 38px 9px 38px; border-radius: var(--radius-pill); border: 1px solid var(--glass-border); font-size: 0.88rem; outline: none; background: rgba(255, 255, 255, 0.85); color: var(--text-dark); box-sizing: border-box;">
      <button type="button" id="clearNewChatSearchBtn" aria-label="Clear search" style="position: absolute; right: 12px; background: none; border: none; font-size: 1.15rem; color: var(--text-muted); cursor: pointer; display: none; line-height: 1;">×</button>
    </div>

    <!-- Dynamic Authors / Users List -->
    <div style="flex: 1; overflow-y: auto; max-height: 360px; min-height: 160px;" id="newChatAuthorsList" role="listbox">
      <!-- Populated dynamically via server-backed search -->
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const currentUserId = <?= (int) $currentUserId ?>;
  const activeUserId = <?= !empty($activeUser) ? (int) $activeUser['id'] : 'null' ?>;
  const activeUserData = <?= !empty($activeUser) ? json_encode([
    'id' => (int) $activeUser['id'],
    'display_name' => $activeName ?? ($activeUser['display_name'] ?? $activeUser['username']),
    'username' => $activeUser['username'] ?? '',
    'avatar_url' => $activeAvatar ?? null,
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null' ?>;

  let lastDmId = <?= !empty($threadMessages) ? (int) $threadMessages[array_key_last($threadMessages)]['id'] : 0 ?>;
  const chatStream = document.getElementById('chatStream');
  const newMessagesPill = document.getElementById('newMessagesPill');
  const sendForm = document.getElementById('sendMessageForm');
  const messageInput = document.getElementById('messageInput');
  const newChatModal = document.getElementById('newChatModal');
  const openNewChatBtn = document.getElementById('openNewChatModalBtn');
  const closeNewChatBtn = document.getElementById('closeNewChatModalBtn');
  const convSearch = document.getElementById('conversationSearchInput');
  const newChatSearch = document.getElementById('newChatSearchInput');
  const clearSearchBtn = document.getElementById('clearNewChatSearchBtn');
  const authorsListContainer = document.getElementById('newChatAuthorsList');

  function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function isUserNearBottom() {
    if (!chatStream) return true;
    return (chatStream.scrollHeight - chatStream.scrollTop - chatStream.clientHeight) <= 90;
  }

  function scrollToBottom(smooth = false) {
    if (!chatStream) return;
    if (smooth) {
      chatStream.scrollTo({ top: chatStream.scrollHeight, behavior: 'smooth' });
    } else {
      chatStream.scrollTop = chatStream.scrollHeight;
    }
    if (newMessagesPill) newMessagesPill.style.display = 'none';
  }

  // Scroll event listener on chat stream to auto-dismiss "New messages" pill when scrolled to bottom
  if (chatStream) {
    chatStream.addEventListener('scroll', () => {
      if (isUserNearBottom() && newMessagesPill) {
        newMessagesPill.style.display = 'none';
      }
    });
  }

  if (newMessagesPill) {
    newMessagesPill.addEventListener('click', () => {
      scrollToBottom(true);
    });
  }

  // Position at bottom on initial load
  scrollToBottom(false);

  // Format timestamp for conversation item
  function formatConvTime(ts) {
    if (!ts) return 'Just now';
    const d = new Date(ts);
    if (isNaN(d.getTime())) return 'Just now';
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
      return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }

  // Format timestamp for message bubble
  function formatBubbleTime(ts) {
    if (!ts) return 'Just now';
    const d = new Date(ts);
    if (isNaN(d.getTime())) return 'Just now';
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  // Update or insert an entry in the left conversations sidebar list dynamically
  function updateOrInsertConversationItem(otherUserId, otherUserData, lastMessageText, timestamp, markActive = true) {
    const list = document.getElementById('conversationsList');
    if (!list) return;

    const uId = parseInt(otherUserId, 10);
    let itemLink = list.querySelector(`.conversation-item[data-user-id="${uId}"]`)
      || list.querySelector(`a[href="/messages/${uId}"]`);

    const snippet = (lastMessageText && lastMessageText.length > 32)
      ? lastMessageText.substring(0, 32) + '...'
      : (lastMessageText || 'No messages yet');
    const timeFormatted = formatConvTime(timestamp);

    if (itemLink) {
      const preview = itemLink.querySelector('.conversation-preview');
      if (preview) preview.textContent = snippet;

      let timeEl = itemLink.querySelector('.conversation-time');
      if (!timeEl) {
        const topRow = itemLink.querySelector('.conversation-info > div');
        if (topRow) {
          timeEl = document.createElement('span');
          timeEl.className = 'conversation-time';
          timeEl.style.fontSize = '0.72rem';
          timeEl.style.color = 'var(--text-muted)';
          topRow.appendChild(timeEl);
        }
      }
      if (timeEl) timeEl.textContent = timeFormatted;

      if (markActive) {
        list.querySelectorAll('.conversation-item').forEach(el => el.classList.remove('active'));
        itemLink.classList.add('active');
      }

      // Reorder to top of sidebar list
      const parentLi = itemLink.closest('li');
      if (parentLi && list.firstElementChild !== parentLi) {
        list.prepend(parentLi);
      }
    } else if (otherUserData) {
      Array.from(list.children).forEach(li => {
        if (!li.querySelector('.conversation-item')) {
          li.remove();
        }
      });

      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = `/messages/${uId}`;
      a.className = `conversation-item ${markActive ? 'active' : ''}`;
      a.setAttribute('data-user-id', uId);

      const dName = otherUserData.display_name || otherUserData.username || 'Author';
      const uName = otherUserData.username || '';
      a.setAttribute('data-name', `${dName} ${uName}`.toLowerCase());

      if (markActive) {
        list.querySelectorAll('.conversation-item').forEach(el => el.classList.remove('active'));
      }

      // Avatar
      if (otherUserData.avatar_url) {
        const img = document.createElement('img');
        img.src = otherUserData.avatar_url;
        img.alt = dName;
        img.style.cssText = 'width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0;';
        const fallback = document.createElement('div');
        fallback.style.cssText = 'display: none; width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;';
        fallback.textContent = (dName[0] || 'A').toUpperCase();
        img.onerror = () => { img.style.display = 'none'; fallback.style.display = 'flex'; };
        a.appendChild(img);
        a.appendChild(fallback);
      } else {
        const fallback = document.createElement('div');
        fallback.style.cssText = 'width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;';
        fallback.textContent = (dName[0] || 'A').toUpperCase();
        a.appendChild(fallback);
      }

      // Conversation info block
      const info = document.createElement('div');
      info.className = 'conversation-info';

      const row1 = document.createElement('div');
      row1.style.cssText = 'display: flex; justify-content: space-between; align-items: center;';
      const nameSpan = document.createElement('span');
      nameSpan.className = 'conversation-name';
      nameSpan.textContent = dName;
      const timeSpan = document.createElement('span');
      timeSpan.className = 'conversation-time';
      timeSpan.style.cssText = 'font-size: 0.72rem; color: var(--text-muted);';
      timeSpan.textContent = timeFormatted;
      row1.appendChild(nameSpan);
      row1.appendChild(timeSpan);

      const row2 = document.createElement('div');
      row2.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-top: 2px;';
      const prevSpan = document.createElement('span');
      prevSpan.className = 'conversation-preview';
      prevSpan.textContent = snippet;
      row2.appendChild(prevSpan);

      info.appendChild(row1);
      info.appendChild(row2);
      a.appendChild(info);
      li.appendChild(a);

      list.prepend(li);
    }
  }

  // Filter conversations in left sidebar
  if (convSearch) {
    convSearch.addEventListener('input', () => {
      const q = convSearch.value.toLowerCase().trim();
      document.querySelectorAll('#conversationsList .conversation-item').forEach(item => {
        const name = item.getAttribute('data-name') || '';
        item.closest('li').style.display = name.includes(q) ? '' : 'none';
      });
    });
  }

  // Build Message Bubble DOM element
  function createMessageBubble(msg, isSent, status = 'sent') {
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${isSent ? 'sent' : 'received'} ${status !== 'sent' ? status : ''}`;
    if (msg.id) {
      bubble.setAttribute('data-msg-id', msg.id);
    }
    if (msg.client_msg_id) {
      bubble.setAttribute('data-client-msg-id', msg.client_msg_id);
    }

    const contentDiv = document.createElement('div');
    contentDiv.className = 'chat-bubble-content';
    contentDiv.textContent = msg.body || '';

    const metaDiv = document.createElement('div');
    metaDiv.className = 'chat-bubble-meta';

    if (status === 'pending') {
      const indicator = document.createElement('span');
      indicator.className = 'chat-status-indicator';
      indicator.textContent = 'Sending...';
      metaDiv.appendChild(indicator);
    } else if (status === 'failed') {
      const errText = document.createElement('span');
      errText.className = 'chat-error-text';
      errText.textContent = 'Failed to send';

      const retryBtn = document.createElement('button');
      retryBtn.type = 'button';
      retryBtn.className = 'chat-retry-btn';
      retryBtn.textContent = 'Retry';
      retryBtn.addEventListener('click', () => {
        retrySendMessage(msg.body, msg.client_msg_id);
      });

      metaDiv.appendChild(errText);
      metaDiv.appendChild(retryBtn);
    } else {
      const timeSpan = document.createElement('span');
      timeSpan.className = 'chat-timestamp';
      timeSpan.textContent = formatBubbleTime(msg.created_at);
      metaDiv.appendChild(timeSpan);
    }

    bubble.appendChild(contentDiv);
    bubble.appendChild(metaDiv);
    return bubble;
  }

  // Send or Retry sending a message
  async function submitDirectMessage(body, clientMsgId) {
    if (!activeUserId) return;
    const cleanBody = body; // Preserves newlines and spaces exactly

    const sendBtn = document.getElementById('sendBtn');
    const btnSpan = sendBtn ? sendBtn.querySelector('span') : null;
    if (sendBtn) {
      sendBtn.disabled = true;
      if (btnSpan) btnSpan.textContent = 'Sending...';
    }

    try {
      const csrf = (typeof window.InfinityBlog?.ensureCsrfToken === 'function')
        ? await window.InfinityBlog.ensureCsrfToken()
        : (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

      const res = await fetch('/api/messages', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': csrf
        },
        body: JSON.stringify({
          recipient_id: activeUserId,
          body: cleanBody,
          client_msg_id: clientMsgId
        })
      });

      if (res.status === 401) {
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        return;
      }

      const data = await res.json();
      if (res.ok && data.data) {
        const msgObj = data.data.message || data.data;

        // Transition pending bubble to confirmed sent state
        const targetBubble = chatStream?.querySelector(`[data-client-msg-id="${clientMsgId}"]`);
        if (targetBubble) {
          targetBubble.classList.remove('pending', 'failed');
          if (msgObj.id) targetBubble.setAttribute('data-msg-id', msgObj.id);

          const meta = targetBubble.querySelector('.chat-bubble-meta');
          if (meta) {
            meta.innerHTML = `<span class="chat-timestamp">${formatBubbleTime(msgObj.created_at)}</span>`;
          }
        }

        lastDmId = Math.max(lastDmId, parseInt(msgObj.id, 10));

        // Update conversations list on the left
        updateOrInsertConversationItem(activeUserId, activeUserData, msgObj.body, msgObj.created_at || new Date(), true);
        scrollToBottom(false);
      } else {
        // Transition bubble to failed state
        markBubbleFailed(clientMsgId, cleanBody, data.error?.message || 'Could not send message.');
      }
    } catch (err) {
      markBubbleFailed(clientMsgId, cleanBody, 'Network error while sending message.');
    } finally {
      if (sendBtn) {
        sendBtn.disabled = false;
        if (btnSpan) btnSpan.textContent = 'Send';
      }
      if (messageInput) messageInput.focus();
    }
  }

  function markBubbleFailed(clientMsgId, body, errMsg) {
    const targetBubble = chatStream?.querySelector(`[data-client-msg-id="${clientMsgId}"]`);
    if (targetBubble) {
      targetBubble.classList.remove('pending');
      targetBubble.classList.add('failed');

      const meta = targetBubble.querySelector('.chat-bubble-meta');
      if (meta) {
        meta.innerHTML = '';
        const errText = document.createElement('span');
        errText.className = 'chat-error-text';
        errText.textContent = 'Failed to send';

        const retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'chat-retry-btn';
        retryBtn.textContent = 'Retry';
        retryBtn.addEventListener('click', () => {
          retrySendMessage(body, clientMsgId);
        });

        meta.appendChild(errText);
        meta.appendChild(retryBtn);
      }
    }

    if (typeof window.InfinityBlog?.showAlert === 'function') {
      window.InfinityBlog.showAlert(errMsg, 'error');
    }
  }

  function retrySendMessage(body, clientMsgId) {
    const targetBubble = chatStream?.querySelector(`[data-client-msg-id="${clientMsgId}"]`);
    if (targetBubble) {
      targetBubble.classList.remove('failed');
      targetBubble.classList.add('pending');
      const meta = targetBubble.querySelector('.chat-bubble-meta');
      if (meta) {
        meta.innerHTML = '<span class="chat-status-indicator">Sending...</span>';
      }
    }
    submitDirectMessage(body, clientMsgId);
  }

  // Handle Form Submission
  if (sendForm && activeUserId) {
    if (messageInput) {
      messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendForm.requestSubmit();
        }
      });

      // Auto-resize textarea height up to 120px
      messageInput.addEventListener('input', () => {
        messageInput.style.height = 'auto';
        messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
      });
    }

    sendForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const body = messageInput.value;
      if (!body || body.trim().length === 0) return;

      const clientMsgId = 'cmsg_' + Date.now() + '_' + Math.random().toString(36).substring(2, 9);

      // Clear input and reset height
      messageInput.value = '';
      messageInput.style.height = '42px';

      // Remove empty-state banner if present
      const emptyState = chatStream?.querySelector('.chat-empty-state');
      if (emptyState) emptyState.remove();

      // Immediately append pending optimistic bubble
      const pendingBubble = createMessageBubble({ body: body, client_msg_id: clientMsgId }, true, 'pending');
      chatStream?.appendChild(pendingBubble);
      scrollToBottom(false);

      // Perform network request
      submitDirectMessage(body, clientMsgId);
    });
  }

  // Polling for incoming messages every 3.5 seconds
  let dmPollInterval = null;
  if (activeUserId) {
    dmPollInterval = setInterval(async () => {
      try {
        const res = await fetch(`/api/messages/${activeUserId}?after_id=${lastDmId}`, {
          headers: { 'Accept': 'application/json' }
        });
        if (res.status === 401) {
          clearInterval(dmPollInterval);
          window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
          return;
        }
        if (res.ok) {
          const data = await res.json();
          const messages = Array.isArray(data.data)
            ? data.data
            : (data.data?.messages || []);

          if (Array.isArray(messages) && messages.length > 0) {
            const wasNearBottom = isUserNearBottom();
            let hasNew = false;

            messages.forEach(msg => {
              // Deduplicate by message ID
              const existsById = chatStream?.querySelector(`[data-msg-id="${msg.id}"]`);
              if (existsById) return;

              // Deduplicate / link by client_msg_id
              if (msg.client_msg_id) {
                const pendingBubble = chatStream?.querySelector(`[data-client-msg-id="${msg.client_msg_id}"]`);
                if (pendingBubble) {
                  pendingBubble.classList.remove('pending', 'failed');
                  pendingBubble.setAttribute('data-msg-id', msg.id);
                  const meta = pendingBubble.querySelector('.chat-bubble-meta');
                  if (meta) {
                    meta.innerHTML = `<span class="chat-timestamp">${formatBubbleTime(msg.created_at)}</span>`;
                  }
                  lastDmId = Math.max(lastDmId, parseInt(msg.id, 10));
                  return;
                }
              }

              // Append incoming bubble
              const emptyState = chatStream?.querySelector('.chat-empty-state');
              if (emptyState) emptyState.remove();

              const isSent = (typeof msg.is_mine === 'boolean')
                ? msg.is_mine
                : (parseInt(msg.sender_id, 10) === currentUserId);

              const bubble = createMessageBubble(msg, isSent, 'sent');
              chatStream?.appendChild(bubble);
              lastDmId = Math.max(lastDmId, parseInt(msg.id, 10));
              hasNew = true;
            });

            if (hasNew) {
              const latestMsg = messages[messages.length - 1];
              updateOrInsertConversationItem(activeUserId, activeUserData, latestMsg.body, latestMsg.created_at, true);
              if (wasNearBottom) {
                scrollToBottom(true);
              } else if (newMessagesPill) {
                newMessagesPill.style.display = 'inline-flex';
              }
            }
          }
        }
      } catch (e) {}
    }, 3500);
  }

  // ==========================================================================
  // Server-Backed People Search in New Chat Modal
  // ==========================================================================
  let searchAbortController = null;
  let searchDebounceTimer = null;

  async function fetchAndRenderUsers(query = '') {
    if (!authorsListContainer) return;

    if (searchAbortController) {
      searchAbortController.abort();
    }
    searchAbortController = new AbortController();

    // Render loading indicator
    authorsListContainer.innerHTML = `
      <div class="chat-search-status-box">
        <div class="chat-search-spinner"></div>
        <span>Searching people...</span>
      </div>
    `;

    try {
      const q = encodeURIComponent(query.trim());
      const res = await fetch(`/api/users/search?q=${q}&limit=20`, {
        headers: { 'Accept': 'application/json' },
        signal: searchAbortController.signal
      });

      if (res.status === 401) {
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        return;
      }

      if (!res.ok) {
        throw new Error('Search failed');
      }

      const json = await res.json();
      const users = json.data?.users || [];

      if (users.length === 0) {
        authorsListContainer.innerHTML = `
          <div class="chat-search-status-box">
            <span>No users found matching "${escapeHtml(query.trim())}".</span>
          </div>
        `;
        return;
      }

      authorsListContainer.innerHTML = '';
      users.forEach(u => {
        const itemLink = document.createElement('a');
        itemLink.href = `/messages/${u.id}`;
        itemLink.className = 'new-chat-user-item';
        itemLink.setAttribute('data-user-id', u.id);

        const dName = u.display_name || u.username;
        const initial = (dName[0] || 'A').toUpperCase();

        let avatarHtml = '';
        if (u.avatar_url) {
          avatarHtml = `
            <img src="${escapeHtml(u.avatar_url)}" alt="${escapeHtml(dName)}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; flex-shrink: 0;" onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
            <div style="display: none; width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
              ${escapeHtml(initial)}
            </div>
          `;
        } else {
          avatarHtml = `
            <div style="width: 38px; height: 38px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; flex-shrink: 0;">
              ${escapeHtml(initial)}
            </div>
          `;
        }

        const handleStr = u.handle || ('@' + u.username);
        const activeBadgeHtml = u.has_conversation
          ? '<span class="badge-existing-chat">Active Chat</span>'
          : '';

        itemLink.innerHTML = `
          ${avatarHtml}
          <div style="flex: 1; min-width: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <span style="font-weight: 700; font-size: 0.92rem; color: var(--text-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                ${escapeHtml(dName)}
              </span>
              ${activeBadgeHtml}
            </div>
            <span style="font-size: 0.78rem; color: var(--text-muted); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              ${escapeHtml(handleStr)}
            </span>
          </div>
        `;

        authorsListContainer.appendChild(itemLink);
      });
    } catch (err) {
      if (err.name === 'AbortError') return;
      authorsListContainer.innerHTML = `
        <div class="chat-search-status-box">
          <span style="color: #dc2626;">Failed to search users. Please try again.</span>
          <button type="button" class="btn btn-secondary btn-sm btn-pill" id="retrySearchBtn" style="font-size: 0.8rem; padding: 4px 12px; margin-top: 6px;">
            Retry
          </button>
        </div>
      `;
      const retryBtn = document.getElementById('retrySearchBtn');
      if (retryBtn) {
        retryBtn.addEventListener('click', () => fetchAndRenderUsers(query));
      }
    }
  }

  // Live Debounced People Search Input Listener
  if (newChatSearch) {
    newChatSearch.addEventListener('input', () => {
      const q = newChatSearch.value;
      if (clearSearchBtn) {
        clearSearchBtn.style.display = q.length > 0 ? 'inline-block' : 'none';
      }

      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        fetchAndRenderUsers(q);
      }, 250);
    });
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener('click', () => {
      if (newChatSearch) {
        newChatSearch.value = '';
        clearSearchBtn.style.display = 'none';
        newChatSearch.focus();
        fetchAndRenderUsers('');
      }
    });
  }

  // Open & Close Modal Controls
  function openModal() {
    if (!newChatModal) return;
    newChatModal.classList.add('open');
    if (newChatSearch) {
      newChatSearch.focus();
      // Load initial list if currently empty
      if (!authorsListContainer || authorsListContainer.children.length === 0) {
        fetchAndRenderUsers('');
      }
    }
  }

  function closeModal() {
    if (!newChatModal) return;
    newChatModal.classList.remove('open');
  }

  if (openNewChatBtn) {
    openNewChatBtn.addEventListener('click', openModal);
  }

  if (closeNewChatBtn) {
    closeNewChatBtn.addEventListener('click', closeModal);
  }

  if (newChatModal) {
    newChatModal.addEventListener('click', (e) => {
      if (e.target === newChatModal) closeModal();
    });
  }

  // Close modal on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && newChatModal && newChatModal.classList.contains('open')) {
      closeModal();
    }
  });
});
</script>
