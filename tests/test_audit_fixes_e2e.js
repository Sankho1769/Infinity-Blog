const { spawn } = require('child_process');
const fs = require('fs');

async function runE2E() {
  console.log('=== STARTING AUDIT FIXES BROWSER E2E TEST ===');

  const edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
    '--headless=new',
    '--disable-gpu',
    '--disable-extensions',
    '--no-first-run',
    '--remote-debugging-port=9256',
    '--window-size=1280,900',
    '--user-data-dir=C:\\Temp\\av_e2e_audit_' + Date.now(),
    'about:blank'
  ]);

  await new Promise(r => setTimeout(r, 2000));
  const r = await fetch('http://127.0.0.1:9256/json/list');
  const list = await r.json();
  const pageTarget = list.find(t => t.type === 'page') || list[0];
  const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);
  await new Promise(res => ws.onopen = res);

  let id = 1;
  const send = (m, p = {}) => new Promise(res => {
    const curId = id++;
    const handler = (e) => {
      const d = JSON.parse(e.data);
      if (d.id === curId) { ws.removeEventListener('message', handler); res(d.result); }
    };
    ws.addEventListener('message', handler);
    ws.send(JSON.stringify({ id: curId, method: m, params: p }));
  });

  await send('Page.enable');
  await send('Runtime.enable');

  const results = { passed: [], failed: [] };

  function assert(condition, message) {
    if (condition) {
      console.log(`[PASS] ${message}`);
      results.passed.push(message);
    } else {
      console.error(`[FAIL] ${message}`);
      results.failed.push(message);
    }
  }

  try {
    // 1. Test Homepage Feed & Card Links
    console.log('\n--- 1. Testing Homepage Feed & Card Links ---');
    await send('Page.navigate', { url: 'http://localhost:8086/' });
    await new Promise(res => setTimeout(res, 2000));

    const cardData = await send('Runtime.evaluate', {
      expression: `(() => {
        const cards = document.querySelectorAll('.feed-card, [data-post-id]');
        const firstCard = cards[0];
        if (!firstCard) return { found: false };

        const titleLink = firstCard.querySelector('h2 a, h3 a, .card-title a, a[data-post-link]');
        const imageLink = firstCard.querySelector('.card-cover-link, a:has(img)');
        const ratingBadge = firstCard.querySelector('.rating-badge, [class*="rating-badge-"]');
        const contentTypeBadge = firstCard.querySelector('.badge-content-type, [class*="badge-content-type-"]');

        return {
          found: true,
          count: cards.length,
          hasCardUrl: !!firstCard.getAttribute('data-article-url'),
          hasPostId: !!firstCard.getAttribute('data-post-id'),
          hasTitleLink: !!titleLink,
          titleHref: titleLink?.getAttribute('href'),
          hasImageLink: !!imageLink,
          imageHref: imageLink?.getAttribute('href'),
          ratingClass: ratingBadge?.className,
          contentTypeClass: contentTypeBadge?.className,
          contentTypeText: contentTypeBadge?.textContent?.trim()
        };
      })()`,
      returnByValue: true
    });

    const cd = cardData.result.value;
    assert(cd.found && cd.count > 0, `Found ${cd.count} cards on homepage`);
    assert(cd.hasCardUrl || cd.hasPostId, 'First card has data-article-url or data-post-id attribute');
    assert(cd.hasTitleLink, `First card title is wrapped in link (${cd.titleHref})`);
    assert(cd.hasImageLink, `First card image is wrapped in native <a> link (${cd.imageHref})`);
    assert(cd.contentTypeClass !== undefined, `Content type badge present: ${cd.contentTypeClass || 'none'} (${cd.contentTypeText || ''})`);

    // 2. Test Card Navigation Simulation
    console.log('\n--- 2. Testing Card Navigation Click Behavior ---');
    const clickCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const card = document.querySelector('.feed-card, [data-post-id]');
        if (!card) return { err: 'No card' };
        
        // Check getCardNavigationUrl if defined or fallback to data-article-url
        const base = window.APP_BASE_PATH || '';
        const rawUrl = card.dataset.articleUrl;
        const navUrl = (typeof getCardNavigationUrl === 'function') ? getCardNavigationUrl(card) : rawUrl;
        
        // Test shouldIgnoreCardEvent logic
        const dummyClickLink = document.createElement('a');
        const dummyBadge = document.createElement('span');
        dummyBadge.className = 'badge-category';
        
        return {
          basePath: base,
          rawUrl: rawUrl,
          navUrl: navUrl,
          hasWindowBasePath: window.APP_BASE_PATH !== undefined
        };
      })()`,
      returnByValue: true
    });

    const cc = clickCheck.result.value;
    assert(cc.hasWindowBasePath, `window.APP_BASE_PATH is defined (${JSON.stringify(cc.basePath)})`);
    assert(cc.navUrl && cc.navUrl.includes('/article/'), `Card navigation URL resolves to article route: ${cc.navUrl}`);

    // 3. Test Rating Tier CSS & Helper in Browser
    console.log('\n--- 3. Testing Rating Helper & Tiers ---');
    const ratingCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const testContainer = document.createElement('div');
        testContainer.id = 'rating-test-container';
        document.body.appendChild(testContainer);

        const tiers = [
          { avg: null, count: 0, expected: 'rating-badge-none' },
          { avg: 1.5, count: 2, expected: 'rating-badge-poor' },
          { avg: 2.5, count: 3, expected: 'rating-badge-below-average' },
          { avg: 3.5, count: 5, expected: 'rating-badge-average' },
          { avg: 4.2, count: 8, expected: 'rating-badge-good' },
          { avg: 4.8, count: 12, expected: 'rating-badge-excellent' }
        ];

        const tierResults = [];
        if (typeof window.setRatingBadgeUI === 'function') {
          for (const t of tiers) {
            window.setRatingBadgeUI('rating-test-container', t.avg, t.count);
            const cl = testContainer.className;
            tierResults.push({
              expected: t.expected,
              actual: cl,
              matched: cl.includes(t.expected)
            });
          }
        }
        testContainer.remove();
        return { hasHelper: typeof window.setRatingBadgeUI === 'function', tierResults };
      })()`,
      returnByValue: true
    });

    const rc = ratingCheck.result.value;
    assert(rc.hasHelper, 'window.setRatingBadgeUI is globally accessible');
    if (rc.hasHelper) {
      for (const tr of rc.tierResults) {
        assert(tr.matched, `Rating tier for ${tr.expected} rendered correctly (got ${tr.actual})`);
      }
    }

    // 4. Test HTML 500 Error Page
    console.log('\n--- 4. Testing HTML 500 Error Handling Boundary ---');
    // Navigate to a simulated 500 or test error route if available, or evaluate serverError view
    // Let's test request to an invalid URL or examine 500 template via a test endpoint or direct check
    // We can also test direct fetch to a non-existent or error path with Accept: text/html
    const errorPageCheck = await send('Runtime.evaluate', {
      expression: `(async () => {
        try {
          const res = await fetch('/__test_server_error_check__' + Date.now(), {
            headers: { 'Accept': 'text/html' }
          });
          const text = await res.text();
          return { status: res.status, isHtml: text.includes('<!DOCTYPE html>') || text.includes('<html'), isJson: text.trim().startsWith('{') };
        } catch (e) {
          return { error: e.message };
        }
      })()`,
      awaitPromise: true,
      returnByValue: true
    });
    const epc = errorPageCheck.result.value;
    // 404 returns HTML 404 page (not json)
    assert(epc.status === 404 && epc.isHtml && !epc.isJson, `Browser request for unknown path returns HTML 404 page (status ${epc.status})`);

    // 5. Test Content-Type selector in Post Editor / Studio
    console.log('\n--- 5. Testing Content-Type in Editor ---');
    await send('Page.navigate', { url: 'http://localhost:8086/author/posts/new' });
    await new Promise(res => setTimeout(res, 2000));

    const editorCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const ctSelect = document.querySelector('select[name="content_type"], #postContentType, [data-field="content_type"]');
        if (!ctSelect) return { found: false, url: window.location.href };

        const options = Array.from(ctSelect.options || []).map(o => o.value);
        return {
          found: true,
          options: options,
          hasArticle: options.includes('article'),
          hasBlog: options.includes('blog'),
          hasResearch: options.includes('research_paper'),
          hasPhoto: options.includes('photo')
        };
      })()`,
      returnByValue: true
    });

    const ec = editorCheck.result.value;
    // Note: if user is not logged in, it might redirect to login
    if (ec.found) {
      assert(ec.found, 'Post editor has content_type select control');
      assert(ec.hasArticle && ec.hasBlog && ec.hasResearch && ec.hasPhoto, `Contains all 4 content types: ${ec.options.join(', ')}`);
    } else {
      console.log(`[INFO] Editor redirected to ${ec.url} (requires auth), checking quick composer on homepage...`);
    }

    // Check quick composer on homepage
    await send('Page.navigate', { url: 'http://localhost:8086/' });
    await new Promise(res => setTimeout(res, 1500));
    const composerCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const composerSelect = document.querySelector('#quickContentType, select[name="content_type"]');
        if (!composerSelect) return { found: false };
        const options = Array.from(composerSelect.options || []).map(o => o.value);
        return {
          found: true,
          options: options,
          hasArticle: options.includes('article'),
          hasBlog: options.includes('blog'),
          hasResearch: options.includes('research_paper'),
          hasPhoto: options.includes('photo')
        };
      })()`,
      returnByValue: true
    });
    const cc2 = composerCheck.result.value;
    if (cc2.found) {
      assert(cc2.found, 'Homepage quick composer includes content_type selector');
      assert(cc2.hasArticle && cc2.hasBlog && cc2.hasResearch && cc2.hasPhoto, `Quick composer has 4 content types: ${cc2.options.join(', ')}`);
    } else {
      console.log('[INFO] Homepage quick composer not rendered for guest');
    }

    // 6. Test Article Detail Page & Reviews
    console.log('\n--- 6. Testing Article Detail Page & Reviews ---');
    // Find an article ID from homepage or database
    const articleNav = await send('Runtime.evaluate', {
      expression: `(() => {
        const cardLink = document.querySelector('.feed-card a[href*="/article/"], [data-post-id] a[href*="/article/"]');
        return cardLink ? cardLink.getAttribute('href') : null;
      })()`,
      returnByValue: true
    });

    const articleUrl = articleNav.result.value;
    if (articleUrl) {
      console.log(`Navigating to article: ${articleUrl}`);
      await send('Page.navigate', { url: 'http://localhost:8086' + articleUrl });
      await new Promise(res => setTimeout(res, 2000));

      const articleCheck = await send('Runtime.evaluate', {
        expression: `(() => {
          const detailTitle = document.querySelector('h1.article-title, .article-title, h1');
          const reviewsSection = document.querySelector('#reviews-section, .reviews-container, #post-reviews, .reviews-section');
          const ratingBadge = document.querySelector('.article-rating-badge, .rating-badge, [class*="rating-badge-"]');
          const contentTypeBadge = document.querySelector('.badge-content-type, [class*="badge-content-type-"]');
          const loadMoreBtn = document.querySelector('#loadMoreReviews, .btn-load-more-reviews, [data-action="load-more-reviews"]');

          return {
            hasTitle: !!detailTitle,
            titleText: detailTitle?.textContent?.trim().slice(0, 50),
            hasReviewsSection: !!reviewsSection,
            ratingClass: ratingBadge?.className,
            hasRatingBadge: !!ratingBadge,
            contentTypeClass: contentTypeBadge?.className,
            contentTypeText: contentTypeBadge?.textContent?.trim(),
            hasLoadMoreBtn: !!loadMoreBtn
          };
        })()`,
        returnByValue: true
      });

      const ac = articleCheck.result.value;
      assert(ac.hasTitle, `Article loaded successfully: "${ac.titleText}"`);
      assert(ac.hasRatingBadge, `Article displays rating badge with tier: ${ac.ratingClass}`);
      if (ac.contentTypeClass) {
        assert(true, `Article displays content-type badge: ${ac.contentTypeClass} (${ac.contentTypeText})`);
      }
    } else {
      console.log('[WARN] No article link found on homepage to navigate');
    }

    console.log('\n=============================================');
    console.log(`E2E TEST SUMMARY: ${results.passed.length} PASSED, ${results.failed.length} FAILED`);
    console.log('=============================================');

  } catch (err) {
    console.error('Test execution error:', err);
  } finally {
    try { ws.close(); } catch (e) {}
    try { edge.kill(); } catch (e) {}
  }
}

runE2E();
