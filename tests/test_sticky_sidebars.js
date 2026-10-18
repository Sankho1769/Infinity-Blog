const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9224;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_sticky_test_profile');

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
            const reqId = this.id++;
            this.callbacks.set(reqId, { resolve, reject });
            this.ws.send(JSON.stringify({ id: reqId, method, params }));
        });
    }

    on(event, handler) {
        if (!this.events.has(event)) {
            this.events.set(event, []);
        }
        this.events.get(event).push(handler);
    }

    async evaluate(expression) {
        const res = await this.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true
        });
        if (res.exceptionDetails) {
            throw new Error(JSON.stringify(res.exceptionDetails));
        }
        return res.result.value;
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

async function testViewport(cdp, width, height) {
    console.log(`\n============================================================`);
    console.log(`Testing Viewport: ${width}px x ${height}px`);
    console.log(`============================================================`);

    await cdp.send('Emulation.setDeviceMetricsOverride', {
        width,
        height,
        deviceScaleFactor: 1,
        mobile: false
    });

    await cdp.send('Page.navigate', { url: 'http://localhost:8086/' });
    await sleep(1500);

    // Initial state check
    const initial = await cdp.evaluate(`
        (() => {
            const left = document.querySelector('.app-left-sidebar');
            const right = document.querySelector('.homepage-sidebar');
            const feed = document.querySelector('.homepage-main-column');
            const leftRect = left.getBoundingClientRect();
            const rightRect = right.getBoundingClientRect();
            const feedRect = feed.getBoundingClientRect();
            const leftStyle = window.getComputedStyle(left);
            const rightStyle = window.getComputedStyle(right);

            return {
                left: {
                    position: leftStyle.position,
                    top: leftRect.top,
                    bottom: leftRect.bottom,
                    left: leftRect.left,
                    right: leftRect.right,
                    height: leftRect.height,
                    maxHeight: leftStyle.maxHeight
                },
                right: {
                    position: rightStyle.position,
                    top: rightRect.top,
                    bottom: rightRect.bottom,
                    left: rightRect.left,
                    right: rightRect.right,
                    height: rightRect.height,
                    maxHeight: rightStyle.maxHeight
                },
                feed: {
                    top: feedRect.top,
                    left: feedRect.left,
                    right: feedRect.right,
                    width: feedRect.width
                },
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                }
            };
        })()
    `);

    console.log(`Initial at scrollY = 0:`);
    console.log(`  Left sidebar: position = ${initial.left.position}, top = ${initial.left.top}px, height = ${initial.left.height}px`);
    console.log(`  Right sidebar: position = ${initial.right.position}, top = ${initial.right.top}px, height = ${initial.right.height}px`);
    console.log(`  Center feed: left = ${initial.feed.left}px, width = ${initial.feed.width}px, right = ${initial.feed.right}px`);

    const ancestry = await cdp.evaluate(`
        (() => {
            function getAncestors(el) {
                const list = [];
                let c = el;
                while (c) {
                    const s = window.getComputedStyle(c);
                    list.push({
                        node: c.tagName + (c.id ? '#' + c.id : '') + (c.className ? '.' + String(c.className).trim().replace(/\\s+/g, '.') : ''),
                        overflow: s.overflow,
                        overflowX: s.overflowX,
                        overflowY: s.overflowY,
                        height: s.height,
                        maxHeight: s.maxHeight,
                        position: s.position,
                        transform: s.transform,
                        contain: s.contain
                    });
                    c = c.parentElement;
                }
                return list;
            }
            return {
                leftAncestors: getAncestors(document.querySelector('.app-left-sidebar')),
                rightAncestors: getAncestors(document.querySelector('.homepage-sidebar'))
            };
        })()
    `);
    console.log('--- Ancestry Diagnostic ---');
    console.log('Left sidebar ancestors:', JSON.stringify(ancestry.leftAncestors, null, 2));
    console.log('Right sidebar ancestors:', JSON.stringify(ancestry.rightAncestors, null, 2));

    if (initial.left.position !== 'sticky') throw new Error(`Left sidebar position should be sticky, got ${initial.left.position}`);
    if (initial.right.position !== 'sticky') throw new Error(`Right sidebar position should be sticky, got ${initial.right.position}`);

    // Verify no horizontal overlap
    if (initial.left.right > initial.feed.left) throw new Error(`Left sidebar overlaps feed: ${initial.left.right} > ${initial.feed.left}`);
    if (initial.feed.right > initial.right.left) throw new Error(`Feed overlaps right sidebar: ${initial.feed.right} > ${initial.right.left}`);
    console.log(`  PASS: No horizontal overlap between left sidebar, center feed, and right recommendations.`);

    // Scroll down through feed: 300px, 600px, 800px
    const scrollPositions = [300, 600, 800];
    for (const scrollY of scrollPositions) {
        await cdp.evaluate(`window.scrollTo(0, ${scrollY})`);
        await sleep(400);

        const scrolled = await cdp.evaluate(`
            (() => {
                const left = document.querySelector('.app-left-sidebar');
                const right = document.querySelector('.homepage-sidebar');
                const feed = document.querySelector('.homepage-main-column');
                const leftRect = left.getBoundingClientRect();
                const rightRect = right.getBoundingClientRect();
                const feedRect = feed.getBoundingClientRect();

                return {
                    scrollY: window.scrollY,
                    leftTop: leftRect.top,
                    leftBottom: leftRect.bottom,
                    rightTop: rightRect.top,
                    rightBottom: rightRect.bottom,
                    feedTop: feedRect.top,
                    feedBottom: feedRect.bottom
                };
            })()
        `);

        console.log(`\n  Scrolled to scrollY = ${scrolled.scrollY}px:`);
        console.log(`    Left sidebar top: ${scrolled.leftTop}px (expected sticky top ≈ 16px)`);
        console.log(`    Right sidebar top: ${scrolled.rightTop}px (expected sticky top ≈ 80px)`);
        console.log(`    Center feed top: ${scrolled.feedTop}px (scrolled naturally)`);

        // Check left sidebar is stuck at 16px
        if (Math.abs(scrolled.leftTop - 16) > 2) {
            throw new Error(`Left sidebar did not stick at top 16px! (got ${scrolled.leftTop}px at scrollY = ${scrolled.scrollY})`);
        }
        // Check right sidebar is stuck at 80px
        if (Math.abs(scrolled.rightTop - 80) > 2) {
            throw new Error(`Right sidebar did not stick at top 80px! (got ${scrolled.rightTop}px at scrollY = ${scrolled.scrollY})`);
        }

        // Check center feed moved up naturally
        if (scrolled.feedTop >= initial.feed.top) {
            throw new Error(`Center feed did not scroll! (feedTop = ${scrolled.feedTop}px)`);
        }
        console.log(`    PASS: Both sidebars remained stuck in viewport while center feed scrolled.`);
    }

    // Verify recommendations links and buttons are accessible
    const interactiveCheck = await cdp.evaluate(`
        (() => {
            const authorsCard = document.querySelector('.sidebar-card-authors');
            const commsCard = document.querySelector('.sidebar-card-communities');
            const followBtns = document.querySelectorAll('.homepage-sidebar .btn-follow-toggle');
            const joinBtns = document.querySelectorAll('.homepage-sidebar .btn-community-join');
            const authorsLink = document.querySelector('.homepage-sidebar a[href="/authors"]');
            const commsLink = document.querySelector('.homepage-sidebar a[href="/communities"]');

            return {
                hasAuthorsCard: !!authorsCard,
                hasCommsCard: !!commsCard,
                followBtnCount: followBtns.length,
                joinBtnCount: joinBtns.length,
                hasAuthorsLink: !!authorsLink,
                hasCommsLink: !!commsLink
            };
        })()
    `);

    console.log(`  Interactive controls:`, interactiveCheck);
    if (!interactiveCheck.hasAuthorsCard || !interactiveCheck.hasCommsCard) {
        throw new Error('Missing recommendation cards in right sidebar');
    }
    if (interactiveCheck.followBtnCount === 0) throw new Error('Missing follow buttons');
    if (interactiveCheck.joinBtnCount === 0) throw new Error('Missing join buttons');
    console.log(`  PASS: Recommendation cards, follow buttons, join buttons, and discovery links accessible.`);

    // Reset scroll
    await cdp.evaluate('window.scrollTo(0, 0)');
    await sleep(200);
}

