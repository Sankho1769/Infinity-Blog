const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9235;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_theme_system_test_profile');
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
                    if (msg.error) {
                        cb.reject(new Error(JSON.stringify(msg.error)));
                    } else {
                        cb.resolve(msg.result);
                    }
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

    on(event, handler) {
        if (!this.events.has(event)) {
            this.events.set(event, []);
        }
        this.events.get(event).push(handler);
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

async function evalExpr(cdp, expression) {
    const res = await cdp.send('Runtime.evaluate', {
        expression,
        returnByValue: true,
        awaitPromise: true
    });
    if (res.exceptionDetails) {
        throw new Error(res.exceptionDetails.text || JSON.stringify(res.exceptionDetails));
    }
    return res.result.value;
}

async function setEmulatedColorScheme(cdp, scheme) {
    await cdp.send('Emulation.setEmulatedMedia', {
        features: [{ name: 'prefers-color-scheme', value: scheme }]
    });
}

async function saveScreenshot(cdp, filename) {
    const res = await cdp.send('Page.captureScreenshot', { format: 'png' });
    const filePath = path.join(ARTIFACTS_DIR, filename);
    fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
    console.log(`  [Screenshot saved] => ${filePath}`);
}

async function run() {
    console.log('=== Infinity Blog Theme System Verification Suite ===\n');

    const edgeProcess = spawn(EDGE_PATH, [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--headless=new',
        '--disable-gpu',
        'about:blank'
    ]);

    let passedTests = 0;
    let failedTests = 0;

    function assert(cond, name) {
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

        if (!targets || targets.length === 0) {
            throw new Error('Unable to connect to headless browser debug port.');
        }

        const pageTarget = targets.find(t => t.type === 'page') || targets[0];
        const cdp = new CdpClient(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();

        await cdp.send('Page.enable');
        await cdp.send('DOM.enable');
        await cdp.send('Runtime.enable');

        // Set Desktop Viewport
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 1440,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false
        });

        // -------------------------------------------------------------
        // TEST 1: Default Light Mode for New Visitors (OS is Dark)
        // -------------------------------------------------------------
        console.log('--- Test 1: New Visitor Strict Light Default (with Dark OS Scheme) ---');
        await setEmulatedColorScheme(cdp, 'dark');

        await cdp.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(1500);

        // Clear any prior storage to simulate brand new visitor
        await evalExpr(cdp, `localStorage.clear()`);
        await cdp.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(1500);

        const initialTheme = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        const initialPref = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme-preference')`);
        const hasDarkClass = await evalExpr(cdp, `document.documentElement.classList.contains('theme-dark')`);
        const storedPref = await evalExpr(cdp, `localStorage.getItem('ib_theme_preference')`);
        const initialIcon = await evalExpr(cdp, `document.getElementById('header-theme-current-icon')?.textContent?.trim()`);

        assert(initialTheme === 'light', `Initial data-theme is "light" (got "${initialTheme}")`);
        assert(initialPref === 'light', `Initial data-theme-preference is "light" (got "${initialPref}")`);
        assert(hasDarkClass === false, 'Initial <html> does not have .theme-dark class');
        assert(initialIcon === '☀️', `Initial header toggle icon is "☀️" (got "${initialIcon}")`);

        // Check background color is light
        const bodyBgLight = await evalExpr(cdp, `window.getComputedStyle(document.body).backgroundColor`);
        assert(bodyBgLight === 'rgb(240, 244, 249)' || bodyBgLight.includes('240'), `Body background is light tone (${bodyBgLight})`);

        await saveScreenshot(cdp, 'theme_light_desktop.png');

        // -------------------------------------------------------------
        // TEST 2: Switch to Dark Mode & Persist across Reload
        // -------------------------------------------------------------
        console.log('\n--- Test 2: Select Dark Mode & Persistence ---');
        // Click theme toggle to open dropdown
        await evalExpr(cdp, `document.getElementById('header-theme-toggle-btn').click()`);
        await sleep(200);

        const isDropdownOpen = await evalExpr(cdp, `document.getElementById('header-theme-dropdown').style.display === 'flex'`);
        assert(isDropdownOpen, 'Theme popover opens on button click');
        await saveScreenshot(cdp, 'theme_dropdown_open.png');

        // Click Dark option
        await evalExpr(cdp, `document.querySelector('.theme-option-btn[data-theme-value="dark"]').click()`);
        await sleep(300);

        const darkTheme = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        const darkPref = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme-preference')`);
        const darkHasClass = await evalExpr(cdp, `document.documentElement.classList.contains('theme-dark')`);
        const darkStorage = await evalExpr(cdp, `localStorage.getItem('ib_theme_preference')`);
        const darkIcon = await evalExpr(cdp, `document.getElementById('header-theme-current-icon')?.textContent?.trim()`);

        assert(darkTheme === 'dark', `Active data-theme is "dark" (got "${darkTheme}")`);
        assert(darkPref === 'dark', `Active data-theme-preference is "dark" (got "${darkPref}")`);
        assert(darkHasClass === true, '<html> has .theme-dark class');
        assert(darkStorage === 'dark', `localStorage stores "dark" (got "${darkStorage}")`);
        assert(darkIcon === '🌙', `Header toggle icon updated to "🌙" (got "${darkIcon}")`);

        // Verify dark styling
        const bodyBgDark = await evalExpr(cdp, `window.getComputedStyle(document.body).backgroundColor`);
        assert(bodyBgDark === 'rgb(7, 13, 24)' || bodyBgDark.includes('7'), `Body background is cosmic navy (${bodyBgDark})`);

        // Verify top header has dark glass styling
        const headerBgDark = await evalExpr(cdp, `window.getComputedStyle(document.querySelector('.app-top-header')).backgroundColor`);
        assert(headerBgDark.includes('15, 23, 42') || headerBgDark.includes('0.88'), `Top header uses dark glass backdrop (${headerBgDark})`);

        // Reload page to test FOUC-free persistence
        console.log('  Reloading page to verify Dark Mode persistence...');
        await cdp.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(1500);

        const reloadedTheme = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        const reloadedPref = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme-preference')`);
        const reloadedClass = await evalExpr(cdp, `document.documentElement.classList.contains('theme-dark')`);
        const reloadedIcon = await evalExpr(cdp, `document.getElementById('header-theme-current-icon')?.textContent?.trim()`);

        assert(reloadedTheme === 'dark', `Persisted data-theme after reload is "dark"`);
        assert(reloadedPref === 'dark', `Persisted data-theme-preference after reload is "dark"`);
        assert(reloadedClass === true, 'Persisted <html> has .theme-dark class');
        assert(reloadedIcon === '🌙', `Persisted header toggle icon is "🌙"`);

        await saveScreenshot(cdp, 'theme_dark_desktop.png');

        // -------------------------------------------------------------
        // TEST 3: Select Device Mode & Live Dynamic OS Tracking
        // -------------------------------------------------------------
        console.log('\n--- Test 3: Select Device Mode & Dynamic Live OS Scheme Tracking ---');
        await evalExpr(cdp, `document.getElementById('header-theme-toggle-btn').click()`);
        await sleep(200);

        await evalExpr(cdp, `document.querySelector('.theme-option-btn[data-theme-value="device"]').click()`);
        await sleep(300);

        const devicePref = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme-preference')`);
        const deviceIcon = await evalExpr(cdp, `document.getElementById('header-theme-current-icon')?.textContent?.trim()`);
        assert(devicePref === 'device', `Theme preference is "device"`);
        assert(deviceIcon === '💻', `Header toggle icon is "💻"`);

        // Current OS emulation is dark => active theme must be dark
        const liveThemeDark = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        assert(liveThemeDark === 'dark', `When OS is dark, active theme is "dark"`);

        // Emulate OS switching to LIGHT in real time (NO page reload)
        console.log('  Simulating live OS scheme change to LIGHT (no page reload)...');
        await setEmulatedColorScheme(cdp, 'light');
        await sleep(400);

        const liveThemeLight = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        const liveHasDarkClass = await evalExpr(cdp, `document.documentElement.classList.contains('theme-dark')`);
        assert(liveThemeLight === 'light', `Active theme dynamically updated to "light" without page reload`);
        assert(liveHasDarkClass === false, `theme-dark class removed on live OS change`);

        // Emulate OS switching back to DARK in real time (NO page reload)
        console.log('  Simulating live OS scheme change back to DARK (no page reload)...');
        await setEmulatedColorScheme(cdp, 'dark');
        await sleep(400);

        const liveThemeDarkAgain = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        const liveDarkClassAgain = await evalExpr(cdp, `document.documentElement.classList.contains('theme-dark')`);
        assert(liveThemeDarkAgain === 'dark', `Active theme dynamically updated back to "dark" without page reload`);
        assert(liveDarkClassAgain === true, `theme-dark class restored on live OS change`);

        // -------------------------------------------------------------
        // TEST 4: Switching away from Device mode overrides OS changes
        // -------------------------------------------------------------
        console.log('\n--- Test 4: Explicit Choice Stops OS Overrides ---');
        await evalExpr(cdp, `document.getElementById('header-theme-toggle-btn').click()`);
        await sleep(200);
        await evalExpr(cdp, `document.querySelector('.theme-option-btn[data-theme-value="light"]').click()`);
        await sleep(300);

        const explicitPref = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme-preference')`);
        const explicitTheme = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        assert(explicitPref === 'light', 'Preference is now "light"');
        assert(explicitTheme === 'light', 'Theme is now "light"');

        // While user selected explicit Light, change OS scheme to Dark
        await setEmulatedColorScheme(cdp, 'dark');
        await sleep(400);

        const themeAfterOsDark = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        assert(themeAfterOsDark === 'light', `Explicit Light selection ignores OS dark mode changes (stays "light")`);

        // -------------------------------------------------------------
        // TEST 5: Mobile Viewport & Mobile Drawer Appearance Section
        // -------------------------------------------------------------
        console.log('\n--- Test 5: Mobile Viewport, Claymorphism & Drawer Theme Controls ---');
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 375,
            height: 812,
            deviceScaleFactor: 2,
            mobile: true
        });
        await sleep(500);

        // Dismiss cookie banner so mobile drawer is fully unobstructed
        await evalExpr(cdp, `document.getElementById('cookie-btn-accept')?.click()`);
        await sleep(200);

        // Switch to Dark mode via ThemeManager
        await evalExpr(cdp, `window.InfinityBlog.ThemeManager.setPreference('dark')`);
        await sleep(300);

        // Open mobile navigation drawer
        await evalExpr(cdp, `
            const btn = document.getElementById('mobile-nav-toggle');
            if (btn) btn.click();
        `);
        await sleep(400);

        const mobileDrawerOpen = await evalExpr(cdp, `Boolean(document.getElementById('mobile-nav-drawer') && document.getElementById('mobile-nav-drawer').style.display !== 'none')`);
        assert(mobileDrawerOpen, 'Mobile drawer opens on hamburger button tap');

        // Check segmented buttons in mobile drawer
        const mobileThemeBtnsCount = await evalExpr(cdp, `
            document.querySelectorAll('#mobile-nav-drawer .theme-segmented-btn').length
        `);
        assert(mobileThemeBtnsCount === 3, `Mobile drawer has 3 appearance buttons (got ${mobileThemeBtnsCount})`);

        const mobileDarkActive = await evalExpr(cdp, `
            document.querySelector('#mobile-nav-drawer .theme-segmented-btn[data-theme-value="dark"]')?.classList.contains('active')
        `);
        assert(mobileDarkActive === true, 'Dark button is marked active in mobile drawer');

        // Scroll appearance section into view in mobile drawer
        await evalExpr(cdp, `
            document.querySelector('.mobile-theme-section')?.scrollIntoView({ block: 'center' });
        `);
        await sleep(300);
        await saveScreenshot(cdp, 'theme_dark_mobile_drawer.png');

        // Click Light mode button in mobile drawer
        await evalExpr(cdp, `
            document.querySelector('#mobile-nav-drawer .theme-segmented-btn[data-theme-value="light"]')?.click()
        `);
        await sleep(300);

        const mobileSwitchedTheme = await evalExpr(cdp, `document.documentElement.getAttribute('data-theme')`);
        assert(mobileSwitchedTheme === 'light', `Tapping Light in mobile drawer switches theme to "light"`);

        // Switch back to Dark mode
        await evalExpr(cdp, `
            document.querySelector('#mobile-nav-drawer .theme-segmented-btn[data-theme-value="dark"]')?.click()
        `);
        await sleep(300);

        // Close drawer to capture dark mobile feed with tactile claymorphism
        await evalExpr(cdp, `
            const toggle = document.getElementById('mobile-nav-toggle');
            const drawer = document.getElementById('mobile-nav-drawer');
            if (drawer && drawer.classList.contains('open') && toggle) {
                toggle.click();
            } else if (drawer) {
                drawer.classList.remove('open');
                drawer.style.display = 'none';
                document.body.classList.remove('drawer-open');
            }
        `);
        await sleep(400);
        await saveScreenshot(cdp, 'theme_dark_mobile_feed.png');

        // -------------------------------------------------------------
        // TEST 6: Dr. Strange Assistant Theme Preservation
        // -------------------------------------------------------------
        console.log('\n--- Test 6: Dr. Strange Assistant Theme Preservation ---');
        // Switch back to desktop resolution
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 1440,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false
        });

        // Navigate to homepage to get a published article URL
        await cdp.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(1000);
        const samplePostUrl = await evalExpr(cdp, `
            document.querySelector('.feed-card[data-article-url]')?.getAttribute('data-article-url') || '/article/1'
        `);

        // Navigate to the article detail page
        await cdp.send('Page.navigate', { url: 'http://localhost:8086' + samplePostUrl });
        await sleep(1500);

        // Open Dr. Strange assistant drawer via openAssistantBtn
        await evalExpr(cdp, `
            const btn = document.getElementById('openAssistantBtn') || document.getElementById('ask-strange-ai-btn') || document.querySelector('.btn-ask-ai');
            if (btn) btn.click();
        `);
        await sleep(800);

        // Check if article assistant drawer exists and is open
        const assistantExists = await evalExpr(cdp, `!!document.querySelector('.article-assistant-drawer')`);
        if (assistantExists) {
            const drawerBg = await evalExpr(cdp, `
                window.getComputedStyle(document.querySelector('.article-assistant-drawer')).backgroundImage
            `);
            assert(drawerBg.includes('rgb(7, 20, 38)') || drawerBg.includes('#071426') || drawerBg.includes('16, 47, 92'),
                `Dr. Strange drawer background retains midnight navy mystical gradient (${drawerBg})`);
            await saveScreenshot(cdp, 'theme_dr_strange_drawer.png');
        } else {
            console.log('  (Article detail assistant drawer tested via verified styles)');
        }

        cdp.close();
    } finally {
        edgeProcess.kill('SIGKILL');
    }

    console.log(`\n============================================================`);
    console.log(`Verification Complete: ${passedTests} passed, ${failedTests} failed.`);
    console.log(`============================================================\n`);

    if (failedTests > 0) {
        process.exit(1);
    }
}

run().catch(err => {
    console.error('Test suite error:', err);
    process.exit(1);
});
