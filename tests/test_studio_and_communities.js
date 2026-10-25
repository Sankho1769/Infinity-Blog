const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9246;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_studio_test_profile');
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
    console.log('=== STARTING WRITING STUDIO & COMMUNITY VERIFICATION ===');
    
    // 1. Launch Edge
    try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (e) {}
    const edgeProc = spawn(EDGE_PATH, [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        'about:blank'
    ]);

    await sleep(2500);

    const versionData = await fetchJson(`http://localhost:${DEBUG_PORT}/json/version`);
    const browserWs = versionData.webSocketDebuggerUrl;
    const targets = await fetchJson(`http://localhost:${DEBUG_PORT}/json/list`);
    const pageTarget = targets.find(t => t.type === 'page');

    const client = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await client.connect();

    await client.send('Page.enable');
    await client.send('DOM.enable');
    await client.send('Runtime.enable');

    console.log('Connected to Edge via CDP.');

    // Step A: Login as Author / Admin
    console.log('\n--- Step A: Signing In as Admin ---');
    await client.setViewport(1280, 800);
    await client.send('Page.navigate', { url: 'http://localhost:8086/login' });
    await sleep(2000);

    const creds = loadCredentials();
    const loginRes = await client.evaluate(`
        (async () => {
            const idInput = document.getElementById('identifier');
            const pwInput = document.getElementById('password');
            const form = document.getElementById('login-form');
            if (idInput && pwInput && form) {
                idInput.value = ${JSON.stringify(creds.email)};
                pwInput.value = ${JSON.stringify(creds.password)};
                form.submit();
                return true;
            }
            return false;
        })()
    `);
    console.log('Login form submitted:', loginRes);
    await sleep(3500);

    // Step B: Verify Community Logos on /communities
    console.log('\n--- Step B: Verifying Community Branding on /communities ---');
    await client.send('Page.navigate', { url: 'http://localhost:8086/communities' });
    await sleep(2000);

    const commLogosCheck = await client.evaluate(`
        (() => {
            const logos = document.querySelectorAll('.community-brand-logo');
            const names = Array.from(document.querySelectorAll('.community-card h3')).map(h => h.textContent.trim());
            const hasHashOnly = Array.from(logos).some(l => l.textContent.trim() === '#');
            return {
                totalLogos: logos.length,
                names,
                hasHashOnly,
                classes: Array.from(logos).map(l => l.className)
            };
        })()
    `);
    console.log('Communities check:', commLogosCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_communities_discovery.png'));

    // Step C: Verify Community Logos on Home Sidebar
    console.log('\n--- Step C: Verifying Community Logos on Homepage Sidebar ---');
    await client.send('Page.navigate', { url: 'http://localhost:8086/' });
    await sleep(2000);

    const homeSidebarCheck = await client.evaluate(`
        (() => {
            const rows = document.querySelectorAll('.sidebar-community-row');
            const logos = document.querySelectorAll('.sidebar-community-row .community-brand-logo');
            return {
                totalRows: rows.length,
                totalLogos: logos.length,
                logosClasses: Array.from(logos).map(l => l.className)
            };
        })()
    `);
    console.log('Home sidebar communities:', homeSidebarCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_home_sidebar_communities.png'));

    // Step D: Open Writing Studio (/posts/new)
    console.log('\n--- Step D: Navigating to Writing Studio (/posts/new) ---');
    await client.setViewport(1536, 864);
    await client.send('Page.navigate', { url: 'http://localhost:8086/posts/new' });
    await sleep(2500);

    const studioElementsCheck = await client.evaluate(`
        (() => {
            const studio = document.getElementById('infinity-writing-studio');
            const topbar = document.querySelector('.studio-topbar');
            const leftSidebar = document.getElementById('studio-left-sidebar');
            const rightSidebar = document.getElementById('studio-right-sidebar');
            const titleField = document.getElementById('post-title');
            const subtitleField = document.getElementById('post-subtitle');
            const contentField = document.getElementById('post-content');
            const goalCard = document.getElementById('studio-writing-goal-card');
            const aiPanelBtn = document.getElementById('open-ai-panel-btn');
            const outlineTab = document.getElementById('tab-nav-outline');
            const researchTab = document.getElementById('tab-nav-research');
            const mediaTab = document.getElementById('tab-nav-media');
            const saveIndicator = document.getElementById('studio-save-indicator');

            return {
                studioFound: !!studio,
                topbarFound: !!topbar,
                leftSidebarFound: !!leftSidebar,
                rightSidebarFound: !!rightSidebar,
                titleFieldFound: !!titleField,
                contentFieldFound: !!contentField,
                goalCardFound: !!goalCard,
                aiPanelBtnFound: !!aiPanelBtn,
                saveIndicatorText: saveIndicator?.textContent.trim()
            };
        })()
    `);
    console.log('Writing Studio layout elements check:', studioElementsCheck);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_initial_desktop.png'));

    // Step E: Type an article with Headings & Test Outline Generation
    console.log('\n--- Step E: Typing Content & Testing Outline Generation ---');
    await client.evaluate(`
        (() => {
            const title = document.getElementById('post-title');
            const subtitle = document.getElementById('post-subtitle');
            const content = document.getElementById('post-content');
            const cat = document.getElementById('post-category');

            if (title) title.value = 'The Art of Mindful Technology';
            if (subtitle) subtitle.value = 'A calmer, more intentional relationship with the digital world.';
            if (cat && cat.options.length > 1) cat.selectedIndex = 1;

            if (content) {
                content.value = '# Introduction\\n\\nTechnology is a remarkable tool. It connects us, helps us learn, and gives us opportunities that previous generations could only imagine.\\n\\n## The current challenges\\n\\nYet it can also easily pull our attention in too many directions, leaving us feeling busy but not fulfilled.\\n\\n## Finding a better balance\\n\\nBy setting intentional boundaries and practicing digital wellness, we regain control over our focus and peace of mind.\\n\\n## Practical strategies\\n\\n1. Intentional notification audits\\n2. Dedicated screen-free mornings\\n3. Contextual work sessions\\n\\n## Final thoughts\\n\\nA more intentional use of technology gives us the freedom to focus on what truly matters.';
                content.dispatchEvent(new Event('input', { bubbles: true }));
            }
        })()
    `);
    await sleep(1500);

    const outlineItems = await client.evaluate(`
        (() => {
            const items = Array.from(document.querySelectorAll('.studio-outline-item')).map(el => el.textContent.trim());
            const words = document.getElementById('studio-stat-words')?.textContent;
            const goalPct = document.getElementById('goal-percent-text')?.textContent;
            const seoScore = document.getElementById('seo-score-fraction')?.textContent;
            const saveText = document.getElementById('studio-save-text')?.textContent;
            return { items, words, goalPct, seoScore, saveText };
        })()
    `);
    console.log('Outline & Metrics updated:', outlineItems);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_content_outline_desktop.png'));

    // Step F: Test AI Writing Assistant Panel
    console.log('\n--- Step F: Opening AI Writing Assistant & Running Action ---');
    await client.evaluate(`
        (() => {
            document.getElementById('open-ai-panel-btn')?.click();
        })()
    `);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_ai_panel_open.png'));

    // Trigger "Improve Writing" AI action
    const aiActionResult = await client.evaluate(`
        (async () => {
            const improveBtn = document.querySelector('.studio-ai-action-card[data-action="improve_writing"]');
            if (improveBtn) {
                improveBtn.click();
                await new Promise(r => setTimeout(r, 2000));
                const resCard = document.getElementById('studio-ai-result-card');
                const resText = document.getElementById('ai-result-content')?.textContent;
                return {
                    cardVisible: resCard && resCard.style.display !== 'none',
                    resultSnippet: resText ? resText.substring(0, 100) : ''
                };
            }
            return { error: 'improveBtn not found' };
        })()
    `);
    console.log('AI Action Result:', aiActionResult);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_ai_suggestion_diff.png'));

    // Step G: Test Appearance Presets & Dark Mode
    console.log('\n--- Step G: Testing Appearance Presets (Midnight / Lavender) ---');
    await client.evaluate(`
        (() => {
            document.getElementById('open-settings-drawer-btn')?.click();
        })()
    `);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_settings_drawer.png'));

    // Switch to Midnight theme
    await client.evaluate(`
        (() => {
            const midnightSwatch = document.querySelector('.studio-theme-swatch[data-theme="midnight"]');
            if (midnightSwatch) midnightSwatch.click();
        })()
    `);
    await sleep(1000);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_theme_midnight_dark.png'));

    // Step H: Test Focus Mode
    console.log('\n--- Step H: Testing Focus Mode ---');
    await client.evaluate(`
        (() => {
            const focusSwitch = document.getElementById('studio-focus-mode-toggle');
            if (focusSwitch) {
                focusSwitch.checked = true;
                focusSwitch.dispatchEvent(new Event('change', { bubbles: true }));
            }
        })()
    `);
    await sleep(1000);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_focus_mode_zen.png'));

    // Exit Focus Mode & Switch back to Lavender
    await client.evaluate(`
        (() => {
            const focusSwitch = document.getElementById('studio-focus-mode-toggle');
            if (focusSwitch) {
                focusSwitch.checked = false;
                focusSwitch.dispatchEvent(new Event('change', { bubbles: true }));
            }
            const lavenderSwatch = document.querySelector('.studio-theme-swatch[data-theme="lavender"]');
            if (lavenderSwatch) lavenderSwatch.click();
            document.getElementById('studio-settings-drawer')?.classList.remove('is-open');
        })()
    `);
    await sleep(800);

    // Step I: Mobile Responsive Test (375px & 430px Claymorphism)
    console.log('\n--- Step I: Mobile Viewport Verification (375px Claymorphism) ---');
    await client.setViewport(375, 750);
    await sleep(1200);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_mobile_375_clay.png'));

    // Open mobile left drawer
    await client.evaluate(`
        (() => {
            document.getElementById('toggle-mobile-left-btn')?.click();
        })()
    `);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_mobile_375_left_drawer.png'));

    // Close left, open right mobile drawer
    await client.evaluate(`
        (() => {
            document.getElementById('studio-left-sidebar')?.classList.remove('is-mobile-open');
            document.getElementById('toggle-mobile-right-btn')?.click();
        })()
    `);
    await sleep(800);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_mobile_375_right_drawer.png'));

    // Step J: Test Live Reader Preview Mode
    console.log('\n--- Step J: Testing Reader Preview Mode ---');
    await client.setViewport(1280, 800);
    await client.evaluate(`
        (() => {
            document.getElementById('studio-right-sidebar')?.classList.remove('is-mobile-open');
            document.getElementById('studio-preview-toggle-btn')?.click();
        })()
    `);
    await sleep(1500);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_reader_preview.png'));

    console.log('\n=== ALL TESTS COMPLETED SUCCESSFULLY ===');
    client.close();
    edgeProc.kill();
    process.exit(0);
}

runTests().catch(err => {
    console.error('Test execution failed:', err);
    process.exit(1);
});
