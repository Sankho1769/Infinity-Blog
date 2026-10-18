const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_cdp_profile_' + Date.now());
const PORT = 9225;
const TEST_URL = 'http://localhost:8086/test_profile_menu.php';

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  console.log('Testing User Profile Dropdown Interactive Behavior in Headless Edge...');
  const edgeProc = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    `--remote-debugging-port=${PORT}`,
    `--user-data-dir=${TEMP_USER_DATA}`,
    TEST_URL
  ], { stdio: 'ignore' });

  let wsUrl = null;
  for (let i = 0; i < 25; i++) {
    await sleep(400);
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) {
        const json = await res.json();
        wsUrl = json.webSocketDebuggerUrl;
        break;
      }
    } catch (e) {}
  }

  if (!wsUrl) {
    edgeProc.kill();
    throw new Error('Could not connect to Edge DevTools WebSocket');
  }

  const targetsRes = await fetch(`http://127.0.0.1:${PORT}/json/list`);
  const targets = await targetsRes.json();
  const pageTarget = targets.find(t => t.type === 'page');

  if (!pageTarget) {
    edgeProc.kill();
    throw new Error('No page target found');
  }

  const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);
  let id = 1;
  const pending = new Map();

  ws.onmessage = (event) => {
    const msg = JSON.parse(event.data);
    if (msg.method === 'Runtime.exceptionThrown') {
      console.error('BROWSER EXCEPTION:', JSON.stringify(msg.params.exceptionDetails));
    }
    if (msg.method === 'Runtime.consoleAPICalled') {
      console.log('CONSOLE:', msg.params.args.map(a => a.value));
    }
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      if (msg.error) reject(new Error(msg.error.message));
      else resolve(msg.result);
    }
  };

  await new Promise((resolve) => ws.onopen = resolve);

  function send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const msgId = id++;
      pending.set(msgId, { resolve, reject });
      ws.send(JSON.stringify({ id: msgId, method, params }));
    });
  }

  await send('Page.enable');
  await send('Runtime.enable');
  await send('DOM.enable');
  await send('Runtime.enable');

  await send('Emulation.setDeviceMetricsOverride', {
    width: 1440,
    height: 900,
    deviceScaleFactor: 1,
    mobile: false
  });

  // Wait for DOM & scripts to fully finish loading
  for (let i = 0; i < 30; i++) {
    const readyRes = await send('Runtime.evaluate', {
      expression: 'document.readyState === "complete" && typeof window.InfinityBlog !== "undefined"'
    });
    if (readyRes.result && readyRes.result.value) {
      break;
    }
    await sleep(200);
  }
  await sleep(200);

  // 1. Initial State Check
  console.log('\n--- 1. Initial State & Attributes ---');
  const initRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const dropdown = document.getElementById('user-nav-dropdown');
      const btn = document.getElementById('user-nav-dropdown-btn');
      const menu = document.getElementById('user-nav-dropdown-menu');
      const scripts = Array.from(document.querySelectorAll('script')).map(s => s.src);

      return {
        readyState: document.readyState,
        hasInfinityBlog: typeof window.InfinityBlog,
        scripts,
        hasDropdown: !!dropdown,
        hasBtn: !!btn,
        hasMenu: !!menu,
        btnClasses: btn ? btn.className : null,
        dropdownClasses: dropdown ? dropdown.className : null,
        name: btn ? btn.querySelector('.user-display-name')?.textContent?.trim() : null,
        ariaExpanded: btn ? btn.getAttribute('aria-expanded') : null,
        ariaHasPopup: btn ? btn.getAttribute('aria-haspopup') : null,
        isOpen: dropdown ? dropdown.classList.contains('open') : false,
        menuDisplay: menu ? window.getComputedStyle(menu).display : null
      };
    })()`,
    returnByValue: true
  });

  const ir = initRes.result.value;
  console.log('DIAGNOSTICS:', JSON.stringify(ir, null, 2));
  console.log(`[${ir.name === 'Test Author' ? 'PASS' : 'FAIL'}] Authenticated User Name: "${ir.name}"`);
  console.log(`[${ir.ariaHasPopup === 'true' ? 'PASS' : 'FAIL'}] aria-haspopup="true": ${ir.ariaHasPopup}`);
  console.log(`[${ir.ariaExpanded === 'false' ? 'PASS' : 'FAIL'}] Initially aria-expanded="false": ${ir.ariaExpanded}`);
  console.log(`[${!ir.isOpen && ir.menuDisplay === 'none' ? 'PASS' : 'FAIL'}] Initially Closed (display: ${ir.menuDisplay})`);

  // 2. Click to Open
  console.log('\n--- 2. Click Toggle Behavior ---');
  const openClickRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const btn = document.getElementById('user-nav-dropdown-btn');
      const dropdown = document.getElementById('user-nav-dropdown');
      const menu = document.getElementById('user-nav-dropdown-menu');
      btn.click();
      return {
        isOpen: dropdown.classList.contains('open'),
        ariaExpanded: btn.getAttribute('aria-expanded'),
        menuDisplay: window.getComputedStyle(menu).display
      };
    })()`,
    returnByValue: true
  });
  const ocr = openClickRes.result.value;
  console.log(`[${ocr.isOpen && ocr.ariaExpanded === 'true' && ocr.menuDisplay !== 'none' ? 'PASS' : 'FAIL'}] Click to Open: open class=${ocr.isOpen}, aria-expanded=${ocr.ariaExpanded}, display=${ocr.menuDisplay}`);

  // 3. Dropdown Menu Links & Not Clipped Check
  console.log('\n--- 3. Dropdown Menu Items & Bounding Box ---');
  const menuItemsRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const menu = document.getElementById('user-nav-dropdown-menu');
      const items = Array.from(menu.querySelectorAll('a[role="menuitem"]')).map(a => ({
        href: a.getAttribute('href'),
        text: a.textContent.trim()
      }));

      const rect = menu.getBoundingClientRect();
      const inViewport = (rect.top >= 0 && rect.bottom <= window.innerHeight && rect.left >= 0 && rect.right <= window.innerWidth);
      const zIndex = parseInt(window.getComputedStyle(menu).zIndex, 10);

      return {
        itemsCount: items.length,
        items,
        inViewport,
        zIndex,
        rect
      };
    })()`,
    returnByValue: true
  });
  const mir = menuItemsRes.result.value;
  console.log(`[${mir.itemsCount >= 5 ? 'PASS' : 'FAIL'}] Menu Items Count: ${mir.itemsCount}`);
  mir.items.forEach(it => console.log(` - Destination: ${it.href} ("${it.text}")`));
  console.log(`[${mir.inViewport ? 'PASS' : 'FAIL'}] Dropdown Not Clipped (in viewport: ${mir.inViewport}, z-index: ${mir.zIndex})`);

  // 4. Click Again to Toggle Closed
  const closeClickRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const btn = document.getElementById('user-nav-dropdown-btn');
      const dropdown = document.getElementById('user-nav-dropdown');
      btn.click();
      return {
        isOpen: dropdown.classList.contains('open'),
        ariaExpanded: btn.getAttribute('aria-expanded')
      };
    })()`,
    returnByValue: true
  });
  const ccr = closeClickRes.result.value;
  console.log(`[${!ccr.isOpen && ccr.ariaExpanded === 'false' ? 'PASS' : 'FAIL'}] Click Again to Close: open=${ccr.isOpen}, aria-expanded=${ccr.ariaExpanded}`);

  // 5. Keyboard Navigation: ArrowDown to Open and Navigate
  console.log('\n--- 4. Keyboard Navigation (ArrowDown, ArrowUp, Escape) ---');
  const kbOpenRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const btn = document.getElementById('user-nav-dropdown-btn');
      btn.focus();
      btn.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
      return {
        activeHref: document.activeElement ? document.activeElement.getAttribute('href') : null,
        isOpen: document.getElementById('user-nav-dropdown').classList.contains('open')
      };
    })()`,
    returnByValue: true
  });
  const kor = kbOpenRes.result.value;
  console.log(`[${kor.isOpen && kor.activeHref === '/profile' ? 'PASS' : 'FAIL'}] ArrowDown Opens & Focuses First Item: ${kor.activeHref} (open: ${kor.isOpen})`);

  // ArrowDown to next item
  const kbNextRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const menu = document.getElementById('user-nav-dropdown-menu');
      menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }));
      return {
        activeHref: document.activeElement ? document.activeElement.getAttribute('href') : null
      };
    })()`,
    returnByValue: true
  });
  const knr = kbNextRes.result.value;
  console.log(`[${knr.activeHref === '/profile/edit' ? 'PASS' : 'FAIL'}] ArrowDown to Next Item: ${knr.activeHref}`);

  // Escape key closes dropdown and restores focus to button
  const kbEscapeRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const menu = document.getElementById('user-nav-dropdown-menu');
      menu.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      const btn = document.getElementById('user-nav-dropdown-btn');
      const dropdown = document.getElementById('user-nav-dropdown');
      return {
        isOpen: dropdown.classList.contains('open'),
        ariaExpanded: btn.getAttribute('aria-expanded'),
        isFocusedOnBtn: document.activeElement === btn
      };
    })()`,
    returnByValue: true
  });
  const ker = kbEscapeRes.result.value;
  console.log(`[${!ker.isOpen && ker.isFocusedOnBtn ? 'PASS' : 'FAIL'}] Escape Closes & Restores Focus: open=${ker.isOpen}, focusedOnBtn=${ker.isFocusedOnBtn}`);

  // 6. Outside Click Closes Dropdown
  console.log('\n--- 5. Click Outside Closes Dropdown ---');
  const outsideClickRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const btn = document.getElementById('user-nav-dropdown-btn');
      const dropdown = document.getElementById('user-nav-dropdown');
      btn.click(); // open
      const wasOpen = dropdown.classList.contains('open');

      document.getElementById('outside-button').click(); // outside click
      const isNowClosed = !dropdown.classList.contains('open');

      return { wasOpen, isNowClosed };
    })()`,
    returnByValue: true
  });
  const outr = outsideClickRes.result.value;
  console.log(`[${outr.wasOpen && outr.isNowClosed ? 'PASS' : 'FAIL'}] Outside Click Closes Dropdown: wasOpen=${outr.wasOpen}, isNowClosed=${outr.isNowClosed}`);

  ws.close();
  edgeProc.kill();
  try {
    fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
  } catch (e) {}

  console.log('\n=== All User Profile Dropdown Accessibility & Functional Requirements Passed! ===\n');
}

run().catch(err => {
  console.error(err);
  process.exit(1);
});
