<!-- Screen: Public Topic Text Chat Rooms -->
<div class="page-container" style="max-width: 1200px; margin: 0 auto; padding: 24px 20px;">

  <!-- Header -->
  <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
    <div>
      <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 700; color: var(--primary-color); background: rgba(37, 99, 235, 0.08); padding: 4px 12px; border-radius: 20px; margin-bottom: 6px;">
        <span>💬</span> COMMUNITY DISCUSSIONS
      </div>
      <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-main); margin: 0;">Public Text Chat Rooms</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 4px;">Join topic-based text channels to exchange ideas with writers and readers in real-time.</p>
    </div>
  </div>

  <!-- Chat Two-Column Container -->
  <div class="glass-panel" style="display: flex; height: 680px; border-radius: 20px; overflow: hidden; border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow);">

    <!-- Left Column: Rooms Directory -->
    <div style="width: 320px; border-right: 1px solid var(--glass-border); display: flex; flex-direction: column; background: rgba(255,255,255,0.4);">
      <div style="padding: 18px; border-bottom: 1px solid var(--glass-border);">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-main);">Topic Rooms</h3>
      </div>

      <div style="overflow-y: auto; flex: 1; padding: 12px 8px; display: flex; flex-direction: column; gap: 6px;">
        <?php foreach ($rooms as $r): ?>
          <?php $isActive = ($activeRoom['slug'] ?? '') === $r['slug']; ?>
          <a href="/chat/<?= htmlspecialchars($r['slug'], ENT_QUOTES, 'UTF-8') ?>" style="padding: 12px 14px; border-radius: 12px; text-decoration: none; display: flex; flex-direction: column; gap: 4px; background: <?= $isActive ? 'rgba(37, 99, 235, 0.12)' : 'transparent' ?>; border: 1px solid <?= $isActive ? 'rgba(37, 99, 235, 0.25)' : 'transparent' ?>; transition: all 0.15s ease;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="font-weight: 700; font-size: 0.95rem; color: <?= $isActive ? 'var(--primary-color)' : 'var(--text-main)' ?>;">
                # <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
              </span>
              <span style="font-size: 0.75rem; color: var(--text-muted);">
                <?= (int) ($r['message_count'] ?? 0) ?> msgs
              </span>
            </div>
            <?php if (!empty($r['description'])): ?>
              <span style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= htmlspecialchars($r['description'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right Column: Active Room Messages -->
    <div style="flex: 1; display: flex; flex-direction: column; background: rgba(255, 255, 255, 0.65);">
      <?php if (!empty($activeRoom)): ?>
        <!-- Room Header -->
        <div style="padding: 16px 24px; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0;">
              # <?= htmlspecialchars($activeRoom['name'], ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <?php if (!empty($activeRoom['description'])): ?>
              <p style="font-size: 0.82rem; color: var(--text-muted); margin: 2px 0 0;">
                <?= htmlspecialchars($activeRoom['description'], ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endif; ?>
          </div>
          <span style="font-size: 0.78rem; background: rgba(16, 185, 129, 0.12); color: #10B981; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
            ● Live Chat
          </span>
        </div>

        <!-- Chat Stream -->
        <div id="chatMessageStream" style="flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 14px;">
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
                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                      <?= date('g:i A', strtotime($msg['created_at'])) ?>
                    </span>
                  </div>
                  <div style="padding: 10px 16px; border-radius: 16px; font-size: 0.92rem; line-height: 1.4; <?= $isMe ? 'background: var(--primary-gradient); color: #fff; border-bottom-right-radius: 4px;' : 'background: #fff; color: var(--text-main); border: 1px solid var(--glass-border); border-bottom-left-radius: 4px;' ?>">
                    <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Chat Input Bar -->
        <div style="padding: 16px 20px; border-top: 1px solid var(--glass-border); background: rgba(255,255,255,0.7);">
          <!-- Inline Error Banner -->
          <div id="chatErrorBanner" style="display: none; margin-bottom: 12px; padding: 10px 14px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; color: #dc2626; font-size: 0.88rem; align-items: center; justify-content: space-between; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="font-size: 1rem; line-height: 1;">⚠️</span>
              <span id="chatErrorMessage" style="font-weight: 500;"></span>
            </div>
            <button type="button" onclick="dismissChatError()" style="background: none; border: none; color: #dc2626; font-size: 1.2rem; cursor: pointer; padding: 0 4px; line-height: 1;" aria-label="Close error">&times;</button>
          </div>

          <?php if (!empty($currentUser)): ?>
            <form id="chatSendForm" onsubmit="handleSendChatMessage(event)" style="display: flex; gap: 10px; align-items: center;">
              <input type="text" id="chatInputMessage" class="form-input" placeholder="Type your message in #<?= htmlspecialchars($activeRoom['name'], ENT_QUOTES, 'UTF-8') ?>..." required autocomplete="off" maxlength="1000" style="flex: 1; border-radius: 24px; padding: 12px 18px;">
              <button type="submit" class="btn btn-primary" id="chatSendBtn" style="border-radius: 24px; padding: 12px 22px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                <span id="chatSendBtnText">Send</span> <span id="chatSendBtnIcon">➔</span>
              </button>
            </form>
          <?php else: ?>
            <div style="text-align: center; padding: 8px; font-size: 0.9rem; color: var(--text-muted);">
              <a href="/login?redirect=<?= urlencode('/chat/' . $activeRoom['slug']) ?>" style="color: var(--primary-color); font-weight: 700; text-decoration: underline;">Sign In</a> to participate in public chat rooms.
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div style="margin: auto; text-align: center; color: var(--text-muted);">
          Select a room from the left to start chatting.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
let lastMsgId = <?= !empty($messages) ? (int) $messages[array_key_last($messages)]['id'] : 0 ?>;
const roomSlug = "<?= htmlspecialchars($activeRoom['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
const stream = document.getElementById('chatMessageStream');

if (stream) {
  stream.scrollTop = stream.scrollHeight;
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

// Polling for new messages every 4 seconds
if (roomSlug) {
  setInterval(async () => {
    try {
      const res = await fetch(`/api/chat/rooms/${roomSlug}/messages?after=${lastMsgId}`);
      if (res.ok) {
        const data = await res.json();
        const newMsgs = data.data || [];
        if (newMsgs.length > 0) {
          newMsgs.forEach(m => {
            appendChatMessage(m);
            lastMsgId = Math.max(lastMsgId, parseInt(m.id, 10));
          });
          stream.scrollTop = stream.scrollHeight;
        }
      }
    } catch (e) {}
  }, 4000);
}

function appendChatMessage(m) {
  if (!m || !stream) return;
  // Prevent duplicate rendering
  if (stream.querySelector(`[data-msg-id="${m.id}"]`)) return;

  // Remove empty placeholder if present
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
      <div style="padding: 10px 16px; border-radius: 16px; font-size: 0.92rem; line-height: 1.4; word-break: break-word; ${isMe ? 'background: var(--primary-gradient); color: #fff; border-bottom-right-radius: 4px;' : 'background: #fff; color: var(--text-main); border: 1px solid var(--glass-border); border-bottom-left-radius: 4px;'}">
        ${escapeHtml(m.message).replace(/\n/g, '<br>')}
      </div>
    </div>
  `;
  stream.appendChild(div);
}

async function handleSendChatMessage(e) {
  e.preventDefault();
  dismissChatError();
  const input = document.getElementById('chatInputMessage');
  if (!input) return;

  const msg = input.value.trim();
  if (!msg) {
    showChatError('Please enter a message before sending.');
    input.focus();
    return;
  }

  if (msg.length > 1000) {
    showChatError('Message exceeds maximum limit of 1000 characters.');
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

    const data = await res.json().catch(() => null);

    if (res.ok && data?.data) {
      input.value = '';
      appendChatMessage(data.data);
      lastMsgId = Math.max(lastMsgId, parseInt(data.data.id, 10));
      stream.scrollTop = stream.scrollHeight;
    } else {
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
