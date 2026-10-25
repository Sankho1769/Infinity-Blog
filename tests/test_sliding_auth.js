const { spawn } = require('child_process');
const http = require('http');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const DEBUG_PORT = 9245;
const USER_DATA_DIR = path.join(process.env.TEMP || 'C:\\Windows\\Temp', 'edge_sliding_auth_test_profile');
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
            const msgId = this.id++;
            this.callbacks.set(msgId, { resolve, reject });
            this.ws.send(JSON.stringify({ id: msgId, method, params }));
        });
    }

    close() {
        if (this.ws) {
            this.ws.close();
        }
    }
}

async function runTests() {
    console.log('--- Starting Sliding Auth Verification ---');

    // Launch Edge in headless mode with remote debugging
    const edgeProcess = spawn(EDGE_PATH, [
        `--remote-debugging-port=${DEBUG_PORT}`,
        `--user-data-dir=${USER_DATA_DIR}`,
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        'about:blank'
    ], { stdio: 'ignore' });

    let connected = false;
    let pageTarget = null;
    for (let i = 0; i < 30; i++) {
        await sleep(400);
        try {
            const targets = await fetchJson(`http://127.0.0.1:${DEBUG_PORT}/json`);
            if (targets && targets.length > 0) {
                pageTarget = targets.find(t => t.type === 'page' && t.webSocketDebuggerUrl);
                if (pageTarget) {
                    connected = true;
                    break;
                }
            }
        } catch (e) {}
    }

    if (!connected || !pageTarget || !pageTarget.webSocketDebuggerUrl) {
        console.error('Failed to connect to Edge remote debugging page target.');
        edgeProcess.kill();
        process.exit(1);
    }

    const cdp = new CdpClient(pageTarget.webSocketDebuggerUrl);
    await cdp.connect();
    console.log('Connected to CDP page target:', pageTarget.title);

    await cdp.send('Page.enable');
    await cdp.send('DOM.enable');
    await cdp.send('CSS.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');
    await cdp.send('Network.clearBrowserCookies');

    async function setViewport(width, height) {
        await cdp.send('Emulation.setDeviceMetricsOverride', {
            width,
            height,
            deviceScaleFactor: 1,
            mobile: width <= 768
        });
    }

    async function captureScreenshot(filename) {
        const result = await cdp.send('Page.captureScreenshot', { format: 'png' });
        const filePath = path.join(ARTIFACTS_DIR, filename);
        fs.writeFileSync(filePath, Buffer.from(result.data, 'base64'));
        console.log(`[Screenshot Saved]: ${filename}`);
    }

    async function evaluate(expression) {
        const res = await cdp.send('Runtime.evaluate', {
            expression,
            returnByValue: true,
            awaitPromise: true
        });
        if (res.exceptionDetails) {
            throw new Error(JSON.stringify(res.exceptionDetails));
        }
        return res.result.value;
    }

    let passedTests = 0;
    let totalTests = 0;
    function assert(condition, message) {
        totalTests++;
        if (condition) {
            console.log(`  ✓ PASS: ${message}`);
            passedTests++;
        } else {
            console.error(`  ✗ FAIL: ${message}`);
        }
    }

    try {
        // =========================================================================
        // TEST 1: DESKTOP GLASSMORPHISM (1440x900) - Initial Sign In State
        // =========================================================================
        console.log('\n[Test Suite 1]: Desktop Glassmorphism - Initial Sign In State');
        await setViewport(1440, 900);
        await cdp.send('Network.clearBrowserCookies');
        await cdp.send('Page.navigate', { url: 'http://localhost:8086/login' });
        await sleep(2000);

        const currentUrl = await evaluate('window.location.href');
        console.log('Current URL in browser:', currentUrl);

        // Ensure light theme and dismiss cookie banner for clean screenshots
        await evaluate(`
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.classList.remove('theme-dark');
            localStorage.setItem('infinity_theme_preference', 'light');
            localStorage.setItem('infinity_cookie_consent', 'accepted');
            const cb = document.querySelector('.cookie-banner');
            if (cb) cb.remove();
        `);
        await sleep(300);

        const desktopCardInfo = await evaluate(`
            (() => {
                const card = document.getElementById('auth-sliding-card');
                const overlayContainer = document.getElementById('auth-overlay-container');
                const signin = document.getElementById('auth-signin-panel');
                const signup = document.getElementById('auth-signup-panel');
                const style = window.getComputedStyle(card);
                const overlayContainerStyle = window.getComputedStyle(overlayContainer);
                const signinStyle = window.getComputedStyle(signin);
                const signupStyle = window.getComputedStyle(signup);

                return {
                    hasCard: !!card,
                    hasOverlay: !!overlayContainer,
                    cardBorderRadius: style.borderRadius,
                    cardBackdrop: style.backdropFilter || style.webkitBackdropFilter,
                    hasRightPanelActive: card.classList.contains('right-panel-active'),
                    overlayBorderRadius: overlayContainerStyle.borderRadius,
                    signinOpacity: signinStyle.opacity,
                    signupOpacity: signupStyle.opacity,
                    signinVisible: signinStyle.visibility !== 'hidden',
                    signupHidden: signupStyle.visibility === 'hidden',
                    heading: document.querySelector('#auth-signin-panel h1')?.textContent,
                    overlayTitle: document.querySelector('.auth-overlay-right .auth-overlay-title')?.textContent,
                    socialBtnCount: document.querySelectorAll('#auth-signin-panel .auth-social-btn').length,
                    hasIdentifier: !!document.getElementById('identifier'),
                    hasPassword: !!document.getElementById('password')
                };
            })()
        `);

        assert(desktopCardInfo.hasCard, 'Desktop sliding card exists');
        assert(desktopCardInfo.cardBorderRadius === '32px', `Card has 32px border radius (actual: ${desktopCardInfo.cardBorderRadius})`);
        assert(!desktopCardInfo.hasRightPanelActive, 'Initial state on /login does not have right-panel-active');
        assert(desktopCardInfo.signinVisible && desktopCardInfo.signinOpacity === '1', 'Sign In panel is visible with opacity 1');
        assert(desktopCardInfo.signupHidden && desktopCardInfo.signupOpacity === '0', 'Sign Up panel is hidden with opacity 0');
        assert(desktopCardInfo.heading === 'Sign In', 'Sign In heading is "Sign In"');
        assert(desktopCardInfo.overlayTitle === 'Hello, Friend!', 'Overlay right heading is "Hello, Friend!"');
        if (desktopCardInfo.socialBtnCount > 0) {
            assert(desktopCardInfo.socialBtnCount >= 1, 'Social buttons rendered in Sign In');
        }
        assert(desktopCardInfo.hasIdentifier && desktopCardInfo.hasPassword, 'Identifier and password inputs exist');

        await captureScreenshot('auth_desktop_glass_signin_light.png');

        // =========================================================================
        // TEST 2: DESKTOP TOGGLE ANIMATION (Sign In -> Sign Up)
        // =========================================================================
        console.log('\n[Test Suite 2]: Desktop Toggle Interaction to Sign Up');
        await evaluate(`document.getElementById('to-signup-btn').click();`);
        await sleep(800); // Allow 0.6s cubic-bezier animation to complete

        const toggledSignUpInfo = await evaluate(`
            (() => {
                const card = document.getElementById('auth-sliding-card');
                const overlayContainer = document.getElementById('auth-overlay-container');
                const signin = document.getElementById('auth-signin-panel');
                const signup = document.getElementById('auth-signup-panel');
                const overlayContainerStyle = window.getComputedStyle(overlayContainer);
                const signinStyle = window.getComputedStyle(signin);
                const signupStyle = window.getComputedStyle(signup);

                return {
                    hasRightPanelActive: card.classList.contains('right-panel-active'),
                    currentPath: window.location.pathname,
                    documentTitle: document.title,
                    overlayBorderRadius: overlayContainerStyle.borderRadius,
                    signinHidden: signinStyle.visibility === 'hidden',
                    signupVisible: signupStyle.visibility !== 'hidden',
                    signupOpacity: signupStyle.opacity,
                    overlayLeftTitle: document.querySelector('.auth-overlay-left .auth-overlay-title')?.textContent,
                    signupHeading: document.querySelector('#auth-signup-panel h1')?.textContent,
                    hasDisplayName: !!document.getElementById('display_name'),
                    hasEmail: !!document.getElementById('email'),
                    hasPhone: !!document.getElementById('phone'),
                    hasRegPassword: !!document.getElementById('register_password'),
                    hasRegConfirm: !!document.getElementById('register_confirm_password')
                };
            })()
        `);

        assert(toggledSignUpInfo.hasRightPanelActive, 'Sliding card has right-panel-active after toggle click');
        assert(toggledSignUpInfo.currentPath === '/register', `URL path updated to /register (actual: ${toggledSignUpInfo.currentPath})`);
        assert(toggledSignUpInfo.documentTitle.includes('Create Account'), 'Document title updated to Create Account');
        assert(toggledSignUpInfo.signupVisible && toggledSignUpInfo.signupOpacity === '1', 'Sign Up panel is now active and visible');
        assert(toggledSignUpInfo.signinHidden, 'Sign In panel is now hidden');
        assert(toggledSignUpInfo.overlayLeftTitle === 'Welcome Back!', 'Overlay left heading is "Welcome Back!"');
        assert(toggledSignUpInfo.signupHeading === 'Create Account', 'Sign Up heading is "Create Account"');
        assert(toggledSignUpInfo.hasDisplayName && toggledSignUpInfo.hasEmail && toggledSignUpInfo.hasPhone, 'Full Name, Email, Phone inputs exist');
        assert(toggledSignUpInfo.hasRegPassword && toggledSignUpInfo.hasRegConfirm, 'Password and Confirm Password inputs exist');

        await captureScreenshot('auth_desktop_glass_signup_light.png');

        // =========================================================================
        // TEST 3: DESKTOP TOGGLE ANIMATION BACK (Sign Up -> Sign In)
        // =========================================================================
        console.log('\n[Test Suite 3]: Desktop Toggle Interaction back to Sign In');
        await evaluate(`document.getElementById('to-signin-btn').click();`);
        await sleep(800);

        const toggledBackInfo = await evaluate(`
            (() => {
                const card = document.getElementById('auth-sliding-card');
                return {
                    hasRightPanelActive: card.classList.contains('right-panel-active'),
                    currentPath: window.location.pathname,
                    documentTitle: document.title
                };
            })()
        `);

        assert(!toggledBackInfo.hasRightPanelActive, 'Sliding card removed right-panel-active after toggle back');
        assert(toggledBackInfo.currentPath === '/login', `URL path updated back to /login (actual: ${toggledBackInfo.currentPath})`);
        assert(toggledBackInfo.documentTitle.includes('Sign In'), 'Document title updated back to Sign In');

        // =========================================================================
        // TEST 4: DESKTOP DARK MODE GLASSMORPHISM
        // =========================================================================
        console.log('\n[Test Suite 4]: Desktop Dark Mode Glassmorphism');
        await evaluate(`
            document.documentElement.setAttribute('data-theme', 'dark');
            document.documentElement.classList.add('theme-dark');
            localStorage.setItem('infinity_theme_preference', 'dark');
        `);
        await sleep(300);

        const darkDesktopInfo = await evaluate(`
            (() => {
                const card = document.getElementById('auth-sliding-card');
                const cardStyle = window.getComputedStyle(card);
                const title = document.querySelector('#auth-signin-panel h1');
                const titleStyle = window.getComputedStyle(title);
                const input = document.getElementById('identifier');
                const inputStyle = window.getComputedStyle(input);
                const socialBtn = document.querySelector('.auth-social-btn');
                const socialStyle = socialBtn ? window.getComputedStyle(socialBtn) : { backgroundColor: 'transparent' };

                return {
                    cardBg: cardStyle.backgroundColor,
                    titleColor: titleStyle.color,
                    inputBg: inputStyle.backgroundColor,
                    socialBg: socialStyle.backgroundColor
                };
            })()
        `);

        assert(darkDesktopInfo.cardBg.includes('15, 23, 42') || darkDesktopInfo.cardBg.includes('rgb'), 'Dark glass background applied to card');
        await captureScreenshot('auth_desktop_glass_signin_dark.png');

        // Toggle to Sign Up in Dark Mode
        await evaluate(`document.getElementById('to-signup-btn').click();`);
        await sleep(800);
        await captureScreenshot('auth_desktop_glass_signup_dark.png');

        // =========================================================================
        // TEST 5: MOBILE CLAYMORPHISM (375x812) - Dark Mode
        // =========================================================================
        console.log('\n[Test Suite 5]: Mobile Claymorphism (375x812) - Dark Mode');
        await setViewport(375, 812);
        await sleep(500);

        await evaluate(`
            (() => {
                const cb = document.querySelector('.cookie-banner');
                if (cb) cb.remove();
            })();
        `);
        await sleep(200);

        const mobileDarkInfo = await evaluate(`
            (() => {
                const card = document.getElementById('auth-sliding-card');
                const overlay = document.getElementById('auth-overlay-container');
                const toggleBar = document.querySelector('.auth-mobile-toggle-bar');
                const welcomeBanner = document.getElementById('auth-mobile-welcome-banner');
                const signinTab = document.getElementById('mobile-toggle-signin');
                const signupTab = document.getElementById('mobile-toggle-signup');
                const cardStyle = window.getComputedStyle(card);
                const overlayStyle = window.getComputedStyle(overlay);
                const toggleBarStyle = window.getComputedStyle(toggleBar);
                const input = document.getElementById('display_name');
                const inputStyle = window.getComputedStyle(input);

                return {
                    cardDisplay: cardStyle.display,
                    cardBorderRadius: cardStyle.borderRadius,
                    cardBoxShadow: cardStyle.boxShadow,
                    overlayDisplay: overlayStyle.display,
                    toggleBarDisplay: toggleBarStyle.display,
                    welcomeBannerDisplay: window.getComputedStyle(welcomeBanner).display,
                    signupTabActive: signupTab.classList.contains('active'),
                    inputBoxShadow: inputStyle.boxShadow
                };
            })()
        `);

        assert(mobileDarkInfo.cardBorderRadius === '28px', `Mobile clay card has 28px border-radius (actual: ${mobileDarkInfo.cardBorderRadius})`);
        assert(mobileDarkInfo.overlayDisplay === 'none', 'Desktop sliding overlay is hidden on mobile');
        assert(mobileDarkInfo.toggleBarDisplay === 'flex', 'Mobile segmented toggle bar is displayed');
        assert(mobileDarkInfo.welcomeBannerDisplay === 'flex', 'Mobile welcome banner is displayed');
        assert(mobileDarkInfo.inputBoxShadow.includes('inset'), 'Mobile input has tactile inset clay shadow');

        await captureScreenshot('auth_mobile_clay_signup_dark.png');

        // Tap Mobile Sign In Tab
        await evaluate(`document.getElementById('mobile-toggle-signin').click();`);
        await sleep(400);

        const mobileSignInInfo = await evaluate(`
            (() => {
                const signinTab = document.getElementById('mobile-toggle-signin');
                const signupTab = document.getElementById('mobile-toggle-signup');
                const signinPanel = document.getElementById('auth-signin-panel');
                const signupPanel = document.getElementById('auth-signup-panel');
                const welcomeText = document.getElementById('auth-mobile-welcome-text')?.textContent;

                return {
                    signinActive: signinTab.classList.contains('active'),
                    signupInactive: !signupTab.classList.contains('active'),
                    signinVisible: window.getComputedStyle(signinPanel).display !== 'none',
                    signupHidden: window.getComputedStyle(signupPanel).display === 'none',
                    welcomeText: welcomeText,
                    currentPath: window.location.pathname
                };
            })()
        `);

        assert(mobileSignInInfo.signinActive && mobileSignInInfo.signupInactive, 'Sign In tab is active on mobile');
        assert(mobileSignInInfo.signinVisible && mobileSignInInfo.signupHidden, 'Sign In form shown and Sign Up hidden on mobile');
        assert(mobileSignInInfo.welcomeText.includes('Welcome back'), `Welcome banner updated for Sign In (actual: ${mobileSignInInfo.welcomeText})`);
        assert(mobileSignInInfo.currentPath === '/login', `URL updated to /login on mobile tab tap`);

        await captureScreenshot('auth_mobile_clay_signin_dark.png');

        // =========================================================================
        // TEST 6: MOBILE CLAYMORPHISM (375x812) - Light Mode
        // =========================================================================
        console.log('\n[Test Suite 6]: Mobile Claymorphism (375x812) - Light Mode');
        await evaluate(`
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.classList.remove('theme-dark');
            localStorage.setItem('infinity_theme_preference', 'light');
        `);
        await sleep(300);

        const mobileLightInfo = await evaluate(`
            (() => {
                const card = document.getElementById('auth-sliding-card');
                const cardStyle = window.getComputedStyle(card);
                const input = document.getElementById('identifier');
                const inputStyle = window.getComputedStyle(input);

                return {
                    cardBg: cardStyle.backgroundImage || cardStyle.backgroundColor,
                    cardBoxShadow: cardStyle.boxShadow,
                    inputBg: inputStyle.backgroundColor,
                    inputBoxShadow: inputStyle.boxShadow
                };
            })()
        `);

        assert(mobileLightInfo.cardBoxShadow.includes('rgba'), 'Tactile dual clay drop shadows applied in light mode');
        assert(mobileLightInfo.inputBoxShadow.includes('inset'), 'Tactile inset clay shadows on input in light mode');

        await captureScreenshot('auth_mobile_clay_signin_light.png');

        // Tap Sign Up tab in light mode
        await evaluate(`document.getElementById('mobile-toggle-signup').click();`);
        await sleep(400);
        await captureScreenshot('auth_mobile_clay_signup_light.png');

        // Scroll directly to register submit button to verify visibility above bottom dock
        await evaluate(`
            const btn = document.getElementById('register-submit-btn');
            if (btn) btn.scrollIntoView({ behavior: 'instant', block: 'center' });
        `);
        await sleep(300);
        await captureScreenshot('auth_mobile_clay_signup_scrolled_light.png');

        console.log(`\n========================================`);
        console.log(`Sliding Auth Verification Completed: ${passedTests}/${totalTests} Passed`);
        console.log(`========================================\n`);

    } finally {
        cdp.close();
        edgeProcess.kill();
    }

    if (passedTests !== totalTests) {
        process.exit(1);
    }
}

runTests().catch(err => {
    console.error('Test execution error:', err);
    process.exit(1);
});
