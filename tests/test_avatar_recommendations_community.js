const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_arc_' + Date.now());
const PORT = 9240;
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

async function runVerification() {
  console.log('=== STARTING AVATAR RESOLUTION, PEOPLE YOU KNOW & COMMUNITY JOIN VERIFICATION ===\n');

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

  async function setSession(userId) {
    if (userId === null) {
      await send('Network.clearBrowserCookies');
      return null;
    }
    const { sessionId, csrf } = createTestSession(userId);
    await send('Network.setCookie', {
      url: 'http://localhost:8086',
      name: 'INFINITYBLOGSESSID',
      value: sessionId,
      domain: 'localhost',
      path: '/'
    });
    return { sessionId, csrf };
  }

  async function navigate(url, waitMs = 1500) {
    await send('Page.navigate', { url });
    await new Promise(r => setTimeout(r, waitMs));
  }

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
    // =========================================================================
    // ISSUE 1: AVATAR RESOLUTION & DISPLAY RELIABILITY
    // =========================================================================
    console.log('\n--- 1. Testing Avatar Resolution & Display (User 13: Cristine) ---');
    await setSession(13); // Cristine has avatar: u13_20261012_233117_ecaaa4c5112a005b7df4873d6fa199f2.jpg

    // 1A. Profile Page Avatar Check
    await navigate('http://localhost:8086/profile');
    const profileAvatarInfo = await evalJs(`
      (() => {
        const img = document.querySelector('.profile-avatar-img');
        const fallback = document.querySelector('.profile-avatar-initial');
        return {
          imgFound: !!img,
          src: img ? img.src : null,
          complete: img ? img.complete : false,
          naturalWidth: img ? img.naturalWidth : 0,
          naturalHeight: img ? img.naturalHeight : 0,
          display: img ? getComputedStyle(img).display : null,
          fallbackDisplay: fallback ? getComputedStyle(fallback).display : null
        };
      })()
    `);
    console.log('  Profile Avatar State:', profileAvatarInfo);
    assert(profileAvatarInfo.imgFound === true, 'Profile avatar <img> exists in DOM');
    assert(profileAvatarInfo.src && profileAvatarInfo.src.includes('/uploads/avatars/u13_'), 'Profile avatar src points to /uploads/avatars/u13_...');
    assert(profileAvatarInfo.naturalWidth > 0, `Profile avatar image loaded successfully (naturalWidth: ${profileAvatarInfo.naturalWidth}px)`);
    assert(profileAvatarInfo.display !== 'none', 'Profile avatar image is visible');
    assert(profileAvatarInfo.fallbackDisplay === 'none', 'Profile initial fallback is hidden');
    await takeScreenshot('avatar_profile_user13_live.png');

    // 1B. Home Page User Menu & Composer Avatar Check
    await navigate('http://localhost:8086/');
    const homeAvatarInfo = await evalJs(`
      (() => {
        const headerImg = document.querySelector('.header-avatar-img');
        const composerImg = document.querySelector('.composer-user-avatar');
        return {
          headerFound: !!headerImg,
          headerSrc: headerImg ? headerImg.src : null,
          headerNaturalWidth: headerImg ? headerImg.naturalWidth : 0,
          composerFound: !!composerImg,
          composerSrc: composerImg ? composerImg.src : null,
          composerNaturalWidth: composerImg ? composerImg.naturalWidth : 0
        };
      })()
    `);
    console.log('  Home Header & Composer Avatar State:', homeAvatarInfo);
    assert(homeAvatarInfo.headerFound && homeAvatarInfo.headerNaturalWidth > 0, 'Header avatar <img> loaded successfully');
    assert(homeAvatarInfo.composerFound && homeAvatarInfo.composerNaturalWidth > 0, 'Feed composer avatar <img> loaded successfully');

    // =========================================================================
    // ISSUE 2: PEOPLE YOU KNOW ALGORITHM & CANONICAL ID SELF-EXCLUSION
    // =========================================================================
    console.log('\n--- 2. Testing People You Know Algorithm & Self-Exclusion ---');

    // 2A. Logged in as User 2 (Shubham Banerjee, ID: 2)
    await setSession(2);
    await navigate('http://localhost:8086/');
    const pynUser2 = await evalJs(`
      (() => {
        const rows = Array.from(document.querySelectorAll('.sidebar-author-row'));
        return rows.map(r => {
          const id = parseInt(r.getAttribute('data-author-id'), 10);
          const nameEl = r.querySelector('.sidebar-row-title');
          const img = r.querySelector('.sidebar-avatar-img');
          const fallback = r.querySelector('.sidebar-avatar-fallback');
          return {
            id,
            name: nameEl ? nameEl.textContent.trim() : '',
            hasImg: !!img,
            imgSrc: img ? img.src : null,
            imgNaturalWidth: img ? img.naturalWidth : 0,
            hasFallback: !!fallback,
            fallbackDisplay: fallback ? getComputedStyle(fallback).display : null
          };
        });
      })()
    `);
    console.log('  People You Know for User 2 (ID: 2):');
    pynUser2.forEach(u => console.log(`    - ID ${u.id}: "${u.name}" | img: ${u.imgSrc ? u.imgSrc.substring(u.imgSrc.lastIndexOf('/')) : 'NONE'} (width: ${u.imgNaturalWidth}px)`));

    const user2InRecommendations = pynUser2.some(u => u.id === 2);
    assert(!user2InRecommendations, 'User 2 (authenticated viewer) is STRICTLY EXCLUDED from People You Know');
    assert(pynUser2.length > 0, `People You Know returns ${pynUser2.length} recommended authors`);

    // Verify accounts with uploaded avatars display real images
    const authorsWithImages = pynUser2.filter(u => u.hasImg && u.imgNaturalWidth > 0);
    assert(authorsWithImages.length >= 2, `Multiple suggested authors have active uploaded profile photos (${authorsWithImages.length} found)`);

    // Check that distinct users sharing the name "Shubham Banerjee" (e.g. ID 43, 35) are NOT incorrectly discarded
    const otherShubhams = pynUser2.filter(u => u.name.includes('Shubham Banerjee'));
    assert(otherShubhams.length > 0, `Distinct users sharing similar name ("Shubham Banerjee") are preserved (IDs: ${otherShubhams.map(o => o.id).join(', ')})`);
    await takeScreenshot('people_you_know_user2_recommendations.png');

    // 2B. Logged in as User 13 (Cristine, ID: 13)
    await setSession(13);
    await navigate('http://localhost:8086/');
    const pynUser13 = await evalJs(`
      (() => {
        const rows = Array.from(document.querySelectorAll('.sidebar-author-row'));
        return rows.map(r => ({
          id: parseInt(r.getAttribute('data-author-id'), 10),
          name: r.querySelector('.sidebar-row-title')?.textContent.trim() || ''
        }));
      })()
    `);
    console.log('  People You Know for User 13 (ID: 13):', pynUser13);
    const user13InRecommendations = pynUser13.some(u => u.id === 13);
    assert(!user13InRecommendations, 'User 13 (authenticated viewer) is STRICTLY EXCLUDED from People You Know');

    // 2C. Logged in as User 43 (Shubham Banerjee 5, ID: 43)
    await setSession(43);
    await navigate('http://localhost:8086/');
    const pynUser43 = await evalJs(`
      (() => {
        const rows = Array.from(document.querySelectorAll('.sidebar-author-row'));
        return rows.map(r => ({
          id: parseInt(r.getAttribute('data-author-id'), 10),
          name: r.querySelector('.sidebar-row-title')?.textContent.trim() || ''
        }));
      })()
    `);
    console.log('  People You Know for User 43 (ID: 43):', pynUser43);
    const user43InRecommendations = pynUser43.some(u => u.id === 43);
    assert(!user43InRecommendations, 'User 43 (authenticated viewer) is STRICTLY EXCLUDED from People You Know');

    // =========================================================================
    // ISSUE 3: COMMUNITY JOIN FLOW DIRECT NAVIGATION
    // =========================================================================
    console.log('\n--- 3. Testing Community Join Direct Navigation ---');

    // Reset membership of room 'technology' or 'web-development' for User 2 so we can test joining
    await setSession(2);
    // Find rooms from /communities
    await navigate('http://localhost:8086/communities');
    const roomsInfo = await evalJs(`
      (() => {
        const cards = Array.from(document.querySelectorAll('.community-card, .btn-community-join'));
        return Array.from(document.querySelectorAll('.btn-community-join')).map(b => ({
          slug: b.getAttribute('data-slug'),
          roomId: b.getAttribute('data-room-id'),
          isMember: b.classList.contains('joined') || b.getAttribute('data-is-member') === '1',
          text: b.textContent.trim()
        }));
      })()
    `);
    console.log('  Available Community Buttons for User 2:', roomsInfo);

    // Pick first unjoined community or join one
    const targetRoom = roomsInfo.find(r => !r.isMember) || roomsInfo[0];
    console.log('  Testing join for target room:', targetRoom.slug);

    // Click the Join button
    const clickResult = await evalJs(`
      (() => {
        const btn = document.querySelector('.btn-community-join[data-slug="${targetRoom.slug}"]');
        if (btn) {
          btn.click();
          return { clicked: true, slug: "${targetRoom.slug}" };
        }
        return { clicked: false };
      })()
    `);
    assert(clickResult.clicked === true, `Clicked Join Community button for "${targetRoom.slug}"`);

    // Wait for the navigation to finish
    await new Promise(r => setTimeout(r, 2000));

    const chatPageState = await evalJs(`
      (() => {
        return {
          currentUrl: window.location.href,
          pathname: window.location.pathname,
          activeRoomSlug: "${targetRoom.slug}",
          composerVisible: !!document.getElementById('chatInputMessage') && getComputedStyle(document.getElementById('chatComposerContainer') || document.body).display !== 'none',
          membershipBadge: document.getElementById('chatMembershipBadge')?.textContent.trim() || '',
          roomTitle: document.querySelector('.chat-room-title')?.textContent.trim() || ''
        };
      })()
    `);
    console.log('  Chat Page State after Join:', chatPageState);
    assert(chatPageState.pathname.includes('/chat/') || chatPageState.pathname.includes('/community/'), 'Directly navigated to community chat URL');
    assert(chatPageState.pathname.includes(targetRoom.slug), `Current URL matches joined community slug (${chatPageState.pathname})`);
    assert(chatPageState.composerVisible === true, 'Message composer is active and visible in discussion');
    assert(chatPageState.membershipBadge.includes('Joined Member'), `Chat page confirms membership status: "${chatPageState.membershipBadge}"`);
    await takeScreenshot('community_joined_direct_navigation.png');

    // 3B. Already-joined member clicking through from /communities opens discussion immediately
    console.log('\n--- 3B. Already Joined Member Navigation from /communities ---');
    await navigate('http://localhost:8086/communities');
    const joinedBtnInfo = await evalJs(`
      (() => {
        const btn = document.querySelector('.btn-community-join[data-slug="${targetRoom.slug}"]');
        return {
          btnFound: !!btn,
          isMember: btn ? (btn.classList.contains('joined') || btn.getAttribute('data-is-member') === '1') : false,
          text: btn ? btn.textContent.trim() : ''
        };
      })()
    `);
    assert(joinedBtnInfo.isMember === true, `Button on /communities reflects joined state: "${joinedBtnInfo.text}"`);

    // Click the joined button
    await evalJs(`document.querySelector('.btn-community-join[data-slug="${targetRoom.slug}"]').click();`);
    await new Promise(r => setTimeout(r, 1500));
    const alreadyJoinedUrl = await evalJs('window.location.pathname');
    assert(alreadyJoinedUrl.includes('/chat/' + targetRoom.slug), `Clicking joined community opened chat directly (${alreadyJoinedUrl})`);

    // 3C. Logged-out guest click challenges to log in and preserves destination
    console.log('\n--- 3C. Logged-out Guest Join Challenge & Preserved Destination ---');
    await setSession(null); // Clear all cookies
    await navigate('http://localhost:8086/communities');

    // Click Join Community as guest
    await evalJs(`
      (() => {
        const firstJoinBtn = document.querySelector('.btn-community-join');
        if (firstJoinBtn) firstJoinBtn.click();
      })()
    `);
    await new Promise(r => setTimeout(r, 1500));

    const guestChallengeState = await evalJs(`
      (() => {
        return {
          currentUrl: window.location.href,
          pathname: window.location.pathname,
          search: window.location.search,
          hasRedirectParam: window.location.search.includes('redirect='),
          redirectValue: new URLSearchParams(window.location.search).get('redirect')
        };
      })()
    `);
    console.log('  Guest Challenge State:', guestChallengeState);
    assert(guestChallengeState.pathname === '/login', 'Logged-out user was redirected to /login');
    assert(guestChallengeState.hasRedirectParam === true, `Login URL preserves redirect parameter: "${guestChallengeState.redirectValue}"`);
    assert(guestChallengeState.redirectValue.startsWith('/chat/'), 'Redirect target preserves the community discussion destination');
    await takeScreenshot('community_guest_login_challenge.png');

    // Submit credentials in login form and verify return to the community
    console.log('\n--- 3D. Completing Login to Return to Target Community ---');
    const loginResult = await evalJs(`
      (async () => {
        document.getElementById('identifier').value = 'shubham_banerjee';
        document.getElementById('password').value = 'AdminPass123!';
        const submitBtn = document.getElementById('login-submit-btn');
        if (submitBtn) submitBtn.click();
        return true;
      })()
    `);
    await new Promise(r => setTimeout(r, 2500));

    const postLoginUrl = await evalJs('window.location.href');
    console.log('  URL after login submission:', postLoginUrl);
    assert(postLoginUrl.includes('/chat/'), `Successfully redirected back to target community chat after login (${postLoginUrl})`);
    await takeScreenshot('community_post_login_returned.png');

    console.log('\n=======================================================');
    if (allPassed) {
      console.log('ALL VERIFICATION ASSERTIONS PASSED SUCCESSFULLY!');
    } else {
      console.error('SOME ASSERTIONS FAILED. CHECK LOGS ABOVE.');
    }
    console.log('=======================================================\n');

  } catch (err) {
    console.error('Test execution error:', err);
    allPassed = false;
  } finally {
    try {
      ws.close();
      edge.kill();
    } catch (e) {}
    process.exit(allPassed ? 0 : 1);
  }
}

runVerification();
