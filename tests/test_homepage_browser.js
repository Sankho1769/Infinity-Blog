const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_cdp_home_' + Date.now());
const PORT = 9224;
const BASE_URL = 'http://localhost:8086';

const targetViewports = [
  { name: '1440px (Desktop Large)', width: 1440, height: 900, isMobile: false },
  { name: '1280px (Desktop Medium)', width: 1280, height: 800, isMobile: false },
  { name: '1024px (Tablet Landscape)', width: 1024, height: 768, isMobile: false },
  { name: '768px (Tablet Portrait / Stacked)', width: 768, height: 1024, isMobile: true },
  { name: '430px (Mobile Large)', width: 430, height: 932, isMobile: true },
  { name: '375px (Mobile Standard)', width: 375, height: 667, isMobile: true }
];

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  console.log('Launching headless Edge for Comprehensive Layout & Navigation Validation...');
  const edgeProc = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    `--remote-debugging-port=${PORT}`,
    `--user-data-dir=${TEMP_USER_DATA}`,
    `${BASE_URL}/`
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
  await send('Runtime.enable');

  console.log('\n--- 1. Viewport Layout Validation (1440, 1280, 1024, 768, 430, 375px) ---');
  let allPass = true;

  for (const vp of targetViewports) {
    await send('Emulation.setDeviceMetricsOverride', {
      width: vp.width,
      height: vp.height,
      deviceScaleFactor: 1,
      mobile: vp.isMobile
    });

    await sleep(250);

    const evalRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const docWidth = document.documentElement.scrollWidth;
        const winWidth = window.innerWidth;
        const hasHScroll = docWidth > winWidth + 1; // 1px threshold for fractional rounding

        const sidebarNav = document.querySelector('.app-left-sidebar');
        const sidebarNavDisplay = sidebarNav ? window.getComputedStyle(sidebarNav).display : 'none';
        const isLeftNavVisible = sidebarNavDisplay !== 'none';

        const mobileToggle = document.getElementById('mobile-nav-toggle');
        const mobileToggleDisplay = mobileToggle ? window.getComputedStyle(mobileToggle).display : 'none';
        const isMobileToggleVisible = mobileToggleDisplay !== 'none';

        const mainCol = document.querySelector('.homepage-main-column');
        const sidebar = document.querySelector('.homepage-sidebar');
        const mainRect = mainCol ? mainCol.getBoundingClientRect() : null;
        const sideRect = sidebar ? sidebar.getBoundingClientRect() : null;

        const isBeside = (mainRect && sideRect && mainRect.top < sideRect.bottom && sideRect.left >= mainRect.right - 10);
        const isStacked = (mainRect && sideRect && sideRect.top >= mainRect.bottom - 20);

        const searchBar = document.querySelector('.homepage-main-search-bar');
        const searchRect = searchBar ? searchBar.getBoundingClientRect() : null;
        const searchInput = document.querySelector('.homepage-main-search-input');

        const authorsCard = document.querySelector('.sidebar-card-authors');
        const commCard = document.querySelector('.sidebar-card-communities');
        const authorRows = document.querySelectorAll('.sidebar-author-row').length;
        const commRows = document.querySelectorAll('.sidebar-community-row').length;

        // Verify that old horizontal nav does NOT exist
        const oldNavLinks = document.querySelector('.nav-section-middle .nav-links');

        return {
          winWidth,
          docWidth,
          hasHScroll,
          isLeftNavVisible,
          isMobileToggleVisible,
          isBeside,
          isStacked,
          hasOldNavLinks: !!oldNavLinks,
          searchWidth: searchRect ? Math.round(searchRect.width) : 0,
          searchHeight: searchRect ? Math.round(searchRect.height) : 0,
          searchPlaceholder: searchInput ? searchInput.getAttribute('placeholder') : null,
          hasAuthorsCard: !!authorsCard,
          hasCommCard: !!commCard,
          authorRows,
          commRows
        };
      })()`,
      returnByValue: true
    });

    const m = evalRes.result.value;
    const hScrollOk = !m.hasHScroll;
    const navModeOk = vp.width >= 1025 ? (m.isLeftNavVisible && !m.isMobileToggleVisible) : (!m.isLeftNavVisible && m.isMobileToggleVisible);
    const layoutModeOk = vp.width >= 1025 ? m.isBeside : m.isStacked;
    const noOldNav = !m.hasOldNavLinks;

    const vpPass = hScrollOk && navModeOk && layoutModeOk && noOldNav;
    if (!vpPass) allPass = false;

    console.log(`[${vpPass ? 'PASS' : 'FAIL'}] ${vp.name.padEnd(32)} | HScroll: ${m.hasHScroll ? 'YES' : 'NO'} | LeftSidebar: ${m.isLeftNavVisible ? 'Visible' : 'Hidden'} | MobileToggle: ${m.isMobileToggleVisible ? 'Visible' : 'Hidden'} | ColLayout: ${m.isBeside ? 'Beside' : (m.isStacked ? 'Stacked' : 'Unknown')} | Search: ${m.searchWidth}x${m.searchHeight}px`);
  }

  console.log('\n--- 2. Functional Elements & Recommendations Validation (1440px Desktop) ---');
  await send('Emulation.setDeviceMetricsOverride', {
    width: 1440,
    height: 900,
    deviceScaleFactor: 1,
    mobile: false
  });
  await sleep(200);

  const funcRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const searchInput = document.querySelector('.homepage-main-search-input');
      const placeholder = searchInput ? searchInput.getAttribute('placeholder') : '';
      const authors = Array.from(document.querySelectorAll('.sidebar-author-row')).map(r => ({
        id: r.getAttribute('data-author-id'),
        name: r.querySelector('.sidebar-row-title')?.textContent?.trim(),
        handle: r.querySelector('.sidebar-row-sub')?.textContent?.trim(),
        hasBtn: !!r.querySelector('.btn-follow-toggle')
      }));
      const comms = Array.from(document.querySelectorAll('.sidebar-community-row')).map(r => ({
        slug: r.getAttribute('data-slug'),
        name: r.querySelector('.sidebar-row-title')?.textContent?.trim(),
        hasBtn: !!r.querySelector('.btn-community-join')
      }));

      // Community discovery link check
      const sidebarCommLink = document.querySelector('.sidebar-card-communities a.sidebar-card-header-link, .sidebar-card-communities a[href="/communities"]');
      const commHref = sidebarCommLink ? sidebarCommLink.getAttribute('href') : null;

      const sidebarNavCommLink = document.querySelector('.app-left-sidebar a[href="/communities"]');

      return {
        placeholder,
        authorsCount: authors.length,
        authors,
        commsCount: comms.length,
        comms,
        hasDiscoverAuthors: !!document.querySelector('a[href="/authors"]'),
        sidebarCommHref: commHref,
        hasSidebarNavCommLink: !!sidebarNavCommLink
      };
    })()`,
    returnByValue: true
  });

  const f = funcRes.result.value;
  console.log(`Search Placeholder: "${f.placeholder}"`);
  console.log(`Authors count: ${f.authorsCount} (at most 5)`);
  f.authors.forEach(a => console.log(` - ID ${a.id}: ${a.name} (${a.handle}) [FollowBtn: ${a.hasBtn}]`));
  console.log(`Communities count: ${f.commsCount} (at most 5)`);
  f.comms.forEach(c => console.log(` - ${c.name} (slug: ${c.slug}) [JoinBtn: ${c.hasBtn}]`));
  console.log(`Discover Authors link (/authors): ${f.hasDiscoverAuthors ? 'PASS' : 'FAIL'}`);
  console.log(`Sidebar Community link destination: ${f.sidebarCommHref} ${f.sidebarCommHref === '/communities' ? '(PASS)' : '(FAIL)'}`);
  console.log(`Left sidebar "Discover Communities" link (/communities): ${f.hasSidebarNavCommLink ? 'PASS' : 'FAIL'}`);

  if (f.sidebarCommHref !== '/communities' || !f.hasSidebarNavCommLink) {
    allPass = false;
  }

  console.log('\n--- 3. Mobile Navigation Drawer Interactive Test (430px Viewport) ---');
  await send('Emulation.setDeviceMetricsOverride', {
    width: 430,
    height: 932,
    deviceScaleFactor: 1,
    mobile: true
  });
  await sleep(250);

  // Open drawer by clicking mobile toggle
  const drawerOpenRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const toggle = document.getElementById('mobile-nav-toggle');
      const drawer = document.getElementById('mobile-nav-drawer');
      if (!toggle || !drawer) return { error: 'Toggle or drawer element missing' };
      toggle.click();
      return {
        isOpen: drawer.classList.contains('open'),
        isExpanded: toggle.getAttribute('aria-expanded') === 'true',
        bodyHasLock: document.body.classList.contains('drawer-open'),
        hasDiscoverComm: !!drawer.querySelector('a[href="/communities"]'),
        hasChat: !!drawer.querySelector('a[href="/chat"]'),
        hasGuestActions: !!drawer.querySelector('.mobile-guest-actions')
      };
    })()`,
    returnByValue: true
  });
  const doRes = drawerOpenRes.result.value;
  console.log(`Mobile Drawer Open: ${doRes.isOpen ? 'PASS' : 'FAIL'} | ScrollLock: ${doRes.bodyHasLock ? 'PASS' : 'FAIL'} | DiscoverCommunities: ${doRes.hasDiscoverComm ? 'PASS' : 'FAIL'}`);

  // Close drawer via Escape key
  await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
  await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
  await sleep(200);

  const drawerCloseRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const toggle = document.getElementById('mobile-nav-toggle');
      const drawer = document.getElementById('mobile-nav-drawer');
      return {
        isClosed: !drawer.classList.contains('open'),
        isExpanded: toggle.getAttribute('aria-expanded') === 'false',
        bodyLockRemoved: !document.body.classList.contains('drawer-open')
      };
    })()`,
    returnByValue: true
  });
  const dcRes = drawerCloseRes.result.value;
  console.log(`Mobile Drawer Escape Close: ${dcRes.isClosed ? 'PASS' : 'FAIL'} | ScrollLock Cleared: ${dcRes.bodyLockRemoved ? 'PASS' : 'FAIL'}`);

  if (!doRes.isOpen || !dcRes.isClosed) allPass = false;

  console.log('\n--- 4. Dedicated Community Discovery Page (/communities) ---');
  await send('Page.navigate', { url: `${BASE_URL}/communities` });
  await sleep(600);

  const commPageRes = await send('Runtime.evaluate', {
    expression: `(() => {
      const title = document.title;
      const heading = document.querySelector('h1')?.textContent?.trim();
      const searchBar = document.querySelector('.hero-search-bar input[name="search"]');
      const cards = document.querySelectorAll('.community-card');
      const chatLinks = document.querySelectorAll('.community-card a[href^="/chat/"]');
      const joinBtns = document.querySelectorAll('.btn-community-join');
      const docWidth = document.documentElement.scrollWidth;
      const winWidth = window.innerWidth;
      return {
        title,
        heading,
        hasSearchBar: !!searchBar,
        cardsCount: cards.length,
        chatLinksCount: chatLinks.length,
        joinBtnsCount: joinBtns.length,
        hasHScroll: docWidth > winWidth + 1
      };
    })()`,
    returnByValue: true
  });

  const cp = commPageRes.result.value;
  console.log(`Communities Page Heading: "${cp.heading}"`);
  console.log(`Search filter bar present: ${cp.hasSearchBar ? 'PASS' : 'FAIL'}`);
  console.log(`Community cards rendered: ${cp.cardsCount} | Join buttons: ${cp.joinBtnsCount} | Chat buttons: ${cp.chatLinksCount}`);
  console.log(`No horizontal scroll: ${!cp.hasHScroll ? 'PASS' : 'FAIL'}`);

  if (!cp.hasSearchBar || cp.cardsCount === 0 || cp.hasHScroll) {
    allPass = false;
  }

  ws.close();
  edgeProc.kill();
  try {
    fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
  } catch (e) {}

  if (!allPass) {
    throw new Error('Some layout, navigation, or functional checks failed!');
  }
  console.log('\n=== All Homepage & Navigation Overhaul Checks Passed in Headless Edge! ===\n');
}

run().catch(err => {
  console.error(err);
  process.exit(1);
});
