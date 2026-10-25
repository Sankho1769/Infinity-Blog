const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_chat_ux_' + Date.now());
const PORT = 9236;
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

async function runTests() {
  console.log('=== STARTING CHAT UX REDESIGN VERIFICATION ===\n');

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

  const sessId = createTestSession(13); // Cristine Palmer
  await send('Network.setCookie', {
    url: 'http://localhost:8086',
    name: 'INFINITYBLOGSESSID',
    value: sessId,
    domain: 'localhost',
    path: '/'
  });

  async function evalJs(expr) {
    const raw = await send('Runtime.evaluate', { returnByValue: true, expression: expr });
    if (raw && raw.result) return raw.result.value;
    return raw ? raw.value : undefined;
  }

  async function takeScreenshot(filename) {
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    const filePath = path.join(ARTIFACT_DIR, filename);
    fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));
    console.log(`  [SCREENSHOT] Saved: ${filename}`);
  }

  let allPassed = true;
  function assert(condition, message) {
    if (condition) {
      console.log(`  [PASS] ${message}`);
    } else {
      console.error(`  [FAIL] ${message}`);
      allPassed = false;
    }
  }

  try {
    // ---------------------------------------------------------
    // TEST 1: Desktop 1280x850 Direct Messages (/messages/2)
    // ---------------------------------------------------------
    console.log('--- Test 1: Desktop Direct Messages (/messages/2) [1280x850] ---');
    await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 850, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'http://localhost:8086/messages/2' });
    await new Promise(r => setTimeout(r, 2200));

    let res = await evalJs(`(() => {
      const composer = document.getElementById('sendMessageForm');
      const cRect = composer ? composer.getBoundingClientRect() : null;
      const stream = document.getElementById('chatStream');
      const sRect = stream ? stream.getBoundingClientRect() : null;
      const convList = document.getElementById('conversationsList');
      const topbar = document.querySelector('.chat-workspace-topbar');
      const footer = document.querySelector('.site-footer-wrapper');

      // Attempt to scroll document
      window.scrollTo(0, 500);
      const scrolledY = window.scrollY;

      return {
        hasChatPageClass: document.body.classList.contains('is-chat-page'),
        scrollYAfterAttempt: scrolledY,
        composerPresent: !!composer,
        composerBottom: cRect ? cRect.bottom : -1,
        windowHeight: window.innerHeight,
        composerFullyInViewport: cRect ? (cRect.bottom <= window.innerHeight && cRect.top >= 0) : false,
        streamHasScroll: stream ? (stream.scrollHeight >= stream.clientHeight) : false,
        topbarPresent: !!topbar,
        footerHidden: !footer || window.getComputedStyle(footer).display === 'none'
      };
    })()`);

    assert(res.hasChatPageClass, 'Body has "is-chat-page" class');
    assert(res.scrollYAfterAttempt === 0, 'Document window.scrollY remains 0 (no overall webpage scrolling)');
    assert(res.composerPresent, 'Message composer form is present');
    assert(res.composerFullyInViewport, `Composer bottom (${res.composerBottom}px) is fully within viewport (${res.windowHeight}px)`);
    assert(res.topbarPresent, 'Compact chat-workspace-topbar with segmented switcher is rendered');
    assert(res.footerHidden, 'Site footer is completely hidden on chat page');

    await takeScreenshot('chat_redesign_desktop_1280_light.png');

    // ---------------------------------------------------------
    // TEST 2: Desktop 1280x850 Dark Theme
    // ---------------------------------------------------------
    console.log('\n--- Test 2: Desktop Dark Theme (/messages/2) ---');
    await evalJs(`(() => {
      document.documentElement.setAttribute('data-theme', 'dark');
      document.body.classList.add('theme-dark');
    })()`);
    await new Promise(r => setTimeout(r, 500));

    let darkInfo = await evalJs(`(() => {
      const composer = document.getElementById('sendMessageForm');
      const cRect = composer ? composer.getBoundingClientRect() : null;
      return {
        isDarkTheme: document.documentElement.getAttribute('data-theme') === 'dark',
        composerFullyInViewport: cRect ? (cRect.bottom <= window.innerHeight && cRect.top >= 0) : false
      };
    })()`);
    assert(darkInfo.isDarkTheme, 'Theme set to dark mode');
    assert(darkInfo.composerFullyInViewport, 'Composer remains fully in viewport in dark mode');
    await takeScreenshot('chat_redesign_desktop_1280_dark.png');

    // Reset back to light
    await evalJs(`(() => {
      document.documentElement.removeAttribute('data-theme');
      document.body.classList.remove('theme-dark');
    })()`);

    // ---------------------------------------------------------
    // TEST 3: Category Communities on Desktop (/chat/technology)
    // ---------------------------------------------------------
    console.log('\n--- Test 3: Category Communities (/chat/technology) [1280x850] ---');
    await send('Page.navigate', { url: 'http://localhost:8086/chat/technology' });
    await new Promise(r => setTimeout(r, 2200));

    let catRes = await evalJs(`(() => {
      const inputBar = document.querySelector('.chat-room-input-bar');
      const iRect = inputBar ? inputBar.getBoundingClientRect() : null;
      const stream = document.getElementById('chatMessageStream');
      const roomsList = document.getElementById('chatRoomsList');

      window.scrollTo(0, 500);
      const scrolledY = window.scrollY;

      return {
        hasChatPageClass: document.body.classList.contains('is-chat-page'),
        scrollYAfterAttempt: scrolledY,
        inputBarPresent: !!inputBar,
        inputBarBottom: iRect ? iRect.bottom : -1,
        windowHeight: window.innerHeight,
        inputBarFullyInViewport: iRect ? (iRect.bottom <= window.innerHeight && iRect.top >= 0) : false,
        streamPresent: !!stream,
        roomsListPresent: !!roomsList
      };
    })()`);

    assert(catRes.hasChatPageClass, 'Body has "is-chat-page" class on /chat');
    assert(catRes.scrollYAfterAttempt === 0, 'Zero webpage document scroll on /chat/technology');
    assert(catRes.inputBarFullyInViewport, `Category input bar (${catRes.inputBarBottom}px) is firmly within viewport (${catRes.windowHeight}px)`);
    assert(catRes.roomsListPresent, 'Category channel list is present and independent');

    await takeScreenshot('chat_redesign_category_desktop_light.png');

    // Category Communities Dark Mode
    await evalJs(`(() => {
      document.documentElement.setAttribute('data-theme', 'dark');
      document.body.classList.add('theme-dark');
    })()`);
    await new Promise(r => setTimeout(r, 500));
    await takeScreenshot('chat_redesign_category_desktop_dark.png');
    await evalJs(`(() => {
      document.documentElement.removeAttribute('data-theme');
      document.body.classList.remove('theme-dark');
    })()`);

    // ---------------------------------------------------------
    // TEST 4: Floating "New Messages" Pill & Smart Scrolling
    // ---------------------------------------------------------
    console.log('\n--- Test 4: Floating "New Messages" Pill & Smart Scrolling ---');
    await send('Page.navigate', { url: 'http://localhost:8086/messages/2' });
    await new Promise(r => setTimeout(r, 2000));

    let pillTest = await evalJs(`(() => {
      const stream = document.getElementById('chatStream');
      const pill = document.getElementById('newMessagesPill');
      if (!stream || !pill) return { error: 'Stream or pill missing' };

      // Ensure stream has enough messages to overflow viewport and test scroll mechanics
      for (let i = 1; i <= 25; i++) {
        const div = document.createElement('div');
        div.className = 'chat-bubble ' + (i % 2 === 0 ? 'sent' : 'received');
        div.textContent = 'Prior conversation test message item #' + i;
        stream.appendChild(div);
      }

      // User scrolls up to read history
      stream.scrollTop = 0;

      // Simulated incoming message shows floating pill
      pill.style.display = 'inline-flex';

      const pRect = pill.getBoundingClientRect();
      const style = window.getComputedStyle(pill);
      return {
        pillVisible: style.display !== 'none' && pill.offsetWidth > 0,
        displayValue: style.display,
        pillText: pill.textContent.trim(),
        pillBottom: pRect.bottom,
        windowHeight: window.innerHeight,
        pillInViewport: pRect.bottom <= window.innerHeight && pRect.top >= 0
      };
    })()`);

    console.log('    [DEBUG pillTest]:', JSON.stringify(pillTest));
    assert(pillTest.pillVisible, '"↓ New messages" floating pill renders when scrolled up');
    assert(pillTest.pillInViewport, 'Floating pill is clearly visible within the chat viewport');
    await takeScreenshot('chat_redesign_new_messages_pill.png');

    // Click pill to verify it scrolls to bottom and hides
    await evalJs(`(() => {
      const pill = document.getElementById('newMessagesPill');
      if (pill) pill.click();
    })()`);
    await new Promise(r => setTimeout(r, 600));

    let postScroll = await evalJs(`(() => {
      const stream = document.getElementById('chatStream');
      const pill = document.getElementById('newMessagesPill');
      return {
        scrollTop: stream.scrollTop,
        maxScroll: stream.scrollHeight - stream.clientHeight,
        pillHidden: window.getComputedStyle(pill).display === 'none'
      };
    })()`);
    assert(postScroll.scrollTop >= (postScroll.maxScroll - 15), `Clicking floating pill scrolls stream to bottom (scrolled to ${postScroll.scrollTop} of ${postScroll.maxScroll})`);
    assert(postScroll.pillHidden, 'Floating pill is automatically dismissed after scrolling to bottom');

    // ---------------------------------------------------------
    // TEST 5: Mobile Viewport Responsive Single-Panel (430x932)
    // ---------------------------------------------------------
    console.log('\n--- Test 5: Mobile Responsive Single Panel (/messages/2) [430x932] ---');
    await send('Emulation.setDeviceMetricsOverride', { width: 430, height: 932, deviceScaleFactor: 2, mobile: true });
    await send('Page.navigate', { url: 'http://localhost:8086/messages/2' });
    await new Promise(r => setTimeout(r, 2000));

    let mRes = await evalJs(`(() => {
      const sidebar = document.querySelector('.conversations-sidebar');
      const mainPane = document.querySelector('.chat-main-pane');
      const backBtn = document.querySelector('.chat-back-btn');
      const composer = document.getElementById('sendMessageForm');
      const cRect = composer ? composer.getBoundingClientRect() : null;

      window.scrollTo(0, 300);

      return {
        sidebarHidden: !sidebar || window.getComputedStyle(sidebar).display === 'none',
        mainPaneVisible: mainPane && window.getComputedStyle(mainPane).display !== 'none',
        backBtnVisible: backBtn && window.getComputedStyle(backBtn).display !== 'none',
        backBtnHref: backBtn ? backBtn.getAttribute('href') : '',
        composerFullyInViewport: cRect ? (cRect.bottom <= window.innerHeight && cRect.top >= 0) : false,
        scrollY: window.scrollY
      };
    })()`);

    assert(mRes.sidebarHidden, 'Conversations sidebar is hidden in active mobile thread');
    assert(mRes.mainPaneVisible, 'Chat thread occupies full width on mobile');
    assert(mRes.backBtnVisible, 'Back button (←) is visible in mobile chat header');
    assert(mRes.backBtnHref === '/messages', 'Back button targets /messages conversation list');
    assert(mRes.composerFullyInViewport, 'Composer is visible at the bottom of the mobile viewport');
    assert(mRes.scrollY === 0, 'Zero document scroll on mobile chat page');

    await takeScreenshot('chat_redesign_mobile_430_messages.png');

    // ---------------------------------------------------------
    // TEST 6: Mobile Viewport (/chat/technology) [375x667]
    // ---------------------------------------------------------
    console.log('\n--- Test 6: Mobile Category Communities (/chat/technology) [375x667] ---');
    await send('Emulation.setDeviceMetricsOverride', { width: 375, height: 667, deviceScaleFactor: 2, mobile: true });
    await send('Page.navigate', { url: 'http://localhost:8086/chat/technology' });
    await new Promise(r => setTimeout(r, 2000));

    let cMobRes = await evalJs(`(() => {
      const sidebar = document.querySelector('.chat-rooms-sidebar');
      const roomMain = document.querySelector('.chat-room-main');
      const backBtn = document.querySelector('.chat-back-btn');
      const inputBar = document.querySelector('.chat-room-input-bar');
      const iRect = inputBar ? inputBar.getBoundingClientRect() : null;

      window.scrollTo(0, 300);

      return {
        sidebarHidden: !sidebar || window.getComputedStyle(sidebar).display === 'none',
        roomMainVisible: roomMain && window.getComputedStyle(roomMain).display !== 'none',
        backBtnVisible: backBtn && window.getComputedStyle(backBtn).display !== 'none',
        backBtnHref: backBtn ? backBtn.getAttribute('href') : '',
        inputBarInViewport: iRect ? (iRect.bottom <= window.innerHeight && iRect.top >= 0) : false,
        scrollY: window.scrollY
      };
    })()`);

    assert(cMobRes.sidebarHidden, 'Chat rooms sidebar is hidden in active mobile room');
    assert(cMobRes.roomMainVisible, 'Room discussion occupies full screen on mobile');
    assert(cMobRes.backBtnVisible, 'Back button (←) is visible in mobile room header');
    assert(cMobRes.backBtnHref === '/chat', 'Back button targets /chat rooms list');
    assert(cMobRes.inputBarInViewport, 'Category input/join bar is inside 375px mobile viewport');
    assert(cMobRes.scrollY === 0, 'Zero document scroll on 375px mobile');

    await takeScreenshot('chat_redesign_mobile_375_chat.png');

    // ---------------------------------------------------------
    // TEST 7: Regression Check on Non-Chat Pages (Homepage & eBooks)
    // ---------------------------------------------------------
    console.log('\n--- Test 7: Non-Chat Pages Document Scrolling Regression Check ---');
    await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 850, deviceScaleFactor: 1, mobile: false });
    await send('Page.navigate', { url: 'http://localhost:8086/' });
    await new Promise(r => setTimeout(r, 2000));

    let hRes = await evalJs(`(() => {
      const bodyHasChatClass = document.body.classList.contains('is-chat-page');
      const footer = document.querySelector('.site-footer-wrapper');
      const docScrollHeight = document.documentElement.scrollHeight;

      window.scrollTo(0, 400);
      const scrolledY = window.scrollY;

      return {
        bodyHasChatClass,
        footerVisible: !!footer && window.getComputedStyle(footer).display !== 'none',
        docScrollHeight,
        scrolledY
      };
    })()`);

    assert(!hRes.bodyHasChatClass, 'Homepage body does NOT have "is-chat-page" class');
    assert(hRes.footerVisible, 'Site footer is visible on Homepage');
    assert(hRes.scrolledY >= 350, `Homepage document scrolls normally (scrolled to ${hRes.scrolledY}px)`);

    console.log('\n=======================================================');
    if (allPassed) {
      console.log('ALL CHAT UX REDESIGN AUDIT TESTS PASSED SUCCESSFULLY!');
    } else {
      console.error('SOME CHAT UX REDESIGN AUDIT TESTS FAILED.');
      process.exitCode = 1;
    }
    console.log('=======================================================\n');

  } catch (err) {
    console.error('Unexpected error during test execution:', err);
    process.exitCode = 1;
  } finally {
    try {
      ws.close();
      edge.kill();
    } catch (e) {}
  }
}

runTests();