async function run() {
    console.log('=== Starting Headless Edge Browser Sticky Sidebars Test ===');

    if (!fs.existsSync(USER_DATA_DIR)) {
        fs.mkdirSync(USER_DATA_DIR, { recursive: true });
    }

    const edgeProc = spawn(EDGE_PATH, [
        '--headless=new',
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--window-size=1920,1080',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        'about:blank'
    ]);

    let edgeClosed = false;
    edgeProc.on('exit', () => { edgeClosed = true; });

    try {
        let targets = null;
        for (let i = 0; i < 30; i++) {
            await sleep(300);
            try {
                targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json/list`);
                if (targets && targets.length > 0) break;
            } catch (e) {}
        }

        if (!targets || targets.length === 0) {
            throw new Error('Failed to connect to Edge remote debugging port');
        }

        const pageTarget = targets.find(t => t.type === 'page') || targets[0];
        console.log(`Connected to target: ${pageTarget.title} (${pageTarget.webSocketDebuggerUrl})`);

        const cdp = new CdpClient(pageTarget.webSocketDebuggerUrl);
        await cdp.connect();

        await cdp.send('Page.enable');
        await cdp.send('DOM.enable');
        await cdp.send('CSS.enable');

        // Test at the three required viewports
        await testViewport(cdp, 1854, 950);
        await testViewport(cdp, 1440, 900);
        await testViewport(cdp, 1280, 800);

        console.log('\n============================================================');
        console.log('ALL VIEWPORT TESTS PASSED SUCCESSFULLY!');
        console.log('============================================================');

        cdp.close();
    } finally {
        if (!edgeClosed) {
            edgeProc.kill('SIGKILL');
        }
    }
}

run().catch(err => {
    console.error('Test failed with error:', err);
    process.exit(1);
});
