/**
 * Infinity Blog - Urgent Fixes Browser & E2E Verification
 * Verifies:
 * 1. Bug 1: One-to-one direct message sending, DB persistence, dynamic left sidebar list updates,
 *    recipient receipt and reply, and persistence across page refreshes.
 * 2. Bug 2: Complete server-side exclusion of authenticated user and clones/duplicates from "People You Know".
 */

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_urgent_test_' + Date.now());
const PORT = 9232;
const ARTIFACT_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function createTestSession(userId) {
  const sessionId = require('crypto').randomBytes(16).toString('hex');
  const time = Math.floor(Date.now() / 1000);
  const csrf = require('crypto').randomBytes(32).toString('hex');
  const sessData = `auth|a:4:{s:7:"user_id";i:${userId};s:12:"auth_version";i:1;s:9:"issued_at";i:${time};s:13:"last_activity";i:${time};}csrf_token|s:64:"${csrf}";`;
  const sessPath = path.join('C:\\xampp\\tmp', 'sess_' + sessionId);
  fs.writeFileSync(sessPath, sessData);
  return sessionId;
}

let passed = 0;
let failed = 0;
function assert(cond, msg) {
  if (cond) {
    passed++;
    console.log(`  [PASS] ${msg}`);
  } else {
    failed++;
    console.error(`  [FAIL] ${msg}`);
  }
}

