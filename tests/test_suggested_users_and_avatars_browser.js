const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9249;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_identity_test_profile');
const ARTIFACTS_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function loadCredentials() {
    let email = process.env.TEST_ADMIN_EMAIL || process.env.ADMIN_EMAIL;
    let password = process.env.TEST_ADMIN_PASSWORD || process.env.ADMIN_PASSWORD;
    try {
        const envPath = path.join(__dirname, '..', '.env');
        if (fs.existsSync(envPath)) {
            const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
            for (const line of lines) {
                const match = line.match(/^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$/);
                if (match) {
                    if (match[1] === 'TEST_ADMIN_EMAIL' && !email) email = match[2].trim().replace(/^['"](.*)['"]$/, '$1');
                    if (match[1] === 'TEST_ADMIN_PASSWORD' && !password) password = match[2].trim().replace(/^['"](.*)['"]$/, '$1');
                }
            }
        }
    } catch (e) {}
    return { email: email || '', password: password || '' };
}

function fetchJson(url) {
    return new Promise((resolve, reject) => {
        http.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    reject(e);
                }
            });
        }).on('error', reject);
    });
}

class CdpClient {
    constructor(wsUrl) {
        this.wsUrl = wsUrl;
        this.ws = null;
        this.id = 1;
        this.callbacks = new Map();
    }

    connect() {
        return new Promise((resolve, reject) => {
            this.ws = new WebSocket(this.wsUrl);
            this.ws.onopen = () => resolve();
            this.ws.onerror = (err) => reject(err);
            this.ws.onmessage = (event) => {
                const msg = JSON.parse(event.data);
                if (msg.id && this.callbacks.has(msg.id)) {
                    const cb = this.callbacks.get(msg.id);
                    this.callbacks.delete(msg.id);
                    if (msg.error) cb.reject(new Error(msg.error.message));
                    else cb.resolve(msg.result);
                }
            };
        });
    }

