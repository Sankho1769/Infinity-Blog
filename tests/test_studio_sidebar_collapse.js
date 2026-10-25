const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9247;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_studio_collapse_test_profile');
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

    async screenshot(filePath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
        console.log(`Saved screenshot: ${filePath}`);
    }
}

async function main() {
    console.log('=== STARTING BROWSER VERIFICATION: WRITING STUDIO SIDEBAR COLLAPSE ===\n');

    let edgeProcess = null;
    let wsUrl = null;

    try {
        const versionData = await fetchJson(`http://localhost:${DEBUG_PORT}/json/version`);
        wsUrl = versionData.webSocketDebuggerUrl;
        console.log('Connected to existing Edge instance.');
    } catch (e) {
        console.log('Launching dedicated Edge browser on port ' + DEBUG_PORT + '...');
        edgeProcess = spawn(EDGE_PATH, [
            `--remote-debugging-port=${DEBUG_PORT}`,
            `--user-data-dir=${USER_DATA_DIR}`,
            '--no-first-run',
            '--no-default-browser-check',
            '--headless=new',
            'http://localhost:8086/login'
        ]);
        for (let i = 0; i < 20; i++) {
            await sleep(500);
            try {
                const versionData = await fetchJson(`http://localhost:${DEBUG_PORT}/json/version`);
                wsUrl = versionData.webSocketDebuggerUrl;
                break;
            } catch (err) {}
        }
    }

    const targets = await fetchJson(`http://localhost:${DEBUG_PORT}/json/list`);
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    if (!pageTarget) {
        throw new Error('No page target found');
    }

    const client = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await client.connect();
    console.log('Connected to Microsoft Edge page target via CDP.');

    await client.send('Page.enable');
    await client.send('DOM.enable');

    let passes = 0;
    let fails = 0;
    function assert(condition, message) {
        if (condition) {
            console.log(`  [PASS] ${message}`);
            passes++;
        } else {
            console.error(`  [FAIL] ${message}`);
            fails++;
        }
    }

    // Step 1: Sign in
    console.log('\n--- Step 1: Sign in as authenticated user ---');
    await client.send('Page.navigate', { url: 'http://localhost:8086/login' });
    await sleep(2000);

    const { email, password } = loadCredentials();
    const loginRes = await client.evaluate(`
        (async () => {
            const emailInput = document.querySelector('input[name="identifier"]') || document.querySelector('input[type="email"]') || document.getElementById('login-email');
            const passInput = document.querySelector('input[name="password"]') || document.getElementById('login-password');
            const form = document.querySelector('form[action*="login"]') || document.querySelector('form');
            if (emailInput && passInput && form) {
                emailInput.value = '${email}';
                passInput.value = '${password}';
                form.submit();
                return { submitted: true };
            }
            return { submitted: false };
        })()
    `);
    console.log('Login form submitted:', loginRes);
    await sleep(2500);

    // Dismiss cookie banner
    await client.evaluate(`
        (() => {
            const btn = document.querySelector('.cookie-banner .btn-primary, [data-action="accept-cookies"], #cookie-accept-all');
            if (btn) btn.click();
        })()
    `);

    // Step 2: Open Writing Studio on Desktop (1536x864)
    console.log('\n--- Step 2: Navigating to Writing Studio (/posts/new) Desktop ---');
    await client.setViewport(1536, 864);
    await client.send('Page.navigate', { url: 'http://localhost:8086/posts/new' });
    await sleep(2500);

    // Verify Toolbar layout
    const toolbarAudit = await client.evaluate(`
        (() => {
            const farLeftBtn = document.querySelector('.studio-topbar-left #toggle-mobile-left-btn');
            const brandLink = document.querySelector('.studio-topbar-left .studio-brand-link');
            const leftToggle = document.getElementById('toggle-left-sidebar-btn');
            const rightToggle = document.getElementById('toggle-right-sidebar-btn');
            const group = document.querySelector('.studio-sidebar-toggles-group');
            const leftSidebar = document.getElementById('studio-left-sidebar');
            const rightSidebar = document.getElementById('studio-right-sidebar');
            const canvas = document.getElementById('studio-center-canvas');

            return {
                farLeftBtnPresent: !!farLeftBtn,
                brandLinkPresent: !!brandLink,
                leftTogglePresent: !!leftToggle,
                rightTogglePresent: !!rightToggle,
                groupPresent: !!group,
                leftToggleLabel: leftToggle?.getAttribute('aria-label'),
                rightToggleLabel: rightToggle?.getAttribute('aria-label'),
                leftToggleExpanded: leftToggle?.getAttribute('aria-expanded'),
                rightToggleExpanded: rightToggle?.getAttribute('aria-expanded'),
                leftWidth: leftSidebar?.offsetWidth,
                rightWidth: rightSidebar?.offsetWidth,
                canvasWidth: canvas?.offsetWidth
            };
        })()
    `);
    console.log('Toolbar audit:', toolbarAudit);

    assert(!toolbarAudit.farLeftBtnPresent, 'Far-left standalone toggle button is removed from topbar-left');
    assert(toolbarAudit.brandLinkPresent, 'Infinity Blog logo and wordmark aligned neatly on the left');
    assert(toolbarAudit.leftTogglePresent, 'Left sidebar toggle is present on the right toolbar');
    assert(toolbarAudit.rightTogglePresent, 'Right sidebar toggle is present on the right toolbar');
    assert(toolbarAudit.groupPresent, 'Both sidebar toggles grouped beside each other on right toolbar');
    assert(toolbarAudit.leftToggleLabel === 'Toggle left sidebar', 'Left sidebar toggle has accessible label "Toggle left sidebar"');
    assert(toolbarAudit.rightToggleLabel === 'Toggle right sidebar', 'Right sidebar toggle has accessible label "Toggle right sidebar"');
    assert(toolbarAudit.leftToggleExpanded === 'true', 'Left toggle initial aria-expanded is true');
    assert(toolbarAudit.rightToggleExpanded === 'true', 'Right toggle initial aria-expanded is true');
    assert(toolbarAudit.leftWidth >= 230 && toolbarAudit.leftWidth <= 250, 'Left sidebar initial width is ~240px');
    assert(toolbarAudit.rightWidth >= 300 && toolbarAudit.rightWidth <= 320, 'Right sidebar initial width is ~310px');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_sidebars_both_open_desktop.png'));

    // Step 3: Test Left Sidebar Collapse & Expansion
    console.log('\n--- Step 3: Testing Left Sidebar Independent Collapse ---');
    const collapseLeftRes = await client.evaluate(`
        (() => {
            const btn = document.getElementById('toggle-left-sidebar-btn');
            btn.click();
            return { clicked: true };
        })()
    `);
    await sleep(400);

    const leftCollapsedAudit = await client.evaluate(`
        (() => {
            const studio = document.getElementById('infinity-writing-studio');
            const leftSidebar = document.getElementById('studio-left-sidebar');
            const rightSidebar = document.getElementById('studio-right-sidebar');
            const canvas = document.getElementById('studio-center-canvas');
            const bottomBar = document.getElementById('studio-bottom-bar');
            const btn = document.getElementById('toggle-left-sidebar-btn');
            const style = window.getComputedStyle(leftSidebar);

            return {
                isLeftCollapsedClass: studio.classList.contains('is-left-collapsed'),
                isRightCollapsedClass: studio.classList.contains('is-right-collapsed'),
                leftMarginLeft: style.marginLeft,
                leftVisibility: style.visibility,
                rightWidth: rightSidebar.offsetWidth,
                canvasWidth: canvas.offsetWidth,
                bottomBarLeft: window.getComputedStyle(bottomBar).left,
                btnExpanded: btn.getAttribute('aria-expanded'),
                btnActive: btn.classList.contains('active')
            };
        })()
    `);
    console.log('Left collapsed audit:', leftCollapsedAudit);

    assert(leftCollapsedAudit.isLeftCollapsedClass, 'Container has class "is-left-collapsed"');
    assert(!leftCollapsedAudit.isRightCollapsedClass, 'Right sidebar is NOT collapsed when only left is clicked');
    assert(parseInt(leftCollapsedAudit.leftMarginLeft, 10) <= -230, 'Left sidebar smoothly retreated with negative margin');
    assert(leftCollapsedAudit.leftVisibility === 'hidden', 'Left sidebar visibility is hidden when collapsed');
    assert(leftCollapsedAudit.canvasWidth > toolbarAudit.canvasWidth + 200, 'Center editor canvas expanded into newly available space');
    assert(leftCollapsedAudit.btnExpanded === 'false', 'Left toggle aria-expanded updated to "false"');
    assert(!leftCollapsedAudit.btnActive, 'Left toggle active class removed when collapsed');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_left_collapsed_desktop.png'));

    // Reopen left sidebar
    await client.evaluate(`document.getElementById('toggle-left-sidebar-btn').click()`);
    await sleep(400);
    const leftReopenedWidth = await client.evaluate(`document.getElementById('studio-left-sidebar').offsetWidth`);
    assert(leftReopenedWidth >= 230 && leftReopenedWidth <= 250, 'Left sidebar restored smoothly to previous width (240px)');

    // Step 4: Test Right Sidebar Collapse & Expansion
    console.log('\n--- Step 4: Testing Right Sidebar Independent Collapse ---');
    await client.evaluate(`document.getElementById('toggle-right-sidebar-btn').click()`);
    await sleep(400);

    const rightCollapsedAudit = await client.evaluate(`
        (() => {
            const studio = document.getElementById('infinity-writing-studio');
            const leftSidebar = document.getElementById('studio-left-sidebar');
            const rightSidebar = document.getElementById('studio-right-sidebar');
            const canvas = document.getElementById('studio-center-canvas');
            const bottomBar = document.getElementById('studio-bottom-bar');
            const btn = document.getElementById('toggle-right-sidebar-btn');
            const style = window.getComputedStyle(rightSidebar);

            return {
                isRightCollapsedClass: studio.classList.contains('is-right-collapsed'),
                isLeftCollapsedClass: studio.classList.contains('is-left-collapsed'),
                rightMarginRight: style.marginRight,
                rightVisibility: style.visibility,
                leftWidth: leftSidebar.offsetWidth,
                canvasWidth: canvas.offsetWidth,
                bottomBarRight: window.getComputedStyle(bottomBar).right,
                btnExpanded: btn.getAttribute('aria-expanded'),
                btnActive: btn.classList.contains('active')
            };
        })()
    `);
    console.log('Right collapsed audit:', rightCollapsedAudit);

    assert(rightCollapsedAudit.isRightCollapsedClass, 'Container has class "is-right-collapsed"');
    assert(!rightCollapsedAudit.isLeftCollapsedClass, 'Left sidebar remains visible and uncollapsed');
    assert(parseInt(rightCollapsedAudit.rightMarginRight, 10) <= -300, 'Right sidebar retreated with negative margin');
    assert(rightCollapsedAudit.rightVisibility === 'hidden', 'Right sidebar visibility is hidden when collapsed');
    assert(rightCollapsedAudit.canvasWidth > toolbarAudit.canvasWidth + 280, 'Center editor canvas expanded into newly available space');
    assert(rightCollapsedAudit.btnExpanded === 'false', 'Right toggle aria-expanded updated to "false"');
    assert(!rightCollapsedAudit.btnActive, 'Right toggle active class removed when collapsed');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_right_collapsed_desktop.png'));

    // Reopen right sidebar
    await client.evaluate(`document.getElementById('toggle-right-sidebar-btn').click()`);
    await sleep(400);

    // Step 5: Test Simultaneous Collapse (Both sidebars hidden)
    console.log('\n--- Step 5: Testing Simultaneous Collapse of Both Sidebars ---');
    await client.evaluate(`
        (() => {
            document.getElementById('toggle-left-sidebar-btn').click();
            document.getElementById('toggle-right-sidebar-btn').click();
        })()
    `);
    await sleep(400);

    const bothCollapsedAudit = await client.evaluate(`
        (() => {
            const studio = document.getElementById('infinity-writing-studio');
            const canvas = document.getElementById('studio-center-canvas');
            const inner = document.getElementById('studio-canvas-inner');
            const bottomBar = document.getElementById('studio-bottom-bar');
            const leftBtn = document.getElementById('toggle-left-sidebar-btn');
            const rightBtn = document.getElementById('toggle-right-sidebar-btn');

            const canvasRect = canvas.getBoundingClientRect();
            const innerRect = inner.getBoundingClientRect();
            const leftGap = innerRect.left - (canvasRect.left + canvas.clientLeft);
            const rightEdge = canvasRect.left + canvas.clientLeft + canvas.clientWidth;
            const rightGap = rightEdge - innerRect.right;
            const isCentered = Math.abs(leftGap - rightGap) <= 2;

            return {
                isLeftCollapsed: studio.classList.contains('is-left-collapsed'),
                isRightCollapsed: studio.classList.contains('is-right-collapsed'),
                canvasWidth: canvas.offsetWidth,
                innerMaxWidth: window.getComputedStyle(inner).maxWidth,
                isCentered: Math.abs(leftGap - rightGap) < 5,
                bottomBarLeft: window.getComputedStyle(bottomBar).left,
                bottomBarRight: window.getComputedStyle(bottomBar).right,
                leftBtnExpanded: leftBtn.getAttribute('aria-expanded'),
                rightBtnExpanded: rightBtn.getAttribute('aria-expanded')
            };
        })()
    `);
    console.log('Both collapsed audit:', bothCollapsedAudit);

    assert(bothCollapsedAudit.isLeftCollapsed && bothCollapsedAudit.isRightCollapsed, 'Both sidebars collapsed simultaneously');
    assert(bothCollapsedAudit.canvasWidth > 1400, 'Canvas expands across full width (>1400px)');
    assert(bothCollapsedAudit.isCentered, 'Editor canvas inner content is horizontally centered');
    assert(bothCollapsedAudit.bottomBarLeft === '0px' && bothCollapsedAudit.bottomBarRight === '0px', 'Bottom status bar spans full viewport width (0px to 0px)');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_both_collapsed_desktop.png'));

    // Step 6: Test Reopening Left while Right stays Collapsed
    console.log('\n--- Step 6: Testing Independent Reopen of Left Sidebar ---');
    await client.evaluate(`document.getElementById('toggle-left-sidebar-btn').click()`);
    await sleep(400);

    const leftOnlyOpenAudit = await client.evaluate(`
        (() => {
            const studio = document.getElementById('infinity-writing-studio');
            const left = document.getElementById('studio-left-sidebar');
            const right = document.getElementById('studio-right-sidebar');
            return {
                isLeftCollapsed: studio.classList.contains('is-left-collapsed'),
                isRightCollapsed: studio.classList.contains('is-right-collapsed'),
                leftWidth: left.offsetWidth,
                rightVisibility: window.getComputedStyle(right).visibility
            };
        })()
    `);
    console.log('Left only open audit:', leftOnlyOpenAudit);

    assert(!leftOnlyOpenAudit.isLeftCollapsed, 'Left sidebar reopened successfully');
    assert(leftOnlyOpenAudit.isRightCollapsed, 'Right sidebar remains collapsed');
    assert(leftOnlyOpenAudit.leftWidth >= 230, 'Left sidebar has full width');
    assert(leftOnlyOpenAudit.rightVisibility === 'hidden', 'Right sidebar remains hidden');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_left_open_right_collapsed_desktop.png'));

    // Reopen right sidebar as well
    await client.evaluate(`document.getElementById('toggle-right-sidebar-btn').click()`);
    await sleep(400);

    // Step 7: Content, Cursor, Selection & AI State Preservation
    console.log('\n--- Step 7: Verifying Editor State, Selection & AI Assistant Preservation ---');
    const stateTestInput = await client.evaluate(`
        (() => {
            const title = document.getElementById('post-title');
            const subtitle = document.getElementById('post-subtitle');
            const content = document.getElementById('post-content');

            title.value = 'Mindful Architecture & Living Systems';
            subtitle.value = 'How intentional spaces elevate human creativity and focus.';
            content.value = '## 1. The Geometry of Solitude\\nDeep focus begins in quiet environments.\\n\\n## 2. Dynamic Expanding Canvas\\nWhen sidebars collapse, the mind expands.\\n\\n## 3. Persistent Continuity\\nState must remain intact across layout shifts.';

            content.dispatchEvent(new Event('input', { bubbles: true }));

            // Set selection on specific words
            content.focus();
            content.setSelectionRange(15, 35);

            return {
                selStart: content.selectionStart,
                selEnd: content.selectionEnd,
                outlineCount: document.querySelectorAll('.studio-outline-item').length
            };
        })()
    `);
    console.log('Input state before collapse:', stateTestInput);
    assert(stateTestInput.outlineCount === 3, 'Outline generated 3 heading sections dynamically');

    // Open AI writing assistant panel
    await client.evaluate(`document.getElementById('open-ai-panel-btn').click()`);
    await sleep(300);

    // Collapse right sidebar and check AI panel repositioning
    await client.evaluate(`document.getElementById('toggle-right-sidebar-btn').click()`);
    await sleep(400);

    const postCollapseState = await client.evaluate(`
        (() => {
            const title = document.getElementById('post-title');
            const subtitle = document.getElementById('post-subtitle');
            const content = document.getElementById('post-content');
            const aiPanel = document.getElementById('studio-ai-panel');
            const aiStyle = window.getComputedStyle(aiPanel);

            return {
                titleVal: title.value,
                subtitleVal: subtitle.value,
                contentVal: content.value,
                selStart: content.selectionStart,
                selEnd: content.selectionEnd,
                aiPanelIsOpen: aiPanel.classList.contains('is-open'),
                aiPanelRight: aiStyle.right
            };
        })()
    `);
    console.log('Post-collapse state check:', postCollapseState);

    assert(postCollapseState.titleVal === 'Mindful Architecture & Living Systems', 'Article title preserved through collapse');
    assert(postCollapseState.subtitleVal.includes('intentional spaces'), 'Article subtitle preserved through collapse');
    assert(postCollapseState.contentVal.includes('Dynamic Expanding Canvas'), 'Article body markdown preserved through collapse');
    assert(postCollapseState.selStart === 15 && postCollapseState.selEnd === 35, 'Caret selection range preserved through collapse');
    assert(postCollapseState.aiPanelIsOpen, 'AI Assistant panel remains open without closing');
    assert(postCollapseState.aiPanelRight === '20px', 'AI Assistant panel smoothly adjusted docked right offset to 20px');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_ai_open_with_collapsed_right.png'));

    // Restore right sidebar
    await client.evaluate(`document.getElementById('toggle-right-sidebar-btn').click()`);
    await sleep(400);

    // Step 8: Test Tablet Responsive Layout (768x1024)
    console.log('\n--- Step 8: Testing Tablet Responsive Viewport (768x1024) ---');
    await client.setViewport(768, 1024);
    await sleep(500);

    const tabletCheck = await client.evaluate(`
        (() => {
            const leftBtn = document.getElementById('toggle-left-sidebar-btn');
            const rightBtn = document.getElementById('toggle-right-sidebar-btn');
            const left = document.getElementById('studio-left-sidebar');
            const right = document.getElementById('studio-right-sidebar');
            const backdrop = document.getElementById('studio-drawer-backdrop');

            // Click left toggle to open mobile drawer
            leftBtn.click();

            return {
                leftDrawerOpen: left.classList.contains('is-mobile-open'),
                backdropVisible: backdrop.classList.contains('is-visible'),
                leftPosition: window.getComputedStyle(left).position
            };
        })()
    `);
    console.log('Tablet left drawer test:', tabletCheck);

    assert(tabletCheck.leftDrawerOpen, 'Tablet clicking left toggle opens left off-canvas drawer');
    assert(tabletCheck.backdropVisible, 'Tablet backdrop overlay becomes visible');
    assert(tabletCheck.leftPosition === 'fixed', 'Left sidebar is fixed overlay in drawer mode');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_tablet_left_drawer.png'));

    // Click backdrop to close
    await client.evaluate(`document.getElementById('studio-drawer-backdrop').click()`);
    await sleep(300);
    const backdropClosed = await client.evaluate(`!document.getElementById('studio-left-sidebar').classList.contains('is-mobile-open')`);
    assert(backdropClosed, 'Clicking drawer backdrop closes the mobile drawer');

    // Test right drawer on tablet
    await client.evaluate(`document.getElementById('toggle-right-sidebar-btn').click()`);
    await sleep(300);
    const rightDrawerOpen = await client.evaluate(`document.getElementById('studio-right-sidebar').classList.contains('is-mobile-open')`);
    assert(rightDrawerOpen, 'Tablet clicking right toggle opens right off-canvas inspector drawer');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_tablet_right_drawer.png'));

    // Press Escape to close
    await client.send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', windowsVirtualKeyCode: 27 });
    await client.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', windowsVirtualKeyCode: 27 });
    await sleep(300);

    // Step 9: Test Mobile Viewport (375x667)
    console.log('\n--- Step 9: Testing Mobile Viewport (375x667) Tactile Claymorphism ---');
    await client.setViewport(375, 667);
    await sleep(500);

    const mobileCheck = await client.evaluate(`
        (() => {
            const topbar = document.querySelector('.studio-topbar');
            const leftBtn = document.getElementById('toggle-left-sidebar-btn');
            const rightBtn = document.getElementById('toggle-right-sidebar-btn');
            const brandImg = document.querySelector('.studio-topbar-left .studio-brand-symbol-img');

            const hasOverflow = topbar.scrollWidth > topbar.clientWidth;
            return {
                hasOverflow,
                leftBtnVisible: leftBtn.offsetWidth > 0,
                rightBtnVisible: rightBtn.offsetWidth > 0,
                brandImgVisible: brandImg.offsetWidth > 0
            };
        })()
    `);
    console.log('Mobile check:', mobileCheck);

    assert(!mobileCheck.hasOverflow, 'Mobile 375px header has no horizontal overflow');
    assert(mobileCheck.leftBtnVisible && mobileCheck.rightBtnVisible, 'Both left and right sidebar toggles accessible on 375px');
    assert(mobileCheck.brandImgVisible, 'Brand symbol neatly visible on mobile');

    // Open mobile left drawer
    await client.evaluate(`document.getElementById('toggle-left-sidebar-btn').click()`);
    await sleep(300);
    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_mobile_clay_drawer.png'));
    await client.evaluate(`document.getElementById('studio-drawer-backdrop').click()`);
    await sleep(300);

    // Step 10: Dark / Light Mode Theme Toggle Verification
    console.log('\n--- Step 10: Verifying Theme Toggle Mode ---');
    await client.setViewport(1536, 864);
    await sleep(400);

    const initialTheme = await client.evaluate(`document.documentElement.getAttribute('data-theme') || 'light'`);
    console.log('Initial theme before toggle:', initialTheme);

    await client.evaluate(`document.getElementById('studio-theme-toggle-btn').click()`);
    await sleep(400);

    const toggledTheme = await client.evaluate(`document.documentElement.getAttribute('data-theme') || 'light'`);
    console.log('Theme after first toggle:', toggledTheme);
    assert(toggledTheme !== initialTheme, `Theme toggled from ${initialTheme} to ${toggledTheme}`);

    // If not dark, toggle once more to capture dark screenshot
    if (toggledTheme !== 'dark') {
        await client.evaluate(`document.getElementById('studio-theme-toggle-btn').click()`);
        await sleep(400);
    }
    const isNowDark = await client.evaluate(`document.documentElement.getAttribute('data-theme') === 'dark'`);
    assert(isNowDark, 'Studio is active in Dark Theme Mode');

    await client.screenshot(path.join(ARTIFACTS_DIR, 'studio_dark_mode_expanded.png'));

    console.log('\n======================================================');
    console.log(`Writing Studio verification completed: ${passes} passed, ${fails} failed`);
    console.log('======================================================\n');

    if (edgeProcess) {
        edgeProcess.kill();
    }
}

main().catch(err => {
    console.error('Fatal error during test run:', err);
    process.exit(1);
});