async function run() {
  console.log('=== STARTING URGENT FIXES VERIFICATION SUITE ===\n');

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
  await send('Network.enable');
  await send('DOM.enable');
  await send('Runtime.enable');
  await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 850, deviceScaleFactor: 1, mobile: false });

  const saveScreenshot = async (filename, description) => {
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    const fullPath = path.join(ARTIFACT_DIR, filename);
    fs.writeFileSync(fullPath, Buffer.from(shot.data, 'base64'));
    console.log(`  [SCREENSHOT] Saved: ${filename} (${description})`);
  };

  // -------------------------------------------------------------
  // PART 1: BUG 1 - One-to-One Messaging Sending & Dynamic UI Update
  // -------------------------------------------------------------
  console.log('--- Step 1: User 13 (Cristine) sends "tyt" to User 2 (Shubham Banerjee) ---');
  const cristineSess = createTestSession(13);
  await send('Network.setCookie', {
    url: 'http://localhost:8086',
    name: 'INFINITYBLOGSESSID',
    value: cristineSess,
    domain: 'localhost',
    path: '/'
  });

  await send('Page.navigate', { url: 'http://localhost:8086/messages/2' });
  await new Promise(r => setTimeout(r, 2000));

  const initialCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      return {
        header: document.querySelector('.chat-header h2')?.innerText.trim(),
        hasInput: !!document.getElementById('messageInput'),
        hasSendBtn: !!document.getElementById('sendBtn'),
        convItems: Array.from(document.querySelectorAll('#conversationsList .conversation-item')).map(a => a.innerText.trim())
      };
    })()`,
    returnByValue: true
  });
  console.log('  Initial state on /messages/2:', initialCheck.result.value);
  assert(initialCheck.result.value.header === 'Shubham Banerjee', 'Header displays recipient Shubham Banerjee');
  assert(initialCheck.result.value.hasInput && initialCheck.result.value.hasSendBtn, 'Message composer and Send button are present');

  // Send unique test message
  const testMsgBody = 'tyt - browser test verification ' + Date.now();
  console.log(`  Submitting message: "${testMsgBody}"...`);
  const sendAction = await send('Runtime.evaluate', {
    expression: `(async () => {
      const input = document.getElementById('messageInput');
      const form = document.getElementById('sendMessageForm');
      input.value = ${JSON.stringify(testMsgBody)};
      form.requestSubmit();
      return { submitted: true };
    })()`,
    awaitPromise: true,
    returnByValue: true
  });

  // Wait for network response and UI DOM updates
  await new Promise(r => setTimeout(r, 2500));

  const afterSendCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const bubbles = Array.from(document.querySelectorAll('.chat-bubble.sent')).map(b => b.innerText.trim());
      const hasBubble = bubbles.some(b => b.includes(${JSON.stringify(testMsgBody)}));
      const emptyPlaceholder = Array.from(document.querySelectorAll('#conversationsList li')).some(li => li.innerText.includes('No conversations yet'));
      const activeConv = document.querySelector('#conversationsList .conversation-item.active');
      const activeName = activeConv?.querySelector('.conversation-name')?.innerText.trim();
      const activePreview = activeConv?.querySelector('.conversation-preview')?.innerText.trim();
      const activeTime = activeConv?.querySelector('.conversation-time')?.innerText.trim();
      const firstLiUserId = document.querySelector('#conversationsList li a')?.getAttribute('data-user-id');
      const inputValue = document.getElementById('messageInput')?.value;

      return { hasBubble, emptyPlaceholder, activeName, activePreview, activeTime, firstLiUserId, inputValue };
    })()`,
    returnByValue: true
  });
  console.log('  After sending state:', afterSendCheck.result.value);

  assert(afterSendCheck.result.value.hasBubble, 'Sent message bubble is rendered in chat stream immediately');
  assert(!afterSendCheck.result.value.emptyPlaceholder, '"No conversations yet" placeholder was dynamically removed from sidebar');
  assert(afterSendCheck.result.value.activeName === 'Shubham Banerjee', 'Conversation item for Shubham Banerjee exists and is active');
  assert(afterSendCheck.result.value.firstLiUserId === '2', 'Shubham Banerjee is positioned at the top of the conversations list');
  assert(afterSendCheck.result.value.activePreview.includes('tyt'), 'Sidebar preview displays the latest message snippet');
  assert(afterSendCheck.result.value.inputValue === '', 'Composer textarea is cleared after sending');

  await saveScreenshot('chat_direct_message_sent_live.png', 'Cristine sends message to Shubham with dynamic conversation list update');

  // Verify persistence on full page reload
  console.log('\n--- Step 2: Refreshing /messages/2 to verify database persistence ---');
  await send('Page.reload');
  await new Promise(r => setTimeout(r, 2000));

  const reloadCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const bubbles = Array.from(document.querySelectorAll('.chat-bubble.sent')).map(b => b.innerText.trim());
      const hasBubble = bubbles.some(b => b.includes(${JSON.stringify(testMsgBody)}));
      const activeConv = document.querySelector('#conversationsList .conversation-item.active');
      const activeName = activeConv?.querySelector('.conversation-name')?.innerText.trim();
      return { hasBubble, activeName };
    })()`,
    returnByValue: true
  });
  assert(reloadCheck.result.value.hasBubble, 'Message persists in thread after page reload (genuine DB row)');
  assert(reloadCheck.result.value.activeName === 'Shubham Banerjee', 'Conversation list persists on page reload');

  // -------------------------------------------------------------
  // PART 2: Recipient Account Interaction (Shubham Banerjee logs in & replies)
  // -------------------------------------------------------------
  console.log('\n--- Step 3: Recipient User 2 (Shubham Banerjee) logs in to view and reply ---');
  const shubhamSess = createTestSession(2);
  await send('Network.setCookie', {
    url: 'http://localhost:8086',
    name: 'INFINITYBLOGSESSID',
    value: shubhamSess,
    domain: 'localhost',
    path: '/'
  });

  await send('Page.navigate', { url: 'http://localhost:8086/messages' });
  await new Promise(r => setTimeout(r, 2000));

  const shubhamInboxCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const convItems = Array.from(document.querySelectorAll('#conversationsList .conversation-item')).map(a => ({
        name: a.querySelector('.conversation-name')?.innerText.trim(),
        preview: a.querySelector('.conversation-preview')?.innerText.trim(),
        userId: a.getAttribute('data-user-id')
      }));
      const hasCristine = convItems.some(c => c.name.toLowerCase().includes('cristine'));
      const activeHeader = document.querySelector('.chat-header h2')?.innerText.trim();
      return { convItems, hasCristine, activeHeader };
    })()`,
    returnByValue: true
  });
  console.log('  Shubham inbox state:', shubhamInboxCheck.result.value);
  assert(shubhamInboxCheck.result.value.hasCristine, 'Cristine appears in Shubham\'s conversation list with preview snippet');

  // Shubham opens thread with Cristine and sends a reply
  await send('Page.navigate', { url: 'http://localhost:8086/messages/13' });
  await new Promise(r => setTimeout(r, 2000));

  const replyMsg = 'Hello Cristine, reply confirmed! ' + Date.now();
  console.log(`  Shubham sending reply: "${replyMsg}"...`);
  await send('Runtime.evaluate', {
    expression: `(async () => {
      const input = document.getElementById('messageInput');
      const form = document.getElementById('sendMessageForm');
      input.value = ${JSON.stringify(replyMsg)};
      form.requestSubmit();
    })()`,
    awaitPromise: true
  });
  await new Promise(r => setTimeout(r, 2500));

  const shubhamReplyCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const bubbles = Array.from(document.querySelectorAll('.chat-bubble.sent')).map(b => b.innerText.trim());
      const hasReply = bubbles.some(b => b.includes(${JSON.stringify(replyMsg)}));
      const activeConv = document.querySelector('#conversationsList .conversation-item.active');
      return { hasReply, activeName: activeConv?.querySelector('.conversation-name')?.innerText.trim() };
    })()`,
    returnByValue: true
  });
  assert(shubhamReplyCheck.result.value.hasReply, 'Shubham successfully sent reply to Cristine');
  await saveScreenshot('chat_direct_message_reply_live.png', 'Shubham views thread from Cristine and replies');

  // -------------------------------------------------------------
  // PART 3: BUG 2 - Complete Exclusion of Authenticated User from "People You Know"
  // -------------------------------------------------------------
  console.log('\n--- Step 4: Verify "People You Know" Self-Exclusion for Cristine (User 13) ---');
  await send('Network.setCookie', {
    url: 'http://localhost:8086',
    name: 'INFINITYBLOGSESSID',
    value: cristineSess,
    domain: 'localhost',
    path: '/'
  });

  await send('Page.navigate', { url: 'http://localhost:8086/' });
  await new Promise(r => setTimeout(r, 2500));

  const cristineHomeCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const rows = Array.from(document.querySelectorAll('.sidebar-author-row')).map(r => ({
        authorId: r.getAttribute('data-author-id'),
        name: r.querySelector('.sidebar-row-title')?.innerText.trim(),
        handle: r.querySelector('.sidebar-row-sub')?.innerText.trim(),
        avatarSrc: r.querySelector('.sidebar-avatar-img')?.src || null
      }));
      return rows;
    })()`,
    returnByValue: true
  });
  console.log('  Cristine homepage "People You Know" suggestions:', cristineHomeCheck.result.value);

  const containsCristine = cristineHomeCheck.result.value.some(r =>
    r.authorId === '13' ||
    r.authorId === '9' ||
    r.name.toLowerCase().includes('cristine') ||
    r.handle.toLowerCase().includes('cristine') ||
    (r.avatarSrc && r.avatarSrc.includes('u13_20261012'))
  );
  assert(!containsCristine, 'Cristine Palmer is 100% EXCLUDED from "People You Know" for User 13');

  // Check no duplicates among suggestions
  const authorNames = cristineHomeCheck.result.value.map(r => r.name.toLowerCase());
  const hasDupNames = new Set(authorNames).size !== authorNames.length;
  assert(!hasDupNames, 'No duplicate author recommendations in "People You Know"');

  await saveScreenshot('people_you_know_cristine_live.png', 'Homepage "People You Know" strictly excluding Cristine');

  console.log('\n--- Step 5: Verify "People You Know" Self-Exclusion for Shubham Banerjee (User 35) ---');
  const shubham35Sess = createTestSession(35);
  await send('Network.setCookie', {
    url: 'http://localhost:8086',
    name: 'INFINITYBLOGSESSID',
    value: shubham35Sess,
    domain: 'localhost',
    path: '/'
  });

  await send('Page.navigate', { url: 'http://localhost:8086/' });
  await new Promise(r => setTimeout(r, 2500));

  const shubhamHomeCheck = await send('Runtime.evaluate', {
    expression: `(() => {
      const rows = Array.from(document.querySelectorAll('.sidebar-author-row')).map(r => ({
        authorId: r.getAttribute('data-author-id'),
        name: r.querySelector('.sidebar-row-title')?.innerText.trim(),
        handle: r.querySelector('.sidebar-row-sub')?.innerText.trim(),
        avatarSrc: r.querySelector('.sidebar-avatar-img')?.src || null
      }));
      return rows;
    })()`,
    returnByValue: true
  });
  console.log('  Shubham (User 35) homepage "People You Know" suggestions:', shubhamHomeCheck.result.value);

  const containsShubham = shubhamHomeCheck.result.value.some(r =>
    r.authorId === '35' ||
    r.authorId === '43' ||
    r.name.toLowerCase().includes('shubham banerjee') ||
    (r.avatarSrc && r.avatarSrc.includes('u43_20261019'))
  );
  assert(!containsShubham, 'Shubham Banerjee is 100% EXCLUDED from "People You Know" for User 35');

  await saveScreenshot('people_you_know_shubham_live.png', 'Homepage "People You Know" strictly excluding Shubham');

  // -------------------------------------------------------------
  // SUMMARY
  // -------------------------------------------------------------
  console.log('\n=================================================');
  console.log(`TOTAL CHECKS: ${passed + failed} | PASSED: ${passed} | FAILED: ${failed}`);
  console.log('=================================================\n');

  ws.close();
  edge.kill();

  if (failed > 0) {
    process.exit(1);
  }
}

run().catch(err => {
  console.error('Test execution failed:', err);
  process.exit(1);
});
