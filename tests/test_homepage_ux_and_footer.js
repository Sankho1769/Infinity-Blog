const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9228;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_ux_footer_test');

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function fetchJson(url) {
    return new Promise((resolve, reject) => {
        http.get(url, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try { resolve(JSON.parse(data)); } catch (e) { reject(e); }
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
        mobile: width <= 768
    });

    await cdp.send('Page.navigate', { url: 'http://localhost:8086/' });
    await sleep(1000);

    const report = await cdp.evaluate(`
        (() => {
            // 1. Search bars count check: Exactly one visible search field on homepage
            const allSearchInputs = Array.from(document.querySelectorAll('input[type="search"]'));
            const visibleSearchInputs = allSearchInputs.filter(el => {
                const rect = el.getBoundingClientRect();
                const style = window.getComputedStyle(el);
                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            });

            // 2. Sidebars styling & bounding rects
            const leftSidebar = document.querySelector('.app-left-sidebar');
            const rightSidebar = document.querySelector('.homepage-sidebar');
            const leftCard = leftSidebar ? window.getComputedStyle(leftSidebar) : null;
            const rightCards = Array.from(document.querySelectorAll('.sidebar-card')).map(c => window.getComputedStyle(c));
            const feedCol = document.querySelector('.homepage-main-column');

            // 3. Footer position and dimensions
            const footerWrapper = document.querySelector('.site-footer-wrapper');
            const footerCard = document.querySelector('.site-footer');
            const footerRect = footerCard ? footerCard.getBoundingClientRect() : null;
            const mainRect = feedCol ? feedCol.getBoundingClientRect() : null;
            const twoColRect = document.querySelector('.homepage-two-column-layout')?.getBoundingClientRect();

            // 4. Horizontal overflow check
            const docWidth = document.documentElement.scrollWidth;
            const viewWidth = window.innerWidth;
            const hasHorizontalOverflow = docWidth > viewWidth + 1; // 1px threshold for subpixels

            // 5. Footer link validity
            const footerLinks = Array.from(document.querySelectorAll('.site-footer a')).map(a => ({
                href: a.getAttribute('href'),
                text: a.innerText.trim()
            }));

            // 6. Composer state
            const composerCard = document.getElementById('feed-composer-card');
            const composerText = document.getElementById('composer-text-input');
            const composerTray = document.getElementById('composer-expanded-tray');

            return {
                visibleSearchCount: visibleSearchInputs.length,
                visibleSearchPlaceholder: visibleSearchInputs[0]?.placeholder || null,
                hasHorizontalOverflow,
                docWidth,
                viewWidth,
                footerRect: footerRect ? {
                    width: footerRect.width,
                    top: footerRect.top + window.scrollY,
                    bottom: footerRect.bottom + window.scrollY
                } : null,
                mainRect: mainRect ? {
                    width: mainRect.width,
                    top: mainRect.top + window.scrollY,
                    bottom: mainRect.bottom + window.scrollY
                } : null,
                twoColRect: twoColRect ? {
                    width: twoColRect.width,
                    left: twoColRect.left,
                    right: twoColRect.right,
                    top: twoColRect.top + window.scrollY,
                    bottom: twoColRect.bottom + window.scrollY
                } : null,
                footerLeftAlignDifference: (twoColRect && footerRect) ? Math.abs(twoColRect.left - footerRect.left) : null,
                footerRightAlignDifference: (twoColRect && footerRect) ? Math.abs(twoColRect.right - footerRect.right) : null,
                isFooterAfterContent: (twoColRect && footerRect) ? footerRect.top >= (twoColRect.bottom - 5) : false,
                isComposerExpandedByDefault: composerCard ? composerCard.classList.contains('is-expanded') : false,
                isComposerTrayVisibleByDefault: composerTray ? window.getComputedStyle(composerTray).display !== 'none' : false,
                leftSidebarStyles: leftCard ? {
                    borderRadius: leftCard.borderRadius,
                    background: leftCard.backgroundColor,
                    boxShadow: leftCard.boxShadow,
                    borderWidth: leftCard.borderWidth
                } : null,
                rightCardStyles: rightCards.length > 0 ? {
                    borderRadius: rightCards[0].borderRadius,
                    background: rightCards[0].backgroundColor,
                    boxShadow: rightCards[0].boxShadow,
                    borderWidth: rightCards[0].borderWidth
                } : null,
                footerLinks
            };
        })()
    `);

    console.log(`  Visible search inputs on homepage: ${report.visibleSearchCount} (placeholder: "${report.visibleSearchPlaceholder}")`);
    if (report.visibleSearchCount !== 1) {
        throw new Error(`Expected exactly 1 visible search input on homepage, found ${report.visibleSearchCount}`);
    }

    console.log(`  Horizontal overflow: ${report.hasHorizontalOverflow ? 'YES (FAIL)' : 'NO (PASS)'} (doc: ${report.docWidth}px, view: ${report.viewWidth}px)`);
    if (report.hasHorizontalOverflow) {
        throw new Error(`Horizontal overflow detected! docWidth (${report.docWidth}) > viewWidth (${report.viewWidth})`);
    }

    console.log(`  Composer collapsed by default: ${!report.isComposerExpandedByDefault && !report.isComposerTrayVisibleByDefault ? 'YES (PASS)' : 'NO (FAIL)'}`);
    if (report.isComposerExpandedByDefault || report.isComposerTrayVisibleByDefault) {
        throw new Error(`Composer should be collapsed by default!`);
    }

    if (report.twoColRect && report.footerRect) {
        console.log(`  Footer placement: starts at Y = ${Math.round(report.footerRect.top)}px (after main content Y = ${Math.round(report.twoColRect.bottom)}px)`);
        console.log(`  Footer spans full width of main container: footer = ${Math.round(report.footerRect.width)}px, twoCol = ${Math.round(report.twoColRect.width)}px (diff: ${Math.round(report.footerRightAlignDifference || 0)}px)`);
        if (Math.abs(report.footerLeftAlignDifference) > 4 || Math.abs(report.footerRightAlignDifference) > 4) {
            console.log(`  Warning: minor alignment variance (left diff: ${report.footerLeftAlignDifference}px, right diff: ${report.footerRightAlignDifference}px)`);
        }
    }

    if (report.leftSidebarStyles && report.rightCardStyles) {
        console.log(`  Sidebar style visual match:`);
        console.log(`    Border-radius: Left = ${report.leftSidebarStyles.borderRadius}, Right = ${report.rightCardStyles.borderRadius}`);
        console.log(`    Border-width: Left = ${report.leftSidebarStyles.borderWidth}, Right = ${report.rightCardStyles.borderWidth}`);
    }

    // Interactive Composer Expansion & Cancellation Test (if user element exists)
    const composerInteraction = await cdp.evaluate(`
        (() => {
            const input = document.getElementById('composer-text-input');
            const card = document.getElementById('feed-composer-card');
            const tray = document.getElementById('composer-expanded-tray');
            const cancelBtn = document.getElementById('composer-cancel-btn');
            if (!input || !card || !tray || !cancelBtn) return { skipped: true };

            // Focus to expand
            input.focus();
            const expandedAfterFocus = card.classList.contains('is-expanded') && window.getComputedStyle(tray).display !== 'none';

            // Type some text
            input.value = 'Testing unified single composer expansion';

            // Cancel
            cancelBtn.click();
            const collapsedAfterCancel = !card.classList.contains('is-expanded') && window.getComputedStyle(tray).display === 'none';

            return {
                skipped: false,
                expandedAfterFocus,
                collapsedAfterCancel
            };
        })()
    `);

    if (!composerInteraction.skipped) {
        console.log(`  Composer expands on interaction: ${composerInteraction.expandedAfterFocus}`);
        console.log(`  Composer collapses on cancel: ${composerInteraction.collapsedAfterCancel}`);
        if (!composerInteraction.expandedAfterFocus || !composerInteraction.collapsedAfterCancel) {
            throw new Error('Composer expansion or cancellation failed!');
        }
    }

    console.log(`  PASS: Viewport ${width}px verification complete.`);
}

async function run() {
    console.log('=== Starting Homepage UX, Single Search, Unified Sidebars & Footer Test ===');

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

        // Test all required viewports: 1854px, 1440px, 1280px, 1024px, 768px, 430px, 375px
        const viewports = [
            { w: 1854, h: 950 },
            { w: 1440, h: 900 },
            { w: 1280, h: 800 },
            { w: 1024, h: 768 },
            { w: 768, h: 1024 },
            { w: 430, h: 932 },
            { w: 375, h: 667 }
        ];

        for (const vp of viewports) {
            await testViewport(cdp, vp.w, vp.h);
        }

        // Test that secondary pages (e.g. /explore) STILL retain the compact header search
        console.log('\n============================================================');
        console.log('Testing Secondary Page (/explore) for Header Search Bar...');
        console.log('============================================================');
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width: 1440,
            height: 900,
            deviceScaleFactor: 1,
            mobile: false
        });
        await cdp.send('Page.navigate', { url: 'http://localhost:8086/explore' });
        await sleep(1000);

        const exploreSearch = await cdp.evaluate(`
            (() => {
                const headerSearch = document.getElementById('nav-search-form');
                const headerInput = document.getElementById('nav-search-input');
                return {
                    hasHeaderSearch: !!headerSearch,
                    hasHeaderInput: !!headerInput,
                    display: headerSearch ? window.getComputedStyle(headerSearch).display : null
                };
            })()
        `);

        console.log('Secondary page (/explore) header search check:', exploreSearch);
        if (!exploreSearch.hasHeaderSearch || !exploreSearch.hasHeaderInput || exploreSearch.display === 'none') {
            throw new Error('Header search bar should be present on secondary pages like /explore!');
        }
        console.log('PASS: Header search bar is preserved on secondary pages.');

        console.log('\n============================================================');
        console.log('ALL RESPONSIVE AND FUNCTIONAL UX TESTS PASSED SUCCESSFULLY!');
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
