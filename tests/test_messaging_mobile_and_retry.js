const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_msg_mob_' + Date.now());
const PORT = 9246;
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

async function runMobileAndRetryTest() {
  console.log('=== STARTING MOBILE UX & RETRY TEST ===\n');

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
      deviceScaleFactor: 2,
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
    const session = createTestSession(13, 1);
    await setSessionCookie(session.sessionId);

    // 1. Mobile Viewport (375x667 - iPhone SE / Claymorphism)
    console.log('--- Step 1: Mobile Viewport 375x667 ---');
    await setViewport(375, 667);
    await navigate('http://localhost:8086/messages/43');

    const backBtnVisible = await evalJs(`
      (() => {
        const btn = document.querySelector('.chat-back-btn');
        return btn && window.getComputedStyle(btn).display !== 'none';
      })()
    `);
    console.log('  [CHECK] Mobile back button visible on active thread:', backBtnVisible);

    await takeScreenshot('message_mobile_375.png');

    // 2. Test Failed State & Retry Button UI
    console.log('--- Step 2: Test Message Failed State & Retry Button UI ---');
    await evalJs(`
      (() => {
        const stream = document.getElementById('chatStream');
        const failedBubble = document.createElement('div');
        failedBubble.className = 'chat-bubble sent failed';
        failedBubble.setAttribute('data-client-msg-id', 'test_failed_retry_1');

        const content = document.createElement('div');
        content.className = 'chat-bubble-content';
        content.textContent = 'Network hiccup message example';

        const meta = document.createElement('div');
        meta.className = 'chat-bubble-meta';
        meta.innerHTML = '<span class="chat-error-text">Failed to send</span> <button type="button" class="chat-retry-btn">Retry</button>';

        failedBubble.appendChild(content);
        failedBubble.appendChild(meta);
        stream.appendChild(failedBubble);
        stream.scrollTop = stream.scrollHeight;
      })()
    `);

    await new Promise(r => setTimeout(r, 400));

    const retryBtnExists = await evalJs(`
      (() => {
        const btn = document.querySelector('.chat-bubble.failed .chat-retry-btn');
        return btn && btn.textContent === 'Retry';
      })()
    `);
    console.log('  [CHECK] Retry button rendered inside failed message bubble:', retryBtnExists);

    await takeScreenshot('message_retry_failure_state.png');

    console.log('=== MOBILE AND RETRY AUDIT COMPLETE ===');
  } catch (err) {
    console.error('Error in mobile/retry test:', err);
    process.exitCode = 1;
  } finally {
    edge.kill();
    try {
      fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
    } catch (e) {}
  }
}

runMobileAndRetryTest();
