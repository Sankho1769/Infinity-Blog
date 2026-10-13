<!-- Direct Messages & Conversations View -->
<div class="content-header content-page-hero" style="padding-bottom: 16px;">
  <span class="hero-badge" style="display: inline-flex; margin-bottom: 12px;">Private Discussions</span>
  <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.02em;">Direct Messages</h1>
  <p style="font-size: 1.05rem; color: var(--text-muted); max-width: 600px; margin: 8px auto 0;">
    Connect privately with writers, collaborators, and contributors on Infinity Blog.
  </p>
</div>

<div class="messages-container" id="messagesApp">
  <!-- Left Column: Conversations List -->
  <aside class="conversations-sidebar">
    <div class="conversations-header">
      <h2 style="font-size: 1.1rem; font-weight: 800; margin: 0; color: var(--text-dark);">Conversations</h2>
      <button type="button" class="btn btn-primary btn-pill btn-sm" id="openNewChatModalBtn" style="padding: 4px 12px; font-size: 0.8rem;">
        + New Chat
      </button>
    </div>

    <!-- Quick Filter Search Input -->
    <div style="padding: 10px 16px; border-bottom: 1px solid var(--glass-border);">
      <input type="text" id="conversationSearchInput" placeholder="Filter conversations..." style="width: 100%; padding: 6px 12px; border-radius: var(--radius-pill); border: 1px solid var(--glass-border); font-size: 0.82rem; background: rgba(255, 255, 255, 0.8); outline: none;">
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
            <a href="/messages/<?= (int) $other['id'] ?>" class="conversation-item <?= $isActive ? 'active' : '' ?>" data-name="<?= htmlspecialchars(strtolower($displayName . ' ' . $other['username']), ENT_QUOTES, 'UTF-8') ?>">
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
                    <span style="font-size: 0.72rem; color: var(--text-muted);"><?= date('M j', strtotime($c['last_message']['created_at'])) ?></span>
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
  <main class="chat-main-pane">
    <?php if (empty($activeUser)): ?>
      <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 12px;">💬</div>
        <h3 style="font-size: 1.3rem; font-weight: 700; color: var(--text-dark); margin: 0 0 8px 0;">Select a Conversation</h3>
        <p style="color: var(--text-muted); max-width: 380px; margin: 0 0 20px 0; font-size: 0.92rem;">
          Choose an existing contact from the left list or start a new message with any Infinity Blog author.
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
        <?php if (!empty($activeAvatar)): ?>
          <img src="<?= htmlspecialchars($activeAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; const fb = this.nextElementSibling; if (fb) fb.style.display='flex';">
          <div style="display: none; width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); align-items: center; justify-content: center; color: #fff; font-weight: 700;">
            <?= strtoupper(substr($activeName, 0, 1)) ?>
          </div>
        <?php else: ?>
          <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;">
            <?= strtoupper(substr($activeName, 0, 1)) ?>
          </div>
        <?php endif; ?>

        <div style="flex: 1; min-width: 0;">
          <h2 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--text-dark);">
            <?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?>
          </h2>
          <span style="font-size: 0.78rem; color: var(--text-muted);">
            @<?= htmlspecialchars($activeUser['username'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>

        <a href="/author/<?= (int) $activeUser['id'] ?>" class="btn btn-secondary btn-sm btn-pill" style="font-size: 0.8rem; padding: 4px 12px;">
          View Profile →
        </a>
      </div>

      <!-- Messages Stream -->
      <div class="chat-messages-stream" id="chatStream">
        <?php if (empty($threadMessages)): ?>
          <div style="text-align: center; color: var(--text-muted); margin: auto; padding: 30px;">
            <p style="font-size: 0.95rem; margin-bottom: 4px;">No messages with <?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?> yet.</p>
            <p style="font-size: 0.82rem; margin: 0;">Send a message below to break the ice!</p>
          </div>
        <?php else: ?>
          <?php foreach ($threadMessages as $msg): ?>
            <?php
              $isSentByMe = (int) $msg['sender_id'] !== (int) $activeUser['id'];
            ?>
            <div class="chat-bubble <?= $isSentByMe ? 'sent' : 'received' ?>" data-msg-id="<?= (int) $msg['id'] ?>">
              <?= nl2br(htmlspecialchars($msg['body'], ENT_QUOTES, 'UTF-8')) ?>
              <span class="chat-timestamp">
                <?= date('g:i A', strtotime($msg['created_at'])) ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Message Input Form -->
      <form class="chat-input-bar" id="sendMessageForm">
        <textarea class="chat-textarea" id="messageInput" name="body" placeholder="Write a message to <?= htmlspecialchars($activeName, ENT_QUOTES, 'UTF-8') ?>... (Enter to send)" rows="1" required></textarea>
        <button type="submit" class="btn btn-primary btn-pill btn-sm" id="sendBtn" style="height: 44px; padding: 0 18px; display: inline-flex; align-items: center; gap: 6px;">
          <span>Send</span>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        </button>
      </form>
    <?php endif; ?>
  </main>
</div>

<!-- New Chat Modal -->
<div class="modal-backdrop" id="newChatModal">
  <div class="modal-card" style="max-width: 460px; width: 90%; max-height: 80vh; display: flex; flex-direction: column;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 1.2rem; font-weight: 800; color: var(--text-dark);">Start New Conversation</h3>
      <button type="button" class="alert-close-btn" id="closeNewChatModalBtn" style="font-size: 1.4rem;">×</button>
    </div>

    <input type="text" id="newChatSearchInput" placeholder="Search authors..." style="width: 100%; padding: 8px 14px; border-radius: var(--radius-pill); border: 1px solid var(--glass-border); font-size: 0.88rem; outline: none; margin-bottom: 14px;">

    <div style="flex: 1; overflow-y: auto; max-height: 340px;" id="newChatAuthorsList">
      <?php if (empty($allAuthors)): ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.88rem; padding: 20px;">No other active authors found.</p>
      <?php else: ?>
        <?php foreach ($allAuthors as $a): ?>
          <a href="/messages/<?= (int) $a['id'] ?>" class="conversation-item" style="border-radius: var(--radius-md); margin-bottom: 4px;" data-name="<?= htmlspecialchars(strtolower(($a['display_name'] ?? $a['username']) . ' ' . $a['username']), ENT_QUOTES, 'UTF-8') ?>">
            <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #a5b4fc, #6366f1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.95rem; flex-shrink: 0;">
              <?= strtoupper(substr($a['display_name'] ?? $a['username'], 0, 1)) ?>
            </div>
            <div style="flex: 1; min-width: 0;">
              <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= htmlspecialchars($a['display_name'] ?? $a['username'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div style="font-size: 0.78rem; color: var(--text-muted);">
                @<?= htmlspecialchars($a['username'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const activeUserId = <?= !empty($activeUser) ? (int) $activeUser['id'] : 'null' ?>;
  const chatStream = document.getElementById('chatStream');
  const sendForm = document.getElementById('sendMessageForm');
  const messageInput = document.getElementById('messageInput');
  const newChatModal = document.getElementById('newChatModal');
  const openNewChatBtn = document.getElementById('openNewChatModalBtn');
  const closeNewChatBtn = document.getElementById('closeNewChatModalBtn');
  const convSearch = document.getElementById('conversationSearchInput');
  const newChatSearch = document.getElementById('newChatSearchInput');

  // Auto scroll to bottom
  function scrollToBottom() {
    if (chatStream) {
      chatStream.scrollTop = chatStream.scrollHeight;
    }
  }
  scrollToBottom();

  // Filter conversations
  if (convSearch) {
    convSearch.addEventListener('input', () => {
      const q = convSearch.value.toLowerCase().trim();
      document.querySelectorAll('#conversationsList .conversation-item').forEach(item => {
        const name = item.getAttribute('data-name') || '';
        item.closest('li').style.display = name.includes(q) ? '' : 'none';
      });
    });
  }

  // Filter new chat authors
  if (newChatSearch) {
    newChatSearch.addEventListener('input', () => {
      const q = newChatSearch.value.toLowerCase().trim();
      document.querySelectorAll('#newChatAuthorsList .conversation-item').forEach(item => {
        const name = item.getAttribute('data-name') || '';
        item.style.display = name.includes(q) ? 'flex' : 'none';
      });
    });
  }

  // Modal controls
  if (openNewChatBtn) {
    openNewChatBtn.addEventListener('click', () => {
      if (newChatModal) newChatModal.classList.add('open');
    });
  }
  if (closeNewChatBtn) {
    closeNewChatBtn.addEventListener('click', () => {
      if (newChatModal) newChatModal.classList.remove('open');
    });
  }

  // Submit message via AJAX
  if (sendForm && activeUserId) {
    // Submit on Enter (unless Shift+Enter)
    if (messageInput) {
      messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendForm.requestSubmit();
        }
      });
    }

    sendForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const body = messageInput.value.trim();
      if (!body) return;

      messageInput.value = '';
      const sendBtn = document.getElementById('sendBtn');
      if (sendBtn) sendBtn.disabled = true;

      try {
        const csrf = await window.InfinityBlog.ensureCsrfToken();
        const res = await fetch('/api/messages', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-Token': csrf
          },
          body: JSON.stringify({
            recipient_id: activeUserId,
            body: body
          })
        });

        const data = await res.json();
        if (res.ok && data.data) {
          appendMessageBubble(data.data, true);
          scrollToBottom();
        } else {
          window.InfinityBlog.showAlert(data.error?.message || 'Could not send message.', 'error');
        }
      } catch (err) {
        window.InfinityBlog.showAlert('Network error while sending message.', 'error');
      } finally {
        if (sendBtn) sendBtn.disabled = false;
        if (messageInput) messageInput.focus();
      }
    });
  }

  function appendMessageBubble(msg, isSent) {
    if (!chatStream) return;
    const bubble = document.createElement('div');
    bubble.className = `chat-bubble ${isSent ? 'sent' : 'received'}`;
    bubble.setAttribute('data-msg-id', msg.id);

    const safeBody = document.createElement('div');
    safeBody.textContent = msg.body;

    const timeSpan = document.createElement('span');
    timeSpan.className = 'chat-timestamp';
    const dateObj = new Date(msg.created_at);
    timeSpan.textContent = dateObj.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

    bubble.appendChild(safeBody);
    bubble.appendChild(timeSpan);
    chatStream.appendChild(bubble);
  }

  // Polling for new incoming messages every 5 seconds
  if (activeUserId) {
    setInterval(async () => {
      try {
        const res = await fetch(`/api/messages/${activeUserId}`, {
          headers: { 'Accept': 'application/json' }
        });
        if (res.ok) {
          const data = await res.json();
          const messages = data.data || [];
          let hasNew = false;
          messages.forEach(msg => {
            const exists = chatStream.querySelector(`[data-msg-id="${msg.id}"]`);
            if (!exists) {
              const isSent = parseInt(msg.sender_id, 10) !== parseInt(activeUserId, 10);
              appendMessageBubble(msg, isSent);
              hasNew = true;
            }
          });
          if (hasNew) scrollToBottom();
        }
      } catch (e) {}
    }, 5000);
  }
});
</script>
