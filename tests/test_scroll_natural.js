const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9222;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_scroll_test_profile');

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

async function run() {
    console.log('=== Starting Headless Edge Browser Natural Scroll Test ===');

    if (!fs.existsSync(USER_DATA_DIR)) {
        fs.mkdirSync(USER_DATA_DIR, { recursive: true });
    }

    const edgeProc = spawn(EDGE_PATH, [
        '--headless=new',
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--window-size=1440,900',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        'about:blank'
    ]);

    let edgeClosed = false;
    edgeProc.on('exit', () => {
        edgeClosed = true;
    });

    try {
        let targets = null;
        for (let i = 0; i < 30; i++) {
            await sleep(300);
            try {
                targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json/list`);
                if (targets && targets.length > 0) break;
            } catch (e) {
                // waiting for edge to listen
            }
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

        const pageLoaded = new Promise(resolve => {
            cdp.on('Page.loadEventFired', resolve);
        });

        console.log('Navigating to http://localhost:8086/ ...');
        await cdp.send('Page.navigate', { url: 'http://localhost:8086/' });
        await pageLoaded;
        await sleep(1000); // allow styles & layout to settle

        console.log('\n--- 1. Inspecting Computed Styles ---');
        const computedStyles = await cdp.evaluate(`
            (() => {
                const leftSidebar = document.querySelector('.app-left-sidebar');
                const rightSidebar = document.querySelector('.homepage-sidebar');
                const shell = document.querySelector('.app-layout-shell');
                const leftStyle = leftSidebar ? window.getComputedStyle(leftSidebar) : null;
                const rightStyle = rightSidebar ? window.getComputedStyle(rightSidebar) : null;
                const shellStyle = shell ? window.getComputedStyle(shell) : null;

                return {
                    shell: {
                        display: shellStyle ? shellStyle.display : null,
                        alignItems: shellStyle ? shellStyle.alignItems : null
                    },
                    leftSidebar: {
                        position: leftStyle ? leftStyle.position : null,
                        top: leftStyle ? leftStyle.top : null,
                        borderRadius: leftStyle ? leftStyle.borderRadius : null,
                        boxShadow: leftStyle ? leftStyle.boxShadow : null,
                        borderWidth: leftStyle ? leftStyle.borderWidth : null,
                        height: leftStyle ? leftStyle.height : null,
                        maxHeight: leftStyle ? leftStyle.maxHeight : null
                    },
                    rightSidebar: {
                        position: rightStyle ? rightStyle.position : null,
                        top: rightStyle ? rightStyle.top : null,
                        maxHeight: rightStyle ? rightStyle.maxHeight : null,
                        overflowY: rightStyle ? rightStyle.overflowY : null
                    }
                };
            })()
        `);

        console.log('App shell styles:', computedStyles.shell);
        console.log('Left sidebar styles:', computedStyles.leftSidebar);
        console.log('Right sidebar styles:', computedStyles.rightSidebar);

        let passCount = 0;
        let failCount = 0;

        function assert(cond, msg) {
            if (cond) {
                console.log(`PASS: ${msg}`);
                passCount++;
            } else {
                console.log(`FAIL: ${msg}`);
                failCount++;
            }
        }

        assert(computedStyles.leftSidebar.position === 'relative', `Left sidebar position is 'relative' (got ${computedStyles.leftSidebar.position})`);
        assert(computedStyles.leftSidebar.position !== 'sticky' && computedStyles.leftSidebar.position !== 'fixed', 'Left sidebar is NOT sticky or fixed');
        assert(computedStyles.rightSidebar.position === 'relative', `Right sidebar position is 'relative' (got ${computedStyles.rightSidebar.position})`);
        assert(computedStyles.rightSidebar.position !== 'sticky' && computedStyles.rightSidebar.position !== 'fixed', 'Right sidebar is NOT sticky or fixed');
        assert(computedStyles.rightSidebar.maxHeight === 'none', `Right sidebar has no max-height restriction (got ${computedStyles.rightSidebar.maxHeight})`);
        assert(computedStyles.rightSidebar.overflowY === 'visible', `Right sidebar has overflow-y: visible (got ${computedStyles.rightSidebar.overflowY})`);

        console.log('\n--- 2. Scroll Position Bounding Rect Measurements ---');

        // Scroll to top
        await cdp.evaluate('window.scrollTo(0, 0)');
        await sleep(300);

        const atTop = await cdp.evaluate(`
            (() => {
                const left = document.querySelector('.app-left-sidebar').getBoundingClientRect();
                const right = document.querySelector('.homepage-sidebar').getBoundingClientRect();
                return {
                    scrollY: window.scrollY,
                    leftTop: left.top,
                    leftBottom: left.bottom,
                    leftHeight: left.height,
                    rightTop: right.top,
                    rightBottom: right.bottom,
                    rightHeight: right.height,
                    viewportHeight: window.innerHeight
                };
            })()
        `);

        console.log('Initial positions at scrollY = 0:');
        console.log(`  Left sidebar: top = ${atTop.leftTop}px, bottom = ${atTop.leftBottom}px, height = ${atTop.leftHeight}px`);
        console.log(`  Right sidebar: top = ${atTop.rightTop}px, bottom = ${atTop.rightBottom}px, height = ${atTop.rightHeight}px`);
        console.log(`  Viewport height: ${atTop.viewportHeight}px`);

        assert(atTop.leftTop >= 0 && atTop.leftTop <= 30, `Left sidebar initial top is in comfortable floating margin range (got ${atTop.leftTop}px)`);
        assert(atTop.rightTop >= 0 && atTop.rightTop <= 150, `Right sidebar initial top is in expected top range (got ${atTop.rightTop}px)`);

        // Scroll down by 800px (typical mid-page scroll)
        console.log('\n--- Scrolling window by 800px ---');
        await cdp.evaluate('window.scrollTo(0, 800)');
        await sleep(300);

        const at800 = await cdp.evaluate(`
            (() => {
                const left = document.querySelector('.app-left-sidebar').getBoundingClientRect();
                const right = document.querySelector('.homepage-sidebar').getBoundingClientRect();
                return {
                    scrollY: window.scrollY,
                    leftTop: left.top,
                    leftBottom: left.bottom,
                    rightTop: right.top,
                    rightBottom: right.bottom
                };
            })()
        `);

        console.log(`Positions at scrollY = ${at800.scrollY}:`);
        console.log(`  Left sidebar: top = ${at800.leftTop}px, bottom = ${at800.leftBottom}px`);
        console.log(`  Right sidebar: top = ${at800.rightTop}px, bottom = ${at800.rightBottom}px`);

        // If sidebars were sticky, leftTop would still be ~16px and rightTop would still be ~80px.
        // Because they scroll naturally, top should be: initialTop - 800px.
        const expectedLeftTop800 = atTop.leftTop - 800;
        const expectedRightTop800 = atTop.rightTop - 800;

        assert(Math.abs(at800.leftTop - expectedLeftTop800) < 5, `Left sidebar moved up naturally with scroll by ~800px (expected ${expectedLeftTop800}px, got ${at800.leftTop}px)`);
        assert(Math.abs(at800.rightTop - expectedRightTop800) < 5, `Right sidebar moved up naturally with scroll by ~800px (expected ${expectedRightTop800}px, got ${at800.rightTop}px)`);

        // Scroll down by 2000px (deep scroll)
        console.log('\n--- Scrolling window by 2000px ---');
        await cdp.evaluate('window.scrollTo(0, 2000)');
        await sleep(300);

        const at2000 = await cdp.evaluate(`
            (() => {
                const left = document.querySelector('.app-left-sidebar').getBoundingClientRect();
                const right = document.querySelector('.homepage-sidebar').getBoundingClientRect();
                return {
                    scrollY: window.scrollY,
                    leftTop: left.top,
                    leftBottom: left.bottom,
                    rightTop: right.top,
                    rightBottom: right.bottom
                };
            })()
        `);

        console.log(`Positions at scrollY = ${at2000.scrollY}:`);
        console.log(`  Left sidebar: top = ${at2000.leftTop}px, bottom = ${at2000.leftBottom}px`);
        console.log(`  Right sidebar: top = ${at2000.rightTop}px, bottom = ${at2000.rightBottom}px`);

        assert(at2000.leftBottom < 0, `Left sidebar bottom is negative (${at2000.leftBottom}px) - fully moved up and out of viewport`);
        assert(at2000.rightBottom < 0, `Right sidebar bottom is negative (${at2000.rightBottom}px) - fully moved up and out of viewport`);

        // Scroll back to top to verify restoration
        console.log('\n--- Scrolling back to top ---');
        await cdp.evaluate('window.scrollTo(0, 0)');
        await sleep(300);

        const restored = await cdp.evaluate(`
            (() => {
                const left = document.querySelector('.app-left-sidebar').getBoundingClientRect();
                const right = document.querySelector('.homepage-sidebar').getBoundingClientRect();
                return {
                    leftTop: left.top,
                    rightTop: right.top
                };
            })()
        `);

        assert(Math.abs(restored.leftTop - atTop.leftTop) < 2, `Left sidebar accurately restored to initial top (${restored.leftTop}px)`);
        assert(Math.abs(restored.rightTop - atTop.rightTop) < 2, `Right sidebar accurately restored to initial top (${restored.rightTop}px)`);

        console.log(`\n=== Verification Complete: ${passCount} passed, ${failCount} failed ===`);

        cdp.close();

        if (failCount > 0) {
            process.exit(1);
        }
    } finally {
        if (!edgeClosed) {
            edgeProc.kill('SIGKILL');
        }
    }
}

run().catch(err => {
    console.error('Fatal error during browser test:', err);
    process.exit(1);
});
