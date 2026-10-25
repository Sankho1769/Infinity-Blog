const { spawn, execSync } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9255;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_ebook_test_profile_' + Date.now());
const ARTIFACTS_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';
const BASE_URL = 'http://127.0.0.1:8086';

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
            const WebSocket = global.WebSocket || require('ws');
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

    async navigate(url) {
        await this.send('Page.navigate', { url });
        await sleep(1500);
    }

    async screenshot(filePath) {
        const res = await this.send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(filePath, Buffer.from(res.data, 'base64'));
        console.log(`Saved screenshot: ${path.basename(filePath)}`);
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

async function main() {
    console.log('--- Launching Edge Browser for eBook Library Visual Testing ---');
    const edgeProcess = spawn(EDGE_PATH, [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--no-first-run',
        '--no-default-browser-check',
        '--headless=new',
        '--disable-gpu',
        '--window-size=1536,960'
    ]);

    await sleep(2500);

    let versionData;
    for (let i = 0; i < 15; i++) {
        try {
            versionData = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json/version`);
            if (versionData && versionData.webSocketDebuggerUrl) break;
        } catch (e) {
            await sleep(500);
        }
    }

    if (!versionData || !versionData.webSocketDebuggerUrl) {
        console.error('Failed to connect to Edge debug port.');
        edgeProcess.kill();
        process.exit(1);
    }

    const browserClient = new CdpClient(versionData.webSocketDebuggerUrl);
    await browserClient.connect();

    // Create a new target / page
    const target = await browserClient.send('Target.createTarget', { url: 'about:blank' });
    const targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json/list`);
    const pageTarget = targets.find(t => t.id === target.targetId);

    const client = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await client.connect();
    await client.send('Page.enable');
    await client.send('DOM.enable');
    await client.send('Runtime.enable');

    console.log('Connected to CDP page target.');

    try {
        // ========================================================
        // 1. eBook Library Catalogue — Multi-Viewport Testing
        // ========================================================
        console.log('\n[1/6] Testing eBook Catalogue (/ebooks)...');
        await client.navigate(`${BASE_URL}/ebooks`);

        // Check essential elements
        const titleText = await client.evaluate(`document.querySelector('h1')?.innerText`);
        console.log(`Catalogue H1: "${titleText}"`);
        const bookCount = await client.evaluate(`document.querySelectorAll('.ebook-card').length`);
        console.log(`Rendered eBook cards: ${bookCount}`);

        // Viewport 1: 1536px Desktop (Light)
        await client.setViewport(1536, 960);
        await sleep(500);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_library_desktop_1536_light.png'));

        // Viewport 2: 1536px Desktop (Dark)
        await client.evaluate(`document.documentElement.setAttribute('data-theme', 'dark')`);
        await sleep(400);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_library_desktop_1536_dark.png'));
        await client.evaluate(`document.documentElement.setAttribute('data-theme', 'light')`);

        // Viewport 3: 1280px Laptop
        await client.setViewport(1280, 800);
        await sleep(400);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_library_laptop_1280_light.png'));

        // Viewport 4: 1024px Tablet
        await client.setViewport(1024, 768);
        await sleep(400);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_library_tablet_1024_light.png'));

        // Viewport 5: 768px Tablet Portrait
        await client.setViewport(768, 1024);
        await sleep(400);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_library_tablet_768_light.png'));

        // Viewport 6: 430px Mobile (Light)
        await client.setViewport(430, 932);
        await sleep(400);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_library_mobile_430_light.png'));

        // Viewport 7: 375px Mobile (Dark)
        await client.setViewport(375, 667);
        await client.evaluate(`document.documentElement.setAttribute('data-theme', 'dark')`);
        await sleep(400);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_library_mobile_375_dark.png'));
        await client.evaluate(`document.documentElement.setAttribute('data-theme', 'light')`);

        // ========================================================
        // 2. eBook Detail View (/ebooks/1)
        // ========================================================
        console.log('\n[2/6] Testing eBook Detail View (/ebooks/1)...');
        await client.setViewport(1536, 960);
        await client.navigate(`${BASE_URL}/ebooks/1`);
        const detailTitle = await client.evaluate(`document.querySelector('h1')?.innerText`);
        console.log(`Detail Page Title: "${detailTitle}"`);
        const uploaderText = await client.evaluate(`document.querySelector('.ebook-detail-page')?.innerText.includes('Shared by')`);
        console.log(`Uploader Card present: ${uploaderText}`);

        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_detail_desktop_light.png'));

        // Check report modal opening
        await client.evaluate(`openReportModal()`);
        await sleep(300);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_detail_report_modal.png'));
        await client.evaluate(`closeReportModal()`);

        // ========================================================
        // 3. In-Browser PDF Reader (/ebooks/1/read)
        // ========================================================
        console.log('\n[3/6] Testing In-Browser PDF Reader (/ebooks/1/read)...');
        await client.navigate(`${BASE_URL}/ebooks/1/read`);
        await sleep(2500); // Allow PDF.js to render page onto canvas

        const canvasVisible = await client.evaluate(`document.getElementById('pdfCanvas')?.style.display !== 'none'`);
        console.log(`PDF Canvas rendered and visible: ${canvasVisible}`);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_reader_pdf_desktop.png'));

        // Switch to Sepia mode
        await client.evaluate(`document.documentElement.setAttribute('data-reader-theme', 'sepia')`);
        await sleep(300);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_reader_pdf_sepia.png'));

        // Switch to Dark mode
        await client.evaluate(`document.documentElement.setAttribute('data-reader-theme', 'dark')`);
        await sleep(300);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_reader_pdf_dark.png'));

        // ========================================================
        // 4. In-Browser EPUB Reader (/ebooks/2/read)
        // ========================================================
        console.log('\n[4/6] Testing In-Browser EPUB Reader (/ebooks/2/read)...');
        await client.navigate(`${BASE_URL}/ebooks/2/read`);
        await sleep(2000); // Allow chapter to fetch and render

        const epubRendered = await client.evaluate(`document.getElementById('epubContentArea')?.innerText.length > 50`);
        console.log(`EPUB chapter HTML loaded: ${epubRendered}`);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_reader_epub_desktop.png'));

        // Open Table of Contents drawer
        await client.evaluate(`document.getElementById('tocDrawer')?.classList.add('open')`);
        await sleep(300);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_reader_epub_toc_drawer.png'));
        await client.evaluate(`document.getElementById('tocDrawer')?.classList.remove('open')`);

        // ========================================================
        // 5. eBook Upload Form (/ebooks/upload)
        // ========================================================
        console.log('\n[5/6] Testing Upload Form (/ebooks/upload)...');
        // Authenticate using SessionManager via test session script
        const sessionId = execSync('C:\\xampp\\php\\php.exe tests/create_test_session.php').toString().trim();
        console.log(`Generated authenticated session: ${sessionId}`);
        await client.send('Network.enable');
        await client.send('Network.setCookie', {
            name: 'INFINITYBLOGSESSID',
            value: sessionId,
            domain: '127.0.0.1',
            path: '/'
        });

        await client.navigate(`${BASE_URL}/ebooks/upload`);
        await sleep(1200);
        const formHeading = await client.evaluate(`document.querySelector('h1')?.innerText`);
        console.log(`Upload Page Heading: "${formHeading}"`);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_upload_form_desktop.png'));

        // ========================================================
        // 6. Personal Reading Library — Saved eBooks (/library?tab=ebooks)
        // ========================================================
        console.log('\n[6/6] Testing Personal Library Saved eBooks tab (/library?tab=ebooks)...');
        // Ensure eBooks 1 and 2 are saved for the authenticated user
        execSync('C:\\xampp\\php\\php.exe -r "require \'autoload.php\'; $cfg = \\InfinityBlog\\Config\\AppConfig::fromEnvironment(); $pdo = \\InfinityBlog\\Database\\ConnectionFactory::create($cfg->database); $repo = new \\InfinityBlog\\Ebooks\\EbookRepository($pdo); if (!$repo->isBookSaved(2, 1)) { $repo->saveBook(2, 1); } if (!$repo->isBookSaved(2, 2)) { $repo->saveBook(2, 2); }"');
        await sleep(300);

        await client.navigate(`${BASE_URL}/library?tab=ebooks`);
        await sleep(1200);
        const savedTabHeader = await client.evaluate(`document.querySelector('.library-header h1, h1')?.innerText`);
        console.log(`Library Tab Header: "${savedTabHeader}"`);
        const savedEbookCards = await client.evaluate(`document.querySelectorAll('.saved-ebook-card, .ebook-card, [data-ebook-id]').length`);
        console.log(`Saved eBook cards found: ${savedEbookCards}`);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_personal_library_saved_tab.png'));

        console.log('\n=== ALL BROWSER CDP TESTS & SCREENSHOTS COMPLETED SUCCESSFULLY ===');
    } catch (err) {
        console.error('Error during CDP testing:', err);
    } finally {
        client.close();
        browserClient.close();
        edgeProcess.kill();
        try { fs.rmSync(USER_DATA_DIR, { recursive: true, force: true }); } catch (e) {}
    }
}

main().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
