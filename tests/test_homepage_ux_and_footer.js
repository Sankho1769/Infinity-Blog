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
            function getRect(el) {
                if (!el) return null;
                const b = el.getBoundingClientRect();
                return {
                    top: Math.round(b.top),
                    bottom: Math.round(b.bottom),
                    left: Math.round(b.left),
                    right: Math.round(b.right),
                    width: Math.round(b.width),
                    height: Math.round(b.height)
                };
            }

            // 1. Search bars count check: Exactly one visible search field on homepage
            const allSearchInputs = Array.from(document.querySelectorAll('input[type="search"]'));
            const visibleSearchInputs = allSearchInputs.filter(el => {
                const rect = el.getBoundingClientRect();
                const style = window.getComputedStyle(el);
                return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
            });

            // 2. Header metrics
            const header = document.querySelector('.site-header');
            const headerInner = document.querySelector('.site-header-inner');
            const headerCS = header ? window.getComputedStyle(header) : null;
            const innerCS = headerInner ? window.getComputedStyle(headerInner) : null;
            const mobileToggle = document.querySelector('.mobile-nav-toggle');

            // 3. Sidebars & content
            const leftSidebar = document.querySelector('.app-left-sidebar');
            const rightSidebar = document.querySelector('.homepage-sidebar');
            const leftCS = leftSidebar ? window.getComputedStyle(leftSidebar) : null;
            const rightCards = Array.from(document.querySelectorAll('.sidebar-card')).map(c => window.getComputedStyle(c));
            const feedHeader = document.querySelector('.feed-header-section');

            // 4. Footer metrics
            const footerWrapper = document.querySelector('.site-footer-wrapper');
            const footerCard = document.querySelector('.site-footer');
            const footerCS = footerCard ? window.getComputedStyle(footerCard) : null;
            const footerWrapperCS = footerWrapper ? window.getComputedStyle(footerWrapper) : null;

            // 5. Overflow check
            const docWidth = document.documentElement.scrollWidth;
            const viewWidth = window.innerWidth;
            const hasHorizontalOverflow = docWidth > viewWidth + 1;

            // 6. Composer state
            const composerCard = document.getElementById('feed-composer-card');
            const composerTray = document.getElementById('composer-expanded-tray');

            return {
                viewWidth,
                docWidth,
                hasHorizontalOverflow,
                visibleSearchCount: visibleSearchInputs.length,
                visibleSearchPlaceholder: visibleSearchInputs[0]?.placeholder || null,
                header: {
                    rect: getRect(header),
                    innerRect: getRect(headerInner),
                    paddingTop: innerCS ? parseFloat(innerCS.paddingTop) : 0,
                    paddingBottom: innerCS ? parseFloat(innerCS.paddingBottom) : 0,
                    paddingLeft: innerCS ? parseFloat(innerCS.paddingLeft) : 0,
                    paddingRight: innerCS ? parseFloat(innerCS.paddingRight) : 0,
                    height: innerCS ? parseFloat(innerCS.height) : 0,
                    position: headerCS ? headerCS.position : null,
                    marginBottom: headerCS ? parseFloat(headerCS.marginBottom) : 0
                },
                feedHeader: {
                    rect: getRect(feedHeader)
                },
                leftSidebar: {
                    rect: getRect(leftSidebar),
                    display: leftCS ? leftCS.display : 'none',
                    borderRadius: leftCS ? leftCS.borderRadius : null
                },
                rightSidebar: {
                    rect: getRect(rightSidebar),
                    display: rightSidebar ? window.getComputedStyle(rightSidebar).display : 'none'
                },
                footer: {
                    wrapperRect: getRect(footerWrapper),
                    cardRect: getRect(footerCard),
                    cardPaddingLeft: footerCS ? parseFloat(footerCS.paddingLeft) : 0,
                    cardPaddingRight: footerCS ? parseFloat(footerCS.paddingRight) : 0,
                    borderRadius: footerCS ? footerCS.borderRadius : null
                },
                isComposerExpandedByDefault: composerCard ? composerCard.classList.contains('is-expanded') : false,
                isComposerTrayVisibleByDefault: composerTray ? window.getComputedStyle(composerTray).display !== 'none' : false,
                rightCardStyles: rightCards.length > 0 ? {
                    borderRadius: rightCards[0].borderRadius
                } : null
            };
        })()
    `);

    // Verification 1: Exactly 1 search input on homepage
    console.log(`  Visible search inputs on homepage: ${report.visibleSearchCount} (placeholder: "${report.visibleSearchPlaceholder}")`);
    if (report.visibleSearchCount !== 1) {
        throw new Error(`Expected exactly 1 visible search input on homepage, found ${report.visibleSearchCount}`);
    }

    // Verification 2: Zero horizontal overflow
    console.log(`  Horizontal overflow: ${report.hasHorizontalOverflow ? 'YES (FAIL)' : 'NO (PASS)'} (doc: ${report.docWidth}px, view: ${report.viewWidth}px)`);
    if (report.hasHorizontalOverflow) {
        throw new Error(`Horizontal overflow detected! docWidth (${report.docWidth}) > viewWidth (${report.viewWidth})`);
    }

    // Verification 3: Top bar removal check
    console.log(`  Top bar removed check: header is ${report.header.rect ? 'PRESENT (FAIL)' : 'REMOVED (PASS)'}`);
    if (report.header.rect !== null) {
        throw new Error(`Top bar (.site-header) must be removed! Found element in DOM.`);
    }

    // Verification 4: Clean alignment at top of viewport
    if (report.feedHeader.rect && width >= 1025) {
        console.log(`  Feed starts at Y = ${report.feedHeader.rect.top}px (target 16px)`);
        if (report.feedHeader.rect.top !== 16) {
            throw new Error(`Feed does not start at top 16px! Found top = ${report.feedHeader.rect.top}px`);
        }
    }

    // Verification 5: Truly Full-Width Footer
    const footerCard = report.footer.cardRect;
    if (footerCard) {
        const leftSpace = footerCard.left;
        const rightSpace = report.viewWidth - footerCard.right;
        console.log(`  Footer width: ${footerCard.width}px across ${report.viewWidth}px viewport (outer margins: left = ${leftSpace}px, right = ${rightSpace}px)`);

        // Check footer left edge starts near left edge of viewport (e.g. 10-24px)
        if (leftSpace > 28) {
            throw new Error(`Footer is not full width! Left margin ${leftSpace}px indicates footer is still trapped in a sub-column!`);
        }
        if (rightSpace > 35) {
            throw new Error(`Footer is not full width! Right margin ${rightSpace}px is too large!`);
        }

        // On desktop, verify footer spans beneath the left navigation sidebar region
        if (width >= 1025 && report.leftSidebar.rect) {
            console.log(`  Left sidebar region: x = ${report.leftSidebar.rect.left}px to ${report.leftSidebar.rect.right}px; Footer spans: x = ${footerCard.left}px to ${footerCard.right}px`);
            if (footerCard.left > report.leftSidebar.rect.left + 8) {
                throw new Error(`Footer does not span underneath the left navigation region!`);
            }
        }
    }

    // Verification 6: Scroll to bottom - Sidebars must NOT overlap footer
    await cdp.evaluate('window.scrollTo(0, 10000)');
    await sleep(500);

    const bottomScrollReport = await cdp.evaluate(`
        (() => {
            function getRect(el) {
                if (!el) return null;
                const b = el.getBoundingClientRect();
                return {
                    top: Math.round(b.top),
                    bottom: Math.round(b.bottom),
                    left: Math.round(b.left),
                    right: Math.round(b.right),
                    width: Math.round(b.width),
                    height: Math.round(b.height)
                };
            }
            const leftSidebar = document.querySelector('.app-left-sidebar');
            const rightSidebar = document.querySelector('.homepage-sidebar');
            const footerCard = document.querySelector('.site-footer');
            return {
                scrollY: window.scrollY,
                leftSidebar: getRect(leftSidebar),
                rightSidebar: getRect(rightSidebar),
                footer: getRect(footerCard)
            };
        })()
    `);

    if (bottomScrollReport.footer) {
        if (width >= 1025 && bottomScrollReport.leftSidebar) {
            const leftOverlap = bottomScrollReport.leftSidebar.bottom > bottomScrollReport.footer.top;
            console.log(`  Left sidebar stop check at bottom scroll: sidebar bottom = ${bottomScrollReport.leftSidebar.bottom}px, footer top = ${bottomScrollReport.footer.top}px (overlap: ${leftOverlap ? 'YES (FAIL)' : 'NO (PASS)'})`);
            if (leftOverlap) {
                throw new Error(`Left sidebar overlaps the footer at page bottom! sidebar.bottom (${bottomScrollReport.leftSidebar.bottom}) > footer.top (${bottomScrollReport.footer.top})`);
            }
        }
        if (width >= 1025 && bottomScrollReport.rightSidebar) {
            const rightOverlap = bottomScrollReport.rightSidebar.bottom > bottomScrollReport.footer.top;
            console.log(`  Right sidebar stop check at bottom scroll: sidebar bottom = ${bottomScrollReport.rightSidebar.bottom}px, footer top = ${bottomScrollReport.footer.top}px (overlap: ${rightOverlap ? 'YES (FAIL)' : 'NO (PASS)'})`);
            if (rightOverlap) {
                throw new Error(`Right sidebar overlaps the footer at page bottom! sidebar.bottom (${bottomScrollReport.rightSidebar.bottom}) > footer.top (${bottomScrollReport.footer.top})`);
            }
        }
    }

    console.log(`  PASS: Viewport ${width}px verification complete.`);
}

async function run() {
    console.log('=== Starting Full-Width Footer & Compact Header Multi-Viewport Suite ===');

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

        const exploreCheck = await cdp.evaluate(`
            (() => {
                const header = document.querySelector('.site-header');
                const docWidth = document.documentElement.scrollWidth;
                const viewWidth = window.innerWidth;
                return {
                    hasHeader: !!header,
                    hasHOverflow: docWidth > viewWidth + 1
                };
            })()
        `);

        console.log('Secondary page (/explore) top bar removed check:', exploreCheck);
        if (exploreCheck.hasHeader) {
            throw new Error('Top bar should be removed site-wide, including on secondary pages like /explore!');
        }
        if (exploreCheck.hasHOverflow) {
            throw new Error('Horizontal overflow detected on secondary page!');
        }
        console.log('PASS: Top bar is cleanly removed site-wide, including on secondary pages.');

        console.log('\n============================================================');
        console.log('ALL FULL-WIDTH FOOTER AND COMPACT HEADER TESTS PASSED!');
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
