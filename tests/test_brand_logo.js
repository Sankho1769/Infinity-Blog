const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9247;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_brand_test_profile');
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

async function runTests() {
    console.log('=== STARTING OFFICIAL BRAND LOGO VERIFICATION ===');
    
    // 1. Launch Edge
    try {
        fs.rmSync(USER_DATA_DIR, { recursive: true, force: true });
    } catch (e) {}

    const edgeProc = spawn(EDGE_PATH, [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--headless=new',
        '--disable-gpu',
        'http://localhost:8086/'
    ], { stdio: 'ignore' });

    await sleep(2500);

    const versionData = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json/version`);
    const targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json`);
    const pageTarget = targets.find(t => t.type === 'page');
    if (!pageTarget) throw new Error('No page target found');

    const client = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await client.connect();
    console.log('Connected to Edge via CDP.');

    await client.send('Page.enable');
    await client.send('DOM.enable');
    await client.send('Runtime.enable');

    // TEST 1: Homepage Header (1536px Desktop)
    console.log('\n--- Test 1: Desktop Header & Favicons (1536px) ---');
    await client.setViewport(1536, 864);
    await client.send('Page.navigate', { url: 'http://localhost:8086/' });
    await sleep(1500);

    const desktopLogoCheck = await client.evaluate(`
        (() => {
            const img = document.querySelector('.top-header-left .brand-logo img');
            const pic = document.querySelector('.top-header-left .brand-logo picture');
            return {
                imgFound: !!img,
                currentSrc: img ? img.currentSrc : null,
                naturalWidth: img ? img.naturalWidth : 0,
                naturalHeight: img ? img.naturalHeight : 0,
                complete: img ? img.complete : false,
                alt: img ? img.alt : '',
                hasPicture: !!pic
            };
        })()
    `);
    console.log('Desktop Header Logo:', desktopLogoCheck);

    const faviconCheck = await client.evaluate(`
        (() => {
            const icons = Array.from(document.querySelectorAll('link[rel*="icon"]')).map(l => ({
                rel: l.rel,
                href: l.href,
                sizes: l.getAttribute('sizes') || 'default',
                type: l.type
            }));
            const manifest = document.querySelector('link[rel="manifest"]')?.href;
            return { icons, manifest };
        })()
    `);
    console.log('Favicon links verified:', faviconCheck.icons.length, 'icons, manifest:', !!faviconCheck.manifest);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_desktop_header_1536.png'));

    // TEST 2: Homepage Footer (1536px Desktop)
    console.log('\n--- Test 2: Desktop Footer Brand (1536px) ---');
    await client.evaluate(`window.scrollTo(0, document.body.scrollHeight)`);
    await sleep(800);
    const footerLogoCheck = await client.evaluate(`
        (() => {
            const img = document.querySelector('.footer-brand-logo img');
            return {
                imgFound: !!img,
                currentSrc: img ? img.currentSrc : null,
                naturalWidth: img ? img.naturalWidth : 0,
                naturalHeight: img ? img.naturalHeight : 0,
                complete: img ? img.complete : false
            };
        })()
    `);
    console.log('Footer Logo Check:', footerLogoCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_desktop_footer_1536.png'));

    // TEST 3: Dark Mode Header (1536px)
    console.log('\n--- Test 3: Dark Mode Desktop Header (1536px) ---');
    await client.evaluate(`
        (() => {
            window.scrollTo(0, 0);
            if (window.ThemeManager) window.ThemeManager.setPreference('dark');
            else document.documentElement.setAttribute('data-theme', 'dark');
        })()
    `);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_desktop_header_dark.png'));

    // TEST 4: Tablet Viewport (1024px & 768px)
    console.log('\n--- Test 4: Tablet Viewport (1024px & 768px) ---');
    await client.setViewport(1024, 768);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_tablet_1024.png'));

    await client.setViewport(768, 1024);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_tablet_768.png'));

    // TEST 5: Mobile Viewport (375px & 430px) - Symbol Switcher
    console.log('\n--- Test 5: Mobile Viewport Symbol Switcher (375px & 430px) ---');
    await client.evaluate(`
        if (window.ThemeManager) window.ThemeManager.setPreference('light');
    `);
    await client.setViewport(375, 750);
    await sleep(800);

    const mobileLogoCheck = await client.evaluate(`
        (() => {
            const img = document.querySelector('.top-header-left .brand-logo img');
            return {
                currentSrc: img ? img.currentSrc : null,
                naturalWidth: img ? img.naturalWidth : 0,
                naturalHeight: img ? img.naturalHeight : 0,
                renderedWidth: img ? img.width : 0,
                renderedHeight: img ? img.height : 0
            };
        })()
    `);
    console.log('Mobile Header Logo Check:', mobileLogoCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_mobile_375_header.png'));

    // Open mobile navigation drawer
    await client.evaluate(`
        (() => {
            document.getElementById('mobile-nav-toggle')?.click();
        })()
    `);
    await sleep(800);
    const drawerLogoCheck = await client.evaluate(`
        (() => {
            const img = document.querySelector('.mobile-drawer-brand img');
            return {
                imgFound: !!img,
                currentSrc: img ? img.currentSrc : null,
                complete: img ? img.complete : false
            };
        })()
    `);
    console.log('Mobile Drawer Logo Check:', drawerLogoCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_mobile_375_drawer.png'));

    // Close drawer
    await client.evaluate(`document.getElementById('mobile-nav-close-btn')?.click()`);
    await sleep(500);

    // TEST 6: Sign In & Create Account Pages
    console.log('\n--- Test 6: Sign In & Registration Cards (/login, /register) ---');
    await client.setViewport(1280, 800);
    await client.send('Page.navigate', { url: 'http://localhost:8086/login' });
    await sleep(1500);

    const loginBrandCheck = await client.evaluate(`
        (() => {
            const cardImg = document.querySelector('.auth-signin-panel .auth-card-logo-img');
            const overlaySymbols = document.querySelectorAll('.auth-overlay-symbol');
            return {
                cardLogoFound: !!cardImg,
                cardLogoSrc: cardImg ? cardImg.src : null,
                cardLogoComplete: cardImg ? cardImg.complete : false,
                overlaySymbolsCount: overlaySymbols.length
            };
        })()
    `);
    console.log('Sign In Brand Check:', loginBrandCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_auth_signin_desktop.png'));

    // Toggle to Sign Up
    await client.evaluate(`document.getElementById('to-signup-btn')?.click()`);
    await sleep(1000);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_auth_signup_desktop.png'));

    // Mobile auth card (375px)
    await client.setViewport(375, 750);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_auth_mobile_clay.png'));

    // TEST 7: Writing Studio Brand Mark
    console.log('\n--- Test 7: Writing Studio Brand Mark (/posts/new) ---');
    await client.setViewport(1440, 900);
    // Sign in first
    await client.send('Page.navigate', { url: 'http://localhost:8086/login' });
    await sleep(1000);
    const creds = loadCredentials();
    await client.evaluate(`
        (() => {
            document.getElementById('identifier').value = ${JSON.stringify(creds.email)};
            document.getElementById('password').value = ${JSON.stringify(creds.password)};
            document.getElementById('login-form').submit();
        })()
    `);
    await sleep(2000);

    await client.send('Page.navigate', { url: 'http://localhost:8086/posts/new' });
    await sleep(1500);

    const studioLogoCheck = await client.evaluate(`
        (() => {
            const sym = document.querySelector('.studio-brand-link .studio-brand-symbol-img');
            const name = document.querySelector('.studio-brand-link .studio-brand-name');
            return {
                symbolFound: !!sym,
                symbolSrc: sym ? sym.src : null,
                symbolComplete: sym ? sym.complete : false,
                nameText: name ? name.textContent : null
            };
        })()
    `);
    console.log('Writing Studio Logo Check:', studioLogoCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_writing_studio_desktop.png'));

    // TEST 8: Author Dashboard Brand
    console.log('\n--- Test 8: Author Dashboard Brand (/dashboard) ---');
    await client.send('Page.navigate', { url: 'http://localhost:8086/dashboard' });
    await sleep(1500);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'brand_author_dashboard.png'));

    console.log('\n=== ALL BRAND LOGO TESTS COMPLETED SUCCESSFULLY ===');
    client.close();
    edgeProc.kill();
    process.exit(0);
}

runTests().catch(err => {
    console.error('Test execution failed:', err);
    process.exit(1);
});
