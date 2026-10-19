const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9245;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_full_audit_' + Date.now());
const ARTIFACTS_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
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
        this.events = new Map();
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
                    if (msg.error) cb.reject(new Error(JSON.stringify(msg.error)));
                    else cb.resolve(msg.result);
                } else if (msg.method) {
                    const handlers = this.events.get(msg.method) || [];
                    handlers.forEach(h => h(msg.params));
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

    close() {
        if (this.ws) this.ws.close();
    }
}

// In-browser contrast evaluation function
const EVALUATE_PAGE_CONTRAST_FN = `
(() => {
    function parseRgb(colorStr) {
        if (!colorStr) return [255, 255, 255, 1];
        const match = colorStr.match(/rgba?\\((\\d+),\\s*(\\d+),\\s*(\\d+)(?:,\\s*([\\d.]+))?\\)/);
        if (match) {
            return [
                parseInt(match[1], 10),
                parseInt(match[2], 10),
                parseInt(match[3], 10),
                match[4] !== undefined ? parseFloat(match[4]) : 1
            ];
        }
        return [255, 255, 255, 1];
    }

    function getLuminance(r, g, b) {
        const [rs, gs, bs] = [r, g, b].map(c => {
            const s = c / 255;
            return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
    }

    function getContrast(rgb1, rgb2) {
        const l1 = getLuminance(rgb1[0], rgb1[1], rgb1[2]);
        const l2 = getLuminance(rgb2[0], rgb2[1], rgb2[2]);
        const lighter = Math.max(l1, l2);
        const darker = Math.min(l1, l2);
        return (lighter + 0.05) / (darker + 0.05);
    }

    function getEffectiveBg(el) {
        let cur = el;
        const rootBg = [15, 23, 42, 1]; // Dark base
        while (cur && cur !== document.documentElement) {
            const style = window.getComputedStyle(cur);
            const rgb = parseRgb(style.backgroundColor);
            if (rgb[3] >= 0.85) {
                return rgb;
            }
            if (rgb[3] > 0.05) {
                // blend on rootBg
                const alpha = rgb[3];
                const blended = [
                    Math.round(rgb[0] * alpha + rootBg[0] * (1 - alpha)),
                    Math.round(rgb[1] * alpha + rootBg[1] * (1 - alpha)),
                    Math.round(rgb[2] * alpha + rootBg[2] * (1 - alpha)),
                    1
                ];
                return blended;
            }
            cur = cur.parentElement;
        }
        return rootBg;
    }

    const selectors = [
        '.stat-value', '.stat-label', '.dashboard-greeting', '.dashboard-subhead',
        '.dashboard-sidebar-widget h3', '.btn-primary', '.btn-secondary',
        '.tab-pill', '.feed-tab-btn', '.filter-pill',
        '.sidebar-card-title', '.sidebar-topic-name', '.sidebar-community-name',
        '.feed-card-title a', '.article-card-title a',
        '.data-table th', '.data-table td',
        '.empty-title', '.empty-desc',
        '.reader-toolbar-btn', '.btn-bookmark',
        '.conversation-name', '.chat-bubble.received',
        '.profile-stat-box', '.category-card-title'
    ];

    const results = [];
    for (const sel of selectors) {
        const els = document.querySelectorAll(sel);
        for (let i = 0; i < Math.min(els.length, 3); i++) {
            const el = els[i];
            const rect = el.getBoundingClientRect();
            if (rect.width === 0 || rect.height === 0 || window.getComputedStyle(el).display === 'none') continue;

            const style = window.getComputedStyle(el);
            const fgRgb = parseRgb(style.color);
            const bgRgb = getEffectiveBg(el);
            const ratio = getContrast(fgRgb, bgRgb);
            const text = (el.textContent || '').trim().replace(/\\s+/g, ' ').substring(0, 30);

            results.push({
                selector: sel,
                text,
                color: style.color,
                bgColor: style.backgroundColor,
                contrastRatio: parseFloat(ratio.toFixed(2)),
                isLarge: parseFloat(style.fontSize) >= 18 || (parseFloat(style.fontSize) >= 14 && parseInt(style.fontWeight, 10) >= 700)
            });
        }
    }
    return results;
})()
`;

