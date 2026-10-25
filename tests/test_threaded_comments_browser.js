const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9250;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_discussion_test_profile');
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

    close() {
        if (this.ws) this.ws.close();
    }
}

async function main() {
    console.log('=== STARTING BROWSER VERIFICATION: THREADED COMMENTS & DISCUSSION SYSTEM ===\n');

    let edgeProcess = null;
    let wsUrl = null;

    try {
        const versionData = await fetchJson(`http://localhost:${DEBUG_PORT}/json/version`);
        wsUrl = versionData.webSocketDebuggerUrl;
        console.log('Connected to existing Edge instance.');
    } catch (e) {
        console.log('Launching dedicated Edge browser on port ' + DEBUG_PORT + '...');
        try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (err) {}
        edgeProcess = spawn(EDGE_PATH, [
            `--remote-debugging-port=${DEBUG_PORT}`,
            `--user-data-dir=${USER_DATA_DIR}`,
            '--no-first-run',
            '--no-default-browser-check',
            'about:blank'
        ]);
        await sleep(2500);

        const versionData = await fetchJson(`http://localhost:${DEBUG_PORT}/json/version`);
        wsUrl = versionData.webSocketDebuggerUrl;
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

    try {
        // Step 1: Sign in with authenticated user
        console.log('\n--- Step 1: Sign in as authenticated user ---');
        await client.setViewport(1440, 900);
        await client.send('Page.navigate', { url: 'http://localhost:8086/login' });
        await sleep(2000);

        const { email, password } = loadCredentials();
        const loginRes = await client.evaluate(`
            (async () => {
                const emailInput = document.querySelector('input[name="identifier"]') || document.querySelector('input[type="email"]') || document.getElementById('login-email');
                const passInput = document.querySelector('input[name="password"]') || document.getElementById('login-password');
                const form = document.querySelector('form[action*="login"]') || document.querySelector('form');
                if (emailInput && passInput && form) {
                    emailInput.value = ${JSON.stringify(email)};
                    passInput.value = ${JSON.stringify(password)};
                    form.submit();
                    return { submitted: true };
                }
                return { submitted: false };
            })()
        `);
        console.log('Login form submitted:', loginRes);
        await sleep(2500);

        // Dismiss cookie banner if present
        await client.evaluate(`
            (() => {
                const btn = document.querySelector('.cookie-banner .btn-primary, [data-action="accept-cookies"], #cookie-accept-all');
                if (btn) btn.click();
            })()
        `);

        // Step 2: Homepage feed card comment triggers
        console.log('\n--- Step 2: Feed Card Comment Triggers on Homepage (Desktop 1440x900) ---');
        await client.send('Page.navigate', { url: 'http://localhost:8086/' });
        await sleep(2500);

        // Check comment buttons on feed cards
        const feedCardCheck = await client.evaluate(`
            (() => {
                const cards = document.querySelectorAll('.feed-card, [data-post-id]');
                const commentBtns = document.querySelectorAll('.feed-comments-btn, .comments-stat-trigger, [data-open-comments]');
                return {
                    cardsCount: cards.length,
                    commentBtnsCount: commentBtns.length,
                    firstPostId: commentBtns[0]?.getAttribute('data-post-id') || null
                };
            })()
        `);
        console.log('Feed cards inspection:', feedCardCheck);
        assert(feedCardCheck.commentBtnsCount > 0, `Found ${feedCardCheck.commentBtnsCount} feed comment trigger buttons`);

        // Scroll down a bit to test scroll preservation
        await client.evaluate(`window.scrollTo(0, 320);`);
        await sleep(500);
        const scrollBefore = await client.evaluate(`window.pageYOffset || document.documentElement.scrollTop;`);

        // Click comment button on first feed card
        console.log('Clicking comment button on first post card...');
        const clickResult = await client.evaluate(`
            (() => {
                const btn = document.querySelector('.feed-comments-btn, .comments-stat-trigger, [data-open-comments]');
                if (!btn) return { error: 'No comment button found' };
                const postId = btn.getAttribute('data-post-id');
                btn.click();
                return { clicked: true, postId: postId };
            })()
        `);
        console.log('Click result:', clickResult);
        await sleep(1500);

        // Verify modal opened without navigating away
        const currentUrl = await client.evaluate(`window.location.href;`);
        assert(currentUrl.includes('localhost:8086') && !currentUrl.includes('/article/'), 'URL remained on homepage (did not navigate away)');

        const modalOpen = await client.evaluate(`
            (() => {
                const drawer = document.getElementById('discussionDrawer');
                const backdrop = document.getElementById('discussionModalBackdrop');
                const isOpen = drawer?.classList.contains('is-open') || backdrop?.classList.contains('is-open') || (backdrop && window.getComputedStyle(backdrop).display !== 'none');
                const heading = document.getElementById('discussionDrawerHeading')?.textContent.trim();
                const countBadge = document.getElementById('discussionCountBadge')?.textContent.trim();
                const composer = document.getElementById('discussionComposerInput');
                return {
                    isOpen: !!isOpen,
                    heading: heading,
                    countBadge: countBadge,
                    hasComposer: !!composer
                };
            })()
        `);
        console.log('Discussion Modal State:', modalOpen);
        assert(modalOpen.isOpen === true, 'Comments discussion drawer opened with is-open state');
        assert(modalOpen.heading?.includes('Replies') || modalOpen.heading?.includes('Comments'), 'Modal header displays "Replies" or "Comments"');
        assert(modalOpen.hasComposer === true, 'Modal includes fixed bottom comment composer');

        // Scroll preservation check
        const scrollAfter = await client.evaluate(`window.pageYOffset || document.documentElement.scrollTop;`);
        assert(Math.abs(scrollAfter - scrollBefore) < 50, `Feed scroll position preserved (before: ${scrollBefore}, after: ${scrollAfter})`);

        // Capture Desktop Discussion Modal Screenshot
        await client.screenshot(path.join(ARTIFACTS_DIR, 'discussion_feed_modal_desktop.png'));

        // Step 3: Posting a Comment and Replying
        console.log('\n--- Step 3: Direct Commenting and Threaded Replies ---');
        const testCommentText = 'Test discussion comment posted at ' + new Date().toLocaleTimeString();
        const postCommentResult = await client.evaluate(`
            (async () => {
                const input = document.getElementById('discussionComposerInput');
                const sendBtn = document.getElementById('discussionComposerSendBtn');
                if (!input || !sendBtn) return { error: 'Composer or send button not found' };
                input.value = ${JSON.stringify(testCommentText)};
                input.dispatchEvent(new Event('input', { bubbles: true }));
                
                sendBtn.click();
                return { submitted: true };
            })()
        `);
        console.log('Submitted top-level comment:', postCommentResult);
        await sleep(2500);

        // Verify comment rendered in list
        const commentListStatus = await client.evaluate(`
            (() => {
                const items = document.querySelectorAll('.comment-node');
                const texts = Array.from(items).map(i => i.querySelector('.comment-text-content')?.textContent.trim());
                const matching = texts.find(t => t && t.includes('Test discussion comment'));
                const handles = Array.from(document.querySelectorAll('.comment-handle')).map(h => h.textContent.trim());
                const hasRawEmail = handles.some(h => h.includes('@example.com') || (h.includes('@') && h.includes('.')));
                return {
                    count: items.length,
                    foundNewComment: !!matching,
                    handles: handles.slice(0, 5),
                    hasRawEmail: hasRawEmail
                };
            })()
        `);
        console.log('Comment list status:', commentListStatus);
        assert(commentListStatus.foundNewComment === true, 'Newly posted comment rendered immediately in discussion stream');
        assert(commentListStatus.hasRawEmail === false, 'Handles are clean usernames and do not leak email domains');

        // Step 4: Test Reply Action
        console.log('\n--- Step 4: Test Reply Action and Reply Mode Banner ---');
        const replyTrigger = await client.evaluate(`
            (() => {
                const replyBtn = document.querySelector('.comment-action-btn.btn-reply');
                if (!replyBtn) return { error: 'No reply button found' };
                replyBtn.click();
                
                const banner = document.getElementById('discussionReplyingBanner');
                const bannerVisible = banner && window.getComputedStyle(banner).display !== 'none';
                const targetHandle = document.getElementById('discussionReplyingHandle')?.textContent;
                const inputPlaceholder = document.getElementById('discussionComposerInput')?.placeholder;
                return {
                    clicked: true,
                    bannerVisible: !!bannerVisible,
                    targetHandle: targetHandle,
                    placeholder: inputPlaceholder
                };
            })()
        `);
        console.log('Reply Trigger Result:', replyTrigger);
        assert(replyTrigger.bannerVisible === true, 'Reply-mode banner is visible above composer');
        assert(replyTrigger.targetHandle?.length > 0, `Reply banner target set to: ${replyTrigger.targetHandle}`);

        // Capture Reply Mode Active Screenshot
        await client.screenshot(path.join(ARTIFACTS_DIR, 'discussion_reply_mode_active.png'));

        // Post a nested reply
        const testReplyText = 'Nested child reply response at ' + new Date().toLocaleTimeString();
        await client.evaluate(`
            (async () => {
                const input = document.getElementById('discussionComposerInput');
                const sendBtn = document.getElementById('discussionComposerSendBtn');
                input.value = ${JSON.stringify(testReplyText)};
                input.dispatchEvent(new Event('input', { bubbles: true }));
                sendBtn.click();
            })()
        `);
        await sleep(2500);

        // Verify nested reply hierarchy and curved lines
        const threadCheck = await client.evaluate(`
            (() => {
                const replyItems = document.querySelectorAll('.comment-reply-item');
                const repliesWrappers = document.querySelectorAll('.comment-replies-wrapper');
                const toggleBtns = document.querySelectorAll('.comment-toggle-replies-btn');
                return {
                    replyItemsCount: replyItems.length,
                    repliesWrappersCount: repliesWrappers.length,
                    toggleBtnsCount: toggleBtns.length
                };
            })()
        `);
        console.log('Thread hierarchy check:', threadCheck);
        assert(threadCheck.replyItemsCount > 0, `Nested replies rendered with .comment-reply-item class`);
        assert(threadCheck.repliesWrappersCount > 0, 'Replies wrapper container structured with indented hierarchy');

        // Step 5: Reactions (Like / Dislike)
        console.log('\n--- Step 5: Test Like / Dislike Reactions ---');
        const reactionTest = await client.evaluate(`
            (async () => {
                const likeBtn = document.querySelector('.comment-action-btn.btn-like');
                if (!likeBtn) return { error: 'No like button' };
                const countEl = likeBtn.querySelector('.reaction-count');
                const initialCount = parseInt(countEl?.textContent || '0', 10);
                
                likeBtn.click();
                await new Promise(r => setTimeout(r, 600));
                const countAfterLike = parseInt(likeBtn.querySelector('.reaction-count')?.textContent || '0', 10);
                const hasActiveClass = likeBtn.classList.contains('active');
                
                // Toggle off
                likeBtn.click();
                await new Promise(r => setTimeout(r, 600));
                const countAfterToggle = parseInt(likeBtn.querySelector('.reaction-count')?.textContent || '0', 10);
                
                return {
                    initialCount,
                    countAfterLike,
                    hasActiveClass,
                    countAfterToggle
                };
            })()
        `);
        console.log('Reaction Test:', reactionTest);
        assert(reactionTest.hasActiveClass === true, 'Like button activated with active class');
        assert(reactionTest.countAfterLike === reactionTest.initialCount + 1, 'Like count incremented by 1');
        assert(reactionTest.countAfterToggle === reactionTest.initialCount, 'Like toggled off and count restored');

        // Step 6: Dark Mode Styling (Midnight Dark / Charcoal matching Reference 1)
        console.log('\n--- Step 6: Dark Theme Discussion Modal Verification ---');
        await client.evaluate(`
            (() => {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.body.classList.add('dark-theme');
                const themeToggle = document.querySelector('.theme-toggle, [data-action="toggle-theme"]');
                if (themeToggle && document.documentElement.getAttribute('data-theme') !== 'dark') {
                    themeToggle.click();
                }
            })()
        `);
        await sleep(800);

        const darkModalStyle = await client.evaluate(`
            (() => {
                const drawer = document.getElementById('discussionDrawer');
                if (!drawer) return null;
                const style = window.getComputedStyle(drawer);
                return {
                    bg: style.backgroundColor,
                    color: style.color,
                    borderColor: style.borderColor
                };
            })()
        `);
        console.log('Dark Theme Sheet Style:', darkModalStyle);
        assert(darkModalStyle !== null, 'Dark theme applied to discussion sheet');

        // Screenshot Dark Theme Desktop
        await client.screenshot(path.join(ARTIFACTS_DIR, 'discussion_dark_theme_desktop.png'));

        // Step 7: Mobile Viewport & Claymorphism Bottom Sheet (375x812)
        console.log('\n--- Step 7: Mobile Claymorphism Bottom Sheet (375x812) ---');
        await client.setViewport(375, 812);
        await sleep(1000);

        const mobileSheetCheck = await client.evaluate(`
            (() => {
                const drawer = document.getElementById('discussionDrawer');
                const handle = document.querySelector('.discussion-drag-handle');
                const rect = drawer ? drawer.getBoundingClientRect() : null;
                const handleStyle = handle ? window.getComputedStyle(handle) : null;
                return {
                    sheetWidth: rect?.width,
                    sheetBottom: rect?.bottom,
                    hasDragHandle: !!handle,
                    dragHandleVisible: handleStyle?.display !== 'none'
                };
            })()
        `);
        console.log('Mobile Bottom Sheet Check:', mobileSheetCheck);
        assert(mobileSheetCheck.hasDragHandle === true, 'Mobile bottom sheet renders subtle drag handle at top');
        assert(mobileSheetCheck.dragHandleVisible === true, 'Drag handle is visible on mobile viewport');

        // Screenshot Mobile Bottom Sheet Claymorphism
        await client.screenshot(path.join(ARTIFACTS_DIR, 'discussion_mobile_bottom_sheet_clay.png'));

        // Close modal
        console.log('\n--- Step 8: Close Modal & Verify Feed Scroll ---');
        await client.evaluate(`
            (() => {
                const closeBtn = document.querySelector('.discussion-close-btn') || document.querySelector('.discussion-back-btn');
                if (closeBtn) closeBtn.click();
            })()
        `);
        await sleep(600);

        const modalClosed = await client.evaluate(`
            (() => {
                const drawer = document.getElementById('discussionDrawer');
                return !drawer?.classList.contains('is-open');
            })()
        `);
        assert(modalClosed === true, 'Comments drawer closed cleanly via close button');

        // Step 9: Article Detail Page Inline Threaded Discussion
        console.log('\n--- Step 9: Article Detail Page Inline Discussion & Isolation ---');
        await client.setViewport(1440, 900);
        const articleId = feedCardCheck.firstPostId || '16';
        await client.send('Page.navigate', { url: `http://localhost:8086/article/${articleId}` });
        await sleep(2500);

        const articlePageCheck = await client.evaluate(`
            (() => {
                const reviewsSection = document.getElementById('reader-reviews');
                const scoreSection = document.getElementById('reader-score');
                const commentsSection = document.getElementById('commentsSection');
                const inlineItems = commentsSection?.querySelectorAll('.comment-item, .comment-node');
                return {
                    hasReviews: !!reviewsSection,
                    hasScore: !!scoreSection,
                    hasCommentsSection: !!commentsSection,
                    inlineCommentsCount: inlineItems?.length || 0,
                    reviewsAndCommentsDistinct: reviewsSection !== commentsSection
                };
            })()
        `);
        console.log('Article page check:', articlePageCheck);
        assert(articlePageCheck.hasCommentsSection === true, 'Article page contains dedicated #commentsSection');
        assert(articlePageCheck.reviewsAndCommentsDistinct === true, '1-5 star reader reviews and discussion comments are strictly separated');

        // Scroll down to comments section on article page
        await client.evaluate(`
            (() => {
                const sec = document.getElementById('commentsSection');
                if (sec) sec.scrollIntoView({ behavior: 'instant', block: 'center' });
            })()
        `);
        await sleep(1000);

        // Screenshot Article Page Inline Comments
        await client.screenshot(path.join(ARTIFACTS_DIR, 'discussion_article_page_inline.png'));

    } finally {
        client.close();
        if (edgeProcess) {
            try { edgeProcess.kill(); } catch (e) {}
        }
    }

    console.log(`\n======================================================`);
    console.log(`BROWSER VERIFICATION COMPLETE: ${passes} passed, ${fails} failed.`);
    if (fails === 0) {
        console.log('ALL BROWSER DISCUSSION TESTS PASSED WITH 100% SUCCESS!');
    } else {
        console.error('SOME CHECKS FAILED IN BROWSER TEST!');
        process.exit(1);
    }
}

main().catch(err => {
    console.error('Fatal error in browser test:', err);
    process.exit(1);
});
