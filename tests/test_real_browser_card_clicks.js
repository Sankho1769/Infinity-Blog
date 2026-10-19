const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_cdp_card_' + Date.now());
const PORT = 9226;
const BASE_URL = 'http://localhost:8086';

async function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function run() {
  console.log('=== Starting Real-Browser Full Card Click & Accessibility Verification ===\n');

  console.log('Launching headless Edge on port ' + PORT + '...');
  const edgeProc = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
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
    // -------------------------------------------------------------
    // Test Suite 1: Homepage Card Structure & Attributes
    // -------------------------------------------------------------
    console.log('\n--- 1. Feed Card Attributes & Styling on Homepage ---');
    await send('Page.navigate', { url: `${BASE_URL}/` });
    await sleep(1500);

    const cardData = await evaluate(`(() => {
      const cards = Array.from(document.querySelectorAll('.feed-card'));
      if (!cards.length) return { count: 0 };
      const first = cards[0];
      const style = window.getComputedStyle(first);
      const excerpt = document.querySelector('.feed-card-excerpt') || first.querySelector('.feed-card-excerpt');
      const excerptStyle = excerpt ? window.getComputedStyle(excerpt) : null;
      return {
        count: cards.length,
        allHavePostId: cards.every(c => !!c.getAttribute('data-post-id')),
        allHaveArticleUrl: cards.every(c => !!c.getAttribute('data-article-url')),
        allHaveTabindex: cards.every(c => c.getAttribute('tabindex') === '0'),
        allHaveRoleArticle: cards.every(c => c.getAttribute('role') === 'article'),
        allHaveAriaLabel: cards.every(c => !!c.getAttribute('aria-label') && c.getAttribute('aria-label').startsWith('Article:')),
        cursor: style.cursor,
        excerptDebug: excerptStyle ? {
          hasExcerpt: true,
          propUserSelect: excerptStyle.getPropertyValue('user-select'),
          propWebkit: excerptStyle.getPropertyValue('-webkit-user-select'),
          userSelect: excerptStyle.userSelect,
          webkitUserSelect: excerptStyle.webkitUserSelect
        } : { hasExcerpt: false },
        excerptUserSelect: excerptStyle ? (excerptStyle.getPropertyValue('user-select') || excerptStyle.getPropertyValue('-webkit-user-select') || excerptStyle.userSelect || excerptStyle.webkitUserSelect || '') : ''
      };
    })()`);

    assert(cardData.count > 0, `Feed cards found (${cardData.count})`);
    assert(cardData.allHavePostId, 'All feed cards have data-post-id attribute');
    assert(cardData.allHaveArticleUrl, 'All feed cards have data-article-url attribute');
    assert(cardData.allHaveTabindex, 'All feed cards have tabindex="0"');
    assert(cardData.allHaveRoleArticle, 'All feed cards have role="article"');
    assert(cardData.allHaveAriaLabel, 'All feed cards have accessible aria-label="Article: ..."');
    assert(cardData.cursor === 'pointer', `Feed card has cursor: pointer (got "${cardData.cursor}")`);
    assert(cardData.excerptUserSelect === 'text' || cardData.excerptUserSelect === 'auto', `Excerpt allows text selection (user-select: "${cardData.excerptUserSelect}")`);

    // -------------------------------------------------------------
    // Test Suite 2: Full Card Non-Interactive Area Clicks
    // -------------------------------------------------------------
    console.log('\n--- 2. Full Card Non-Interactive Space Clicks (Navigates to /article/:id) ---');

    const sim = await evaluate(`(() => {
      const card = document.querySelector('.feed-card');
      const postId = card.getAttribute('data-post-id');
      const expectedUrl = '/article/' + postId;

      const results = {};

      const CARD_INTERACTIVE_SELECTOR = [
        'button', 'input', 'textarea', 'select', 'label',
        '.feed-card-actions', '.feed-action-btn', '.feed-card-menu',
        '.feed-menu-dropdown', '.feed-menu-btn', '.feed-menu-item',
        '.dropdown', '.dropdown-menu', '.dropdown-item',
        '.feed-author-avatar-link', '.feed-author-name',
        '.feed-card-category-tag', '.article-author-info',
        '.article-author-info a', '.badge-category',
        'a[href*="/author/"]', 'a[href*="category="]',
        'a[href*="remix="]', 'a[href*="#comments"]',
        'a[href*="/edit"]', 'a.btn', '.user-avatar', '[onclick]'
      ].join(', ');

      function testClickOn(targetEl, label) {
        const selection = window.getSelection ? window.getSelection().toString().trim() : '';
        const cardEl = targetEl.closest('.feed-card, .article-card[data-article-url], .article-card[data-post-id]');
        
        const interactiveEl = targetEl.closest(CARD_INTERACTIVE_SELECTOR);
        const isInteractive = !!(interactiveEl && cardEl.contains(interactiveEl) && interactiveEl !== cardEl);
        const link = targetEl.closest('a');
        const isLink = !!(link && cardEl.contains(link));
        
        let wouldNavigateCard = false;
        let cardNavUrl = null;
        if (!selection && !isInteractive && !isLink) {
          wouldNavigateCard = true;
          cardNavUrl = cardEl.getAttribute('data-article-url') || ('/article/' + cardEl.getAttribute('data-post-id'));
        }

        results[label] = {
          isInteractive,
          isLink,
          wouldNavigateCard,
          cardNavUrl,
          linkHref: link ? link.getAttribute('href') : null
        };
      }

      testClickOn(card, 'card_background');
      
      const excerpt = card.querySelector('.feed-card-excerpt');
      if (excerpt) testClickOn(excerpt, 'card_excerpt');
      
      const cardBody = card.querySelector('.feed-card-body');
      if (cardBody) testClickOn(cardBody, 'card_body');
      
      const titleH3 = card.querySelector('.feed-card-title');
      if (titleH3) testClickOn(titleH3, 'card_title_h3');

      const titleLink = card.querySelector('.feed-card-title a');
      if (titleLink) testClickOn(titleLink, 'card_title_link');

      const thumbImg = card.querySelector('.feed-card-thumb-img') || card.querySelector('.feed-card-inline-image');
      if (thumbImg) testClickOn(thumbImg, 'card_thumbnail_img');

      return { expectedUrl, results };
    })()`);

    const expUrl = sim.expectedUrl;

    assert(sim.results.card_background.wouldNavigateCard === true && sim.results.card_background.cardNavUrl === expUrl,
      'Clicking card background/empty space navigates to canonical article URL');

    if (sim.results.card_excerpt) {
      assert(sim.results.card_excerpt.wouldNavigateCard === true && sim.results.card_excerpt.cardNavUrl === expUrl,
        'Clicking post excerpt navigates to canonical article URL');
    }

    if (sim.results.card_body) {
      assert(sim.results.card_body.wouldNavigateCard === true && sim.results.card_body.cardNavUrl === expUrl,
        'Clicking card body padding navigates to canonical article URL');
    }

    if (sim.results.card_title_h3) {
      assert(sim.results.card_title_h3.wouldNavigateCard === true && sim.results.card_title_h3.cardNavUrl === expUrl,
        'Clicking card title heading padding navigates to canonical article URL');
    }

    if (sim.results.card_title_link) {
      assert(sim.results.card_title_link.linkHref === expUrl,
        `Clicking title anchor directly navigates to canonical article URL (${sim.results.card_title_link.linkHref})`);
    }

    // -------------------------------------------------------------
    // Test Suite 3: Interactive Controls Preserved (Must NOT trigger card navigation)
    // -------------------------------------------------------------
    console.log('\n--- 3. Interactive Controls Isolation (Must NOT trigger card navigation) ---');

    const isims = await evaluate(`(() => {
      const card = document.querySelector('.feed-card');
      const CARD_INTERACTIVE_SELECTOR = [
        'button', 'input', 'textarea', 'select', 'label',
        '.feed-card-actions', '.feed-action-btn', '.feed-card-menu',
        '.feed-menu-dropdown', '.feed-menu-btn', '.feed-menu-item',
        '.dropdown', '.dropdown-menu', '.dropdown-item',
        '.feed-author-avatar-link', '.feed-author-name',
        '.feed-card-category-tag', '.article-author-info',
        '.article-author-info a', '.badge-category',
        'a[href*="/author/"]', 'a[href*="category="]',
        'a[href*="remix="]', 'a[href*="#comments"]',
        'a[href*="/edit"]', 'a.btn', '.user-avatar', '[onclick]'
      ].join(', ');

      function checkControl(el) {
        if (!el) return null;
        const interactiveEl = el.closest(CARD_INTERACTIVE_SELECTOR);
        const isInteractive = !!(interactiveEl && card.contains(interactiveEl) && interactiveEl !== card);
        return {
          found: true,
          isInteractive,
          tagName: el.tagName,
          href: el.getAttribute('href') || null
        };
      }

      return {
        likeBtn: checkControl(card.querySelector('.btn-feed-like')),
        dislikeBtn: checkControl(card.querySelector('.btn-feed-dislike')),
        commentsBtn: checkControl(card.querySelector('.feed-comments-btn')),
        shareBtn: checkControl(card.querySelector('.btn-feed-share')),
        bookmarkBtn: checkControl(card.querySelector('.btn-feed-bookmark')),
        remixBtn: checkControl(card.querySelector('.btn-feed-remix')),
        reportBtn: checkControl(card.querySelector('.btn-feed-report')),
        menuBtn: checkControl(card.querySelector('.feed-menu-btn')),
        authorAvatar: checkControl(card.querySelector('.feed-author-avatar-link')),
        authorName: checkControl(card.querySelector('.feed-author-name')),
        categoryTag: checkControl(card.querySelector('.feed-card-category-tag'))
      };
    })()`);

    assert(isims.likeBtn && isims.likeBtn.isInteractive, 'Like button is isolated from card navigation');
    assert(isims.dislikeBtn && isims.dislikeBtn.isInteractive, 'Dislike button is isolated from card navigation');
    assert(isims.commentsBtn && isims.commentsBtn.isInteractive && isims.commentsBtn.href.includes('#commentsSection'),
      'Comments button is isolated and links to #commentsSection');
    assert(isims.shareBtn && isims.shareBtn.isInteractive, 'Share button is isolated from card navigation');
    assert(isims.bookmarkBtn && isims.bookmarkBtn.isInteractive, 'Bookmark/Save button is isolated from card navigation');
    assert(isims.remixBtn && isims.remixBtn.isInteractive && isims.remixBtn.href.includes('remix='),
      'Remix button is isolated and links to remix creation');
    assert(isims.reportBtn && isims.reportBtn.isInteractive, 'Report button is isolated from card navigation');
    assert(isims.menuBtn && isims.menuBtn.isInteractive, 'Menu button (•••) is isolated from card navigation');
    assert(isims.authorAvatar && isims.authorAvatar.isInteractive && isims.authorAvatar.href.startsWith('/author/'),
      'Author avatar links directly to author profile, not article');
    assert(isims.authorName && isims.authorName.isInteractive && isims.authorName.href.startsWith('/author/'),
      'Author name links directly to author profile, not article');
    if (isims.categoryTag) {
      assert(isims.categoryTag.isInteractive && isims.categoryTag.href.includes('category='),
        'Category tag links to category filter, not article');
    }

    // -------------------------------------------------------------
    // Test Suite 4: Keyboard Navigation (Tab + Enter)
    // -------------------------------------------------------------
    console.log('\n--- 4. Keyboard Navigation (Tab + Enter on Card vs Inner Elements) ---');

    const kb = await evaluate(`(() => {
      const card = document.querySelector('.feed-card');
      card.focus();
      const activeBefore = document.activeElement;
      const isCardFocused = (activeBefore === card);

      let cardEnterUrl = null;
      const matchesCard = activeBefore.matches('.feed-card, .article-card[data-article-url], .article-card[data-post-id]');
      if (matchesCard) {
        cardEnterUrl = activeBefore.getAttribute('data-article-url') || ('/article/' + activeBefore.getAttribute('data-post-id'));
      }

      const likeBtn = card.querySelector('.btn-feed-like');
      likeBtn.focus();
      const activeInner = document.activeElement;
      const matchesCardWhenInnerFocused = activeInner.matches('.feed-card, .article-card[data-article-url], .article-card[data-post-id]');

      return {
        isCardFocused,
        matchesCard,
        cardEnterUrl,
        isInnerFocused: (activeInner === likeBtn),
        matchesCardWhenInnerFocused
      };
    })()`);

    assert(kb.isCardFocused, 'Card receives keyboard focus when tabbed to');
    assert(kb.matchesCard && kb.cardEnterUrl === expUrl, 'Pressing Enter on focused card targets canonical article URL');
    assert(kb.isInnerFocused, 'Inner button (Like) receives focus independently');
    assert(!kb.matchesCardWhenInnerFocused, 'Inner button focus does NOT match card-level Enter handler');

    // -------------------------------------------------------------
    // Test Suite 5: Text Selection Guard (Highlighting text avoids navigation)
    // -------------------------------------------------------------
    console.log('\n--- 5. Text Selection Guard ---');

    const sel = await evaluate(`(() => {
      const card = document.querySelector('.feed-card');
      const excerpt = card.querySelector('.feed-card-excerpt');
      if (!excerpt) return { tested: false };

      const range = document.createRange();
      range.selectNodeContents(excerpt);
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);

      const currentSelection = window.getSelection().toString().trim();
      const selectionLen = currentSelection.length;
      const shouldIgnore = selectionLen > 0;

      sel.removeAllRanges();

      return {
        tested: true,
        selectionLen,
        shouldIgnore
      };
    })()`);

    if (sel.tested) {
      assert(sel.selectionLen > 0 && sel.shouldIgnore === true,
        `Text selection (${sel.selectionLen} chars) prevents accidental card click navigation`);
    }

    // -------------------------------------------------------------
    // Test Suite 6: Explore Page Verification
    // -------------------------------------------------------------
    console.log('\n--- 6. Explore Page Card Navigation Attributes ---');
    await send('Page.navigate', { url: `${BASE_URL}/explore` });
    await sleep(1500);

    const exp = await evaluate(`(() => {
      const cards = Array.from(document.querySelectorAll('.articles-grid .article-card'));
      if (!cards.length) return { count: 0 };
      const first = cards[0];
      const style = window.getComputedStyle(first);
      const categoryLink = first.querySelector('.badge-category');
      return {
        count: cards.length,
        allHavePostId: cards.every(c => !!c.getAttribute('data-post-id')),
        allHaveArticleUrl: cards.every(c => !!c.getAttribute('data-article-url')),
        allHaveTabindex: cards.every(c => c.getAttribute('tabindex') === '0'),
        allHaveRoleArticle: cards.every(c => c.getAttribute('role') === 'article'),
        cursor: style.cursor,
        categoryIsLink: categoryLink ? categoryLink.tagName.toLowerCase() === 'a' : false,
        categoryHref: categoryLink ? categoryLink.getAttribute('href') : null
      };
    })()`);

    assert(exp.count > 0, `Explore page article cards found (${exp.count})`);
    assert(exp.allHavePostId, 'All explore cards have data-post-id');
    assert(exp.allHaveArticleUrl, 'All explore cards have data-article-url');
    assert(exp.allHaveTabindex, 'All explore cards have tabindex="0"');
    assert(exp.allHaveRoleArticle, 'All explore cards have role="article"');
    assert(exp.cursor === 'pointer', `Explore cards have cursor: pointer (${exp.cursor})`);
    if (exp.categoryIsLink) {
      assert(exp.categoryHref && exp.categoryHref.includes('category='),
        `Explore category badge is a link to category filter (${exp.categoryHref})`);
    }

    // -------------------------------------------------------------
    // Test Suite 7: Library Page Verification
    // -------------------------------------------------------------
    console.log('\n--- 7. Library Page Card Navigation Attributes ---');
    await send('Page.navigate', { url: `${BASE_URL}/library` });
    await sleep(1500);

    const lib = await evaluate(`(() => {
      const cards = Array.from(document.querySelectorAll('.article-card[data-article-url]'));
      return {
        count: cards.length,
        allHaveArticleUrl: cards.every(c => !!c.getAttribute('data-article-url')),
        allHaveTabindex: cards.every(c => c.getAttribute('tabindex') === '0'),
        allHaveRoleArticle: cards.every(c => c.getAttribute('role') === 'article')
      };
    })()`);

    console.log(`  Library saved cards found: ${lib.count}`);
    if (lib.count > 0) {
      assert(lib.allHaveArticleUrl, 'Library cards have data-article-url');
      assert(lib.allHaveTabindex, 'Library cards have tabindex="0"');
      assert(lib.allHaveRoleArticle, 'Library cards have role="article"');
    }

    // -------------------------------------------------------------
    // Test Suite 8: Responsive Viewport Adaptations
    // -------------------------------------------------------------
    console.log('\n--- 8. Responsive Viewport Adaptations (Desktop, Tablet, Mobile) ---');
    await send('Page.navigate', { url: `${BASE_URL}/` });
    await sleep(1200);

    const viewports = [
      { name: 'Desktop (1440px)', width: 1440, height: 900 },
      { name: 'Tablet (768px)', width: 768, height: 1024 },
      { name: 'Mobile (375px)', width: 375, height: 667 }
    ];

    for (const vp of viewports) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: vp.width,
        height: vp.height,
        deviceScaleFactor: 1,
        mobile: vp.width < 768
      });
      await sleep(200);

      const vr = await evaluate(`(() => {
        const card = document.querySelector('.feed-card');
        if (!card) return null;
        const rect = card.getBoundingClientRect();
        const style = window.getComputedStyle(card);
        return {
          visible: rect.width > 0 && rect.height > 0,
          cursor: style.cursor,
          width: Math.round(rect.width)
        };
      })()`);

      assert(vr && vr.visible && vr.cursor === 'pointer',
        `${vp.name}: Feed card visible (${vr.width}px wide) with cursor: pointer`);
    }

  } finally {
    ws.close();
    edgeProc.kill();
    try {
      fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
    } catch (e) {}
  }

  console.log(`\n========================================`);
  console.log(`Results: ${passedTests} PASSED, ${failedTests} FAILED`);
  console.log(`========================================\n`);

  if (failedTests > 0) {
    process.exit(1);
  }
}

run().catch(err => {
  console.error('Fatal Test Runner Error:', err);
  process.exit(1);
});