    send(method, params = {}) {
        return new Promise((resolve, reject) => {
            const id = this.id++;
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    async evaluate(expr) {
        const res = await this.send('Runtime.evaluate', {
            expression: expr,
            returnByValue: true,
            awaitPromise: true
        });
        if (res.exceptionDetails) {
            throw new Error(res.exceptionDetails.exception?.description || 'Evaluation error');
        }
        return res.result?.value;
    }

    async setViewport(width, height) {
        await this.send('Emulation.setDeviceMetricsOverride', {
            width,
            height,
            deviceScaleFactor: 1,
            mobile: width < 768
        });
    }

    async screenshot(filepath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(filepath, Buffer.from(res.data, 'base64'));
        console.log(`Saved screenshot: ${filepath}`);
    }

    close() {
        if (this.ws) this.ws.close();
    }
}

async function runBrowserTests() {
    console.log('=== STARTING BROWSER VERIFICATION: SUGGESTED USERS, AVATARS, AND HANDLES ===\n');

    try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (e) {}
    const edgeProc = spawn(EDGE_PATH, [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        'about:blank'
    ]);

    await sleep(2500);

    const targets = await fetchJson(`http://localhost:${DEBUG_PORT}/json/list`);
    const pageTarget = targets.find(t => t.type === 'page');
    if (!pageTarget) {
        throw new Error('No page target found');
    }

    const client = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await client.connect();

    await client.send('Page.enable');
    await client.send('DOM.enable');
    await client.send('Runtime.enable');

    console.log('Connected to Microsoft Edge via CDP.');

    let passedChecks = 0;
    let failedChecks = 0;

    function assert(condition, message) {
        if (condition) {
            passedChecks++;
            console.log(`  [PASS] ${message}`);
        } else {
            failedChecks++;
            console.log(`  [FAIL] ${message}`);
        }
    }

    try {
        // Step 1: Sign in
        console.log('\n--- Step 1: Sign in with authenticated user ---');
        await client.setViewport(1440, 900);
        await client.send('Page.navigate', { url: 'http://localhost:8086/login' });
        await sleep(2000);

        const creds = loadCredentials();
        await client.evaluate(`
            (() => {
                const idInput = document.getElementById('identifier');
                const pwInput = document.getElementById('password');
                const form = document.getElementById('login-form');
                if (idInput && pwInput && form) {
                    idInput.value = ${JSON.stringify(creds.email)};
                    pwInput.value = ${JSON.stringify(creds.password)};
                    form.submit();
                }
            })()
        `);
        await sleep(3000);

        async function dismissCookies() {
            await client.evaluate(`
                (() => {
                    const btns = Array.from(document.querySelectorAll('button'));
                    const acceptBtn = btns.find(b => b.textContent.trim().toLowerCase().includes('accept all'));
                    if (acceptBtn) acceptBtn.click();
                    const banners = document.querySelectorAll('.cookie-banner, .cookie-consent-banner, [aria-label*="cookie"], [aria-label*="Cookie"]');
                    banners.forEach(b => b.style.display = 'none');
                })()
            `);
            await sleep(600);
        }

        // Step 2: Homepage Verification (Desktop Glassmorphism)
        console.log('\n--- Step 2: Verify Homepage "People You Know" & Feed ---');
        await client.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(2500);
        await dismissCookies();

        const homeData = await client.evaluate(`
            (() => {
                // Header user info
                const headerHandleEl = document.querySelector('.header-user-handle');
                const headerHandle = headerHandleEl ? headerHandleEl.textContent.trim() : '';

                // People You Know sidebar
                const authorRows = Array.from(document.querySelectorAll('.sidebar-author-row')).map(row => {
                    const id = row.getAttribute('data-author-id');
                    const name = row.querySelector('.sidebar-row-title')?.textContent.trim() || '';
                    const handle = row.querySelector('.sidebar-row-sub')?.textContent.trim() || '';
                    const followers = row.querySelector('.sidebar-follower-meta')?.textContent.trim() || '';
                    const btn = row.querySelector('.btn-follow-toggle');
                    const btnText = btn ? btn.textContent.trim().replace(/\\s+/g, ' ') : '';
                    const isFollowing = btn ? btn.getAttribute('data-following') : '';
                    const img = row.querySelector('.sidebar-avatar-img');
                    const hasImg = !!img && (img.offsetParent !== null || img.naturalWidth > 0);
                    const fallback = row.querySelector('.sidebar-avatar-fallback');
                    const hasFallback = !!fallback && fallback.offsetParent !== null;

                    return { id, name, handle, followers, btnText, isFollowing, hasImg, hasFallback };
                });

                // Feed cards
                const feedCards = Array.from(document.querySelectorAll('.feed-card')).slice(0, 5).map(card => {
                    const authorName = card.querySelector('.feed-author-name')?.textContent.trim() || '';
                    const authorHandle = card.querySelector('.feed-author-handle')?.textContent.trim() || '';
                    const img = card.querySelector('.feed-author-avatar-img');
                    const avatarSrc = img ? img.src : null;
                    const fallback = card.querySelector('.feed-avatar-fallback');
                    const hasImg = !!img;
                    const hasFallback = !!fallback;
                    return { authorName, authorHandle, avatarSrc, hasImg, hasFallback };
                });

                return { headerHandle, authorRows, feedCards };
            })()
        `);

        console.log('Homepage inspection result:');
        console.log(`  Current User Header Handle: "${homeData.headerHandle}"`);
        console.log(`  Found ${homeData.authorRows.length} authors in "People You Know"`);
        console.log(`  Found ${homeData.feedCards.length} feed cards inspected`);

        // Check 1: Header handle is clean
        assert(
            homeData.headerHandle.startsWith('@') && !homeData.headerHandle.includes('@example.com') && !homeData.headerHandle.includes('@gmail.com'),
            `Header user dropdown shows authentic handle "${homeData.headerHandle}" (no email leak)`
        );

        // Check 2: People You Know never recommends current user
        const selfInSidebar = homeData.authorRows.some(a => a.handle.toLowerCase() === homeData.headerHandle.toLowerCase());
        assert(!selfInSidebar, `Current user (${homeData.headerHandle}) is NOT in "People You Know" recommendations`);

        // Check 3: People You Know authors have clean handles and avatars
        homeData.authorRows.forEach((author, i) => {
            assert(
                author.handle.startsWith('@') && !author.handle.includes('.com') && !author.handle.includes(' '),
                `Author #${i + 1} (${author.name}) has valid handle "${author.handle}"`
            );
            assert(
                author.hasImg || author.hasFallback,
                `Author #${i + 1} (${author.name}) has rendered profile picture or fallback`
            );
        });

        // Check 4: Feed posts have clean handles and no email leaks
        homeData.feedCards.forEach((post, i) => {
            assert(
                post.authorHandle.startsWith('@') && !post.authorHandle.includes('.com'),
                `Feed post #${i + 1} (${post.authorName}) displays clean handle "${post.authorHandle}"`
            );
            assert(
                post.hasImg || post.hasFallback,
                `Feed post #${i + 1} has valid avatar container`
            );
        });

        // Step 3: Test Follow / Unfollow Interaction in sidebar
        if (homeData.authorRows.length > 0) {
            console.log('\n--- Step 3: Testing Follow / Unfollow Button Interaction ---');
            const toggleResult = await client.evaluate(`
                (async () => {
                    const btn = document.querySelector('.sidebar-author-row .btn-follow-toggle');
                    if (!btn) return { error: 'No follow button found' };
                    const initialText = btn.textContent.trim().replace(/\\s+/g, ' ');
                    btn.click();
                    await new Promise(r => setTimeout(r, 1200));
                    const afterClickText = btn.textContent.trim().replace(/\\s+/g, ' ');
                    btn.click(); // revert
                    await new Promise(r => setTimeout(r, 1200));
                    const finalRevertText = btn.textContent.trim().replace(/\\s+/g, ' ');
                    return { initialText, afterClickText, finalRevertText };
                })()
            `);
            console.log('Follow toggle test result:', toggleResult);
            assert(
                toggleResult.initialText !== toggleResult.afterClickText,
                `Follow button changed state from "${toggleResult.initialText}" to "${toggleResult.afterClickText}"`
            );
        }

        await client.screenshot(path.join(ARTIFACTS_DIR, 'suggested_users_and_avatars_desktop.png'));

        // Step 4: Article View
        console.log('\n--- Step 4: Verify Article Page Author Profile & Avatar ---');
        await client.send('Page.navigate', { url: 'http://localhost:8086/article/16' });
        await sleep(2500);
        await dismissCookies();

        const articleAuthorData = await client.evaluate(`
            (() => {
                const info = document.querySelector('.article-author-info');
                if (!info) return null;
                const name = info.querySelector('a[title*="Author"]')?.textContent.trim() || '';
                const handle = info.querySelector('.article-author-handle')?.textContent.trim() || '';
                const img = info.querySelector('.article-author-avatar-img');
                const avatarSrc = img ? img.src : null;
                const fallback = info.querySelector('.article-avatar-fallback');
                return { name, handle, avatarSrc, hasImg: !!img, hasFallback: !!fallback };
            })()
        `);

        if (articleAuthorData) {
            console.log('Article author data:', articleAuthorData);
            assert(
                articleAuthorData.handle.startsWith('@') && !articleAuthorData.handle.includes('.com'),
                `Article author header displays clean handle "${articleAuthorData.handle}"`
            );
            assert(
                articleAuthorData.hasImg || articleAuthorData.hasFallback,
                `Article author header renders profile picture or initial fallback`
            );
        } else {
            console.log('Article 16 not found, navigating to homepage first article...');
            await client.send('Page.navigate', { url: 'http://localhost:8086/' });
            await sleep(1500);
            await client.evaluate(`document.querySelector('.feed-card a.feed-author-avatar-link')?.click()`);
            await sleep(2000);
            await dismissCookies();
        }
        await client.screenshot(path.join(ARTIFACTS_DIR, 'article_author_avatar_desktop.png'));

        // Step 5: Mobile Viewport (Claymorphism & Off-Canvas Drawer)
        console.log('\n--- Step 5: Verify Mobile Viewport & Drawer User Identity ---');
        await client.setViewport(375, 812);
        await client.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(2000);
        await dismissCookies();

        // Verify mobile feed
        const mobileFeedData = await client.evaluate(`
            (() => {
                const card = document.querySelector('.feed-card');
                if (!card) return null;
                const handle = card.querySelector('.feed-author-handle')?.textContent.trim() || '';
                const name = card.querySelector('.feed-author-name')?.textContent.trim() || '';
                return { name, handle };
            })()
        `);
        if (mobileFeedData) {
            assert(
                mobileFeedData.handle.startsWith('@') && !mobileFeedData.handle.includes('.com'),
                `Mobile feed card displays clean handle "${mobileFeedData.handle}"`
            );
        }
        await client.screenshot(path.join(ARTIFACTS_DIR, 'mobile_feed_avatars_clay.png'));

        // Open mobile drawer
        await client.evaluate(`
            (() => {
                const btn = document.getElementById('mobile-nav-toggle') || document.querySelector('.mobile-nav-toggle, .header-mobile-toggle');
                if (btn) btn.click();
                const drawer = document.getElementById('mobile-nav-drawer');
                if (drawer && !drawer.classList.contains('open')) {
                    drawer.classList.add('open');
                    drawer.setAttribute('aria-hidden', 'false');
                }
            })()
        `);
        await sleep(1000);

        const drawerData = await client.evaluate(`
            (() => {
                const drawerHandleEl = document.querySelector('.mobile-user-handle, .sidebar-user-handle');
                const drawerNameEl = document.querySelector('.mobile-user-name, .sidebar-user-name');
                return {
                    name: drawerNameEl ? drawerNameEl.textContent.trim() : '',
                    handle: drawerHandleEl ? drawerHandleEl.textContent.trim() : ''
                };
            })()
        `);
        console.log('Drawer user data:', drawerData);
        assert(
            drawerData.handle.startsWith('@') && !drawerData.handle.includes('@example.com') && !drawerData.handle.includes('@gmail.com'),
            `Mobile drawer user meta displays clean handle "${drawerData.handle}" (no email leak)`
        );

        await client.evaluate(`
            (() => {
                const drawer = document.getElementById('mobile-nav-drawer');
                if (drawer) drawer.scrollTop = drawer.scrollHeight;
            })()
        `);
        await sleep(500);

        await client.screenshot(path.join(ARTIFACTS_DIR, 'mobile_drawer_user_handle.png'));

    } finally {
        client.close();
        edgeProc.kill();
    }

    console.log('\n======================================================');
    console.log(`Browser verification completed: ${passedChecks} passed, ${failedChecks} failed`);
    console.log('======================================================\n');

    process.exit(failedChecks === 0 ? 0 : 1);
}

runBrowserTests().catch(err => {
    console.error('Browser test failed:', err);
    process.exit(1);
});
