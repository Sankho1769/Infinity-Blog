const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_cdp_profile_' + Date.now());
const PORT = 9222;

const viewports = [
  { name: '320px (min-mobile)', width: 320, height: 640 },
  { name: '375px (mobile)', width: 375, height: 667 },
  { name: '430px (large-mobile)', width: 430, height: 932 },
  { name: '768px (tablet-portrait)', width: 768, height: 1024 },
  { name: '1024px (tablet-landscape)', width: 1024, height: 768 },
  { name: '1280px (small-desktop)', width: 1280, height: 800 },
  { name: '1440px (standard-desktop)', width: 1440, height: 900 },
  { name: '1920px (full-hd)', width: 1920, height: 1080 }
];

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  console.log('Launching headless Edge for CDP viewport validation...');
  const edgeProc = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    `--remote-debugging-port=${PORT}`,
    `--user-data-dir=${TEMP_USER_DATA}`,
    'http://localhost:8086/'
  ], { stdio: 'ignore' });

  // Wait for CDP port
  let wsUrl = null;
  for (let i = 0; i < 20; i++) {
    await sleep(500);
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

  console.log('Connected to Edge CDP. Fetching target page...');
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
  await send('DOM.enable');

  console.log('\n--- Viewport Layout Validation ---');
  let allPass = true;

  for (const vp of viewports) {
    await send('Emulation.setDeviceMetricsOverride', {
      width: vp.width,
      height: vp.height,
      deviceScaleFactor: 1,
      mobile: vp.width <= 768
    });

    await sleep(250);

    const evalResult = await send('Runtime.evaluate', {
      expression: `(() => {
        const docWidth = document.documentElement.scrollWidth;
        const bodyWidth = document.body.scrollWidth;
        const winWidth = window.innerWidth;
        const nav = document.querySelector('.nav-bar');
        const navRect = nav ? nav.getBoundingClientRect() : null;
        const toggle = document.querySelector('#mobile-nav-toggle');
        const toggleVisible = toggle ? window.getComputedStyle(toggle).display !== 'none' : false;
        const desktopLinks = document.querySelector('.nav-links');
        const desktopLinksVisible = desktopLinks ? window.getComputedStyle(desktopLinks).display !== 'none' : false;

        return {
          winWidth,
          docWidth,
          bodyWidth,
          navWidth: navRect ? Math.round(navRect.width) : null,
          hasHScroll: docWidth > winWidth,
          toggleVisible,
          desktopLinksVisible
        };
      })()`,
      returnByValue: true
    });

    const metrics = evalResult.result.value;
    const hScroll = metrics.hasHScroll;
    const status = !hScroll ? 'PASS' : 'FAIL';
    if (hScroll) allPass = false;

    console.log(`[${status}] ${vp.name.padEnd(26)} | Win: ${metrics.winWidth}px | Doc: ${metrics.docWidth}px | Nav: ${metrics.navWidth}px | H-Scroll: ${hScroll ? 'YES' : 'NO'} | Mode: ${metrics.desktopLinksVisible ? 'Desktop' : 'Mobile'}`);
  }

  console.log('\n--- Mobile Drawer Interaction Validation (375px) ---');
  await send('Emulation.setDeviceMetricsOverride', {
    width: 375,
    height: 667,
    deviceScaleFactor: 1,
    mobile: true
  });
  await sleep(200);

  // Click mobile nav toggle to open drawer
  await send('Runtime.evaluate', {
    expression: `document.querySelector('#mobile-nav-toggle').click()`
  });
  await sleep(250);

  const drawerOpenResult = await send('Runtime.evaluate', {
    expression: `(() => {
      const drawer = document.querySelector('#mobile-nav-drawer');
      const toggle = document.querySelector('#mobile-nav-toggle');
      return {
        isOpen: drawer.classList.contains('open') && window.getComputedStyle(drawer).display !== 'none',
        ariaExpanded: toggle.getAttribute('aria-expanded'),
        hasSearch: drawer.querySelector('.mobile-search-input') !== null,
        linksCount: drawer.querySelectorAll('.mobile-nav-link').length
      };
    })()`,
    returnByValue: true
  });
  const drawerOpenMetrics = drawerOpenResult.result.value;
  console.log(`Drawer open: ${drawerOpenMetrics.isOpen ? 'PASS' : 'FAIL'} | aria-expanded: ${drawerOpenMetrics.ariaExpanded} | Search present: ${drawerOpenMetrics.hasSearch} | Links: ${drawerOpenMetrics.linksCount}`);

  // Dispatch Escape key to close drawer
  await send('Runtime.evaluate', {
    expression: `document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))`
  });
  await sleep(250);

  const drawerClosedResult = await send('Runtime.evaluate', {
    expression: `(() => {
      const drawer = document.querySelector('#mobile-nav-drawer');
      const toggle = document.querySelector('#mobile-nav-toggle');
      return {
        isClosed: !drawer.classList.contains('open') && window.getComputedStyle(drawer).display === 'none',
        ariaExpanded: toggle.getAttribute('aria-expanded')
      };
    })()`,
    returnByValue: true
  });
  const drawerClosedMetrics = drawerClosedResult.result.value;
  console.log(`Drawer close on Escape: ${drawerClosedMetrics.isClosed ? 'PASS' : 'FAIL'} | aria-expanded: ${drawerClosedMetrics.ariaExpanded}`);

  ws.close();
  edgeProc.kill();

  try {
    fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
  } catch (e) {}

  if (!allPass) {
    throw new Error('Some viewports showed horizontal scroll overflow!');
  }
  console.log('\nAll 8 viewport breakpoints passed with ZERO horizontal overflow!\n');
}

run().catch(err => {
  console.error(err);
  process.exit(1);
});
