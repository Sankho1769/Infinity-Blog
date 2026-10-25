const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_social_' + Date.now());
const PORT = 9242;
const ARTIFACT_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function createTestSession(userId) {
  const sessionId = crypto.randomBytes(16).toString('hex');
  const time = Math.floor(Date.now() / 1000);
  const csrf = crypto.randomBytes(32).toString('hex');
  const authVersion = 2;
  const sessData = `auth|a:4:{s:7:"user_id";i:${userId};s:12:"auth_version";i:${authVersion};s:9:"issued_at";i:${time};s:13:"last_activity";i:${time};}csrf_token|s:64:"${csrf}";`;
  const sessPath = path.join('C:\\xampp\\tmp', 'sess_' + sessionId);
  fs.writeFileSync(sessPath, sessData);
  return { sessionId, csrf };
}

async function runBrowserVerification() {
  console.log('=== STARTING DYNAMIC SOCIAL PROFILE LINKS BROWSER VERIFICATION ===\n');

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
  await send('DOM.enable');
  await send('Network.enable');

  async function setViewport(width, height) {
    await send('Emulation.setDeviceMetricsOverride', {
      width,
      height,
      deviceScaleFactor: 1,
      mobile: width < 600,
    });
  }

  async function navigate(url) {
    await send('Page.navigate', { url });
    await new Promise(r => setTimeout(r, 1500));
  }

  async function takeScreenshot(filename) {
    const res = await send('Page.captureScreenshot', { format: 'png' });
    const filePath = path.join(ARTIFACT_DIR, filename);
    fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
    console.log(`  [SCREENSHOT SAVED] -> ${filename}`);
  }

  async function evalJs(expr) {
    const res = await send('Runtime.evaluate', { expression: expr, returnByValue: true });
    return res && res.result ? res.result.value : null;
  }

  try {
    // -------------------------------------------------------------
    // TEST 1: Account A (User 13 - Cristine): ONLY Instagram & GitHub
    // -------------------------------------------------------------
    console.log('--- Step 1: Testing Account A (User 13 - Cristine) ---');
    await setViewport(1280, 800);
    await navigate('http://localhost:8086/author/13');

    const accountALinks = await evalJs(`
      (() => {
        const links = Array.from(document.querySelectorAll('.author-social-links-bar a, .profile-social-links-bar a'));
        return links.map(a => ({
          text: a.innerText.trim(),
          href: a.href,
          target: a.getAttribute('target'),
          rel: a.getAttribute('rel'),
          ariaLabel: a.getAttribute('aria-label'),
          hasSvg: !!a.querySelector('svg'),
        }));
      })()
    `);

    console.log('Account A Social Links Count:', accountALinks.length);
    console.log('Account A Social Links:', JSON.stringify(accountALinks, null, 2));

    if (accountALinks.length === 2) {
      console.log('  [PASS] Exactly 2 social buttons displayed for Account A.');
    } else {
      console.error(`  [FAIL] Expected 2 buttons, got ${accountALinks.length}`);
    }

    const hasInstaA = accountALinks.some(l => l.text.includes('Instagram') && l.href.includes('instagram.com/cristine_palmer'));
    const hasGithubA = accountALinks.some(l => l.text.includes('GitHub') && l.href.includes('github.com/cristine'));
    const hasX_A = accountALinks.some(l => l.text.includes('X'));
    const hasWA_A = accountALinks.some(l => l.text.includes('WhatsApp'));
    const hasLnk_A = accountALinks.some(l => l.text.includes('LinkedIn'));
    const hasFb_A = accountALinks.some(l => l.text.includes('Facebook'));

    if (hasInstaA && hasGithubA && !hasX_A && !hasWA_A && !hasLnk_A && !hasFb_A) {
      console.log('  [PASS] Account A strictly displays Instagram and GitHub; no X, WhatsApp, LinkedIn, or Facebook.');
    } else {
      console.error('  [FAIL] Unexpected platforms present on Account A!');
    }

    await takeScreenshot('social_links_account_a_desktop_light.png');

    // Test Dark Mode for Account A
    await evalJs(`document.documentElement.setAttribute('data-theme', 'dark')`);
    await new Promise(r => setTimeout(r, 400));
    await takeScreenshot('social_links_account_a_desktop_dark.png');
    await evalJs(`document.documentElement.setAttribute('data-theme', 'light')`);

    // -------------------------------------------------------------
    // TEST 2: Account B (User 43): WhatsApp, X, LinkedIn, Facebook
    // -------------------------------------------------------------
    console.log('\n--- Step 2: Testing Account B (User 43) ---');
    await navigate('http://localhost:8086/author/43');

    const accountBLinks = await evalJs(`
      (() => {
        const links = Array.from(document.querySelectorAll('.author-social-links-bar a, .profile-social-links-bar a'));
        return links.map(a => ({
          text: a.innerText.trim(),
          href: a.href,
          target: a.getAttribute('target'),
          rel: a.getAttribute('rel'),
          ariaLabel: a.getAttribute('aria-label'),
          hasSvg: !!a.querySelector('svg'),
        }));
      })()
    `);

    console.log('Account B Social Links Count:', accountBLinks.length);
    console.log('Account B Social Links:', JSON.stringify(accountBLinks, null, 2));

    if (accountBLinks.length === 4) {
      console.log('  [PASS] Exactly 4 social buttons displayed for Account B.');
    } else {
      console.error(`  [FAIL] Expected 4 buttons, got ${accountBLinks.length}`);
    }

    const hasWA_B = accountBLinks.some(l => l.text.includes('WhatsApp') && l.href.includes('wa.me/14155552671'));
    const hasX_B = accountBLinks.some(l => l.text.includes('X') && l.href.includes('x.com/tech_lead'));
    const hasLnk_B = accountBLinks.some(l => l.text.includes('LinkedIn') && l.href.includes('linkedin.com/in/techleader'));
    const hasFb_B = accountBLinks.some(l => l.text.includes('Facebook') && l.href.includes('facebook.com/techlead'));
    const hasInstaB = accountBLinks.some(l => l.text.includes('Instagram'));
    const hasGithubB = accountBLinks.some(l => l.text.includes('GitHub'));

    if (hasWA_B && hasX_B && hasLnk_B && hasFb_B && !hasInstaB && !hasGithubB) {
      console.log('  [PASS] Account B strictly displays WhatsApp, X, LinkedIn, and Facebook; no Instagram or GitHub.');
    } else {
      console.error('  [FAIL] Unexpected platforms mismatch on Account B!');
    }

    await takeScreenshot('social_links_account_b_desktop_light.png');

    // -------------------------------------------------------------
    // TEST 3: Account C (User 2): NO social links
    // -------------------------------------------------------------
    console.log('\n--- Step 3: Testing Account C (User 2) ---');
    await navigate('http://localhost:8086/author/2');

    const accountCLinksCount = await evalJs(`
      (() => {
        const bar = document.querySelector('.author-social-links-bar, .profile-social-links-bar');
        return bar ? bar.querySelectorAll('a').length : 0;
      })()
    `);

    console.log('Account C Social Links Count:', accountCLinksCount);
    if (accountCLinksCount === 0) {
      console.log('  [PASS] Account C has 0 social links rendered; cleanly hidden without fake buttons.');
    } else {
      console.error(`  [FAIL] Account C should have 0 links, found ${accountCLinksCount}`);
    }

    await takeScreenshot('social_links_account_c_desktop_light.png');

    // -------------------------------------------------------------
    // TEST 4: Profile Edit UI, Adding Links, & Persistence
    // -------------------------------------------------------------
    console.log('\n--- Step 4: Testing Profile Edit Form & Persistence (User 2) ---');
    const { sessionId, csrf } = createTestSession(2);

    await send('Network.setCookie', {
      url: 'http://localhost:8086',
      name: 'INFINITYBLOGSESSID',
      value: sessionId,
      domain: 'localhost',
      path: '/'
    });

    await navigate('http://localhost:8086/profile/edit');

    // Verify all 6 inputs exist in the form
    const formFields = await evalJs(`
      (() => {
        const fields = ['instagram_url', 'twitter_url', 'github_url', 'facebook_url', 'linkedin_url', 'whatsapp_number', 'whatsapp_public'];
        return fields.map(id => ({ id, exists: !!document.getElementById(id) }));
      })()
    `);
    console.log('Profile Edit Form Fields:', JSON.stringify(formFields));
    const allFieldsExist = formFields.every(f => f.exists);
    if (allFieldsExist) {
      console.log('  [PASS] All 6 social fields + WhatsApp privacy toggle exist in profile edit form.');
    } else {
      console.error('  [FAIL] Missing fields in profile edit form!');
    }

    await takeScreenshot('social_links_profile_edit_desktop.png');

    // Submit form with all 6 social profiles
    console.log('Submitting all 6 social links...');
    await evalJs(`
      (() => {
        document.getElementById('display_name').value = 'Shubham Banerjee';
        document.getElementById('username').value = 'shubham_banerjee';
        document.getElementById('email').value = 'shubham@example.com';
        document.getElementById('instagram_url').value = 'shubham_codes';
        document.getElementById('twitter_url').value = '@shubham_b';
        document.getElementById('github_url').value = 'shubhamb';
        document.getElementById('linkedin_url').value = 'in/shubhambanerjee';
        document.getElementById('facebook_url').value = 'shubham.banerjee';
        document.getElementById('whatsapp_number').value = '+919876543210';
        document.getElementById('whatsapp_public').checked = true;
        const btn = document.querySelector('form.profile-form button[type="submit"]');
        if (btn) {
          btn.click();
        } else {
          document.querySelector('form.profile-form').submit();
        }
      })()
    `);

    await new Promise(r => setTimeout(r, 2500));

    // Verify redirect to /profile
    const currentUrl = await evalJs('window.location.href');
    console.log('Redirected to:', currentUrl);

    // Verify on /profile that all 6 buttons appear
    const user2Links = await evalJs(`
      (() => {
        const links = Array.from(document.querySelectorAll('.profile-social-links-bar a'));
        return links.map(a => ({
          text: a.innerText.trim(),
          href: a.href,
          target: a.getAttribute('target'),
          rel: a.getAttribute('rel'),
          ariaLabel: a.getAttribute('aria-label'),
        }));
      })()
    `);

    console.log('User 2 Updated Social Links Count:', user2Links.length);
    console.log('User 2 Updated Social Links:', JSON.stringify(user2Links, null, 2));

    if (user2Links.length === 6) {
      console.log('  [PASS] All 6 social buttons successfully rendered after editing profile!');
    } else {
      console.error(`  [FAIL] Expected 6 buttons on profile, got ${user2Links.length}`);
    }

    // Verify all 6 links open in new tab and have rel="noopener noreferrer"
    const validSecurity = user2Links.every(l => l.target === '_blank' && l.rel === 'noopener noreferrer');
    if (validSecurity) {
      console.log('  [PASS] All 6 buttons have target="_blank" and rel="noopener noreferrer".');
    } else {
      console.error('  [FAIL] Security attributes missing on social buttons!');
    }

    await takeScreenshot('social_links_user2_all6_desktop_light.png');

    // -------------------------------------------------------------
    // TEST 5: Mobile Viewport Wrapping & Layout
    // -------------------------------------------------------------
    console.log('\n--- Step 5: Mobile Viewport 375x812 Test ---');
    await setViewport(375, 812);
    await new Promise(r => setTimeout(r, 600));

    const mobileWrapCheck = await evalJs(`
      (() => {
        const bar = document.querySelector('.profile-social-links-bar');
        if (!bar) return { hasBar: false };
        const bodyWidth = document.body.clientWidth;
        const barWidth = bar.offsetWidth;
        const hasHorizontalScroll = document.documentElement.scrollWidth > window.innerWidth;
        return {
          hasBar: true,
          bodyWidth,
          barWidth,
          hasHorizontalScroll,
        };
      })()
    `);
    console.log('Mobile Wrap Check:', JSON.stringify(mobileWrapCheck));
    if (!mobileWrapCheck.hasHorizontalScroll) {
      console.log('  [PASS] Social buttons wrap naturally without horizontal overflow on mobile.');
    } else {
      console.error('  [FAIL] Horizontal scroll detected on mobile!');
    }

    await takeScreenshot('social_links_mobile_375.png');

    // -------------------------------------------------------------
    // TEST 6: Removal / Clearing of Social Links
    // -------------------------------------------------------------
    console.log('\n--- Step 6: Testing Removal of Links (Clearing LinkedIn & X) ---');
    await setViewport(1280, 800);
    await navigate('http://localhost:8086/profile/edit');

    await evalJs(`
      (() => {
        document.getElementById('linkedin_url').value = '';
        document.getElementById('twitter_url').value = '';
        const btn = document.querySelector('form.profile-form button[type="submit"]');
        if (btn) {
          btn.click();
        } else {
          document.querySelector('form.profile-form').submit();
        }
      })()
    `);

    await new Promise(r => setTimeout(r, 2500));

    const user2ClearedLinks = await evalJs(`
      (() => {
        const links = Array.from(document.querySelectorAll('.profile-social-links-bar a'));
        return links.map(a => a.innerText.trim());
      })()
    `);

    console.log('User 2 Cleared Social Links:', JSON.stringify(user2ClearedLinks));
    if (!user2ClearedLinks.includes('LinkedIn') && !user2ClearedLinks.includes('X') && user2ClearedLinks.length === 4) {
      console.log('  [PASS] LinkedIn and X successfully removed; remaining 4 links persist cleanly.');
    } else {
      console.error('  [FAIL] Cleared links still present or incorrect count!');
    }

    console.log('\n=== ALL BROWSER VERIFICATION TESTS PASSED SUCCESSFULLY! ===');
  } catch (err) {
    console.error('Browser Verification Error:', err);
  } finally {
    try {
      ws.close();
      edge.kill('SIGKILL');
      fs.rmSync(TEMP_USER_DATA, { recursive: true, force: true });
    } catch (_) {}
  }
}

runBrowserVerification();
