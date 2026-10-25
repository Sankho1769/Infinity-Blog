<!-- Compact Category Chat Workspace Topbar -->
<div class="chat-workspace-topbar">
  <div class="chat-type-switcher">
    <a href="/chat" class="chat-switch-pill active">
      <span>💬</span> Category Communities
    </a>
    <a href="/messages" class="chat-switch-pill">
      <span>✉️</span> Direct Messages
    </a>
  </div>
  <div class="chat-workspace-meta">
    <span class="chat-workspace-badge">Public Text Chat Rooms</span>
  </div>
</div>

<!-- Chat Two-Column Container -->
<div class="glass-panel chat-two-col-container <?= !empty($activeRoom) ? 'has-active-room' : '' ?>" id="chatApp">

  <!-- Left Column: Rooms Directory -->
  <div class="chat-rooms-sidebar">
    <div style="padding: 12px 16px; border-bottom: 1px solid var(--glass-border); flex-shrink: 0;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <h3 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-main);">Category Channels</h3>
        <span style="font-size: 0.76rem; color: var(--text-muted); font-weight: 600;"><?= count($rooms) ?> rooms</span>
      </div>
      <!-- Search filter for category rooms -->
      <input type="text" id="chatRoomSearch" placeholder="Filter category channels..." style="width: 100%; padding: 7px 12px; border-radius: var(--radius-pill); border: 1px solid var(--glass-border); font-size: 0.82rem; background: rgba(255, 255, 255, 0.7); outline: none; box-sizing: border-box; color: var(--text-main);">
    </div>

    <div id="chatRoomsList" style="overflow-y: auto; flex: 1; padding: 10px 8px; display: flex; flex-direction: column; gap: 6px;">
        <?php foreach ($rooms as $r): ?>
          <?php
            $isActive = ($activeRoom['slug'] ?? '') === $r['slug'];
            $displayName = \InfinityBlog\Community\CommunityNameFormatter::format($r['name'] ?? '');
            $catDesc = !empty($r['description']) ? $r['description'] : '';
            $latestSnippet = !empty($r['latest_message']) ? mb_substr((string) $r['latest_message'], 0, 36) : null;
            $latestTime = !empty($r['latest_message_at']) ? date('g:i A', strtotime($r['latest_message_at'])) : null;
          ?>
          <a href="/chat/<?= htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8') ?>" class="chat-room-item <?= $isActive ? 'active' : '' ?>" data-name="<?= htmlspecialchars(strtolower($displayName . ' ' . $r['slug']), ENT_QUOTES, 'UTF-8') ?>" style="padding: 10px 12px; border-radius: 14px; text-decoration: none; display: flex; align-items: flex-start; gap: 10px; background: <?= $isActive ? 'rgba(37, 99, 235, 0.12)' : 'transparent' ?>; border: 1px solid <?= $isActive ? 'rgba(37, 99, 235, 0.25)' : 'transparent' ?>; transition: all 0.15s ease;">
            <div style="flex-shrink: 0; margin-top: 2px;">
              <?= \InfinityBlog\Community\CommunityLogoHelper::renderLogoHtml($r, 36) ?>
            </div>
            <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px;">
              <div style="display: flex; justify-content: space-between; align-items: center; gap: 6px;">
                <span style="font-weight: 700; font-size: 0.90rem; color: <?= $isActive ? 'var(--primary-color)' : 'var(--text-main)' ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span style="font-size: 0.72rem; color: var(--text-muted); flex-shrink: 0; background: rgba(148, 163, 184, 0.12); padding: 2px 6px; border-radius: 8px;">
                  👥 <?= (int) ($r['member_count'] ?? 0) ?>
                </span>
              </div>
              <?php if ($latestSnippet !== null): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 4px; font-size: 0.76rem; color: var(--text-muted);">
                  <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                    <?= htmlspecialchars($latestSnippet, ENT_QUOTES, 'UTF-8') ?>...
                  </span>
                  <?php if ($latestTime): ?>
                    <span style="font-size: 0.68rem; flex-shrink: 0;"><?= $latestTime ?></span>
                  <?php endif; ?>
                </div>
              <?php elseif (!empty($catDesc)): ?>
                <span style="font-size: 0.76rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?= htmlspecialchars($catDesc, ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
    </div>
  </div>

  <!-- Right Column: Active Room Messages -->
  <div class="chat-room-main" style="flex: 1; display: flex; flex-direction: column; min-width: 0; position: relative;">
    <?php if (!empty($activeRoom)): ?>
      <!-- Room Header -->
      <div class="chat-room-header" style="padding: 12px 20px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; flex-shrink: 0;">
        <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
          <!-- Mobile Back to Channels Button -->
          <a href="/chat" class="chat-back-btn" aria-label="Back to channels">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
          </a>
          <div style="flex-shrink: 0;">
            <?= \InfinityBlog\Community\CommunityLogoHelper::renderLogoHtml($activeRoom, 40) ?>
          </div>
          <div style="min-width: 0;">
            <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              <?= htmlspecialchars(\InfinityBlog\Community\CommunityNameFormatter::format($activeRoom['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <?php if (!empty($activeRoom['description'])): ?>
              <p style="font-size: 0.78rem; color: var(--text-muted); margin: 2px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($activeRoom['description'], ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0; flex-wrap: wrap;">
          <a href="/community/<?= urlencode($activeRoom['slug'] ?? '') ?>" class="btn-chat-category-feed" style="font-size: 0.76rem; background: rgba(37, 99, 235, 0.12); color: var(--primary-color); font-weight: 700; padding: 5px 12px; border-radius: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid rgba(37, 99, 235, 0.25);" title="View community discussion feed, posts, and comments">
            📖 Community Feed & Discussions →
          </a>
          <span id="chatMembershipBadge" style="font-size: 0.76rem; background: <?= !empty($isMember) ? 'rgba(16, 185, 129, 0.12)' : 'rgba(100, 116, 139, 0.12)' ?>; color: <?= !empty($isMember) ? '#10B981' : 'var(--text-muted)' ?>; font-weight: 700; padding: 4px 10px; border-radius: 12px; transition: all 0.2s ease;">
            <?= !empty($isMember) ? '✓ Joined Member' : 'Guest Viewer' ?>
          </span>
          <span style="font-size: 0.76rem; background: rgba(37, 99, 235, 0.08); color: var(--primary-color); font-weight: 700; padding: 4px 10px; border-radius: 12px;">
            👥 <span id="chatMemberCount"><?= (int) ($memberCount ?? 0) ?></span> members
          </span>
          <span style="font-size: 0.76rem; background: rgba(16, 185, 129, 0.12); color: #10B981; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
            ● Live Chat
          </span>
        </div>
      </div>

      <!-- Chat Stream with Floating New Messages Indicator -->
      <div class="chat-stream-container" style="position: relative; flex: 1; min-height: 0; display: flex; flex-direction: column;">
        <div id="chatMessageStream" style="flex: 1; overflow-y: auto; padding: 18px 20px; display: flex; flex-direction: column; gap: 14px;">
          <?php if (empty($messages)): ?>
            <div id="noChatMessagesNotice" style="margin: auto; text-align: center; color: var(--text-muted);">
              <div style="font-size: 2.4rem; margin-bottom: 8px;">💬</div>
              <p style="font-weight: 600;">No messages yet in this topic room.</p>
              <p style="font-size: 0.85rem;">Be the first to start the conversation!</p>
            </div>
          <?php else: ?>
            <?php foreach ($messages as $msg): ?>
              <?php $isMe = !empty($currentUser) && (int) $currentUser['id'] === (int) $msg['user_id']; ?>
              <div style="display: flex; gap: 12px; align-items: flex-start; <?= $isMe ? 'flex-direction: row-reverse;' : '' ?>" data-msg-id="<?= (int) $msg['id'] ?>">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                  <?= htmlspecialchars(mb_substr($msg['user_name'] ?? 'U', 0, 1), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div style="max-width: 70%;">
                  <div style="display: flex; gap: 8px; align-items: baseline; margin-bottom: 4px; <?= $isMe ? 'justify-content: flex-end;' : '' ?>">
                    <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">
                      <?= htmlspecialchars($msg['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <?php if (!empty($msg['has_author_badge'])): ?>
                      <?= \InfinityBlog\Social\AuthorBadgeService::renderBadgeHtml(true) ?>
                    <?php endif; ?>
                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                      <?= date('g:i A', strtotime($msg['created_at'])) ?>
                    </span>
                  </div>
                  <div class="chat-bubble <?= $isMe ? 'sent' : 'received' ?>" style="max-width: 100%;">
                    <?= !empty($msg['rendered_message']) ? $msg['rendered_message'] : nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Floating New Messages Indicator Pill -->
        <button type="button" class="chat-new-messages-pill" id="newCategoryMessagesPill" aria-label="Scroll to new messages">
          <span>↓ New messages</span>
        </button>
      </div>

      <!-- Chat Input Bar (Anchored at Bottom) -->
      <div class="chat-room-input-bar" style="padding: 12px 18px; border-top: 1px solid var(--glass-border); flex-shrink: 0;">
        <!-- Inline Error Banner -->
        <div id="chatErrorBanner" style="display: none; margin-bottom: 10px; padding: 8px 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; color: #dc2626; font-size: 0.85rem; align-items: center; justify-content: space-between; gap: 8px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1rem; line-height: 1;">⚠️</span>
            <span id="chatErrorMessage" style="font-weight: 500;"></span>
          </div>
          <button type="button" onclick="dismissChatError()" style="background: none; border: none; color: #dc2626; font-size: 1.2rem; cursor: pointer; padding: 0 4px; line-height: 1;" aria-label="Close error">&times;</button>
        </div>

        <!-- Inline Success Banner -->
        <div id="chatSuccessBanner" style="display: none; margin-bottom: 10px; padding: 8px 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 12px; color: #059669; font-size: 0.85rem; align-items: center; justify-content: space-between; gap: 8px;">
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 1rem; line-height: 1;">✓</span>
            <span id="chatSuccessMessage" style="font-weight: 500;"></span>
          </div>
          <button type="button" onclick="dismissChatSuccess()" style="background: none; border: none; color: #059669; font-size: 1.2rem; cursor: pointer; padding: 0 4px; line-height: 1;" aria-label="Close message">&times;</button>
        </div>

        <!-- Non-Member Join Prompt -->
        <div id="chatJoinContainer" style="<?= empty($isMember) ? 'display: block;' : 'display: none;' ?> text-align: center; padding: 10px 14px;">
          <div style="max-width: 480px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 6px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="font-size: 1.2rem;">🛡️</span>
              <span style="font-weight: 700; font-size: 0.98rem; color: var(--text-main);">
                Join <?= htmlspecialchars(\InfinityBlog\Community\CommunityNameFormatter::format($activeRoom['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> to Participate
              </span>
            </div>
            <p style="margin: 0; font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
              You must join this community before sending messages in this channel. Membership keeps discussions organized, authentic, and focused.
            </p>
            <button type="button" id="chatJoinBtn" onclick="handleJoinCommunity()" class="btn btn-primary btn-sm" style="border-radius: 20px; padding: 8px 24px; margin-top: 4px; font-weight: 700; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 6px;">
              <span id="chatJoinBtnText">Join Community</span> <span id="chatJoinBtnIcon">➔</span>
            </button>
          </div>
        </div>

        <!-- Member Chat Message Composer -->
        <div id="chatComposerContainer" style="<?= !empty($isMember) ? 'display: block;' : 'display: none;' ?>">
          <form id="chatSendForm" onsubmit="handleSendChatMessage(event)" style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative; flex: 1;">
              <input type="text" id="chatInputMessage" class="form-input" placeholder="Type your message in <?= htmlspecialchars(\InfinityBlog\Community\CommunityNameFormatter::format($activeRoom['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>... (Enter to send)" required autocomplete="off" maxlength="1000" style="width: 100%; border-radius: var(--radius-pill); padding: 10px 16px; box-sizing: border-box; height: 42px;">
            </div>
            <button type="submit" class="btn btn-primary btn-pill btn-sm" id="chatSendBtn" style="height: 42px; padding: 0 20px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0;">
              <span id="chatSendBtnText">Send</span> <span id="chatSendBtnIcon">➔</span>
            </button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div style="margin: auto; text-align: center; color: var(--text-muted); padding: 40px;">
        <div style="font-size: 3rem; margin-bottom: 12px;">💬</div>
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">Select a Category Channel</h3>
        <p style="font-size: 0.9rem; margin: 0;">Choose a topic channel from the left to start reading and chatting.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
let lastMsgId = <?= !empty($messages) ? (int) $messages[array_key_last($messages)]['id'] : 0 ?>;
let isMember = <?= !empty($isMember) ? 'true' : 'false' ?>;
const roomSlug = "<?= htmlspecialchars($activeRoom['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
const stream = document.getElementById('chatMessageStream');
const newCategoryMessagesPill = document.getElementById('newCategoryMessagesPill');

function isCategoryNearBottom() {
  if (!stream) return true;
  return (stream.scrollHeight - stream.scrollTop - stream.clientHeight) <= 90;
}

function scrollCategoryToBottom(smooth = false) {
  if (!stream) return;
  if (smooth) {
    stream.scrollTo({ top: stream.scrollHeight, behavior: 'smooth' });
  } else {
    stream.scrollTop = stream.scrollHeight;
  }
  if (newCategoryMessagesPill) newCategoryMessagesPill.style.display = 'none';
}

if (stream) {
  stream.addEventListener('scroll', () => {
    if (isCategoryNearBottom() && newCategoryMessagesPill) {
      newCategoryMessagesPill.style.display = 'none';
    }
  });
}

if (newCategoryMessagesPill) {
  newCategoryMessagesPill.addEventListener('click', () => {
    scrollCategoryToBottom(true);
  });
}

// Initial position at bottom
scrollCategoryToBottom(false);

// Room filter search
const roomSearch = document.getElementById('chatRoomSearch');
if (roomSearch) {
  roomSearch.addEventListener('input', () => {
    const q = roomSearch.value.toLowerCase().trim();
    document.querySelectorAll('#chatRoomsList .chat-room-item').forEach(item => {
      const name = item.getAttribute('data-name') || '';
      item.style.display = name.includes(q) ? 'flex' : 'none';
    });
  });
}

function showChatError(msg) {
  const banner = document.getElementById('chatErrorBanner');
  const msgEl = document.getElementById('chatErrorMessage');
  if (banner && msgEl) {
    msgEl.textContent = msg;
    banner.style.display = 'flex';
  }
}

function dismissChatError() {
  const banner = document.getElementById('chatErrorBanner');
  if (banner) {
    banner.style.display = 'none';
  }
}

function showChatSuccess(msg) {
  const banner = document.getElementById('chatSuccessBanner');
  const msgEl = document.getElementById('chatSuccessMessage');
  if (banner && msgEl) {
    msgEl.textContent = msg;
    banner.style.display = 'flex';
  }
}

function dismissChatSuccess() {
  const banner = document.getElementById('chatSuccessBanner');
  if (banner) {
    banner.style.display = 'none';
  }
}

// Polling for new messages every 3.5 seconds
let pollInterval = null;
if (roomSlug) {
  pollInterval = setInterval(async () => {
    try {
      const res = await fetch(`/api/chat/rooms/${roomSlug}/messages?after=${lastMsgId}`, {
        headers: { 'Accept': 'application/json' }
      });
      if (res.status === 401) {
        clearInterval(pollInterval);
        window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        return;
      }
      if (res.ok) {
        const data = await res.json();
        const newMsgs = data.data || [];
        if (newMsgs.length > 0) {
          const wasNearBottom = isCategoryNearBottom();
          newMsgs.forEach(m => {
            appendChatMessage(m);
            lastMsgId = Math.max(lastMsgId, parseInt(m.id, 10));
          });
          if (wasNearBottom) {
            scrollCategoryToBottom(true);
          } else if (newCategoryMessagesPill) {
            newCategoryMessagesPill.style.display = 'inline-flex';
          }
        }
        if (data.member_count !== undefined) {
          const countEl = document.getElementById('chatMemberCount');
          if (countEl) countEl.textContent = data.member_count;
        }
      }
    } catch (e) {}
  }, 3500);
}

function appendChatMessage(m) {
  if (!m || !stream) return;
  if (stream.querySelector(`[data-msg-id="${m.id}"]`)) return;

  const emptyNotice = document.getElementById('noChatMessagesNotice');
  if (emptyNotice) {
    emptyNotice.remove();
  }

  const isMe = <?= !empty($currentUser) ? (int) $currentUser['id'] : -1 ?> === parseInt(m.user_id, 10);
  const div = document.createElement('div');
  div.style.display = 'flex';
  div.style.gap = '12px';
  div.style.alignItems = 'flex-start';
  if (isMe) div.style.flexDirection = 'row-reverse';
  div.dataset.msgId = m.id;

  const initial = (m.user_name || 'U').substring(0, 1).toUpperCase();
  const dateVal = typeof m.created_at === 'string' ? m.created_at.replace(' ', 'T') : m.created_at;
  const parsedDate = new Date(dateVal);
  const time = !isNaN(parsedDate.getTime())
    ? parsedDate.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
    : '';

  const avatarHtml = m.user_avatar
    ? `<img src="${escapeHtml(m.user_avatar)}" alt="${escapeHtml(m.user_name || 'User')}" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid var(--glass-border);">`
    : `<div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">${escapeHtml(initial)}</div>`;

  div.innerHTML = `
    ${avatarHtml}
    <div style="max-width: 70%;">
      <div style="display: flex; gap: 8px; align-items: baseline; margin-bottom: 4px; ${isMe ? 'justify-content: flex-end;' : ''}">
        <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">${escapeHtml(m.user_name || 'User')}</span>
        <span style="font-size: 0.72rem; color: var(--text-muted);">${time}</span>
      </div>
      <div class="chat-bubble ${isMe ? 'sent' : 'received'}" style="max-width: 100%;">
        ${m.rendered_message || escapeHtml(m.message).replace(/\n/g, '<br>')}
      </div>
    </div>
  `;
  stream.appendChild(div);
}

async function handleJoinCommunity() {
  if (!roomSlug) return;
  dismissChatError();
  dismissChatSuccess();

  const joinBtn = document.getElementById('chatJoinBtn');
  const btnText = document.getElementById('chatJoinBtnText');
  const btnIcon = document.getElementById('chatJoinBtnIcon');
  if (joinBtn) joinBtn.disabled = true;
  if (btnText) btnText.textContent = 'Joining';
  if (btnIcon) btnIcon.textContent = '...';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch(`/api/chat/rooms/${roomSlug}/join`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      }
    });

    if (res.status === 401) {
      window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
      return;
    }

    const data = await res.json().catch(() => null);

    if (res.ok && data?.data) {
      isMember = true;
      const joinContainer = document.getElementById('chatJoinContainer');
      const composerContainer = document.getElementById('chatComposerContainer');
      if (joinContainer) joinContainer.style.display = 'none';
      if (composerContainer) composerContainer.style.display = 'block';

      const badge = document.getElementById('chatMembershipBadge');
      if (badge) {
        badge.textContent = '✓ Joined Member';
        badge.style.background = 'rgba(16, 185, 129, 0.12)';
        badge.style.color = '#10B981';
      }
      const countEl = document.getElementById('chatMemberCount');
      if (countEl && data.data.member_count !== undefined) {
        countEl.textContent = data.data.member_count;
      }

      showChatSuccess(data.data.message || 'You have joined this community!');
      setTimeout(() => dismissChatSuccess(), 4000);

      const input = document.getElementById('chatInputMessage');
      if (input) input.focus();
    } else {
      const errMsg = data?.error?.message || 'Failed to join community. Please try again.';
      showChatError(errMsg);
    }
  } catch (err) {
    showChatError('Network error. Please check your internet connection.');
  } finally {
    if (joinBtn) joinBtn.disabled = false;
    if (btnText) btnText.textContent = 'Join Community';
    if (btnIcon) btnIcon.textContent = '➔';
  }
}

async function handleSendChatMessage(e) {
  e.preventDefault();
  dismissChatError();
  dismissChatSuccess();

  if (!isMember) {
    showChatError('You must join this community before sending messages.');
    const joinContainer = document.getElementById('chatJoinContainer');
    const composerContainer = document.getElementById('chatComposerContainer');
    if (joinContainer) joinContainer.style.display = 'block';
    if (composerContainer) composerContainer.style.display = 'none';
    return;
  }

  const input = document.getElementById('chatInputMessage');
  if (!input) return;

  const msg = input.value.trim();
  if (!msg) {
    showChatError('Please enter a message before sending.');
    input.focus();
    return;
  }

  if (msg.length > 2000) {
    showChatError('Message exceeds maximum limit of 2000 characters.');
    return;
  }

  const btn = document.getElementById('chatSendBtn');
  const btnText = document.getElementById('chatSendBtnText');
  const btnIcon = document.getElementById('chatSendBtnIcon');
  if (btn) btn.disabled = true;
  if (btnText) btnText.textContent = 'Sending';
  if (btnIcon) btnIcon.textContent = '...';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  try {
    const res = await fetch(`/api/chat/rooms/${roomSlug}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': csrf || ''
      },
      body: JSON.stringify({ message: msg })
    });

    if (res.status === 401) {
      window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
      return;
    }

    const data = await res.json().catch(() => null);

    if (res.ok && data?.data) {
      input.value = '';
      appendChatMessage(data.data);
      lastMsgId = Math.max(lastMsgId, parseInt(data.data.id, 10));
      scrollCategoryToBottom(false);
    } else {
      if (res.status === 403 && data?.error?.code === 'MEMBERSHIP_REQUIRED') {
        isMember = false;
        const joinContainer = document.getElementById('chatJoinContainer');
        const composerContainer = document.getElementById('chatComposerContainer');
        if (joinContainer) joinContainer.style.display = 'block';
        if (composerContainer) composerContainer.style.display = 'none';
        const badge = document.getElementById('chatMembershipBadge');
        if (badge) {
          badge.textContent = 'Guest Viewer';
          badge.style.background = 'rgba(100, 116, 139, 0.12)';
          badge.style.color = 'var(--text-muted)';
        }
      }
      const errMsg = data?.error?.message || 'Failed to send message. Please try again.';
      showChatError(errMsg);
    }
  } catch (err) {
    showChatError('Network error. Please check your internet connection.');
  } finally {
    if (btn) btn.disabled = false;
    if (btnText) btnText.textContent = 'Send';
    if (btnIcon) btnIcon.textContent = '➔';
    input.focus();
  }
}

function escapeHtml(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
