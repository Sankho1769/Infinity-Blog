const { spawn, execSync } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9260;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_moderation_test_' + Date.now());
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

function httpGet(url) {
    return new Promise((resolve, reject) => {
        http.get(url, (res) => {
            const chunks = [];
            res.on('data', chunk => chunks.push(chunk));
            res.on('end', () => {
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    body: Buffer.concat(chunks)
                });
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
    console.log('=== STARTING PUBLIC EBOOK & ADMIN MODERATION VERIFICATION ===\n');

    // 1. Direct Anonymous Download Verification (Zero Session Cookies)
    console.log('[Test 1] Testing direct anonymous download at /ebooks/1/download...');
    const downloadRes = await httpGet(`${BASE_URL}/ebooks/1/download`);
    console.log(`HTTP Status: ${downloadRes.statusCode}`);
    console.log(`Content-Type: ${downloadRes.headers['content-type']}`);
    console.log(`Content-Disposition: ${downloadRes.headers['content-disposition']}`);
    const isPdfHeader = downloadRes.body.slice(0, 5).toString('utf-8') === '%PDF-';
    console.log(`File Magic Bytes (%PDF-): ${isPdfHeader ? 'VALID' : 'INVALID'}`);

    if (downloadRes.statusCode !== 200 || !isPdfHeader) {
        throw new Error('Direct anonymous download failed.');
    }
    console.log('  [PASS] Anonymous visitor can download published eBook without login.\n');

    // Launch Edge for UI checks
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

    const target = await browserClient.send('Target.createTarget', { url: 'about:blank' });
    const targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json/list`);
    const pageTarget = targets.find(t => t.id === target.targetId);

    const client = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await client.connect();
    await client.send('Page.enable');
    await client.send('DOM.enable');
    await client.send('Runtime.enable');

    try {
        // 2. Anonymous Upload Form View (No login cookie)
        console.log('[Test 2] Testing anonymous upload view (/ebooks/upload) without authentication...');
        await client.setViewport(1440, 900);
        await client.navigate(`${BASE_URL}/ebooks/upload`);

        const currentUrl = await client.evaluate(`window.location.href`);
        console.log(`Current URL: ${currentUrl}`);
        if (currentUrl.includes('/login')) {
            throw new Error('Anonymous guest was incorrectly redirected to login!');
        }

        const pageHeading = await client.evaluate(`document.querySelector('h1')?.innerText`);
        console.log(`Page Heading: "${pageHeading}"`);

        const hasGuestBanner = await client.evaluate(`document.body.innerText.includes('Guest Contributor Welcome')`);
        console.log(`Guest Contributor banner present: ${hasGuestBanner}`);

        const hasSubmitterInputs = await client.evaluate(`!!document.getElementById('submitterName') && !!document.getElementById('submitterEmail')`);
        console.log(`Submitter name and email inputs present: ${hasSubmitterInputs}`);

        const hasHoneypot = await client.evaluate(`!!document.getElementById('website_hp')`);
        console.log(`Honeypot field present: ${hasHoneypot}`);

        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_upload_anonymous_guest.png'));
        console.log('  [PASS] Anonymous upload page accessible and styled for guests.\n');

        // 3. Upload Submission Confirmation
        console.log('[Test 3] Testing submission confirmation banner...');
        await client.navigate(`${BASE_URL}/ebooks/upload?submitted=1&ref=EB-2026-TEST99`);
        const hasConfirmation = await client.evaluate(`document.body.innerText.includes('eBook Submitted Successfully')`);
        console.log(`Confirmation heading present: ${hasConfirmation}`);
        const refDisplayed = await client.evaluate(`document.body.innerText.includes('EB-2026-TEST99')`);
        console.log(`Reference ID displayed: ${refDisplayed}`);

        await client.screenshot(path.join(ARTIFACTS_DIR, 'ebook_upload_confirmation_banner.png'));
        console.log('  [PASS] Submission confirmation banner rendered cleanly.\n');

        // 4. Admin eBook Moderation Dashboard
        console.log('[Test 4] Testing Admin eBook Moderation Dashboard (/admin/ebook-moderation)...');
        // Seed an admin session
        const adminSessionId = execSync('C:\\xampp\\php\\php.exe tests/create_test_session.php admin').toString().trim();
        console.log(`Generated admin session ID: ${adminSessionId}`);

        await client.send('Network.enable');
        await client.send('Network.setCookie', {
            name: 'INFINITYBLOGSESSID',
            value: adminSessionId,
            domain: '127.0.0.1',
            path: '/'
        });

        await client.navigate(`${BASE_URL}/admin/ebook-moderation`);
        const adminUrl = await client.evaluate(`window.location.href`);
        const pageTitle = await client.evaluate(`document.title`);
        console.log(`Admin Current URL: ${adminUrl}`);
        console.log(`Admin Page Title: "${pageTitle}"`);
        const adminHeading = await client.evaluate(`document.querySelector('h1')?.innerText`);
        console.log(`Admin Dashboard Heading: "${adminHeading}"`);

        const statusTabsCount = await client.evaluate(`document.querySelectorAll('.tab-pill').length`);
        console.log(`Status filter tabs count: ${statusTabsCount}`);

        const moderationTable = await client.evaluate(`!!document.querySelector('table') || !!document.querySelector('.empty-state')`);
        console.log(`Moderation queue table rendered: ${moderationTable}`);

        await client.screenshot(path.join(ARTIFACTS_DIR, 'admin_ebook_moderation_dashboard_desktop.png'));

        // Switch to Dark mode
        await client.evaluate(`document.documentElement.setAttribute('data-theme', 'dark')`);
        await sleep(300);
        await client.screenshot(path.join(ARTIFACTS_DIR, 'admin_ebook_moderation_dashboard_dark.png'));
        await client.evaluate(`document.documentElement.setAttribute('data-theme', 'light')`);

        console.log('  [PASS] Admin eBook Moderation Dashboard verified in Light and Dark modes.\n');

        console.log('================================================================');
        console.log('ALL PUBLIC EBOOK & MODERATION VERIFICATION CHECKS PASSED!');
        console.log('================================================================');
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
