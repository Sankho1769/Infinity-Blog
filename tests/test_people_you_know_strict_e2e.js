const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_pyn_' + Date.now());
const PORT = 9247;
const ARTIFACT_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function createTestSession(userId) {
  const sessionId = crypto.randomBytes(16).toString('hex');
  const time = Math.floor(Date.now() / 1000);
  const csrf = crypto.randomBytes(32).toString('hex');
  const authVersion = (userId === 2) ? 2 : 1;
  const sessData = `auth|a:4:{s:7:"user_id";i:${userId};s:12:"auth_version";i:${authVersion};s:9:"issued_at";i:${time};s:13:"last_activity";i:${time};}csrf_token|s:64:"${csrf}";`;
  const sessPath = path.join('C:\\xampp\\tmp', 'sess_' + sessionId);
  fs.writeFileSync(sessPath, sessData);
  return { sessionId, csrf };
}

async function runPeopleYouKnowVerification() {
  console.log('=== STARTING STRICT PEOPLE YOU KNOW REGRESSION & AVATAR VERIFICATION ===\n');

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

  async function setSessionCookie(sessionId) {
    if (!sessionId) {
      await send('Network.clearBrowserCookies');
      return;
    }
    await send('Network.setCookie', {
      url: 'http://localhost:8086',
      name: 'INFINITYBLOGSESSID',
      value: sessionId,
      domain: 'localhost',
      path: '/'
    });
  }

  async function navigate(url, waitMs = 1500) {
    await send('Page.navigate', { url });
    await new Promise(r => setTimeout(r, waitMs));
  }

  async function evalJs(expr) {
    const res = await send('Runtime.evaluate', { returnByValue: true, expression: expr });
    return res && res.result ? res.result.value : null;
  }

  async function takeScreenshot(filename) {
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    const filePath = path.join(ARTIFACT_DIR, filename);
    fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));
    console.log(`  [SCREENSHOT SAVED] -> ${filename}`);
  }

  let totalPassed = 0;
  let totalFailed = 0;

  function assert(condition, message) {
    if (condition) {
      console.log(`  [PASS] ${message}`);
      totalPassed++;
    } else {
      console.error(`  [FAIL] ${message}`);
      totalFailed++;
    }
  }

  try {
    // -------------------------------------------------------------
    // Test Accounts:
    // Account A: User 13 (Cristine Palmer, username: cristine)
    // Account B: User 43 (Shubham Banerjee, username: shubham_banerjee_5)
    // Account C: User 2 (Shubham Banerjee, username: shubham_banerjee, admin)
    // -------------------------------------------------------------
    const sessionA = createTestSession(13);
    const sessionB = createTestSession(43);
    const sessionC = createTestSession(2);

    // =============================================================
    // STEP 1: Account A (User 13) Verification
    // =============================================================
    console.log('--- Step 1: Account A (User 13: cristine) ---');
    await setSessionCookie(sessionA.sessionId);
    await navigate('http://localhost:8086/');

    const serverContextA = await evalJs(`
      (() => {
        const link = document.querySelector('.header-dropdown-link[href="/profile"]');
        const handleEl = document.querySelector('.header-user-handle');
        const loggedIn = document.body.getAttribute('data-user-logged-in') === '1';
        return {
          loggedIn,
          handle: handleEl ? handleEl.textContent.trim() : null
        };
      })()
    `);
    console.log('  Server context User 13:', serverContextA);
    assert(serverContextA.loggedIn, 'User 13 authenticated in server context');

    const pynCardsA = await evalJs(`
      (() => {
        const rows = Array.from(document.querySelectorAll('.sidebar-author-row'));
        return rows.map(r => ({
          authorId: parseInt(r.getAttribute('data-author-id'), 10),
          name: r.querySelector('.sidebar-row-title')?.textContent.trim() || '',
          handle: r.querySelector('.sidebar-row-sub')?.textContent.trim() || '',
          hasImg: !!r.querySelector('.sidebar-avatar-img'),
          imgSrc: r.querySelector('.sidebar-avatar-img')?.src || null,
          imgWidth: r.querySelector('.sidebar-avatar-img')?.naturalWidth || 0,
          hasFallback: !!r.querySelector('.sidebar-avatar-fallback'),
          fallbackDisplay: r.querySelector('.sidebar-avatar-fallback') ? getComputedStyle(r.querySelector('.sidebar-avatar-fallback')).display : null,
          fallbackInitial: r.querySelector('.sidebar-avatar-fallback')?.textContent.trim() || ''
        }));
      })()
    `);

    console.log('  People You Know cards for User 13:');
    pynCardsA.forEach(c => console.log(`    - ID ${c.authorId}: "${c.name}" (${c.handle}) | hasImg: ${c.hasImg} (width: ${c.imgWidth}px), fallbackInitial: "${c.fallbackInitial}"`));

    const user13Present = pynCardsA.some(c => c.authorId === 13);
    assert(!user13Present, 'Strict Assertion: Canonical ID 13 (cristine) is strictly ABSENT from People You Know (self-exclusion)');
    const user9PresentInA = pynCardsA.some(c => c.authorId === 9);
    assert(!user9PresentInA, 'Strict Assertion: Test account User 9 (cristine_palmer) is strictly ABSENT from People You Know');
    assert(pynCardsA.length > 0, `People You Know returns ${pynCardsA.length} recommendations`);

    const allTestAccountIds = [6, 9, 10, 11, 12, 20, 22, 23, 32, 33, 34, 38, 39, 40, 41, 42];
    const anyTestAccountInA = pynCardsA.some(c => allTestAccountIds.includes(c.authorId));
    assert(!anyTestAccountInA, 'Strict Assertion: No automated test accounts appear in recommendations for User 13');

    await takeScreenshot('pyn_account_a_user13.png');

    // =============================================================
    // STEP 2: Account B (User 43) Verification
    // =============================================================
    console.log('\n--- Step 2: Account B (User 43: shubham_banerjee_5) ---');
    await setSessionCookie(sessionB.sessionId);
    await navigate('http://localhost:8086/');

    const pynCardsB = await evalJs(`
      (() => {
        const rows = Array.from(document.querySelectorAll('.sidebar-author-row'));
        return rows.map(r => ({
          authorId: parseInt(r.getAttribute('data-author-id'), 10),
          name: r.querySelector('.sidebar-row-title')?.textContent.trim() || '',
          handle: r.querySelector('.sidebar-row-sub')?.textContent.trim() || '',
          hasImg: !!r.querySelector('.sidebar-avatar-img'),
          imgSrc: r.querySelector('.sidebar-avatar-img')?.src || null,
          imgWidth: r.querySelector('.sidebar-avatar-img')?.naturalWidth || 0,
          hasFallback: !!r.querySelector('.sidebar-avatar-fallback')
        }));
      })()
    `);

    console.log('  People You Know cards for User 43:');
    pynCardsB.forEach(c => console.log(`    - ID ${c.authorId}: "${c.name}" (${c.handle}) | hasImg: ${c.hasImg}`));

    const user43Present = pynCardsB.some(c => c.authorId === 43);
    assert(!user43Present, 'Strict Assertion: Canonical ID 43 is strictly ABSENT from People You Know (self-exclusion)');
    assert(pynCardsB.length > 0, `People You Know returns ${pynCardsB.length} recommendations`);

    const user13CardInB = pynCardsB.find(c => c.authorId === 13);
    assert(!!user13CardInB, 'User 13 (Cristine Palmer) appears as a recommendation for User 43');
    if (user13CardInB) {
      assert(user13CardInB.hasImg, 'User 13 displays her authentic uploaded avatar image');
    }

    const user9PresentInB = pynCardsB.some(c => c.authorId === 9);
    assert(!user9PresentInB, 'Test account User 9 does NOT appear for User 43');

    const anyTestAccountInB = pynCardsB.some(c => allTestAccountIds.includes(c.authorId));
    assert(!anyTestAccountInB, 'Strict Assertion: No automated test accounts appear in recommendations for User 43');

    await takeScreenshot('pyn_account_b_user43.png');

    // =============================================================
    // STEP 3: Account C (User 2) Verification
    // =============================================================
    console.log('\n--- Step 3: Account C (User 2: shubham_banerjee admin) ---');
    await setSessionCookie(sessionC.sessionId);
    await navigate('http://localhost:8086/');

    const pynCardsC = await evalJs(`
      (() => {
        const rows = Array.from(document.querySelectorAll('.sidebar-author-row'));
        return rows.map(r => ({
          authorId: parseInt(r.getAttribute('data-author-id'), 10),
          name: r.querySelector('.sidebar-row-title')?.textContent.trim() || '',
          handle: r.querySelector('.sidebar-row-sub')?.textContent.trim() || '',
          hasImg: !!r.querySelector('.sidebar-avatar-img'),
          imgSrc: r.querySelector('.sidebar-avatar-img')?.src || null,
          imgWidth: r.querySelector('.sidebar-avatar-img')?.naturalWidth || 0,
          hasFallback: !!r.querySelector('.sidebar-avatar-fallback')
        }));
      })()
    `);

    console.log('  People You Know cards for User 2:');
    pynCardsC.forEach(c => console.log(`    - ID ${c.authorId}: "${c.name}" (${c.handle}) | hasImg: ${c.hasImg}`));

    const user2Present = pynCardsC.some(c => c.authorId === 2);
    assert(!user2Present, 'Strict Assertion: Canonical ID 2 is strictly ABSENT from People You Know (self-exclusion)');
    assert(pynCardsC.length > 0, `People You Know returns ${pynCardsC.length} recommendations`);

    const user9PresentInC = pynCardsC.some(c => c.authorId === 9);
    assert(!user9PresentInC, 'Test account User 9 does NOT appear for Admin User 2');

    const user13CardInC = pynCardsC.find(c => c.authorId === 13);
    assert(!!user13CardInC, 'User 13 appears as a discoverable recommendation for Admin User 2');
    if (user13CardInC) {
      assert(user13CardInC.hasImg, 'User 13 displays her authentic uploaded avatar image in User 2 recommendations');
    }

    const anyTestAccountInC = pynCardsC.some(c => allTestAccountIds.includes(c.authorId));
    assert(!anyTestAccountInC, 'Strict Assertion: No automated test accounts appear in recommendations for User 2');

    await takeScreenshot('pyn_account_c_user2.png');

    // =============================================================
    // STEP 4: Switch Back to Account A & Refresh
    // =============================================================
    console.log('\n--- Step 4: Account Switching & Refresh Consistency ---');
    await setSessionCookie(sessionA.sessionId);
    await navigate('http://localhost:8086/');

    const pynCardsARefresh = await evalJs(`
      (() => {
        return Array.from(document.querySelectorAll('.sidebar-author-row')).map(r =>
          parseInt(r.getAttribute('data-author-id'), 10)
        );
      })()
    `);

    assert(!pynCardsARefresh.includes(13), 'Canonical ID 13 remains strictly absent after switching back and refreshing');

    // =============================================================
    // STEP 5: Follow / Unfollow Dynamic Interaction Test
    // =============================================================
    console.log('\n--- Step 5: Follow / Unfollow Dynamic Interaction ---');
    // Pick first recommended author
    const targetAuthorId = pynCardsARefresh[0];
    console.log(`  Testing follow toggle on author ID ${targetAuthorId}...`);

    const initialBtnState = await evalJs(`
      (() => {
        const btn = document.querySelector('.sidebar-author-row[data-author-id="${targetAuthorId}"] .btn-follow-toggle');
        const count = document.querySelector('.author-follower-count[data-author-id="${targetAuthorId}"]');
        return {
          text: btn ? btn.textContent.trim() : null,
          following: btn ? btn.getAttribute('data-following') : null,
          count: count ? parseInt(count.textContent.trim(), 10) : 0
        };
      })()
    `);
    console.log('  Initial button state:', initialBtnState);

    // Click follow button
    await evalJs(`
      (() => {
        const btn = document.querySelector('.sidebar-author-row[data-author-id="${targetAuthorId}"] .btn-follow-toggle');
        if (btn) btn.click();
      })()
    `);

    await new Promise(r => setTimeout(r, 1200));

    const toggledBtnState = await evalJs(`
      (() => {
        const btn = document.querySelector('.sidebar-author-row[data-author-id="${targetAuthorId}"] .btn-follow-toggle');
        const count = document.querySelector('.author-follower-count[data-author-id="${targetAuthorId}"]');
        return {
          text: btn ? btn.textContent.trim() : null,
          following: btn ? btn.getAttribute('data-following') : null,
          count: count ? parseInt(count.textContent.trim(), 10) : 0
        };
      })()
    `);
    console.log('  Toggled button state:', toggledBtnState);
    assert(toggledBtnState.following !== initialBtnState.following, 'Follow button state updated dynamically via AJAX');

    // Toggle back to preserve original state
    await evalJs(`
      (() => {
        const btn = document.querySelector('.sidebar-author-row[data-author-id="${targetAuthorId}"] .btn-follow-toggle');
        if (btn) btn.click();
      })()
    `);
    await new Promise(r => setTimeout(r, 1200));

    // =============================================================
    // STEP 6: Avatar Fallback Audit for Tester Author 93 (User 22)
    // =============================================================
    console.log('\n--- Step 6: Avatar Fallback Audit for Tester Author 93 (User 22) ---');
    const u22Card = pynCardsA.find(c => c.authorId === 22);
    if (u22Card) {
      console.log('  User 22 Card details:', u22Card);
      assert(!u22Card.hasImg, 'User 22 does NOT render a synthetic dummy <img> tag');
      assert(u22Card.hasFallback && u22Card.fallbackInitial === 'T', 'User 22 renders the clean initials-based avatar fallback with "T"');
    } else {
      console.log('  User 22 not in top 5 recommendations of Account A; testing profile directly...');
      await navigate('http://localhost:8086/author/22');
      const author22Avatar = await evalJs(`
        (() => {
          const img = document.querySelector('.author-profile-avatar-img, .author-header-avatar img');
          const fallback = document.querySelector('.author-avatar-fallback, .user-avatar');
          return {
            hasImg: !!img && img.style.display !== 'none',
            hasFallback: !!fallback,
            initial: fallback ? fallback.textContent.trim() : ''
          };
        })()
      `);
      console.log('  User 22 author profile avatar:', author22Avatar);
      assert(!author22Avatar.hasImg, 'User 22 profile does not display 1x1 green dummy image');
      assert(author22Avatar.hasFallback, 'User 22 profile displays clean initial fallback');
    }

    // =============================================================
    // STEP 7: Community Join Navigation Audit
    // =============================================================
    console.log('\n--- Step 7: Community Join Navigation Audit ---');
    await navigate('http://localhost:8086/communities');
    const firstCommunitySlug = await evalJs(`
      (() => {
        const firstCard = document.querySelector('.btn-community-join');
        return firstCard ? firstCard.getAttribute('data-slug') : 'web-development';
      })()
    `);
    console.log(`  Testing navigation for community slug "${firstCommunitySlug}"...`);

    // Click "Join Community" or "Discussion Feed"
    await evalJs(`
      (() => {
        const btn = document.querySelector('.btn-community-join[data-slug="${firstCommunitySlug}"]');
        if (btn) btn.click();
      })()
    `);

    await new Promise(r => setTimeout(r, 2000));
    const finalCommunityUrl = await evalJs(`window.location.pathname`);
    console.log('  Navigated URL after join/open:', finalCommunityUrl);
    assert(finalCommunityUrl.includes('/community/'), 'Community navigation lands on /community/{slug}');

    const communityDetail = await evalJs(`
      (() => {
        const h1 = document.querySelector('.community-detail-container h1');
        const feed = document.querySelector('.community-discussion-feed');
        const cards = document.querySelectorAll('.community-discussion-feed .feed-card');
        const chatLink = document.querySelector('a[href^="/chat/"]');
        const joinBtn = document.querySelector('.btn-community-join');
        return {
          title: h1 ? h1.textContent.trim() : null,
          hasFeed: !!feed,
          postCardCount: cards.length,
          hasChatLink: !!chatLink,
          isMember: joinBtn ? joinBtn.getAttribute('data-is-member') === '1' : false
        };
      })()
    `);
    console.log('  Community detail page data:', communityDetail);
    assert(!!communityDetail.title, 'Community detail page displays community title');
    assert(communityDetail.hasFeed, 'Community detail page displays discussion feed');
    assert(communityDetail.hasChatLink, 'Community detail page provides direct link to live chat room');
    await takeScreenshot('community_discussion_navigation.png');

    // Also verify navigation into the Live Text Chat room
    console.log('\n--- Step 8: Community Live Chat Room Audit ---');
    await navigate(`http://localhost:8086/chat/${firstCommunitySlug}`);
    const chatRoomDetails = await evalJs(`
      (() => {
        const roomHeader = document.querySelector('.chat-room-header h2');
        const stream = document.getElementById('chatMessageStream');
        const composer = document.getElementById('chatInputMessage') || document.getElementById('chatJoinBtn');
        const commLink = document.querySelector('a[href^="/community/"]');
        return {
          roomTitle: roomHeader ? roomHeader.textContent.trim() : null,
          hasStream: !!stream,
          hasComposer: !!composer,
          hasCommunityLink: !!commLink
        };
      })()
    `);
    console.log('  Chat room details:', chatRoomDetails);
    assert(chatRoomDetails.hasStream, 'Active community displays its own message stream container');
    assert(chatRoomDetails.hasComposer, 'Active community displays message composer / join action');
    assert(chatRoomDetails.hasCommunityLink, 'Chat room provides direct link to community discussion feed');
    await takeScreenshot('community_chat_stream_navigation.png');

    console.log('\n=============================================================');
    console.log(`PEOPLE YOU KNOW & AVATAR VERIFICATION COMPLETE: ${totalPassed} PASSED, ${totalFailed} FAILED`);
    console.log('=============================================================');

    if (totalFailed > 0) {
      process.exitCode = 1;
    }
  } catch (err) {
    console.error('Test execution failed:', err);
    process.exitCode = 1;
  } finally {
    edge.kill();
    try {
      fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
    } catch (e) {}
  }
}

runPeopleYouKnowVerification();
