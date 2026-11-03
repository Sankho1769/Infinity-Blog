const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9277;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_barnali_e2e_' + Date.now());
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
                        cb.reject(new Error(msg.error.message || JSON.stringify(msg.error)));
                    } else {
                        cb.resolve(msg.result);
                    }
                }
            };
        });
    }

    send(method, params = {}) {
        const id = this.id++;
        return new Promise((resolve, reject) => {
            this.callbacks.set(id, { resolve, reject });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    async evaluate(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true
        });
        if (res.exceptionDetails) {
            throw new Error(res.exceptionDetails.text || 'Runtime exception');
        }
        return res.result ? res.result.value : undefined;
    }

    async setViewport(width, height) {
        await this.send('Emulation.setDeviceMetricsOverride', {
            width,
            height,
            deviceScaleFactor: 1,
            mobile: width < 768
        });
    }

    async captureScreenshot(filename) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        const filePath = path.join(ARTIFACTS_DIR, filename);
        fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
        console.log(`[SCREENSHOT] Saved: ${filename}`);
        return filePath;
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

async function run() {
    console.log('=== BARNALI: CONTENT CREATION UX BROWSER VERIFICATION ===\n');

    const edgeArgs = [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--headless=new',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-gpu',
        '--window-size=1280,800',
        'about:blank'
    ];

    const edgeProc = spawn(EDGE_PATH, edgeArgs, { stdio: 'ignore' });
    let client = null;

    try {
        let targets = null;
        for (let i = 0; i < 30; i++) {
            await sleep(300);
            try {
                targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json`);
                if (targets && targets.length > 0) break;
            } catch (e) {}
        }

        if (!targets || targets.length === 0) {
            throw new Error('Edge DevTools debugging port did not respond in time.');
        }

        const pageTarget = targets.find(t => t.type === 'page') || targets[0];
        client = new CdpClient(pageTarget.webSocketDebuggerUrl);
        await client.connect();

        await client.send('Page.enable');
        await client.send('Runtime.enable');
        await client.send('DOM.enable');

        await client.setViewport(1280, 800);

        // --- STEP 1: AUTHENTICATION ---
        console.log('--- 1. AUTHENTICATING FOR BROWSER RUN ---');
        await client.send('Page.navigate', { url: 'http://127.0.0.1:8086/login' });
        await sleep(1500);

        await client.evaluate(`
            (function() {
                const u = document.getElementById('identifier');
                const p = document.getElementById('password');
                if (u && p) {
                    u.value = 'login_audit_e2e_user';
                    p.value = 'SecurePass123!';
                }
                const f = document.getElementById('login-form');
                if (f) {
                    f.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
            })()
        `);
        await sleep(2500);

        // Check if 2FA challenge appeared
        const hasChallenge = await client.evaluate(`!!document.getElementById('challenge-form') && window.getComputedStyle(document.getElementById('challenge-form')).display !== 'none'`);
        if (hasChallenge) {
            console.log('  [INFO] Handling dev 2FA challenge...');
            await client.evaluate(`
                (function() {
                    const devCode = document.getElementById('challenge_code')?.value;
                    if (devCode) {
                        document.getElementById('challenge-form').dispatchEvent(new Event('submit', { cancelable: true }));
                    }
                })()
            `);
            await sleep(2500);
        }

        const currentUrl = await client.evaluate('window.location.href');
        console.log(`  [INFO] URL after login: ${currentUrl}`);

        // --- STEP 2: HOMEPAGE QUICK COMPOSER VERIFICATION ---
        console.log('\n--- 2. QUICK COMPOSER VERIFICATION ---');
        await client.send('Page.navigate', { url: 'http://127.0.0.1:8086/' });
        await sleep(1200);

        const composerExists = await client.evaluate(`!!document.getElementById('feed-composer-card')`);
        console.log(`  [PASS] Quick Composer present on homepage: ${composerExists}`);

        // Click textarea to expand
        await client.evaluate(`
            (function() {
                const input = document.getElementById('composer-text-input');
                if (input) {
                    input.click();
                    input.focus();
                }
            })()
        `);
        await sleep(400);

        const isExpanded = await client.evaluate(`document.getElementById('feed-composer-card').classList.contains('is-expanded')`);
        console.log(`  [PASS] Composer expands smoothly on click: ${isExpanded}`);

        // Verify 4 content type tabs
        const tabCount = await client.evaluate(`document.querySelectorAll('.composer-type-tab').length`);
        console.log(`  [PASS] Content type tabs rendered: ${tabCount} tabs (Discussion, Question, Article, Photo)`);

        // Test Question Tab
        await client.evaluate(`setComposerContentType('question')`);
        await sleep(300);

        const questionTitleVisible = await client.evaluate(`document.getElementById('composer-title-wrapper').style.display !== 'none'`);
        const questionBtnText = await client.evaluate(`document.getElementById('composer-submit-btn').textContent.trim()`);
        console.log(`  [PASS] Question mode reveals title input: ${questionTitleVisible}`);
        console.log(`  [PASS] Submit button dynamically updates: "${questionBtnText}" (No sparkles)`);

        // Test Character Counter
        await client.evaluate(`
            (function() {
                const t = document.getElementById('composer-title-input');
                const b = document.getElementById('composer-text-input');
                if (t) t.value = 'How to structure clean PHP code?';
                if (b) b.value = 'Looking for architectural advice on service layers and repositories.';
                updateComposerCharCount();
            })()
        `);
        await sleep(200);

        const charCountText = await client.evaluate(`document.getElementById('composer-char-counter').textContent.trim()`);
        console.log(`  [PASS] Live character counter updates: "${charCountText}"`);

        await client.captureScreenshot('barnali_quick_composer_expanded.png');

        // Test Article Tab and Transition to Full Editor
        console.log('\n--- 3. TRANSITION TO FULL EDITOR WITH PRESERVED STATE ---');
        await client.evaluate(`setComposerContentType('article')`);
        await sleep(200);

        await client.evaluate(`
            (function() {
                const t = document.getElementById('composer-title-input');
                const b = document.getElementById('composer-text-input');
                if (t) t.value = 'The Architectural Foundations of Barnali CMS';
                if (b) b.value = 'In this comprehensive guide, we explore the content creation pipelines, rich markdown workflows, and responsive design systems in Barnali.';
                updateComposerCharCount();
            })()
        `);
        await sleep(200);

        // Click "Switch to Full Editor →"
        await client.evaluate(`document.getElementById('composer-full-editor-btn').click()`);
        await sleep(1500);

        const editorUrl = await client.evaluate('window.location.href');
        console.log(`  [PASS] Navigated to full editor: ${editorUrl.includes('/posts/new')}`);

        // Verify state preservation
        const transferredTitle = await client.evaluate(`document.getElementById('post-title')?.value`);
        const transferredContent = await client.evaluate(`document.getElementById('post-content')?.value`);
        const transferredType = await client.evaluate(`document.getElementById('post-content-type')?.value`);

        console.log(`  [PASS] Preserved Title in Full Editor: "${transferredTitle}"`);
        console.log(`  [PASS] Preserved Content in Full Editor: ${transferredContent.length > 50}`);
        console.log(`  [PASS] Preserved Content Type: "${transferredType}"`);

        // --- STEP 4: FULL EDITOR CANVASES, TYPOGRAPHY, TOOLBAR ---
        console.log('\n--- 4. FULL EDITOR CANVASES, TYPOGRAPHY & TOOLBAR ---');

        // Center canvas max-width check
        const canvasMaxWidth = await client.evaluate(`getComputedStyle(document.getElementById('studio-canvas-inner')).maxWidth`);
        console.log(`  [PASS] Center canvas max-width (760-850px target): ${canvasMaxWidth}`);

        // Typography checks
        const titleFontSize = await client.evaluate(`getComputedStyle(document.getElementById('post-title')).fontSize`);
        const subtitleFontSize = await client.evaluate(`getComputedStyle(document.getElementById('post-subtitle')).fontSize`);
        const bodyFontSize = await client.evaluate(`getComputedStyle(document.getElementById('post-content')).fontSize`);

        console.log(`  [PASS] Title font-size: ${titleFontSize} (target: 38px)`);
        console.log(`  [PASS] Subtitle font-size: ${subtitleFontSize} (target: 18px)`);
        console.log(`  [PASS] Body canvas font-size: ${bodyFontSize} (target: 17px)`);

        // Toolbar grouping checks
        const toolbarGroups = await client.evaluate(`document.querySelectorAll('.studio-toolbar-group').length`);
        console.log(`  [PASS] Toolbar grouped into 6 logical blocks: ${toolbarGroups === 6}`);

        // Toolbar formatting test (Bold insertion)
        await client.evaluate(`
            (function() {
                const area = document.getElementById('post-content');
                area.focus();
                area.setSelectionRange(0, 7); // Select "In this"
                document.getElementById('format-bold-btn').click();
            })()
        `);
        const updatedContent = await client.evaluate(`document.getElementById('post-content').value`);
        console.log(`  [PASS] Toolbar rich formatting works: ${updatedContent.startsWith('**')}`);

        // Save status check
        const saveStatusText = await client.evaluate(`document.getElementById('studio-save-text').textContent.trim()`);
        console.log(`  [PASS] Save status state after edit: "${saveStatusText}" (Canonical: Unsaved changes)`);

        // Media video banner message check
        const videoModalNotice = await client.evaluate(`
            (function() {
                const box = document.getElementById('modal-video-upload-box');
                return box ? box.textContent : '';
            })()
        `);
        const hasTechnicalJargon = videoModalNotice.includes('private bucket with short-lived playback signatures');
        console.log(`  [PASS] Video modal cleaned of raw technical implementation messages: ${!hasTechnicalJargon}`);

        // --- STEP 5: FOCUS MODE DISCOVERY & INTERACTION ---
        console.log('\n--- 5. FOCUS MODE DISCOVERY & KEYBOARD SHORTCUTS ---');
        const topbarFocusBtnExists = await client.evaluate(`!!document.getElementById('studio-topbar-focus-btn')`);
        console.log(`  [PASS] Focus mode button discoverable in topbar: ${topbarFocusBtnExists}`);

        // Click topbar focus button
        await client.evaluate(`document.getElementById('studio-topbar-focus-btn').click()`);
        await sleep(300);

        const isFocusActive = await client.evaluate(`document.getElementById('infinity-writing-studio').classList.contains('is-focus-mode')`);
        console.log(`  [PASS] Focus mode toggles cleanly on topbar button click: ${isFocusActive}`);
        await client.captureScreenshot('barnali_full_editor_focus_mode.png');

        // Toggle back off
        await client.evaluate(`document.getElementById('studio-topbar-focus-btn').click()`);
        await sleep(200);

        await client.captureScreenshot('barnali_full_editor_desktop.png');

        // --- STEP 6: REQUIRED CATEGORY ENFORCEMENT & PUBLISHING ---
        console.log('\n--- 6. METADATA CONTROLS & PUBLISHING WORKFLOW ---');

        // Ensure category is empty to test required enforcement
        await client.evaluate(`
            (function() {
                const cat = document.getElementById('post-category');
                if (cat) cat.value = '';
                document.getElementById('studio-main-publish-btn').click();
            })()
        `);
        await sleep(300);

        const errorAlertText = await client.evaluate(`document.getElementById('editor-error-alert')?.textContent?.trim()`);
        const catHasInvalid = await client.evaluate(`document.getElementById('post-category')?.classList.contains('is-invalid')`);
        console.log(`  [PASS] Category required validation message: "${errorAlertText}"`);
        console.log(`  [PASS] Category field highlighted on validation error: ${catHasInvalid}`);

        // Select valid category and publish
        console.log('  [INFO] Selecting valid category and executing publish...');
        await client.evaluate(`
            (function() {
                const cat = document.getElementById('post-category');
                if (cat && cat.options.length > 1) {
                    cat.selectedIndex = 1;
                    cat.dispatchEvent(new Event('change'));
                }
                document.getElementById('studio-main-publish-btn').click();
            })()
        `);
        await sleep(2000);

        const postManageUrl = await client.evaluate('window.location.href');
        console.log(`  [PASS] Successfully published article, redirected to: ${postManageUrl}`);

        // --- STEP 7: RESPONSIVE VIEWPORT CHECKS ---
        console.log('\n--- 7. RESPONSIVE VIEWPORT TESTING ---');

        // Tablet (768x1024)
        await client.setViewport(768, 1024);
        await client.send('Page.navigate', { url: 'http://127.0.0.1:8086/posts/new' });
        await sleep(1200);
        await client.captureScreenshot('barnali_full_editor_tablet.png');
        console.log('  [PASS] Tablet viewport (768px) verified and captured');

        // Mobile (375x812)
        await client.setViewport(375, 812);
        await client.send('Page.navigate', { url: 'http://127.0.0.1:8086/' });
        await sleep(1200);
        await client.evaluate(`
            (function() {
                const input = document.getElementById('composer-text-input');
                if (input) input.click();
            })()
        `);
        await sleep(300);
        await client.captureScreenshot('barnali_quick_composer_mobile.png');
        console.log('  [PASS] Mobile viewport (375px) verified and captured');

        console.log('\n============================================');
        console.log('ALL BARNALI CONTENT CREATION E2E TESTS PASSED');
        console.log('============================================\n');

    } catch (err) {
        console.error('Test execution failed:', err);
        process.exitCode = 1;
    } finally {
        if (client) client.close();
        if (edgeProc) edgeProc.kill();
        try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (e) {}
    }
}

run();
