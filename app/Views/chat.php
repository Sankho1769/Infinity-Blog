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
            <div style="margin: auto; text-align: center; color: var(--text-muted);">
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
          <?php if (!empty($currentUser)): ?>
            <form id="chatSendForm" onsubmit="handleSendChatMessage(event)" style="display: flex; gap: 10px; align-items: center;">
              <input type="text" id="chatInputMessage" class="form-input" placeholder="Type your message in #<?= htmlspecialchars($activeRoom['name'], ENT_QUOTES, 'UTF-8') ?>..." required autocomplete="off" style="flex: 1; border-radius: 24px; padding: 12px 18px;">
              <button type="submit" class="btn btn-primary" id="chatSendBtn" style="border-radius: 24px; padding: 12px 22px;">
                Send ➔
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
let lastMsgId = <?= !empty($messages) ? (int) end($messages)['id'] : 0 ?>;
const roomSlug = "<?= htmlspecialchars($activeRoom['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>";
const stream = document.getElementById('chatMessageStream');

if (stream) {
  stream.scrollTop = stream.scrollHeight;
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
            lastMsgId = Math.max(lastMsgId, m.id);
          });
          stream.scrollTop = stream.scrollHeight;
        }
      }
    } catch (e) {}
  }, 4000);
}

function appendChatMessage(m) {
  const isMe = <?= !empty($currentUser) ? (int) $currentUser['id'] : -1 ?> === parseInt(m.user_id);
  const div = document.createElement('div');
  div.style.display = 'flex';
  div.style.gap = '12px';
  div.style.alignItems = 'flex-start';
  if (isMe) div.style.flexDirection = 'row-reverse';
  div.dataset.msgId = m.id;

  const initial = (m.user_name || 'U').substring(0, 1);
  const time = new Date(m.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

  div.innerHTML = `
    <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-gradient); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
      ${escapeHtml(initial)}
    </div>
    <div style="max-width: 70%;">
      <div style="display: flex; gap: 8px; align-items: baseline; margin-bottom: 4px; ${isMe ? 'justify-content: flex-end;' : ''}">
        <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);">${escapeHtml(m.user_name || 'User')}</span>
        <span style="font-size: 0.72rem; color: var(--text-muted);">${time}</span>
      </div>
      <div style="padding: 10px 16px; border-radius: 16px; font-size: 0.92rem; line-height: 1.4; ${isMe ? 'background: var(--primary-gradient); color: #fff; border-bottom-right-radius: 4px;' : 'background: #fff; color: var(--text-main); border: 1px solid var(--glass-border); border-bottom-left-radius: 4px;'}">
        ${escapeHtml(m.message).replace(/\n/g, '<br>')}
      </div>
    </div>
  `;
  stream.appendChild(div);
}

async function handleSendChatMessage(e) {
  e.preventDefault();
  const input = document.getElementById('chatInputMessage');
  const msg = input.value.trim();
  if (!msg) return;

  const btn = document.getElementById('chatSendBtn');
  btn.disabled = true;
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
    if (res.ok) {
      const data = await res.json();
      input.value = '';
      if (data.data) {
        appendChatMessage(data.data);
        lastMsgId = Math.max(lastMsgId, data.data.id);
        stream.scrollTop = stream.scrollHeight;
      }
    } else {
      const err = await res.json();
      alert(err.error?.message || 'Failed to send message.');
    }
  } catch (e) {
    alert('Network error.');
  } finally {
    btn.disabled = false;
    input.focus();
  }
}

function escapeHtml(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>
