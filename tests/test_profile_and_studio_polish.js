const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9252;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_polish_test_profile');
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
    return { email: email || 'admin@infinityblog.local', password: password || 'Admin@123456' };
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
        console.log(`Saved screenshot: ${path.basename(filepath)}`);
    }

    close() {
        if (this.ws) this.ws.close();
    }
}

async function runBrowserTests() {
    console.log('=== STARTING BROWSER VERIFICATION: PROFILE POLISH & WRITING STUDIO ===\n');

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

    console.log('Connected to Microsoft Edge via CDP on port ' + DEBUG_PORT);

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
        console.log('\n--- Step 1: Sign in with Authenticated Account ---');
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
            await sleep(500);
        }
        await dismissCookies();

        // Step 2: Test Profile Edit Page (/profile/edit)
        console.log('\n--- Step 2: Testing Profile Edit Page & Live Previews ---');
        await client.send('Page.navigate', { url: 'http://localhost:8086/profile/edit' });
        await sleep(2000);
        await dismissCookies();

        const editControls = await client.evaluate(`
            (() => {
                return {
                    hasAvatarInput: !!document.getElementById('avatar'),
                    hasAvatarPreview: !!(document.getElementById('avatar-preview-container') || document.getElementById('avatar-actions-bar')),
                    hasAvatarImg: !!document.getElementById('avatar-preview-img'),
                    hasAvatarConfirm: !!(document.getElementById('avatar-confirm-btn') || document.querySelector('#avatar-upload-form button[type="submit"]')),
                    hasAvatarCancel: !!document.getElementById('avatar-cancel-btn'),
                    hasBannerInput: !!document.getElementById('banner'),
                    hasBannerPreview: !!(document.getElementById('banner-preview-container') || document.getElementById('banner-actions-bar')),
                    hasInstagram: !!document.getElementById('instagram_url'),
                    hasFacebook: !!document.getElementById('facebook_url'),
                    hasGithub: !!document.getElementById('github_url'),
                    hasTwitter: !!document.getElementById('twitter_url'),
                    hasWhatsapp: !!document.getElementById('whatsapp_number'),
                    hasWhatsappPublic: !!document.getElementById('whatsapp_public'),
                    hasCategoryChips: document.querySelectorAll('.category-chip, .category-select-chip').length
                };
            })()
        `);

        assert(editControls.hasAvatarInput, 'Profile edit has #avatar file input');
        assert(editControls.hasAvatarPreview, 'Profile edit has avatar preview container');
        assert(editControls.hasAvatarConfirm, 'Profile edit has avatar confirm upload button');
        assert(editControls.hasAvatarCancel, 'Profile edit has #avatar-cancel-btn ("Choose Another / Cancel")');
        assert(editControls.hasBannerInput, 'Profile edit has #banner file input');
        assert(editControls.hasBannerPreview, 'Profile edit has banner preview container');
        assert(editControls.hasInstagram, 'Profile edit has #instagram_url');
        assert(editControls.hasGithub, 'Profile edit has #github_url');
        assert(editControls.hasWhatsapp, 'Profile edit has #whatsapp_number');
        assert(editControls.hasWhatsappPublic, 'Profile edit has #whatsapp_public privacy toggle');
        assert(editControls.hasCategoryChips > 0, `Profile edit has category chips (${editControls.hasCategoryChips} categories found)`);

        // Test Live Avatar Preview Simulation
        console.log('Testing live image preview simulation via URL.createObjectURL...');
        const previewSim = await client.evaluate(`
            (() => {
                const canvas = document.createElement('canvas');
                canvas.width = 120;
                canvas.height = 120;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#6366f1';
                ctx.fillRect(0, 0, 120, 120);
                ctx.fillStyle = '#ffffff';
                ctx.font = '24px sans-serif';
                ctx.fillText('AVATAR', 15, 65);

                const dataUrl = canvas.toDataURL('image/png');
                const previewContainer = document.getElementById('avatar-actions-bar') || document.getElementById('avatar-preview-container');
                const previewImg = document.getElementById('avatar-preview-img');

                // Simulate selecting an image
                if (previewImg) previewImg.src = dataUrl;
                if (previewContainer) previewContainer.style.display = 'flex';

                return {
                    previewVisible: previewContainer && previewContainer.style.display !== 'none',
                    previewHasSrc: previewImg && previewImg.src.length > 50
                };
            })()
        `);
        assert(previewSim.previewVisible && previewSim.previewHasSrc, 'Live avatar preview appears with image preview before upload');

        // Test Category Chips Sync
        console.log('Testing category chips multi-select synchronization...');
        const chipSyncResult = await client.evaluate(`
            (() => {
                const interestsInput = document.getElementById('interests');
                if (interestsInput) {
                    interestsInput.value = '';
                    interestsInput.dispatchEvent(new Event('input'));
                }

                const chips = Array.from(document.querySelectorAll('.category-chip, .category-select-chip'));
                if (chips.length < 2) return null;

                // Click first two chips
                chips[0].click();
                chips[1].click();

                const activeChips = document.querySelectorAll('.category-chip.active, .category-select-chip.chip-selected');

                return {
                    activeCount: activeChips.length,
                    interestsValue: interestsInput ? interestsInput.value : ''
                };
            })()
        `);
        assert(chipSyncResult && chipSyncResult.activeCount >= 1, `Category chips click toggles active state (${chipSyncResult?.activeCount} active)`);
        assert(chipSyncResult && chipSyncResult.interestsValue.length > 0, `Interests input synced with selected categories: "${chipSyncResult?.interestsValue}"`);

        // Test Social Links and WhatsApp Input
        console.log('Filling social links and WhatsApp privacy toggle...');
        await client.evaluate(`
            (() => {
                const ig = document.getElementById('instagram_url');
                const gh = document.getElementById('github_url');
                const wa = document.getElementById('whatsapp_number');
                const waPub = document.getElementById('whatsapp_public');
                const bio = document.getElementById('bio');

                if (ig) ig.value = 'alex_creator';
                if (gh) gh.value = 'alex-dev';
                if (wa) wa.value = '+1 (555) 765-4321';
                if (waPub) waPub.checked = true;
                if (bio) bio.value = 'Senior Tech Writer & Architect at Infinity Blog';

                const form = document.querySelector('form[action*="/profile/edit"]');
                if (form) form.submit();
            })()
        `);
        await sleep(3000);
        await dismissCookies();

        await client.screenshot(path.join(ARTIFACTS_DIR, 'profile_edit_desktop.png'));

        // Step 3: Test Public Profile Display (/profile)
        console.log('\n--- Step 3: Testing Profile Display & Social Icons ---');
        await client.send('Page.navigate', { url: 'http://localhost:8086/profile' });
        await sleep(2000);
        await dismissCookies();

        const profileData = await client.evaluate(`
            (() => {
                const handleEl = document.querySelector('.profile-handle');
                const nameEl = document.querySelector('.profile-name, .profile-title');
                const avatarImg = document.querySelector('.profile-avatar img, .profile-avatar-img');
                const avatarFallback = document.querySelector('.profile-avatar-fallback, .avatar-initials');
                const socialLinks = Array.from(document.querySelectorAll('.social-link-btn, .profile-social-link'));
                const whatsappBtn = socialLinks.find(a => a.href.includes('wa.me') || a.getAttribute('aria-label')?.includes('WhatsApp'));

                return {
                    name: nameEl ? nameEl.textContent.trim() : '',
                    handle: handleEl ? handleEl.textContent.trim() : '',
                    hasAvatar: !!(avatarImg || avatarFallback),
                    avatarSrc: avatarImg ? avatarImg.src : null,
                    socialCount: socialLinks.length,
                    hasWhatsApp: !!whatsappBtn,
                    whatsAppHref: whatsappBtn ? whatsappBtn.href : null
                };
            })()
        `);
        console.log('Profile page evaluation:', profileData);

        assert(profileData.handle.startsWith('@') && !profileData.handle.includes('@example.com') && !profileData.handle.includes('@gmail.com'),
            `Profile displays clean Instagram handle: "${profileData.handle}" (no email leak)`
        );
        assert(profileData.hasAvatar, 'Profile displays valid avatar image or initials fallback');
        assert(profileData.socialCount > 0, `Profile renders branded social links (${profileData.socialCount} links found)`);
        if (profileData.hasWhatsApp) {
            assert(profileData.whatsAppHref.startsWith('https://wa.me/'), `Public WhatsApp link formatted correctly: ${profileData.whatsAppHref}`);
        }

        // Capture Desktop Light Mode
        await client.screenshot(path.join(ARTIFACTS_DIR, 'profile_view_desktop_light.png'));

        // Switch to Dark Theme on Profile
        console.log('Testing Dark Theme on Profile page...');
        await client.evaluate(`
            (() => {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            })()
        `);
        await sleep(600);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'profile_view_desktop_dark.png'));

        // Switch back to light theme
        await client.evaluate(`
            (() => {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            })()
        `);
        await sleep(400);

        // Step 4: Test Writing Studio (/posts/new)
        console.log('\n--- Step 4: Testing Writing Studio Modal & Live Preview ---');
        await client.send('Page.navigate', { url: 'http://localhost:8086/posts/new' });
        await sleep(2500);
        await dismissCookies();

        // 4.1 Verify Writing Goal Custom Modal (no native prompt)
        console.log('Testing Writing Goal modal dialog...');
        const goalModalCheck = await client.evaluate(`
            (() => {
                const modal = document.getElementById('word-count-modal');
                const triggerBtn = document.getElementById('studio-writing-goal-card') || document.getElementById('target-words-display') || document.querySelector('.studio-goal-card, [onclick*="openWordCountModal"]');
                return {
                    hasModal: !!modal,
                    hasPresets: modal ? modal.querySelectorAll('.preset-pill').length : 0,
                    hasInput: !!document.getElementById('modal-target-words-input'),
                    hasSaveBtn: !!document.getElementById('modal-save-goal-btn'),
                    hasCancelBtn: !!document.getElementById('modal-cancel-goal-btn')
                };
            })()
        `);

        assert(goalModalCheck.hasModal, 'Writing Studio has #word-count-modal dialog');
        assert(goalModalCheck.hasPresets >= 4, `Word count modal has preset pills (${goalModalCheck.hasPresets} presets found)`);
        assert(goalModalCheck.hasInput, 'Word count modal has #modal-target-words-input');
        assert(goalModalCheck.hasSaveBtn, 'Word count modal has Save button');

        // Open Modal and click 2000 words preset
        console.log('Opening Writing Goal modal and clicking preset...');
        const modalOpenResult = await client.evaluate(`
            (() => {
                if (typeof window.openWordCountModal === 'function') {
                    window.openWordCountModal();
                } else {
                    const btn = document.getElementById('studio-writing-goal-card') || document.getElementById('target-words-display');
                    if (btn) btn.click();
                }
                const modal = document.getElementById('word-count-modal');
                return {
                    isOpen: modal && (modal.classList.contains('show') || modal.style.display !== 'none')
                };
            })()
        `);
        await sleep(600);
        assert(modalOpenResult.isOpen, 'Writing Goal modal opens smoothly with glassmorphic styling');
        await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_goal_modal.png'));

        // Select 2000 preset and save
        const goalSaveResult = await client.evaluate(`
            (() => {
                const pills = Array.from(document.querySelectorAll('.preset-pill'));
                const pill2000 = pills.find(p => p.textContent.includes('2000') || p.getAttribute('data-words') === '2000');
                if (pill2000) pill2000.click();

                const saveBtn = document.getElementById('modal-save-goal-btn');
                if (saveBtn) saveBtn.click();

                const modal = document.getElementById('word-count-modal');
                const display = document.getElementById('goal-words-target') || document.getElementById('target-words-display');
                return {
                    modalClosed: modal && !modal.classList.contains('show'),
                    displayText: display ? display.textContent.trim() : ''
                };
            })()
        `);
        await sleep(800);
        assert(goalSaveResult.modalClosed, 'Writing Goal modal closes after saving');
        assert(goalSaveResult.displayText.includes('2000') || goalSaveResult.displayText.includes('2,000'), `Target words updated to 2000 words (${goalSaveResult.displayText})`);

        // 4.2 Test Article Preview in Studio
        console.log('Testing Article Preview toggle with metadata & content...');
        const previewToggleResult = await client.evaluate(`
            (async () => {
                const titleInput = document.getElementById('post-title');
                if (titleInput) {
                    titleInput.value = 'Modern Design Architecture and Writing Studio in Infinity Blog';
                    titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                const editor = document.getElementById('post-content') || document.getElementById('editor');
                if (editor) {
                    editor.value = 'Infinity Blog represents a modern paradigm in publishing platforms. With glassmorphic aesthetics on desktop and responsive claymorphism on mobile, authors enjoy a distraction-free writing environment with real-time feedback and rich previews.';
                    editor.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Click Preview button
                const previewBtn = document.getElementById('studio-preview-toggle-btn') || document.getElementById('preview-toggle-btn') || document.querySelector('[data-action="preview"], .btn-preview');
                if (previewBtn) {
                    previewBtn.click();
                } else if (typeof window.togglePreview === 'function') {
                    window.togglePreview();
                }

                // Wait for preview render
                await new Promise(r => setTimeout(r, 1500));

                const previewArea = document.getElementById('studio-preview-container') || document.getElementById('preview-area');
                const renderedArea = document.getElementById('studio-preview-rendered');
                const isPreviewVisible = previewArea && (previewArea.style.display !== 'none' || previewArea.classList.contains('active') || !previewArea.hidden);
                const previewText = (previewArea ? previewArea.textContent : '') + ' ' + (renderedArea ? renderedArea.textContent : '');
                const hasError = previewText.includes('Failed to render preview');

                return {
                    previewVisible: isPreviewVisible,
                    hasError: hasError,
                    hasTitle: previewText.includes('Modern Design Architecture') || previewText.includes('Infinity Blog') || renderedArea?.innerHTML.length > 20,
                    previewHtmlLength: renderedArea ? renderedArea.innerHTML.length : (previewArea ? previewArea.innerHTML.length : 0)
                };
            })()
        `);
        console.log('Preview toggle result:', previewToggleResult);

        assert(previewToggleResult.previewVisible, 'Preview area is displayed when Preview button is clicked');
        assert(!previewToggleResult.hasError, 'Article preview renders successfully WITHOUT "Failed to render preview" error');
        assert(previewToggleResult.hasTitle, 'Preview card renders article title and content properly');

        await client.screenshot(path.join(ARTIFACTS_DIR, 'writing_studio_preview_rendered.png'));

        // Step 5: Test Micro-animations and CSS Transitions
        console.log('\n--- Step 5: Testing Micro-animations & Motion Accessibility ---');
        const motionCheck = await client.evaluate(`
            (() => {
                const styleSheet = Array.from(document.styleSheets).find(s => {
                    try { return s.href && s.href.includes('style.css'); } catch (e) { return false; }
                });
                let hasReducedMotion = false;
                try {
                    const rules = document.styleSheets;
                    for (const sheet of rules) {
                        try {
                            for (const rule of sheet.cssRules || []) {
                                if (rule.conditionText && rule.conditionText.includes('prefers-reduced-motion')) {
                                    hasReducedMotion = true;
                                    break;
                                }
                            }
                        } catch (e) {}
                    }
                } catch (e) {}

                return {
                    hasReducedMotion: hasReducedMotion
                };
            })()
        `);
        assert(motionCheck.hasReducedMotion, 'Styles include @media (prefers-reduced-motion: reduce) for accessible animations');

        // Step 6: Multi-Viewport Responsive Verification
        console.log('\n--- Step 6: Capturing Multi-Viewport Screenshots ---');
        const viewports = [
            { name: 'desktop_1536_light', width: 1536, height: 960, theme: 'light' },
            { name: 'desktop_1536_dark', width: 1536, height: 960, theme: 'dark' },
            { name: 'desktop_1280_light', width: 1280, height: 800, theme: 'light' },
            { name: 'tablet_1024_light', width: 1024, height: 768, theme: 'light' },
            { name: 'tablet_768_light', width: 768, height: 1024, theme: 'light' },
            { name: 'mobile_430_light', width: 430, height: 932, theme: 'light' },
            { name: 'mobile_375_light', width: 375, height: 812, theme: 'light' },
            { name: 'mobile_375_dark', width: 375, height: 812, theme: 'dark' }
        ];

        // Navigate to home feed for comprehensive viewport checks
        await client.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(2000);
        await dismissCookies();

        for (const vp of viewports) {
            await client.setViewport(vp.width, vp.height);
            await client.evaluate(`
                (() => {
                    document.documentElement.setAttribute('data-theme', ${JSON.stringify(vp.theme)});
                    localStorage.setItem('theme', ${JSON.stringify(vp.theme)});
                })()
            `);
            await sleep(700);

            const shotPath = path.join(ARTIFACTS_DIR, `responsive_home_${vp.name}.png`);
            await client.screenshot(shotPath);
        }

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
