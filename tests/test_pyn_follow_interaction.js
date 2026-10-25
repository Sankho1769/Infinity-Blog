const { spawn, execSync } = require('child_process');
const fs = require('fs');

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

async function testFollow() {
  const edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--remote-debugging-port=9252',
    '--window-size=1280,900',
    '--user-data-dir=C:\\Temp\\av_follow_' + Date.now(),
    'about:blank'
  ]);

  await new Promise(r => setTimeout(r, 2000));
  const r = await fetch('http://127.0.0.1:9252/json/list');
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

  ws.addEventListener('message', (e) => {
    const d = JSON.parse(e.data);
    if (d.method === 'Runtime.exceptionThrown') {
      console.log('Browser JS Exception:', d.params.exceptionDetails);
    }
    if (d.method === 'Runtime.consoleAPICalled') {
      console.log('Browser console:', d.params.type, d.params.args?.map(a => a.value || a.description));
    }
    if (d.method === 'Network.responseReceived') {
      if (d.params.response.url.includes('/api/authors/')) {
        console.log('API Response:', d.params.response.status, d.params.response.url);
      }
    }
  });

  console.log('=== Test Follow/Unfollow Toggle on People You Know ===');
  // Log in as User 13 (Cristine Palmer)
  const sess13 = createTestSession(13);
  await send('Network.setCookie', { name: 'INFINITYBLOGSESSID', value: sess13, url: 'http://localhost:8086' });
  await send('Page.navigate', { url: 'http://localhost:8086/' });
  await new Promise(r => setTimeout(r, 2500));

  // Step 1: Click Follow
  await send('Runtime.evaluate', {
    expression: `(() => {
      const row = document.querySelector('.sidebar-author-row[data-author-id="43"]');
      const btn = row.querySelector('.btn-follow-toggle');
      btn.click();
    })()`
  });
  await new Promise(r => setTimeout(r, 2000));

  const afterFollowBtn = await send('Runtime.evaluate', {
    expression: `(() => {
      const row = document.querySelector('.sidebar-author-row[data-author-id="43"]');
      const btn = row.querySelector('.btn-follow-toggle');
      return {
        text: btn ? btn.textContent.trim() : null,
        isFollowingClass: btn ? btn.classList.contains('following') : false,
        dataFollowing: btn ? btn.getAttribute('data-following') : null
      };
    })()`,
    returnByValue: true
  });
  console.log('After clicking Follow:', afterFollowBtn.result?.value);
  const followPass = afterFollowBtn.result?.value.text.includes('Following') && afterFollowBtn.result?.value.isFollowingClass;
  console.log('Assertion: Transitioned to Following state:', followPass ? 'PASS' : 'FAIL');

  // Step 2: Click Unfollow
  await send('Runtime.evaluate', {
    expression: `(() => {
      const row = document.querySelector('.sidebar-author-row[data-author-id="43"]');
      const btn = row.querySelector('.btn-follow-toggle');
      btn.click();
    })()`
  });
  await new Promise(r => setTimeout(r, 2000));

  const afterUnfollowBtn = await send('Runtime.evaluate', {
    expression: `(() => {
      const row = document.querySelector('.sidebar-author-row[data-author-id="43"]');
      const btn = row.querySelector('.btn-follow-toggle');
      return {
        text: btn ? btn.textContent.trim() : null,
        isFollowingClass: btn ? btn.classList.contains('following') : false,
        dataFollowing: btn ? btn.getAttribute('data-following') : null
      };
    })()`,
    returnByValue: true
  });
  console.log('After clicking Unfollow:', afterUnfollowBtn.result?.value);
  const unfollowPass = afterUnfollowBtn.result?.value.text.includes('Follow') && !afterUnfollowBtn.result?.value.isFollowingClass;
  console.log('Assertion: Transitioned back to Follow state:', unfollowPass ? 'PASS' : 'FAIL');

  ws.close();
  edge.kill();
  console.log('Follow toggle test finished successfully!');
}

testFollow().catch(err => {
  console.error(err);
  process.exit(1);
});
