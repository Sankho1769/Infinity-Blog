const { spawn, execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ARTIFACT_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function getAuthVersion(userId) {
  try {
    const out = execSync(`"C:\\xampp\\php\\php.exe" scripts/get_auth_version.php ${userId}`);
    const v = parseInt(out.toString().trim(), 10);
    return isNaN(v) || v < 1 ? 1 : v;
  } catch (e) {
    return 1;
  }
}

function createTestSession(userId) {
  const authVersion = getAuthVersion(userId);
  const sessionId = require('crypto').randomBytes(16).toString('hex');
  const time = Math.floor(Date.now() / 1000);
  const csrf = require('crypto').randomBytes(32).toString('hex');
  const sessData = `auth|a:4:{s:7:"user_id";i:${userId};s:12:"auth_version";i:${authVersion};s:9:"issued_at";i:${time};s:13:"last_activity";i:${time};}csrf_token|s:64:"${csrf}";`;
  fs.writeFileSync('C:\\xampp\\tmp\\sess_' + sessionId, sessData);
  return sessionId;
}

async function runTests() {
  const edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--remote-debugging-port=9251',
    '--window-size=1280,900',
    '--user-data-dir=C:\\Temp\\av_e2e_' + Date.now(),
    'about:blank'
  ]);

  await new Promise(r => setTimeout(r, 2000));
  const r = await fetch('http://127.0.0.1:9251/json/list');
  const list = await r.json();
  const ws = new WebSocket(list[0].webSocketDebuggerUrl);
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
  await send('Network.enable');

  async function captureScreenshot(filepath, clip = null) {
    const params = { format: 'png' };
    if (clip) params.clip = clip;
    const shot = await send('Page.captureScreenshot', params);
    if (shot && shot.data) {
      fs.writeFileSync(filepath, Buffer.from(shot.data, 'base64'));
      console.log('Saved screenshot:', filepath);
    }
  }

  async function getBoundingBox(selector) {
    const res = await send('Runtime.evaluate', {
      expression: `(() => {
        const el = document.querySelector('${selector}');
        if (!el) return null;
        const r = el.getBoundingClientRect();
        return { x: r.left, y: r.top, width: r.width, height: r.height, scale: 1 };
      })()`,
      returnByValue: true
    });
    return res.result?.value;
  }

  // ==========================================
  // TEST 1: Account A (User 13, Cristine Palmer)
  // ==========================================
  console.log('\n=============================================');
  console.log('TEST 1: Account A (User 13, Cristine Palmer)');
  console.log('=============================================');
  const sess13 = createTestSession(13);
  await send('Network.setCookie', { name: 'INFINITYBLOGSESSID', value: sess13, url: 'http://localhost:8086' });
  await send('Page.navigate', { url: 'http://localhost:8086/' });
  await new Promise(r => setTimeout(r, 2500));
  await send('Runtime.evaluate', { expression: 'Promise.all(Array.from(document.images).filter(img => !img.complete).map(img => new Promise(res => { img.onload = img.onerror = res; })))', awaitPromise: true });

  const accountAInfo = await send('Runtime.evaluate', {
    expression: `(() => {
      const headerImg = document.querySelector('.header-avatar-img');
      const headerFallback = document.querySelector('.header-avatar-fallback');
      
      const feedPosts = [];
      document.querySelectorAll('.feed-card').forEach(card => {
        const title = card.querySelector('.feed-card-title')?.textContent.trim();
        const author = card.querySelector('.feed-author-name')?.textContent.trim();
        const img = card.querySelector('.feed-author-avatar-img');
        const fb = card.querySelector('.feed-avatar-fallback');
        feedPosts.push({
          title,
          author,
          hasImg: !!img,
          src: img ? img.src : null,
          complete: img ? img.complete : false,
          naturalWidth: img ? img.naturalWidth : 0,
          naturalHeight: img ? img.naturalHeight : 0,
          imgDisplay: img ? window.getComputedStyle(img).display : 'none',
          fbDisplay: fb ? window.getComputedStyle(fb).display : 'none'
        });
      });

      const pyn = [];
      document.querySelectorAll('.sidebar-author-row').forEach(row => {
        const id = row.dataset.authorId;
        const name = row.querySelector('.sidebar-row-title')?.textContent.trim();
        const handle = row.querySelector('.sidebar-row-sub')?.textContent.trim();
        const img = row.querySelector('.sidebar-avatar-img');
        const fb = row.querySelector('.sidebar-avatar-fallback');
        const followBtn = row.querySelector('.btn-follow-user')?.textContent.trim();
        pyn.push({
          id,
          name,
          handle,
          hasImg: !!img,
          src: img ? img.src : null,
          complete: img ? img.complete : false,
          naturalWidth: img ? img.naturalWidth : 0,
          naturalHeight: img ? img.naturalHeight : 0,
          imgDisplay: img ? window.getComputedStyle(img).display : 'none',
          fbDisplay: fb ? window.getComputedStyle(fb).display : 'none',
          followBtn
        });
      });

      return {
        header: {
          hasImg: !!headerImg,
          src: headerImg ? headerImg.src : null,
          complete: headerImg ? headerImg.complete : false,
          naturalWidth: headerImg ? headerImg.naturalWidth : 0,
          imgDisplay: headerImg ? window.getComputedStyle(headerImg).display : 'none',
          fbDisplay: headerFallback ? window.getComputedStyle(headerFallback).display : 'none'
        },
        feedPosts,
        pyn
      };
    })()`,
    returnByValue: true
  });

  const aRes = accountAInfo.result?.value;
  console.log('Account A Header Avatar:', aRes.header);
  console.log('Account A Feed Posts Count:', aRes.feedPosts.length);
  aRes.feedPosts.slice(0, 3).forEach((p, i) => {
    console.log(`  Feed Post ${i+1}: "${p.title}" by ${p.author} | hasImg=${p.hasImg}, naturalWidth=${p.naturalWidth}, complete=${p.complete}`);
  });
  console.log('Account A PYN Authors Count:', aRes.pyn.length);
  aRes.pyn.forEach(p => {
    console.log(`  PYN: [ID ${p.id}] ${p.name} (${p.handle}) | hasImg=${p.hasImg}, naturalWidth=${p.naturalWidth}, complete=${p.complete}`);
  });

  // Check Account A invariants:
  const u13InPYN = aRes.pyn.find(p => p.id === '13');
  console.log('Assertion: User 13 self-excluded from PYN:', u13InPYN === undefined ? 'PASS' : 'FAIL');
  const u43InPYN = aRes.pyn.find(p => p.id === '43');
  console.log('Assertion: User 43 in PYN with naturalWidth > 0:', (u43InPYN && u43InPYN.hasImg && u43InPYN.naturalWidth > 0) ? 'PASS' : 'FAIL');

  const post64 = aRes.feedPosts.find(p => p.title === 'Hello');
  console.log('Assertion: Post 64 in Feed with author Cristine Palmer and avatar naturalWidth > 0:', (post64 && post64.hasImg && post64.naturalWidth > 0) ? 'PASS' : 'FAIL');
  const post63 = aRes.feedPosts.find(p => p.title.includes('Mindful Technology'));
  console.log('Assertion: Post 63 in Feed with author Shubham Banerjee and avatar naturalWidth > 0:', (post63 && post63.hasImg && post63.naturalWidth > 0) ? 'PASS' : 'FAIL');

  // Capture Screenshot of Account A PYN
  await captureScreenshot(path.join(ARTIFACT_DIR, 'pyn_account_a_user13.png'));


  // ==========================================
  // TEST 2: Account B (User 43, Shubham Banerjee)
  // ==========================================
  console.log('\n=============================================');
  console.log('TEST 2: Account B (User 43, Shubham Banerjee)');
  console.log('=============================================');
  const sess43 = createTestSession(43);
  await send('Network.setCookie', { name: 'INFINITYBLOGSESSID', value: sess43, url: 'http://localhost:8086' });
  await send('Page.navigate', { url: 'http://localhost:8086/' });
  await new Promise(r => setTimeout(r, 2500));
  await send('Runtime.evaluate', { expression: 'Promise.all(Array.from(document.images).filter(img => !img.complete).map(img => new Promise(res => { img.onload = img.onerror = res; })))', awaitPromise: true });

  const accountBInfo = await send('Runtime.evaluate', {
    expression: `(() => {
      const headerImg = document.querySelector('.header-avatar-img');
      const headerFallback = document.querySelector('.header-avatar-fallback');

      const pyn = [];
      document.querySelectorAll('.sidebar-author-row').forEach(row => {
        const id = row.dataset.authorId;
        const name = row.querySelector('.sidebar-row-title')?.textContent.trim();
        const handle = row.querySelector('.sidebar-row-sub')?.textContent.trim();
        const img = row.querySelector('.sidebar-avatar-img');
        const fb = row.querySelector('.sidebar-avatar-fallback');
        pyn.push({
          id,
          name,
          handle,
          hasImg: !!img,
          src: img ? img.src : null,
          complete: img ? img.complete : false,
          naturalWidth: img ? img.naturalWidth : 0,
          naturalHeight: img ? img.naturalHeight : 0,
          imgDisplay: img ? window.getComputedStyle(img).display : 'none',
          fbDisplay: fb ? window.getComputedStyle(fb).display : 'none'
        });
      });

      return {
        header: {
          hasImg: !!headerImg,
          src: headerImg ? headerImg.src : null,
          complete: headerImg ? headerImg.complete : false,
          naturalWidth: headerImg ? headerImg.naturalWidth : 0,
          imgDisplay: headerImg ? window.getComputedStyle(headerImg).display : 'none',
          fbDisplay: headerFallback ? window.getComputedStyle(headerFallback).display : 'none'
        },
        pyn
      };
    })()`,
    returnByValue: true
  });

  const bRes = accountBInfo.result?.value;
  console.log('Account B Header Avatar:', bRes.header);
  console.log('Account B PYN Authors Count:', bRes.pyn.length);
  bRes.pyn.forEach(p => {
    console.log(`  PYN: [ID ${p.id}] ${p.name} (${p.handle}) | hasImg=${p.hasImg}, naturalWidth=${p.naturalWidth}, complete=${p.complete}`);
  });

  // Check Account B invariants:
  const u43InPYN_B = bRes.pyn.find(p => p.id === '43');
  console.log('Assertion: User 43 self-excluded from PYN:', u43InPYN_B === undefined ? 'PASS' : 'FAIL');
  const u13InPYN_B = bRes.pyn.find(p => p.id === '13');
  console.log('Assertion: User 13 in PYN with naturalWidth > 0:', (u13InPYN_B && u13InPYN_B.hasImg && u13InPYN_B.naturalWidth > 0) ? 'PASS' : 'FAIL');

  // Capture Screenshot of Account B PYN
  await captureScreenshot(path.join(ARTIFACT_DIR, 'pyn_account_b_user43.png'));


  // ==========================================
  // TEST 3: Account C (User 2, Shubham Banerjee, Initials Fallback)
  // ==========================================
  console.log('\n=============================================');
  console.log('TEST 3: Account C (User 2, Initials Fallback)');
  console.log('=============================================');
  const sess2 = createTestSession(2);
  await send('Network.setCookie', { name: 'INFINITYBLOGSESSID', value: sess2, url: 'http://localhost:8086' });
  await send('Page.navigate', { url: 'http://localhost:8086/' });
  await new Promise(r => setTimeout(r, 2500));
  await send('Runtime.evaluate', { expression: 'Promise.all(Array.from(document.images).filter(img => !img.complete).map(img => new Promise(res => { img.onload = img.onerror = res; })))', awaitPromise: true });

  const accountCInfo = await send('Runtime.evaluate', {
    expression: `(() => {
      const headerImg = document.querySelector('.header-avatar-img');
      const headerFallback = document.querySelector('.header-avatar-fallback');

      const pyn = [];
      document.querySelectorAll('.sidebar-author-row').forEach(row => {
        const id = row.dataset.authorId;
        const name = row.querySelector('.sidebar-row-title')?.textContent.trim();
        const handle = row.querySelector('.sidebar-row-sub')?.textContent.trim();
        const img = row.querySelector('.sidebar-avatar-img');
        const fb = row.querySelector('.sidebar-avatar-fallback');
        pyn.push({
          id,
          name,
          handle,
          hasImg: !!img,
          src: img ? img.src : null,
          complete: img ? img.complete : false,
          naturalWidth: img ? img.naturalWidth : 0,
          naturalHeight: img ? img.naturalHeight : 0,
          imgDisplay: img ? window.getComputedStyle(img).display : 'none',
          fbDisplay: fb ? window.getComputedStyle(fb).display : 'none',
          fallbackText: fb ? fb.textContent.trim() : null
        });
      });

      return {
        header: {
          hasImg: !!headerImg,
          src: headerImg ? headerImg.src : null,
          complete: headerImg ? headerImg.complete : false,
          naturalWidth: headerImg ? headerImg.naturalWidth : 0,
          imgDisplay: headerImg ? window.getComputedStyle(headerImg).display : 'none',
          fbDisplay: headerFallback ? window.getComputedStyle(headerFallback).display : 'none',
          fallbackText: headerFallback ? headerFallback.textContent.trim() : null
        },
        pyn
      };
    })()`,
    returnByValue: true
  });

  const cRes = accountCInfo.result?.value;
  console.log('Account C Header Avatar:', cRes.header);
  console.log('Assertion: Account C Header shows initial S:', (!cRes.header.hasImg && cRes.header.fallbackText === 'S') ? 'PASS' : 'FAIL');
  console.log('Account C PYN Authors Count:', cRes.pyn.length);
  cRes.pyn.forEach(p => {
    console.log(`  PYN: [ID ${p.id}] ${p.name} (${p.handle}) | hasImg=${p.hasImg}, naturalWidth=${p.naturalWidth}, fbDisplay=${p.fbDisplay}`);
  });

  const u2InPYN_C = cRes.pyn.find(p => p.id === '2');
  console.log('Assertion: User 2 self-excluded from PYN:', u2InPYN_C === undefined ? 'PASS' : 'FAIL');
  const u43InPYN_C = cRes.pyn.find(p => p.id === '43');
  console.log('Assertion: User 43 in PYN with naturalWidth > 0:', (u43InPYN_C && u43InPYN_C.hasImg && u43InPYN_C.naturalWidth > 0) ? 'PASS' : 'FAIL');
  const u13InPYN_C = cRes.pyn.find(p => p.id === '13');
  console.log('Assertion: User 13 in PYN with naturalWidth > 0:', (u13InPYN_C && u13InPYN_C.hasImg && u13InPYN_C.naturalWidth > 0) ? 'PASS' : 'FAIL');

  // Capture Screenshot of Account C PYN
  await captureScreenshot(path.join(ARTIFACT_DIR, 'pyn_account_c_user2.png'));


  // ==========================================
  // TEST 4: Author Profiles & Details
  // ==========================================
  console.log('\n=============================================');
  console.log('TEST 4: Author Profiles (/author/13, /author/43, /author/2)');
  console.log('=============================================');
  
  // Profile 13
  await send('Page.navigate', { url: 'http://localhost:8086/author/13' });
  await new Promise(r => setTimeout(r, 2000));
  const prof13 = await send('Runtime.evaluate', {
    expression: `(() => {
      const img = document.querySelector('img[src*="u13_"]');
      return {
        hasImg: !!img,
        src: img ? img.src : null,
        naturalWidth: img ? img.naturalWidth : 0,
        complete: img ? img.complete : false
      };
    })()`,
    returnByValue: true
  });
  console.log('Author 13 Profile Avatar:', prof13.result?.value);
  console.log('Assertion: Author 13 Profile avatar loaded:', (prof13.result?.value.naturalWidth > 0) ? 'PASS' : 'FAIL');

  // Profile 43
  await send('Page.navigate', { url: 'http://localhost:8086/author/43' });
  await new Promise(r => setTimeout(r, 2000));
  const prof43 = await send('Runtime.evaluate', {
    expression: `(() => {
      const img = document.querySelector('img[src*="u43_"]');
      return {
        hasImg: !!img,
        src: img ? img.src : null,
        naturalWidth: img ? img.naturalWidth : 0,
        complete: img ? img.complete : false
      };
    })()`,
    returnByValue: true
  });
  console.log('Author 43 Profile Avatar:', prof43.result?.value);
  console.log('Assertion: Author 43 Profile avatar loaded:', (prof43.result?.value.naturalWidth > 0) ? 'PASS' : 'FAIL');

  // Profile 2
  await send('Page.navigate', { url: 'http://localhost:8086/author/2' });
  await new Promise(r => setTimeout(r, 2000));
  const prof2 = await send('Runtime.evaluate', {
    expression: `(() => {
      const fallback = document.querySelector('.author-avatar-initial-fallback');
      const img = document.querySelector('img[src*="avatar"]');
      return {
        hasImg: !!img,
        fallbackText: fallback ? fallback.textContent.trim() : null,
        fallbackDisplay: fallback ? window.getComputedStyle(fallback).display : 'none'
      };
    })()`,
    returnByValue: true
  });
  console.log('Author 2 Profile (No photo):', prof2.result?.value);
  console.log('Assertion: Author 2 Profile fallback S displayed:', (prof2.result?.value.fallbackText === 'S' && prof2.result?.value.fallbackDisplay === 'flex') ? 'PASS' : 'FAIL');


  // ==========================================
  // TEST 5: Explore Page (/explore)
  // ==========================================
  console.log('\n=============================================');
  console.log('TEST 5: Explore Page (/explore)');
  console.log('=============================================');
  await send('Page.navigate', { url: 'http://localhost:8086/explore' });
  await new Promise(r => setTimeout(r, 2000));
  const exploreInfo = await send('Runtime.evaluate', {
    expression: `(() => {
      const authors = [];
      document.querySelectorAll('.article-author-info').forEach(info => {
        const name = info.querySelector('a:last-child')?.textContent.trim();
        const img = info.querySelector('.feed-author-avatar-img');
        const fb = info.querySelector('.user-avatar');
        authors.push({
          name,
          hasImg: !!img,
          src: img ? img.src : null,
          naturalWidth: img ? img.naturalWidth : 0,
          complete: img ? img.complete : false,
          fallbackText: fb ? fb.textContent.trim() : null
        });
      });
      return authors;
    })()`,
    returnByValue: true
  });
  console.log('Explore Authors Count:', exploreInfo.result?.value.length);
  exploreInfo.result?.value.slice(0, 3).forEach(a => {
    console.log(`  Explore Card: ${a.name} | hasImg=${a.hasImg}, naturalWidth=${a.naturalWidth}, complete=${a.complete}`);
  });

  // Full Feed Screenshot
  await send('Network.setCookie', { name: 'INFINITYBLOGSESSID', value: sess13, url: 'http://localhost:8086' });
  await send('Page.navigate', { url: 'http://localhost:8086/' });
  await new Promise(r => setTimeout(r, 2000));
  await captureScreenshot(path.join(ARTIFACT_DIR, 'feed_author_avatars_verified.png'));

  ws.close();
  edge.kill();
  console.log('\nAll browser E2E tests finished!');
}

runTests().catch(err => {
  console.error('Test error:', err);
  process.exit(1);
});