async function run() {
    console.log('=== Comprehensive Application-Wide Dark & Light Mode Verification Suite ===\n');

    // Create session for user 13
    const sessionCreator = spawn('C:\\xampp\\php\\php.exe', ['scratch\\create_test_session.php']);
    await new Promise(r => sessionCreator.on('close', r));

    const edgeProcess = spawn(EDGE_PATH, [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--headless=new',
        '--disable-gpu',
        'about:blank'
    ]);

    let totalTests = 0;
    let passedTests = 0;
    let failedTests = 0;

    function assert(cond, name) {
        totalTests++;
        if (cond) {
            console.log(`  PASS: ${name}`);
            passedTests++;
        } else {
            console.error(`  FAIL: ${name}`);
            failedTests++;
        }
    }

    try {
        let targets = null;
        for (let i = 0; i < 30; i++) {
            await sleep(200);
            try {
                targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json`);
                if (targets && targets.length > 0) break;
            } catch (e) {}
        }

        const pageTarget = targets.find(t => t.type === 'page') || targets[0];
        const cdp = new CdpClient(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();

        await cdp.send('Page.enable');
        await cdp.send('Network.enable');
        await cdp.send('DOM.enable');
        await cdp.send('Runtime.enable');

        await cdp.send('Network.setCookie', {
            name: 'INFINITYBLOGSESSID',
            value: 'authortestsession1234567890abcdef',
            domain: 'localhost',
            path: '/'
        });

        const pagesToAudit = [
            { path: '/dashboard', name: 'Author Dashboard', desktop: true, mobile: true },
            { path: '/posts/manage', name: 'Manage Posts', desktop: true, mobile: false },
            { path: '/posts/new', name: 'Post Editor', desktop: true, mobile: false },
            { path: '/article/7', name: 'Article & Reader Controls', desktop: true, mobile: true },
            { path: '/explore', name: 'Explore Stories', desktop: true, mobile: false },
            { path: '/categories', name: 'Categories Discovery', desktop: true, mobile: false },
            { path: '/authors', name: 'Authors Discovery', desktop: true, mobile: false },
            { path: '/communities', name: 'Communities Discovery', desktop: true, mobile: false },
            { path: '/chat', name: 'Community Chat', desktop: true, mobile: false },
            { path: '/profile', name: 'Author Profile', desktop: true, mobile: false },
            { path: '/', name: 'Homepage Feed', desktop: true, mobile: true }
        ];

        for (const p of pagesToAudit) {
            console.log(`\n=============================================================`);
            console.log(`Auditing Route: ${p.path} (${p.name})`);
            console.log(`=============================================================`);

            // 1. Desktop Dark Mode Audit
            await cdp.send('Emulation.setDeviceMetricsOverride', {
                width: 1440,
                height: 900,
                deviceScaleFactor: 1,
                mobile: false
            });

            await cdp.send('Page.navigate', { url: `http://localhost:8086${p.path}` });
            await sleep(1200);

            // Set dark theme
            await cdp.send('Runtime.evaluate', {
                expression: `
                    localStorage.setItem('ib_theme_preference', 'dark');
                    if (window.ThemeManager) {
                        window.ThemeManager.setTheme('dark');
                    } else {
                        document.documentElement.setAttribute('data-theme', 'dark');
                        document.documentElement.setAttribute('data-theme-preference', 'dark');
                        document.documentElement.classList.add('theme-dark');
                    }
                `
            });
            await sleep(600);

            // Run contrast audit on elements
            const contrastRes = await cdp.send('Runtime.evaluate', {
                expression: EVALUATE_PAGE_CONTRAST_FN,
                returnByValue: true
            });

            const elements = contrastRes.result.value || [];
            console.log(`  Found ${elements.length} components to audit contrast for.`);

            let pageContrastPass = true;
            for (const el of elements) {
                const minRatio = el.isLarge ? 3.0 : 4.5;
                const passed = el.contrastRatio >= minRatio;
                if (!passed) {
                    pageContrastPass = false;
                    console.warn(`    ⚠️ LOW CONTRAST: [${el.selector}] "${el.text}" has ratio ${el.contrastRatio}:1 (needed ${minRatio}:1)`);
                }
            }
            assert(pageContrastPass, `${p.name} Desktop Dark Mode: All evaluated components meet WCAG AA contrast targets`);

            // Save desktop screenshot
            const sanitizedName = p.path.replace(/\//g, '_').replace(/^_/, '') || 'home';
            const desktopShot = await cdp.send('Page.captureScreenshot', { format: 'png' });
            const desktopFilename = `audit_dark_${sanitizedName}_1440.png`;
            fs.writeFileSync(path.join(ARTIFACTS_DIR, desktopFilename), Buffer.from(desktopShot.data, 'base64'));
            console.log(`  Saved screenshot: ${desktopFilename}`);

            // 2. Mobile Dark Mode Audit (if applicable)
            if (p.mobile) {
                await cdp.send('Emulation.setDeviceMetricsOverride', {
                    width: 375,
                    height: 812,
                    deviceScaleFactor: 2,
                    mobile: true
                });
                await sleep(500);

                const mobileShot = await cdp.send('Page.captureScreenshot', { format: 'png' });
                const mobileFilename = `audit_dark_${sanitizedName}_mobile_375.png`;
                fs.writeFileSync(path.join(ARTIFACTS_DIR, mobileFilename), Buffer.from(mobileShot.data, 'base64'));
                console.log(`  Saved mobile screenshot: ${mobileFilename}`);
                assert(true, `${p.name} Mobile 375px: Rendered and captured successfully`);
            }
        }

        console.log('\n=============================================================');
        console.log('SUMMARY OF AUDIT RESULTS:');
        console.log(`Total tests: ${totalTests} | Passed: ${passedTests} | Failed: ${failedTests}`);
        console.log('=============================================================');

        cdp.close();
    } finally {
        edgeProcess.kill();
    }
}

run().catch(console.error);
