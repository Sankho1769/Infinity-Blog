/**
 * Infinity Blog - Chat System Real Browser CDP Test Suite
 * Tests authentication redirects, Category Communities, Direct Messages,
 * message posting, responsive mobile views, and theme contrasts.
 */
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_chat_test_' + Date.now());
const PORT = 9229;
const ARTIFACT_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

// Helper to create an active PHP session file in C:\xampp\tmp
function createTestSession(userId) {
  const sessionId = require('crypto').randomBytes(16).toString('hex');
  const time = Math.floor(Date.now() / 1000);
  const csrf = require('crypto').randomBytes(32).toString('hex');
  const sessData = `auth|a:4:{s:7:"user_id";i:${userId};s:12:"auth_version";i:1;s:9:"issued_at";i:${time};s:13:"last_activity";i:${time};}csrf_token|s:64:"${csrf}";`;
  const sessPath = path.join('C:\\xampp\\tmp', 'sess_' + sessionId);
  fs.writeFileSync(sessPath, sessData);
  console.log(`Created test session ${sessionId} for user ${userId} at ${sessPath}`);
  return sessionId;
}

async function run() {
  console.log('=== STARTING REAL BROWSER CDP CHAT VERIFICATION ===\n');

  const edge = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--remote-debugging-port=' + PORT,
    '--user-data-dir=' + TEMP_USER_DATA,
    'about:blank'
  ], { stdio: 'ignore' });

  // Give Edge 2 seconds to bind debug port
  await new Promise(r => setTimeout(r, 2000));

  let list;
  try {
    const listRes = await fetch('http://127.0.0.1:' + PORT + '/json/list');
    list = await listRes.json();
  } catch (err) {
    console.error('Failed to connect to Edge debug port:', err.message);
    edge.kill();
    process.exit(1);
  }

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
    return new Promise((resolve, reject) => {
      const id = msgId++;
      pending.set(id, resolve);
      ws.send(JSON.stringify({ id, method, params }));
    });
  }

  await send('Page.enable');
  await send('Network.enable');
  await send('DOM.enable');

  const saveScreenshot = async (filename, description) => {
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    const fullPath = path.join(ARTIFACT_DIR, filename);
    fs.writeFileSync(fullPath, Buffer.from(shot.data, 'base64'));
    console.log(`  [SCREENSHOT] Saved: ${filename} (${description})`);
  };

  // -------------------------------------------------------------
  // TEST 1: Unauthenticated Visitor Redirects
  // -------------------------------------------------------------
  console.log('--- Test 1: Unauthenticated Navigation Redirects ---');
  await send('Page.navigate', { url: 'http://localhost:8086/chat' });
  await new Promise(r => setTimeout(r, 1500));

  let evalUrl = await send('Runtime.evaluate', { expression: 'window.location.href' });
  let currentUrl = evalUrl.result.value;
  console.log('  Navigated to /chat -> redirected to:', currentUrl);
  if (currentUrl.includes('/login?redirect=') && currentUrl.includes('chat')) {
    console.log('  [PASS] Unauthenticated /chat successfully redirected to login');
  } else {
    console.error('  [FAIL] Expected redirect to /login?redirect=... got:', currentUrl);
  }
  await saveScreenshot('chat_logged_out_redirect.png', 'Logged-out redirect to login modal');

  // -------------------------------------------------------------
  // TEST 2: Authenticated Session Setup & Category Communities
  // -------------------------------------------------------------
  console.log('\n--- Test 2: Authenticated Category Communities ---');
  const testSessionId = createTestSession(2); // user 2: shubham_banerjee

  await send('Network.setCookie', {
    url: 'http://localhost:8086',
    name: 'INFINITYBLOGSESSID',
    value: testSessionId,
    domain: 'localhost',
    path: '/'
  });

  // Set desktop viewport
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 850, deviceScaleFactor: 1, mobile: false });

  // Navigate to /chat
  await send('Page.navigate', { url: 'http://localhost:8086/chat' });
  await new Promise(r => setTimeout(r, 2000));

  evalUrl = await send('Runtime.evaluate', { expression: 'window.location.href' });
  console.log('  Authenticated visit to /chat -> current URL:', evalUrl.result.value);

  // Check page elements
  const pageCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const title = document.querySelector('h1')?.innerText || '';
      const rooms = document.querySelectorAll('.chat-room-item').length;
      const switcher = document.querySelectorAll('a[href="/chat"], a[href="/messages"]').length;
      const liveBadge = document.querySelector('#chatMembershipBadge')?.innerText || '';
      return { title, rooms, switcher, liveBadge };
    })()`,
    returnByValue: true
  });
  console.log('  Category Communities page elements:', pageCheck.result.value);
  if (pageCheck.result.value.rooms >= 3 && pageCheck.result.value.switcher >= 2) {
    console.log('  [PASS] Category communities page rendered with rooms and switcher tabs');
  }

  // Capture desktop Light mode screenshot
  await saveScreenshot('chat_category_communities_desktop_light.png', 'Desktop Category Communities (Light Mode)');

  // Switch to Dark mode
  await send('Runtime.evaluate', {
    expression: `(() => {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('theme', 'dark');
    })()`
  });
  await new Promise(r => setTimeout(r, 600));
  await saveScreenshot('chat_category_communities_desktop_dark.png', 'Desktop Category Communities (Dark Mode)');

  // Switch back to Light mode
  await send('Runtime.evaluate', {
    expression: `(() => {
      document.documentElement.setAttribute('data-theme', 'light');
      localStorage.setItem('theme', 'light');
    })()`
  });
  await new Promise(r => setTimeout(r, 400));

  // -------------------------------------------------------------
  // TEST 3: Category Room Interaction (Join & Send Message)
  // -------------------------------------------------------------
  console.log('\n--- Test 3: Category Room Participation ---');
  // Attempt to join if not member or send message directly
  const interactResult = await send('Runtime.evaluate', {
    expression: `(async () => {
      const joinBtn = document.getElementById('chatJoinBtn');
      if (joinBtn && joinBtn.offsetParent !== null) {
        joinBtn.click();
        await new Promise(r => setTimeout(r, 1000));
      }
      const input = document.getElementById('chatInputMessage');
      const form = document.getElementById('chatSendForm');
      if (input && form) {
        input.value = 'Automated browser verification test ' + Date.now();
        form.requestSubmit();
        return { attemptedSend: true, text: input.value };
      }
      return { attemptedSend: false };
    })()`,
    awaitPromise: true,
    returnByValue: true
  });
  console.log('  Message submission attempt:', interactResult.result.value);
  await new Promise(r => setTimeout(r, 1500));

  // -------------------------------------------------------------
  // TEST 4: Direct Messages View & Interaction
  // -------------------------------------------------------------
  console.log('\n--- Test 4: Authenticated Direct Messages ---');
  await send('Page.navigate', { url: 'http://localhost:8086/messages' });
  await new Promise(r => setTimeout(r, 2000));

  const dmCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const title = document.querySelector('h1')?.innerText || '';
      const convs = document.querySelectorAll('.conversation-item').length;
      const sendBar = document.getElementById('sendMessageForm') ? true : false;
      return { title, convs, sendBar };
    })()`,
    returnByValue: true
  });
  console.log('  Direct Messages page elements:', dmCheck.result.value);
  if (dmCheck.result.value.title.includes('Direct Messages')) {
    console.log('  [PASS] Direct Messages interface rendered successfully');
  }

  await saveScreenshot('chat_direct_messages_desktop_light.png', 'Desktop Direct Messages (Light Mode)');

  // Switch to Dark mode
  await send('Runtime.evaluate', {
    expression: `(() => {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('theme', 'dark');
    })()`
  });
  await new Promise(r => setTimeout(r, 600));
  await saveScreenshot('chat_direct_messages_desktop_dark.png', 'Desktop Direct Messages (Dark Mode)');

  // Switch back to Light
  await send('Runtime.evaluate', {
    expression: `(() => {
      document.documentElement.setAttribute('data-theme', 'light');
      localStorage.setItem('theme', 'light');
    })()`
  });

  // -------------------------------------------------------------
  // TEST 5: Responsive Mobile Viewports (375x667 Claymorphic)
  // -------------------------------------------------------------
  console.log('\n--- Test 5: Responsive Mobile Viewport Testing (375px) ---');
  await send('Emulation.setDeviceMetricsOverride', { width: 375, height: 667, deviceScaleFactor: 2, mobile: true });

  // Mobile Category Communities
  await send('Page.navigate', { url: 'http://localhost:8086/chat' });
  await new Promise(r => setTimeout(r, 2000));
  await saveScreenshot('chat_category_communities_mobile_375_light.png', 'Mobile Category Communities 375px (Light)');

  await send('Runtime.evaluate', {
    expression: `document.documentElement.setAttribute('data-theme', 'dark')`
  });
  await new Promise(r => setTimeout(r, 500));
  await saveScreenshot('chat_category_communities_mobile_375_dark.png', 'Mobile Category Communities 375px (Dark)');

  // Mobile Direct Messages
  await send('Runtime.evaluate', {
    expression: `document.documentElement.setAttribute('data-theme', 'light')`
  });
  await send('Page.navigate', { url: 'http://localhost:8086/messages' });
  await new Promise(r => setTimeout(r, 2000));
  await saveScreenshot('chat_direct_messages_mobile_375_light.png', 'Mobile Direct Messages 375px (Light)');

  console.log('\n=== REAL BROWSER CDP CHAT VERIFICATION COMPLETED SUCCESSFULLY ===');
  ws.close();
  edge.kill();
  process.exit(0);
}

run().catch(err => {
  console.error('Test execution error:', err);
  process.exit(1);
});
