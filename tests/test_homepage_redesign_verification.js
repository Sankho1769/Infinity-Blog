const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_cdp_redesign_' + Date.now());
const PORT = 9227;
const BASE_URL = 'http://localhost:8086';
const ARTIFACTS_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  console.log('=== Starting Infinity Blog Homepage Redesign & Theme Verification ===\n');

  console.log('Launching headless Edge on port ' + PORT + '...');
  const edgeProc = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    '--hide-scrollbars',
    `--remote-debugging-port=${PORT}`,
    `--user-data-dir=${TEMP_USER_DATA}`,
    `${BASE_URL}/`
  ], { stdio: 'ignore' });

  let wsUrl = null;
  for (let i = 0; i < 30; i++) {
    await sleep(350);
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

  async function evaluate(expression) {
    const res = await send('Runtime.evaluate', {
      expression,
      returnByValue: true
    });
    return res && res.result ? res.result.value : null;
  }

  async function setViewport(width, height) {
    await send('Emulation.setDeviceMetricsOverride', {
      width,
      height,
      deviceScaleFactor: 1,
      mobile: width <= 768
    });
  }

  async function captureScreenshot(filepath) {
    const res = await send('Page.captureScreenshot', { format: 'png' });
    if (res && res.data) {
      fs.writeFileSync(filepath, Buffer.from(res.data, 'base64'));
    }
  }

  await send('Page.enable');
  await send('DOM.enable');
  await send('Runtime.enable');

  let passedTests = 0;
  let failedTests = 0;

  function assert(condition, testName, details = '') {
    if (condition) {
      console.log(`  [PASS] ${testName}`);
      passedTests++;
    } else {
      console.error(`  [FAIL] ${testName} - ${details}`);
      failedTests++;
    }
  }

  try {
    // =========================================================================
    // 1. Desktop Layout & Glassmorphism Verification (1536px and 1440px)
    // =========================================================================
    console.log('\n--- 1. Desktop Layout & Glassmorphism System Verification ---');
    await setViewport(1536, 900);
    await send('Page.navigate', { url: `${BASE_URL}/` });
    await sleep(1500);

    const desktopThemeData = await evaluate(`(() => {
      const header = document.querySelector('.app-top-header');
      const leftSidebar = document.querySelector('.app-left-sidebar');
      const feedCard = document.querySelector('.feed-card');
      const sidebarCards = Array.from(document.querySelectorAll('.sidebar-card'));
      const footer = document.querySelector('.site-footer');

      const getStyles = (el) => {
        if (!el) return null;
        const cs = window.getComputedStyle(el);
        return {
          backdropFilter: cs.backdropFilter || cs.webkitBackdropFilter || 'none',
          backgroundColor: cs.backgroundColor,
          borderRadius: cs.borderRadius,
          boxShadow: cs.boxShadow,
          border: cs.border
        };
      };

      return {
        header: getStyles(header),
        leftSidebar: getStyles(leftSidebar),
        feedCard: getStyles(feedCard),
        sidebarCards: sidebarCards.map(getStyles),
        footer: getStyles(footer),
        docWidth: document.documentElement.scrollWidth,
        viewWidth: window.innerWidth
      };
    })()`);

    assert(desktopThemeData.docWidth <= desktopThemeData.viewWidth + 1, 'Desktop (1536px) has zero horizontal overflow', `doc: ${desktopThemeData.docWidth}, view: ${desktopThemeData.viewWidth}`);
    assert(desktopThemeData.header && desktopThemeData.header.backdropFilter.includes('blur'), 'Desktop header preserves glassmorphism (backdrop-filter: blur)', desktopThemeData.header?.backdropFilter);
    assert(desktopThemeData.leftSidebar && desktopThemeData.leftSidebar.backdropFilter.includes('blur'), 'Desktop left sidebar preserves glassmorphism (backdrop-filter: blur)', desktopThemeData.leftSidebar?.backdropFilter);
    assert(desktopThemeData.feedCard && desktopThemeData.feedCard.backdropFilter.includes('blur'), 'Desktop feed card preserves glassmorphism (backdrop-filter: blur)', desktopThemeData.feedCard?.backdropFilter);
    assert(desktopThemeData.sidebarCards.length > 0 && desktopThemeData.sidebarCards[0].backdropFilter.includes('blur'), 'Desktop discovery cards preserve glassmorphism (backdrop-filter: blur)', desktopThemeData.sidebarCards[0]?.backdropFilter);

    // Save Desktop Screenshot
    const desktopScreenshotPath = path.join(ARTIFACTS_DIR, 'homepage_redesign_desktop_1536.png');
    await captureScreenshot(desktopScreenshotPath);
    console.log(`  Saved desktop screenshot to: ${desktopScreenshotPath}`);

    // =========================================================================
    // 2. Mobile Layout & Claymorphism Verification (375px)
    // =========================================================================
    console.log('\n--- 2. Mobile Layout & Claymorphism System Verification ---');
    await setViewport(375, 812);
    await sleep(500);

    const mobileThemeData = await evaluate(`(() => {
      const header = document.querySelector('.app-top-header');
      const feedCard = document.querySelector('.feed-card');
      const composer = document.querySelector('.feed-composer-card');

      const getStyles = (el) => {
        if (!el) return null;
        const cs = window.getComputedStyle(el);
        return {
          backdropFilter: cs.backdropFilter || cs.webkitBackdropFilter || 'none',
          backgroundColor: cs.backgroundColor,
          backgroundImage: cs.backgroundImage,
          boxShadow: cs.boxShadow,
          borderRadius: cs.borderRadius
        };
      };

      return {
        header: getStyles(header),
        feedCard: getStyles(feedCard),
        composer: getStyles(composer),
        docWidth: document.documentElement.scrollWidth,
        viewWidth: window.innerWidth
      };
    })()`);

    assert(mobileThemeData.docWidth <= mobileThemeData.viewWidth + 1, 'Mobile (375px) has zero horizontal overflow', `doc: ${mobileThemeData.docWidth}, view: ${mobileThemeData.viewWidth}`);
    assert(mobileThemeData.feedCard && (mobileThemeData.feedCard.backdropFilter === 'none' || !mobileThemeData.feedCard.backdropFilter.includes('blur')), 'Mobile feed card drops backdrop blur for claymorphism surface', mobileThemeData.feedCard?.backdropFilter);
    assert(mobileThemeData.feedCard && mobileThemeData.feedCard.boxShadow.includes('rgba'), 'Mobile feed card has tactile claymorphic drop/dual shadow', mobileThemeData.feedCard?.boxShadow);

    // Save Mobile Screenshot
    const mobileScreenshotPath = path.join(ARTIFACTS_DIR, 'homepage_redesign_mobile_375.png');
    await captureScreenshot(mobileScreenshotPath);
    console.log(`  Saved mobile screenshot to: ${mobileScreenshotPath}`);

    // Reset to Desktop for Component Verifications
    await setViewport(1440, 900);
    await sleep(400);

    // =========================================================================
    // 3. Compact Horizontal Header Controls
    // =========================================================================
    console.log('\n--- 3. Compact Horizontal Header Controls Verification ---');
    const headerData = await evaluate(`(() => {
      const header = document.querySelector('.app-top-header');
      const logo = header ? header.querySelector('.brand-logo, .brand-name-text') : null;
      const searchInputs = Array.from(document.querySelectorAll('input[type="search"]')).filter(el => {
        const cs = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        return cs.display !== 'none' && cs.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
      });
      const homeShortcut = header ? header.querySelector('a[href="/"]') : null;
      const peopleShortcut = header ? header.querySelector('a[href="/authors"], a[href="/explore?tab=authors"]') : null;
      const messagesShortcut = header ? header.querySelector('a[href="/messages"]') : null;
      const notifBtn = document.getElementById('nav-notif-btn');
      const notifDropdown = document.getElementById('nav-notif-dropdown');
      const userMenuBtn = document.getElementById('header-user-menu-btn');

      return {
        headerPresent: !!header,
        logoText: logo ? logo.textContent.trim() : null,
        visibleSearchCount: searchInputs.length,
        searchPlaceholder: searchInputs[0]?.placeholder || null,
        homeShortcutPresent: !!homeShortcut,
        peopleShortcutPresent: !!peopleShortcut,
        messagesShortcutPresent: !!messagesShortcut,
        notifBtnPresent: !!notifBtn,
        notifDropdownPresent: !!notifDropdown,
        userMenuBtnPresent: !!userMenuBtn || !!document.querySelector('.header-auth-buttons')
      };
    })()`);

    assert(headerData.headerPresent, 'Compact top header (.app-top-header) is present in layout');
    assert(headerData.logoText && headerData.logoText.includes('Infinity Blog'), `Header displays Infinity Blog brand logo ("${headerData.logoText}")`);
    assert(headerData.visibleSearchCount === 1, `Exactly 1 visible search input across the homepage (got ${headerData.visibleSearchCount})`);
    assert(headerData.searchPlaceholder && headerData.searchPlaceholder.includes('Search posts, people, communities'), 'Search placeholder is "Search posts, people, communities…"');
    assert(headerData.homeShortcutPresent, 'Home navigation shortcut present in header');
    assert(headerData.peopleShortcutPresent, 'People/Authors shortcut present in header');
    assert(headerData.messagesShortcutPresent, 'Messages shortcut present in header');
    assert(headerData.notifBtnPresent, 'Notifications toggle button present in header');

    // Verification of Branding De-duplication
    const leftSidebarBrandCheck = await evaluate(`(() => {
      const leftSidebar = document.querySelector('.app-left-sidebar');
      const brandLogo = leftSidebar ? leftSidebar.querySelector('.brand-logo, .brand-name-text, .sidebar-brand-wrapper') : null;
      const firstGroup = leftSidebar ? leftSidebar.querySelector('.sidebar-nav-group:first-child .sidebar-group-title') : null;
      return {
        hasDuplicateLogo: !!brandLogo,
        firstGroupTitle: firstGroup ? firstGroup.textContent.trim() : null
      };
    })()`);
    assert(!leftSidebarBrandCheck.hasDuplicateLogo, 'Left sidebar no longer repeats the duplicate Infinity Blog logo or wordmark');
    assert(leftSidebarBrandCheck.firstGroupTitle === 'Discover', 'Left sidebar begins cleanly with the Discover navigation group');

    // =========================================================================
    // 3B. Dynamic Scroll Sticky Positioning Verification
    // =========================================================================
    console.log('\n--- 3B. Dynamic Scroll Sticky Positioning Verification ---');
    // Scroll down 600px through central feed cards
    await evaluate(`window.scrollTo(0, 600)`);
    await sleep(400);

    const scrolledData = await evaluate(`(() => {
      const header = document.querySelector('.app-top-header');
      const leftSidebar = document.querySelector('.app-left-sidebar');
      const rightSidebar = document.querySelector('.homepage-sidebar');
      const footer = document.querySelector('.site-footer-wrapper');

      const getRect = el => el ? el.getBoundingClientRect() : null;
      const headerRect = getRect(header);
      const leftRect = getRect(leftSidebar);
      const rightRect = getRect(rightSidebar);
      const footerRect = getRect(footer);

      return {
        scrollY: window.scrollY,
        headerTop: headerRect?.top,
        leftTop: Math.round(leftRect?.top || 0),
        rightTop: Math.round(rightRect?.top || 0),
        leftVisible: leftRect ? (leftRect.bottom > 0 && leftRect.top < window.innerHeight) : false,
        rightVisible: rightRect ? (rightRect.bottom > 0 && rightRect.top < window.innerHeight) : false,
        leftBelowHeader: leftRect && headerRect ? (leftRect.top >= headerRect.bottom - 2) : false,
        rightBelowHeader: rightRect && headerRect ? (rightRect.top >= headerRect.bottom - 2) : false,
        leftDoesNotCoverFooter: leftRect && footerRect ? (leftRect.bottom <= footerRect.top + 10) : true,
        rightDoesNotCoverFooter: rightRect && footerRect ? (rightRect.bottom <= footerRect.top + 10) : true
      };
    })()`);

    assert(scrolledData.scrollY >= 500, `Page scrolled successfully through feed cards (Y = ${scrolledData.scrollY}px)`);
    assert(scrolledData.leftVisible, 'Left sidebar remains visible in viewport while central feed scrolls');
    assert(scrolledData.leftBelowHeader, `Left sidebar stays positioned below the global header (top = ${scrolledData.leftTop}px)`);
    assert(scrolledData.rightVisible, 'Right sidebar remains visible in viewport while central feed scrolls');
    assert(scrolledData.rightBelowHeader, `Right sidebar stays positioned below the global header (top = ${scrolledData.rightTop}px)`);
    assert(scrolledData.leftDoesNotCoverFooter, 'Left sidebar stops naturally without covering the footer');
    assert(scrolledData.rightDoesNotCoverFooter, 'Right sidebar stops naturally without covering the footer');

    // Capture Scrolled Desktop Screenshot
    const scrolledScreenshotPath = path.join(ARTIFACTS_DIR, 'homepage_sticky_sidebars_scrolled_1440.png');
    await captureScreenshot(scrolledScreenshotPath);
    console.log(`  Saved scrolled sticky screenshot to: ${scrolledScreenshotPath}`);

    // Scroll back to top
    await evaluate(`window.scrollTo(0, 0)`);
    await sleep(300);

    // Test Notification Dropdown Toggle
    await evaluate(`(() => {
      const btn = document.getElementById('nav-notif-btn');
      if (btn) btn.click();
    })()`);
    await sleep(200);
    const notifOpen = await evaluate(`(() => {
      const dd = document.getElementById('nav-notif-dropdown');
      return dd ? dd.classList.contains('is-open') || window.getComputedStyle(dd).display !== 'none' : false;
    })()`);
    assert(notifOpen, 'Notification button clicks toggle the notification dropdown');

    // Close notification dropdown
    await evaluate(`(() => {
      const btn = document.getElementById('nav-notif-btn');
      if (btn) btn.click();
    })()`);

    // =========================================================================
    // 4. Central Community Feed Tabs (For You, Following, Trending, Latest)
    // =========================================================================
    console.log('\n--- 4. Central Feed Tabs Verification ---');
    const tabsData = await evaluate(`(() => {
      const tabs = Array.from(document.querySelectorAll('.feed-tab-btn, .feed-tab-item'));
      return {
        tabTexts: tabs.map(t => t.textContent.trim().replace(/\\s+/g, ' ')),
        activeTab: document.querySelector('.feed-tab-btn.active, .feed-tab-item.active')?.textContent.trim()
      };
    })()`);

    assert(tabsData.tabTexts.some(t => t.includes('For You')), 'Tab bar includes "For You" tab');
    assert(tabsData.tabTexts.some(t => t.includes('Following')), 'Tab bar includes "Following" tab');
    assert(tabsData.tabTexts.some(t => t.includes('Trending')), 'Tab bar includes "Trending" tab');
    assert(tabsData.tabTexts.some(t => t.includes('Latest')), 'Tab bar includes "Latest" tab');

    // Test Trending Tab Navigation
    await send('Page.navigate', { url: `${BASE_URL}/?tab=trending` });
    await sleep(1000);
    const trendingActive = await evaluate(`(() => {
      const active = document.querySelector('.feed-tab-btn.active, .feed-tab-item.active');
      return active ? active.textContent.includes('Trending') : false;
    })()`);
    assert(trendingActive, 'Navigating to ?tab=trending activates the Trending tab');

    // Test Following Tab Navigation
    await send('Page.navigate', { url: `${BASE_URL}/?tab=following` });
    await sleep(1000);
    const followingState = await evaluate(`(() => {
      const active = document.querySelector('.feed-tab-btn.active, .feed-tab-item.active');
      const prompt = document.querySelector('.empty-state-compact, .feed-guest-prompt, .following-feed-prompt');
      return {
        isActive: active ? active.textContent.includes('Following') : false,
        hasPrompt: !!prompt
      };
    })()`);
    assert(followingState.isActive, 'Navigating to ?tab=following activates the Following tab');
    assert(followingState.hasPrompt, 'Guest on Following tab receives friendly prompt to follow authors or sign in');

    // Return to main feed
    await send('Page.navigate', { url: `${BASE_URL}/` });
    await sleep(1000);

    // =========================================================================
    // 5. Post Cards Layout (Featured Media, Engagement Row, Header, Clickable Card)
    // =========================================================================
    console.log('\n--- 5. Post Cards Layout & Information Hierarchy ---');
    const postCardsData = await evaluate(`(() => {
      const cards = Array.from(document.querySelectorAll('.feed-card'));
      if (!cards.length) return null;
      const c = cards[0];

      const header = c.querySelector('.feed-card-header');
      const avatar = c.querySelector('.feed-author-avatar-link, .feed-card-avatar-wrap');
      const authorName = c.querySelector('.feed-author-name, .feed-card-author-name');
      const authorHandle = c.querySelector('.feed-author-handle, .feed-card-author-handle');
      const categoryTag = c.querySelector('.feed-card-category-tag');
      const timestamp = c.querySelector('.feed-card-timestamp');
      const menuBtn = c.querySelector('.feed-menu-btn, .feed-card-menu-btn');

      const title = c.querySelector('.feed-card-title');
      const excerpt = c.querySelector('.feed-card-excerpt');
      const featuredMedia = c.querySelector('.feed-card-featured-media');
      const featuredImg = c.querySelector('.feed-card-featured-img');

      const engagementRow = c.querySelector('.feed-card-engagement-summary');
      const actions = c.querySelector('.feed-card-actions');
      const likeBtn = c.querySelector('.btn-feed-like');
      const commentBtn = c.querySelector('.btn-feed-comment');
      const shareBtn = c.querySelector('.btn-feed-share');
      const bookmarkBtn = c.querySelector('.btn-feed-bookmark');

      return {
        cardCount: cards.length,
        hasHeader: !!header,
        hasAvatar: !!avatar,
        hasAuthorName: !!authorName,
        hasAuthorHandle: !!authorHandle,
        hasCategoryTag: !!categoryTag,
        hasTimestamp: !!timestamp,
        hasMenuBtn: !!menuBtn,
        hasTitle: !!title,
        hasExcerpt: !!excerpt,
        hasFeaturedMedia: !!featuredMedia,
        featuredImgWidth: featuredImg ? window.getComputedStyle(featuredImg).width : null,
        hasEngagementRow: !!engagementRow,
        engagementText: engagementRow ? engagementRow.textContent.trim().replace(/\\s+/g, ' ') : null,
        hasActions: !!actions,
        hasLikeBtn: !!likeBtn,
        hasCommentBtn: !!commentBtn,
        hasShareBtn: !!shareBtn,
        hasBookmarkBtn: !!bookmarkBtn,
        cursor: window.getComputedStyle(c).cursor
      };
    })()`);

    assert(postCardsData.cardCount > 0, `Homepage displays active feed cards (found ${postCardsData.cardCount})`);
    assert(postCardsData.hasHeader, 'Feed card has compact header row');
    assert(postCardsData.hasAvatar && postCardsData.hasAuthorName, 'Feed card header includes author avatar and display name');
    assert(postCardsData.hasAuthorHandle, 'Feed card header includes author @username handle');
    assert(postCardsData.hasCategoryTag && postCardsData.hasTimestamp, 'Feed card header includes category badge and relative timestamp');
    assert(postCardsData.hasMenuBtn, 'Feed card header includes three-dot menu button');
    assert(postCardsData.hasTitle && postCardsData.hasExcerpt, 'Feed card includes title and readable excerpt');
    assert(postCardsData.hasFeaturedMedia, 'Feed card displays full-width featured media (replacing tiny thumbnail)');
    assert(postCardsData.hasEngagementRow, `Feed card includes engagement summary row ("${postCardsData.engagementText}")`);
    assert(postCardsData.hasActions && postCardsData.hasLikeBtn && postCardsData.hasBookmarkBtn, 'Feed card includes complete action toolbar');
    assert(postCardsData.cursor === 'pointer', 'Entire feed card has pointer cursor indicating clickable article destination');

    // =========================================================================
    // 6. Right Discovery Sidebar (Popular Communities, People You Know, Trending Topics)
    // =========================================================================
    console.log('\n--- 6. Right Discovery Sidebar Verification ---');
    const sidebarData = await evaluate(`(() => {
      const cards = Array.from(document.querySelectorAll('.homepage-sidebar .sidebar-card'));
      const cardClasses = cards.map(c => {
        if (c.classList.contains('sidebar-card-communities')) return 'communities';
        if (c.classList.contains('sidebar-card-topics')) return 'topics';
        if (c.classList.contains('sidebar-card-authors')) return 'people';
        return 'unknown';
      });

      const commCard = document.querySelector('.sidebar-card-communities');
      const topicsCard = document.querySelector('.sidebar-card-topics');
      const peopleCard = document.querySelector('.sidebar-card-authors');

      const commItems = commCard ? Array.from(commCard.querySelectorAll('.sidebar-community-row, .sidebar-community-item, .community-sidebar-item')) : [];
      const topicItems = topicsCard ? Array.from(topicsCard.querySelectorAll('.sidebar-topic-row')) : [];
      const peopleItems = peopleCard ? Array.from(peopleCard.querySelectorAll('.sidebar-author-row, .sidebar-author-item')) : [];

      // Check for presence of old duplicate header links
      const headerLinks = Array.from(document.querySelectorAll('.homepage-sidebar .sidebar-card .sidebar-card-header-link'));

      // Check footer links
      const commFooter = commCard ? commCard.querySelector('.sidebar-card-footer-link')?.textContent.trim() : null;
      const topicsFooter = topicsCard ? topicsCard.querySelector('.sidebar-card-footer-link')?.textContent.trim() : null;
      const peopleFooter = peopleCard ? peopleCard.querySelector('.sidebar-card-footer-link')?.textContent.trim() : null;

      // Check CSS shimmer rule presence
      let hasShimmerKeyframe = false;
      let hasCardShimmerRule = false;
      for (const sheet of Array.from(document.styleSheets)) {
        try {
          for (const rule of Array.from(sheet.cssRules || [])) {
            if (rule.name === 'glassShimmerSweep') hasShimmerKeyframe = true;
            if (rule.selectorText && rule.selectorText.includes('.sidebar-card::after')) hasCardShimmerRule = true;
          }
        } catch (e) {}
      }

      return {
        cardOrder: cardClasses,
        headerLinksCount: headerLinks.length,
        hasCommCard: !!commCard,
        commTitle: commCard ? commCard.querySelector('.sidebar-card-title')?.textContent.trim() : null,
        commFooter,
        commCount: commItems.length,
        commNames: commItems.map(item => (item.querySelector('.sidebar-row-title')?.textContent || '').trim()),
        hasCommJoinBtn: commItems.some(item => !!item.querySelector('.btn-community-join')),

        hasTopicsCard: !!topicsCard,
        topicsTitle: topicsCard ? topicsCard.querySelector('.sidebar-card-title')?.textContent.trim() : null,
        topicsFooter,
        topicCount: topicItems.length,
        topicSample: topicItems[0] ? {
          rank: (topicItems[0].querySelector('.topic-rank-badge, .sidebar-topic-rank')?.textContent || '').trim(),
          name: (topicItems[0].querySelector('.sidebar-topic-name')?.textContent || '').trim(),
          count: (topicItems[0].querySelector('.sidebar-topic-meta, .sidebar-topic-count')?.textContent || '').trim()
        } : null,

        hasPeopleCard: !!peopleCard,
        peopleTitle: peopleCard ? peopleCard.querySelector('.sidebar-card-title')?.textContent.trim() : null,
        peopleFooter,
        peopleCount: peopleItems.length,
        hasFollowBtn: peopleItems.some(item => !!item.querySelector('.btn-follow-toggle')),

        hasShimmerKeyframe,
        hasCardShimmerRule
      };
    })()`);

    assert(sidebarData.headerLinksCount === 0, `All duplicate top-right header links are removed from sidebar cards (found ${sidebarData.headerLinksCount})`);

    assert(sidebarData.cardOrder[0] === 'people', 'Card 1 is People You Know', `Order: ${sidebarData.cardOrder.join(' -> ')}`);
    assert(sidebarData.hasPeopleCard, 'Card 1: People You Know card is present in right sidebar');
    assert(sidebarData.peopleTitle && sidebarData.peopleTitle.includes('People You Know'), `Card 1 title is full text "People You Know" without truncation ("${sidebarData.peopleTitle}")`);
    assert(sidebarData.peopleFooter && sidebarData.peopleFooter.includes('Discover Authors'), `Card 1 bottom call-to-action is Discover Authors → ("${sidebarData.peopleFooter}")`);
    assert(sidebarData.peopleCount > 0 && sidebarData.peopleCount <= 5, `People You Know shows up to 5 real authors (found ${sidebarData.peopleCount})`);
    assert(sidebarData.hasFollowBtn, 'People You Know includes Follow/Following action buttons');

    assert(sidebarData.cardOrder[1] === 'communities', 'Card 2 is Popular Communities', `Order: ${sidebarData.cardOrder.join(' -> ')}`);
    assert(sidebarData.hasCommCard, 'Card 2: Popular Communities card is present in right sidebar');
    assert(sidebarData.commTitle && sidebarData.commTitle.startsWith('Popular Communities'), `Card 2 title is full text "Popular Communities" without truncation ("${sidebarData.commTitle}")`);
    assert(sidebarData.commFooter && sidebarData.commFooter.includes('Explore Communities'), `Card 2 bottom call-to-action is Explore Communities → ("${sidebarData.commFooter}")`);
    assert(sidebarData.commCount > 0 && sidebarData.commCount <= 5, `Popular Communities shows up to 5 active rooms (found ${sidebarData.commCount})`);
    assert(sidebarData.hasCommJoinBtn, 'Communities card includes Join/Joined action buttons');
    assert(sidebarData.commNames.length > 0 && sidebarData.commNames.every(name => !name.startsWith('#')), `Community names in sidebar render without leading hash prefix (sample: "${sidebarData.commNames[0]}")`);

    assert(sidebarData.cardOrder[2] === 'topics', 'Card 3 is Trending Topics', `Order: ${sidebarData.cardOrder.join(' -> ')}`);
    assert(sidebarData.hasTopicsCard, 'Card 3: Trending Topics card is present in right sidebar');
    assert(sidebarData.topicsTitle && sidebarData.topicsTitle.startsWith('Trending Topics'), `Card 3 title is full text "Trending Topics" without truncation ("${sidebarData.topicsTitle}")`);
    assert(sidebarData.topicsFooter && sidebarData.topicsFooter.toLowerCase().includes('see all topics'), `Card 2 bottom call-to-action is See All Topics → ("${sidebarData.topicsFooter}")`);
    assert(sidebarData.topicCount > 0 && sidebarData.topicCount <= 5, `Trending Topics shows top 5 active categories (found ${sidebarData.topicCount})`);
    assert(sidebarData.topicSample && sidebarData.topicSample.rank === '1', `Trending Topics starts at Rank 1 derived from database post counts (got "${sidebarData.topicSample?.rank}")`);
    assert(sidebarData.topicSample && sidebarData.topicSample.count.includes('post'), `Trending Topics displays real database post counts ("${sidebarData.topicSample?.count}")`);
    assert(sidebarData.topicSample && sidebarData.topicSample.name.startsWith('#'), `Trending topics preserve genuine hashtag format (e.g. "${sidebarData.topicSample?.name}")`);

    assert(sidebarData.hasShimmerKeyframe, 'CSS includes @keyframes glassShimmerSweep animation definition');
    assert(sidebarData.hasCardShimmerRule, 'CSS includes .sidebar-card::after shimmer pseudo-element with sweep effect');

    // Verify /communities page clean names
    await send('Page.navigate', { url: `${BASE_URL}/communities` });
    await sleep(1000);
    const commPageData = await evaluate(`(() => {
      const titles = Array.from(document.querySelectorAll('.community-card h3')).map(h => h.textContent.trim());
      return { count: titles.length, titles, noHash: titles.every(t => !t.startsWith('#')) };
    })()`);
    assert(commPageData.count > 0, `Discovery page /communities lists active communities (found ${commPageData.count})`);
    assert(commPageData.noHash, `All community names on /communities page render without leading hash (sample: "${commPageData.titles[0]}")`);

    // Verify /chat page clean room names
    await send('Page.navigate', { url: `${BASE_URL}/chat` });
    await sleep(1000);
    const chatPageData = await evaluate(`(() => {
      const roomLinks = Array.from(document.querySelectorAll('a[href^="/chat/"] span[style*="font-weight: 700"]')).map(s => s.textContent.trim());
      const headerTitle = document.querySelector('h2')?.textContent.trim() || '';
      return { roomCount: roomLinks.length, roomLinks, headerTitle, noHash: roomLinks.every(r => !r.startsWith('#')) && !headerTitle.startsWith('#') };
    })()`);
    assert(chatPageData.roomCount > 0, `Chat page /chat lists topic rooms (found ${chatPageData.roomCount})`);
    assert(chatPageData.noHash, `Room directory and header on /chat render without leading hash (header: "${chatPageData.headerTitle}")`);

    // Return to homepage
    await send('Page.navigate', { url: `${BASE_URL}/` });
    await sleep(1000);

    // =========================================================================
    // 7. Full-Width Footer Verification
    // =========================================================================
    console.log('\n--- 7. Full-Width Footer Verification ---');
    const footerData = await evaluate(`(() => {
      const footerWrapper = document.querySelector('.site-footer-wrapper');
      const footer = document.querySelector('.site-footer');
      const twoColLayout = document.querySelector('.homepage-two-column-layout');

      const isOutsideGrid = !twoColLayout.contains(footerWrapper);
      const rect = footerWrapper ? footerWrapper.getBoundingClientRect() : null;

      return {
        footerWrapperPresent: !!footerWrapper,
        footerPresent: !!footer,
        isOutsideGrid,
        wrapperWidth: rect ? rect.width : 0,
        viewWidth: window.innerWidth,
        hasBrand: !!footer.querySelector('.footer-brand-col, .footer-brand-logo, .brand-name-text'),
        hasExploreLinks: !!footer.querySelector('.footer-nav-col, .footer-link-list, .footer-nav-link')
      };
    })()`);

    assert(footerData.footerWrapperPresent && footerData.footerPresent, 'Footer and full-width wrapper present in DOM');
    assert(footerData.isOutsideGrid, 'Footer is placed outside the 3-column feed/sidebar layout');
    assert(footerData.wrapperWidth >= footerData.viewWidth - 2, 'Footer spans full width across the viewport');
    assert(footerData.hasBrand && footerData.hasExploreLinks, 'Footer contains branding, concise description, and explore links');

    // =========================================================================
    // 8. Article Reading Experience & Dr. Strange AI Assistant
    // =========================================================================
    console.log('\n--- 8. Article Reading Experience & Dr. Strange AI Assistant ---');
    await send('Page.navigate', { url: `${BASE_URL}/article/7` });
    await sleep(1500);

    const articleData = await evaluate(`(() => {
      const readFullBtn = document.getElementById('tab-read-full');
      const listenFullBtn = document.getElementById('tab-listen-full');
      const focusModeBtn = document.getElementById('focusModeBtn');
      const bookmarkBtn = document.getElementById('bookmarkArticleBtn');
      const askAiBtn = document.getElementById('openAssistantBtn');
      const audioPlayer = document.getElementById('articleAudioPlayer');
      const waveform = document.getElementById('audioWaveformVisualizer');
      const assistantDrawer = document.getElementById('articleAssistantDrawer');
      const portalRing = document.querySelector('.portal-ring-svg');
      const drStrangeHeading = document.querySelector('.assistant-title');

      return {
        hasReadFull: !!readFullBtn,
        hasListenFull: !!listenFullBtn,
        hasFocusMode: !!focusModeBtn,
        hasBookmark: !!bookmarkBtn,
        hasAskAi: !!askAiBtn,
        hasAudioPlayer: !!audioPlayer,
        hasWaveform: !!waveform,
        waveformBarsCount: waveform ? waveform.querySelectorAll('.waveform-bar').length : 0,
        hasDrawer: !!assistantDrawer,
        hasPortalAvatar: !!portalRing,
        drawerTitle: drStrangeHeading ? drStrangeHeading.textContent.trim() : null
      };
    })()`);

    assert(articleData.hasReadFull && articleData.hasListenFull, 'Article toolbar contains Read Full and Listen Full controls');
    assert(articleData.hasFocusMode && articleData.hasBookmark, 'Article toolbar contains Focus Mode and Bookmark controls');
    assert(articleData.hasAskAi, 'Article toolbar contains Ask AI button');
    assert(articleData.hasAudioPlayer && articleData.hasWaveform, 'Article audio player and waveform visualizer are present');
    assert(articleData.waveformBarsCount >= 40, `Waveform visualizer has high-fidelity bars (found ${articleData.waveformBarsCount})`);
    assert(articleData.hasDrawer, 'Dr. Strange Assistant drawer exists in article markup');
    assert(articleData.hasPortalAvatar, 'Drawer contains Dr. Strange mystical portal ring visual identity');
    assert(articleData.drawerTitle && articleData.drawerTitle.includes('Dr. Strange'), `Drawer title is branded as Dr. Strange ("${articleData.drawerTitle}")`);

    // Test Opening Dr. Strange Drawer
    await evaluate(`(() => {
      const btn = document.getElementById('openAssistantBtn');
      if (btn) btn.click();
    })()`);
    await sleep(400);

    const drawerOpen = await evaluate(`(() => {
      const drawer = document.getElementById('articleAssistantDrawer');
      return drawer ? drawer.classList.contains('open') || window.getComputedStyle(drawer).display !== 'none' : false;
    })()`);
    assert(drawerOpen, 'Clicking "Ask AI" opens the Dr. Strange assistant drawer');

    // Close drawer
    await evaluate(`(() => {
      const closeBtn = document.querySelector('.assistant-close-btn');
      if (closeBtn) closeBtn.click();
    })()`);

    // Test Audio Player Listen Full toggle
    await evaluate(`(() => {
      const listenBtn = document.getElementById('tab-listen-full');
      if (listenBtn) listenBtn.click();
    })()`);
    await sleep(400);

    const playerVisible = await evaluate(`(() => {
      const player = document.getElementById('articleAudioPlayer');
      return player ? window.getComputedStyle(player).display !== 'none' : false;
    })()`);
    assert(playerVisible, 'Clicking "Listen Full" reveals the audio player and waveform');

    // =========================================================================
    // 9. Multi-Viewport Responsive Overflow Checks
    // =========================================================================
    console.log('\n--- 9. Comprehensive Multi-Viewport Overflow Matrix ---');
    const testViewports = [
      { w: 1920, h: 1080, name: '1920px Full HD' },
      { w: 1536, h: 864, name: '1536px Laptop Large' },
      { w: 1440, h: 900, name: '1440px Desktop Standard' },
      { w: 1280, h: 800, name: '1280px Small Desktop' },
      { w: 1024, h: 768, name: '1024px Tablet Landscape' },
      { w: 768, h: 1024, name: '768px Tablet Portrait' },
      { w: 430, h: 932, name: '430px iPhone Pro Max' },
      { w: 375, h: 667, name: '375px iPhone Standard' }
    ];

    await send('Page.navigate', { url: `${BASE_URL}/` });
    await sleep(1000);

    for (const vp of testViewports) {
      await setViewport(vp.w, vp.h);
      await sleep(250);
      const metrics = await evaluate(`(() => {
        return {
          docWidth: document.documentElement.scrollWidth,
          viewWidth: window.innerWidth
        };
      })()`);
      assert(metrics.docWidth <= metrics.viewWidth + 1, `Viewport ${vp.name} has zero horizontal overflow (doc: ${metrics.docWidth}px, view: ${metrics.viewWidth}px)`);
    }

    console.log('\n=============================================================');
    console.log(`TOTAL PASSED: ${passedTests} | TOTAL FAILED: ${failedTests}`);
    console.log('=============================================================\n');

  } catch (err) {
    console.error('Test execution failed with error:', err);
    failedTests++;
  } finally {
    try {
      ws.close();
      edgeProc.kill();
    } catch (e) {}
    try {
      fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
    } catch (e) {}
  }

  process.exit(failedTests > 0 ? 1 : 0);
}

run();
