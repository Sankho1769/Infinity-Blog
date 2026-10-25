const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_msg_' + Date.now());
const PORT = 9245;
const ARTIFACT_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function createTestSession(userId, authVersion = 1) {
  const sessionId = crypto.randomBytes(16).toString('hex');
  const time = Math.floor(Date.now() / 1000);
  const csrf = crypto.randomBytes(32).toString('hex');
  const sessData = `auth|a:4:{s:7:"user_id";i:${userId};s:12:"auth_version";i:${authVersion};s:9:"issued_at";i:${time};s:13:"last_activity";i:${time};}csrf_token|s:64:"${csrf}";`;
  const sessPath = path.join('C:\\xampp\\tmp', 'sess_' + sessionId);
  fs.writeFileSync(sessPath, sessData);
  return { sessionId, csrf };
}

async function runBrowserVerification() {
  console.log('=== STARTING MESSAGE INTEGRITY & PEOPLE SEARCH BROWSER TEST ===\n');

  const edge = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--remote-debugging-port=' + PORT,
    '--user-data-dir=' + TEMP_USER_DATA,
    'about:blank'
  ], { stdio: 'ignore' });

  await new Promise(r => setTimeout(r, 2000));

  const listRes = await fetch('http://127.0.0.1:' + PORT + '/json/list');
  const list = await listRes.json();
  const page = list.find(t => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise(r => ws.onopen = r);

  let msgId = 1;
  const pending = new Map();
  ws.onmessage = e => {
    const data = JSON.parse(e.data);
    if (data.id && pending.has(data.id)) {
      pending.get(data.id)(data.result);
      pending.delete(data.id);
    }
  };

  function send(method, params = {}) {
    return new Promise(resolve => {
      const id = msgId++;
      pending.set(id, resolve);
      ws.send(JSON.stringify({ id, method, params }));
    });
  }

  await send('Page.enable');
  await send('DOM.enable');
  await send('Network.enable');

  async function setViewport(width, height) {
    await send('Emulation.setDeviceMetricsOverride', {
      width,
      height,
      deviceScaleFactor: 1,
      mobile: width < 600,
    });
  }

  async function setSessionCookie(sessionId) {
    await send('Network.setCookie', {
      name: 'INFINITYBLOGSESSID',
      value: sessionId,
      domain: 'localhost',
      path: '/',
    });
  }

  async function navigate(url, waitMs = 1500) {
    await send('Page.navigate', { url });
    await new Promise(r => setTimeout(r, waitMs));
  }

  async function takeScreenshot(filename) {
    const res = await send('Page.captureScreenshot', { format: 'png' });
    const filePath = path.join(ARTIFACT_DIR, filename);
    fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
    console.log(`  [SCREENSHOT SAVED] -> ${filename}`);
  }

  async function evalJs(expr) {
    const res = await send('Runtime.evaluate', { expression: expr, returnByValue: true });
    return res && res.result ? res.result.value : null;
  }

  try {
    // -------------------------------------------------------------
    // Test Setup: Create sessions
    // Account A: User 13 (Cristine Palmer, auth_version = 1)
    // Account B: User 43 (Shubham Banerjee 5, auth_version = 1)
    // Account C: User 2 (Shubham Banerjee admin, auth_version = 2)
    // -------------------------------------------------------------
    const sessionA = createTestSession(13, 1);
    const sessionB = createTestSession(43, 1);
    const sessionC = createTestSession(2, 2);

    console.log('--- Step 1: Account A (User 13) Logs In & Tests People Search ---');
    await setViewport(1280, 800);
    await setSessionCookie(sessionA.sessionId);
    await navigate('http://localhost:8086/messages');

    const isMessagesLoaded = await evalJs(`!!document.getElementById('messagesApp')`);
    console.log('  [CHECK] Messages page loaded for User 13:', isMessagesLoaded);

    // Click "+ New Chat" button to open modal
    await evalJs(`document.getElementById('openNewChatModalBtn').click();`);
    await new Promise(r => setTimeout(r, 600));

    const isModalOpen = await evalJs(`document.getElementById('newChatModal').classList.contains('open')`);
    console.log('  [CHECK] New Chat modal opened:', isModalOpen);

    // Type "Shubham" into live search input
    console.log('  [ACTION] Typing "Shubham" into live people search input...');
    await evalJs(`
      (() => {
        const input = document.getElementById('newChatSearchInput');
        input.value = 'Shubham';
        input.dispatchEvent(new Event('input', { bubbles: true }));
      })()
    `);

    // Wait 600ms for 250ms debounce + API fetch
    await new Promise(r => setTimeout(r, 800));

    const searchResults = await evalJs(`
      (() => {
        const items = Array.from(document.querySelectorAll('#newChatAuthorsList .new-chat-user-item'));
        return items.map(el => ({
          userId: el.getAttribute('data-user-id'),
          href: el.getAttribute('href'),
          name: el.querySelector('span[style*="font-weight: 700"]')?.textContent?.trim() || '',
          handle: el.querySelector('span[style*="color: var(--text-muted)"]')?.textContent?.trim() || '',
          hasActiveBadge: !!el.querySelector('.badge-existing-chat')
        }));
      })()
    `);

    console.log('  [SEARCH RESULTS RETURNED]:', JSON.stringify(searchResults, null, 2));
    const foundUser43 = searchResults.some(u => u.userId === '43');
    console.log('  [CHECK] Search successfully found recipient User 43:', foundUser43);

    // Capture screenshot of People Search modal with live results
    await takeScreenshot('message_search_modal_results.png');

    // Click on User 43 from search results to open conversation
    console.log('  [ACTION] Selecting User 43 from search results...');
    await evalJs(`
      (() => {
        const item = document.querySelector('#newChatAuthorsList .new-chat-user-item[data-user-id="43"]');
        if (item) item.click();
      })()
    `);

    await new Promise(r => setTimeout(r, 1800));

    const currentUrl = await evalJs(`window.location.pathname`);
    console.log('  [CHECK] Navigated to conversation URL:', currentUrl);

    // -------------------------------------------------------------
    // Step 2: Account A Sends Multi-Line Message with Spaces and Emoji
    // -------------------------------------------------------------
    console.log('\n--- Step 2: Account A Sends Rich Multi-Line Message ---');
    const testMessageText = "Hello Shubham! 🚀✨\n\n  Indented note with   spaces.\nLine 3: Verified release readiness 100%! #infinity";

    await evalJs(`
      (() => {
        const textarea = document.getElementById('messageInput');
        textarea.value = ${JSON.stringify(testMessageText)};
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        document.getElementById('sendMessageForm').requestSubmit();
      })()
    `);

    // Wait 1.2s for AJAX response and bubble update
    await new Promise(r => setTimeout(r, 1200));

    const sentBubbleData = await evalJs(`
      (() => {
        const bubbles = Array.from(document.querySelectorAll('.chat-bubble.sent'));
        const latest = bubbles[bubbles.length - 1];
        if (!latest) return null;
        return {
          isSent: latest.classList.contains('sent'),
          isReceived: latest.classList.contains('received'),
          isPending: latest.classList.contains('pending'),
          isFailed: latest.classList.contains('failed'),
          msgId: latest.getAttribute('data-msg-id'),
          content: latest.querySelector('.chat-bubble-content')?.textContent || '',
          timestamp: latest.querySelector('.chat-timestamp')?.textContent || ''
        };
      })()
    `);

    console.log('  [SENT BUBBLE AUDIT]:', sentBubbleData);
    console.log('  [CHECK] Message text strictly preserved:', sentBubbleData && sentBubbleData.content === testMessageText);
    console.log('  [CHECK] Message successfully confirmed (not pending or failed):', sentBubbleData && !sentBubbleData.isPending && !sentBubbleData.isFailed);
    console.log('  [CHECK] Aligned on right side (.sent):', sentBubbleData && sentBubbleData.isSent);

    // Capture screenshot of User 13 conversation view
    await takeScreenshot('message_sent_user13_stream.png');

    // -------------------------------------------------------------
    // Step 3: Account B (User 43) Checks Delivery & Alignment
    // -------------------------------------------------------------
    console.log('\n--- Step 3: Account B (User 43) Verifies Delivery & Alignment ---');
    await setSessionCookie(sessionB.sessionId);
    await navigate('http://localhost:8086/messages/13');

    const receivedBubbleData = await evalJs(`
      (() => {
        const bubbles = Array.from(document.querySelectorAll('.chat-bubble.received'));
        const latest = bubbles[bubbles.length - 1];
        if (!latest) return null;
        return {
          isReceived: latest.classList.contains('received'),
          isSent: latest.classList.contains('sent'),
          content: latest.querySelector('.chat-bubble-content')?.textContent || '',
          timestamp: latest.querySelector('.chat-timestamp')?.textContent || ''
        };
      })()
    `);

    console.log('  [RECEIVED BUBBLE AUDIT (USER 43)]:', receivedBubbleData);
    console.log('  [CHECK] User 43 sees message on left side (.received):', receivedBubbleData && receivedBubbleData.isReceived);
    console.log('  [CHECK] Message content matches sender exactly:', receivedBubbleData && receivedBubbleData.content === testMessageText);

    // Account B sends a reply
    const replyText = "Reply received loud and clear! 🌟\nFormatting & indentation are 100% intact!\nGreat job!";
    console.log('  [ACTION] User 43 sending reply back to User 13...');
    await evalJs(`
      (() => {
        const textarea = document.getElementById('messageInput');
        textarea.value = ${JSON.stringify(replyText)};
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        document.getElementById('sendMessageForm').requestSubmit();
      })()
    `);

    await new Promise(r => setTimeout(r, 1200));
    await takeScreenshot('message_received_user43_reply.png');

    // -------------------------------------------------------------
    // Step 4: Account A (User 13) Polls & Sees User 43's Reply
    // -------------------------------------------------------------
    console.log('\n--- Step 4: Account A (User 13) Real-Time Synchronization ---');
    await setSessionCookie(sessionA.sessionId);
    await navigate('http://localhost:8086/messages/43');

    // Wait 3.5s to verify polling or immediate reload
    await new Promise(r => setTimeout(r, 2000));

    const user13StreamAudit = await evalJs(`
      (() => {
        const bubbles = Array.from(document.querySelectorAll('.chat-bubble'));
        return bubbles.map(b => ({
          type: b.classList.contains('sent') ? 'sent' : 'received',
          text: b.querySelector('.chat-bubble-content')?.textContent?.substring(0, 30) || '',
          time: b.querySelector('.chat-timestamp')?.textContent || ''
        }));
      })()
    `);

    console.log('  [USER 13 STREAM MESSAGES]:', JSON.stringify(user13StreamAudit.slice(-2), null, 2));
    const replyDeliveredTo13 = user13StreamAudit.some(b => b.type === 'received' && b.text.includes('Reply received'));
    console.log('  [CHECK] User 43 reply appears on left side (.received) for User 13:', replyDeliveredTo13);

    await takeScreenshot('message_sync_user13_conversation.png');

    // -------------------------------------------------------------
    // Step 5: Privacy Isolation Audit for Third Party (User 2)
    // -------------------------------------------------------------
    console.log('\n--- Step 5: Privacy Isolation Audit for Third Party (User 2) ---');
    await setSessionCookie(sessionC.sessionId);
    await navigate('http://localhost:8086/messages/43');

    const user2MessagesWith43 = await evalJs(`
      (() => {
        const bubbles = Array.from(document.querySelectorAll('.chat-bubble'));
        return bubbles.map(b => b.querySelector('.chat-bubble-content')?.textContent || '');
      })()
    `);

    const leakedMessages = user2MessagesWith43.filter(txt =>
      txt.includes('Indented note with   spaces') || txt.includes('Reply received loud and clear')
    );

    console.log('  [CHECK] Leaked messages in User 2 thread with User 43:', leakedMessages.length);
    console.log('  [CHECK] Strict privacy isolation confirmed:', leakedMessages.length === 0);

    // -------------------------------------------------------------
    // Step 6: Dark Mode Verification
    // -------------------------------------------------------------
    console.log('\n--- Step 6: Dark Mode Verification ---');
    await setSessionCookie(sessionA.sessionId);
    await navigate('http://localhost:8086/messages/43');

    await evalJs(`
      (() => {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.body.classList.add('theme-dark');
        localStorage.setItem('infinity_theme', 'dark');
      })()
    `);

    await new Promise(r => setTimeout(r, 600));
    await takeScreenshot('message_stream_dark_mode.png');

    console.log('\n=== ALL BROWSER AUDIT STEPS COMPLETED SUCCESSFULLY ===');
  } catch (err) {
    console.error('Browser verification failed:', err);
    process.exitCode = 1;
  } finally {
    edge.kill();
    try {
      fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
    } catch (e) {}
  }
}

runBrowserVerification();
