/**
 * Infinity Blog — Comprehensive End-to-End Verification:
 * 1. Part 1: Unexpected post image elimination & author-selected image display integrity.
 * 2. Part 2: Writing Studio controls: Indent, Outdent, Video Embed/Upload, Left Nav, Workspaces, Goal Modal, AI Panel, Focus Mode, Preview.
 */

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_studio_test_' + Date.now());
const PORT = 9245;
const ARTIFACT_DIR = 'C:\\Users\\hp\\.gemini\\antigravity\\brain\\e2b304a9-e46e-42e9-a1f1-af2a8020370b';

function createTestSession(userId) {
  const sessionId = require('crypto').randomBytes(16).toString('hex');
  const time = Math.floor(Date.now() / 1000);
  const csrf = require('crypto').randomBytes(32).toString('hex');
  const sessData = `auth|a:4:{s:7:"user_id";i:${userId};s:12:"auth_version";i:1;s:9:"issued_at";i:${time};s:13:"last_activity";i:${time};}csrf_token|s:64:"${csrf}";`;
  const sessPath = path.join('C:\\xampp\\tmp', 'sess_' + sessionId);
  fs.writeFileSync(sessPath, sessData);
  return sessionId;
}

let passed = 0;
let failed = 0;
function assert(cond, msg) {
  if (cond) {
    passed++;
    console.log(`  [PASS] ${msg}`);
  } else {
    failed++;
    console.error(`  [FAIL] ${msg}`);
  }
}

async function run() {
  console.log('=== STARTING WRITING STUDIO & IMAGE INTEGRITY E2E SUITE ===\n');

  const edge = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--remote-debugging-port=' + PORT,
    '--user-data-dir=' + TEMP_USER_DATA,
    'about:blank'
  ], { stdio: 'ignore' });

  await new Promise(r => setTimeout(r, 2000));

  const listRes = await fetch('http://127.0.0.1:' + PORT + '/json/list');
  const list = await listRes.json();
  const page = list.find(t => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise(r => ws.onopen = r);

  let msgId = 1;
  const pending = new Map();
  ws.onmessage = e => {
    const data = JSON.parse(e.data);
    if (data.id && pending.has(data.id)) {
      pending.get(data.id)(data.result);
      pending.delete(data.id);
    }
  };

  function send(method, params = {}) {
    return new Promise(resolve => {
      const id = msgId++;
      pending.set(id, resolve);
      ws.send(JSON.stringify({ id, method, params }));
    });
  }

  await send('Page.enable');
  await send('Runtime.enable');
  await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride', {
    width: 1440,
    height: 900,
    deviceScaleFactor: 1,
    mobile: false
  });

  async function navigate(url, wait = 1000) {
    await send('Page.navigate', { url });
    await new Promise(r => setTimeout(r, wait));
  }

  async function evaluate(expression) {
    const res = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    return res.result?.value;
  }

  async function takeScreenshot(filename) {
    const res = await send('Page.captureScreenshot', { format: 'png' });
    if (res?.data) {
      const buf = Buffer.from(res.data, 'base64');
      fs.writeFileSync(path.join(ARTIFACT_DIR, filename), buf);
      console.log(`  [SCREENSHOT] Saved ${filename}`);
    }
  }

  const sessionId = createTestSession(13); // User 13 Cristine Palmer
  await send('Network.setCookie', {
    name: 'INFINITYBLOGSESSID',
    value: sessionId,
    url: 'http://localhost:8086'
  });

  // ---------------------------------------------------------------------------
  // PART 1: Unexpected Image Elimination Verification
  // ---------------------------------------------------------------------------
  console.log('--- TEST 1: Post 64 ("Hello") on Homepage ---');
  await navigate('http://localhost:8086/', 1200);

  const homePost64Info = await evaluate(`(() => {
    const cards = document.querySelectorAll('.feed-card');
    for (const card of cards) {
      const link = card.querySelector('.feed-card-title a');
      if (link && link.textContent.trim().toLowerCase() === 'hello') {
        const img = card.querySelector('.feed-card-featured-img');
        const media = card.querySelector('.feed-card-featured-media');
        return {
          found: true,
          hasImg: !!img,
          imgSrc: img ? img.src : null,
          mediaDisplay: media ? window.getComputedStyle(media).display : 'none'
        };
      }
    }
    return { found: false };
  })()`);

  console.log('Home Post 64 info:', homePost64Info);
  assert(homePost64Info.found, 'Post "Hello" found on homepage');
  assert(!homePost64Info.hasImg || homePost64Info.mediaDisplay === 'none', 'Post "Hello" does NOT display unexpected thumbnail on homepage');
  await takeScreenshot('studio_part1_post64_homepage.png');

  console.log('\n--- TEST 2: Post 64 ("Hello") Article View ---');
  await navigate('http://localhost:8086/article/64', 1000);

  const articleImageInfo = await evaluate(`(() => {
    const banner = document.querySelector('.article-featured-banner');
    const img = document.querySelector('.article-featured-img');
    const title = document.querySelector('.article-title')?.textContent.trim();
    return {
      title,
      hasBanner: !!banner,
      bannerDisplay: banner ? window.getComputedStyle(banner).display : 'none',
      hasImg: !!img,
      imgSrc: img ? img.src : null
    };
  })()`);

  console.log('Article 64 info:', articleImageInfo);
  assert(!articleImageInfo.hasBanner || articleImageInfo.bannerDisplay === 'none', 'Article 64 has NO unexpected cover banner');
  assert(!articleImageInfo.hasImg, 'Article 64 has NO unexpected cover image');
  await takeScreenshot('studio_part1_post64_article.png');

  console.log('\n--- TEST 3: Post 64 ("Hello") Author Profile View ---');
  await navigate('http://localhost:8086/author/13', 1000);

  const authorProfileInfo = await evaluate(`(() => {
    const cards = document.querySelectorAll('.article-card');
    for (const card of cards) {
      const titleLink = card.querySelector('.article-card-title a');
      if (titleLink && titleLink.textContent.trim().toLowerCase() === 'hello') {
        const banner = card.querySelector('.article-card-banner');
        const img = card.querySelector('.article-banner-img');
        return {
          found: true,
          hasBanner: !!banner,
          bannerDisplay: banner ? window.getComputedStyle(banner).display : 'none',
          hasImg: !!img,
          imgSrc: img ? img.src : null
        };
      }
    }
    return { found: false };
  })()`);

  console.log('Author Profile Post 64 info:', authorProfileInfo);
  assert(authorProfileInfo.found, 'Post 64 found on author profile');
  assert(!authorProfileInfo.hasBanner || authorProfileInfo.bannerDisplay === 'none', 'Post 64 on author profile has NO thumbnail banner');
  await takeScreenshot('studio_part1_post64_author.png');

  console.log('\n--- TEST 4: Post 64 ("Hello") Explore View ---');
  await navigate('http://localhost:8086/explore', 1000);

  const exploreInfo = await evaluate(`(() => {
    const cards = document.querySelectorAll('.article-card');
    for (const card of cards) {
      const titleLink = card.querySelector('.article-card-title a');
      if (titleLink && titleLink.textContent.trim().toLowerCase() === 'hello') {
        const banner = card.querySelector('.article-card-banner');
        return {
          found: true,
          hasBanner: !!banner,
          bannerDisplay: banner ? window.getComputedStyle(banner).display : 'none'
        };
      }
    }
    return { found: false };
  })()`);

  console.log('Explore Post 64 info:', exploreInfo);
  if (exploreInfo.found) {
    assert(!exploreInfo.hasBanner || exploreInfo.bannerDisplay === 'none', 'Post 64 on explore has NO thumbnail banner');
  }

  // ---------------------------------------------------------------------------
  // PART 2: Writing Studio Full Controls Verification
  // ---------------------------------------------------------------------------
  console.log('\n--- TEST 5: Writing Studio Loading & Initial Layout ---');
  await navigate('http://localhost:8086/posts/new', 1500);

  const studioLoaded = await evaluate(`(() => {
    return {
      titleExists: !!document.getElementById('post-title'),
      contentExists: !!document.getElementById('post-content'),
      toolbarExists: !!document.getElementById('studio-formatting-toolbar'),
      leftSidebarExists: !!document.getElementById('studio-left-sidebar'),
      rightSidebarExists: !!document.getElementById('studio-right-sidebar'),
      coverBannerVisible: window.getComputedStyle(document.getElementById('studio-cover-banner')).display !== 'none'
    };
  })()`);

  console.log('Studio initial state:', studioLoaded);
  assert(studioLoaded.titleExists && studioLoaded.contentExists, 'Studio title and content textarea exist');
  assert(studioLoaded.toolbarExists, 'Studio formatting toolbar exists');
  assert(!studioLoaded.coverBannerVisible, 'New post starts with cover banner cleanly hidden (isolated draft)');
  await takeScreenshot('studio_part2_initial_layout.png');

  console.log('\n--- TEST 6: Formatting Toolbar Controls (Bold, Italic, Lists, Indent, Outdent, Quote, Code) ---');
  // Initialize textarea with sample content
  await evaluate(`(() => {
    const ta = document.getElementById('post-content');
    ta.value = "First paragraph line\\nSecond paragraph line\\nThird paragraph line";
    ta.focus();
    ta.setSelectionRange(0, 0);
  })()`);

  // Test Bold
  await evaluate(`document.getElementById('format-bold-btn').click()`);
  const boldVal = await evaluate(`document.getElementById('post-content').value`);
  assert(boldVal.includes('**bold text**'), 'Bold button inserts **bold text**');

  // Test Italic
  await evaluate(`document.getElementById('format-italic-btn').click()`);
  const italicVal = await evaluate(`document.getElementById('post-content').value`);
  assert(italicVal.includes('*italic text*'), 'Italic button inserts *italic text*');

  // Test Underline
  await evaluate(`document.getElementById('format-underline-btn').click()`);
  const underlineVal = await evaluate(`document.getElementById('post-content').value`);
  assert(underlineVal.includes('<u>underlined text</u>'), 'Underline button inserts <u>underlined text</u>');

  // Test Strikethrough
  await evaluate(`document.getElementById('format-strike-btn').click()`);
  const strikeVal = await evaluate(`document.getElementById('post-content').value`);
  assert(strikeVal.includes('~~strikethrough text~~'), 'Strikethrough button inserts ~~strikethrough text~~');

  // Test Bullet List
  await evaluate(`document.getElementById('format-bullet-btn').click()`);
  const bulletVal = await evaluate(`document.getElementById('post-content').value`);
  assert(bulletVal.includes('- Bullet item'), 'Bullet list button inserts bullet item');

  // Test Numbered List
  await evaluate(`document.getElementById('format-number-btn').click()`);
  const numberVal = await evaluate(`document.getElementById('post-content').value`);
  assert(numberVal.includes('1. Numbered item'), 'Numbered list button inserts numbered item');

  // Test Indent (Align Center / ⇥)
  await evaluate(`(() => {
    const ta = document.getElementById('post-content');
    ta.value = "Line to indent";
    ta.setSelectionRange(0, 14);
    document.getElementById('format-align-center-btn').click();
  })()`);
  const indentVal = await evaluate(`document.getElementById('post-content').value`);
  assert(indentVal.startsWith('    Line to indent'), 'Indent button adds 4 spaces indentation');

  // Test Outdent (Align Left / ⇤)
  await evaluate(`(() => {
    const ta = document.getElementById('post-content');
    ta.setSelectionRange(0, 18);
    document.getElementById('format-align-left-btn').click();
  })()`);
  const outdentVal = await evaluate(`document.getElementById('post-content').value`);
  assert(outdentVal.startsWith('Line to indent'), 'Outdent button removes 4 spaces indentation');

  // Test Quote (collapsing selection first)
  await evaluate(`(() => {
    const ta = document.getElementById('post-content');
    ta.setSelectionRange(ta.value.length, ta.value.length);
    document.getElementById('format-quote-btn').click();
  })()`);
  const quoteVal = await evaluate(`document.getElementById('post-content').value`);
  assert(quoteVal.includes('> Inspiring quote...') || quoteVal.includes('> '), 'Quote button inserts blockquote markdown');

  // Test Code Block
  await evaluate(`document.getElementById('format-code-btn').click()`);
  const codeVal = await evaluate(`document.getElementById('post-content').value`);
  assert(codeVal.includes('```\n// code here\n```'), 'Code block button inserts code fence markdown');

  console.log('\n--- TEST 7: Video Insert Modal & Embed Action ---');
  await evaluate(`document.getElementById('format-video-btn').click()`);
  await new Promise(r => setTimeout(r, 400));

  const videoModalOpen = await evaluate(`(() => {
    const modal = document.getElementById('insert-video-modal');
    return modal.classList.contains('open') || window.getComputedStyle(modal).display === 'flex';
  })()`);
  assert(videoModalOpen, 'Video format button opens insert video modal');

  // Enter embed URL and confirm
  await evaluate(`(() => {
    document.getElementById('video-embed-url').value = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    document.getElementById('confirm-embed-video-btn').click();
  })()`);
  await new Promise(r => setTimeout(r, 400));

  const videoInserted = await evaluate(`(() => {
    const ta = document.getElementById('post-content');
    const modal = document.getElementById('insert-video-modal');
    const isOpen = modal.classList.contains('open');
    return {
      hasVideoMarkdown: ta.value.includes('[video](https://www.youtube.com/watch?v=dQw4w9WgXcQ)'),
      isModalClosed: !isOpen
    };
  })()`);
  assert(videoInserted.hasVideoMarkdown, 'Embed video inserts [video](url) into post content');
  assert(videoInserted.isModalClosed, 'Video modal closes cleanly after inserting embed');
  await takeScreenshot('studio_part2_video_embed_complete.png');

  console.log('\n--- TEST 8: Left Sidebar Navigation & Workspaces ---');
  // Click Ideas -> opens right sidebar and research notes
  await evaluate(`document.getElementById('nav-item-ideas').click()`);
  const ideasState = await evaluate(`(() => {
    const navActive = document.getElementById('nav-item-ideas').classList.contains('active');
    const researchPanelVisible = document.getElementById('tab-panel-research').style.display !== 'none';
    const isRightOpen = !document.getElementById('infinity-writing-studio').classList.contains('is-right-collapsed');
    return { navActive, researchPanelVisible, isRightOpen };
  })()`);
  assert(ideasState.navActive && ideasState.researchPanelVisible && ideasState.isRightOpen, 'Nav Ideas activates research panel and ensures right sidebar is open');

  // Click Media -> opens media tab
  await evaluate(`document.getElementById('nav-item-media').click()`);
  const mediaState = await evaluate(`(() => {
    const navActive = document.getElementById('nav-item-media').classList.contains('active');
    const mediaPanelVisible = document.getElementById('tab-panel-media').style.display !== 'none';
    return { navActive, mediaPanelVisible };
  })()`);
  assert(mediaState.navActive && mediaState.mediaPanelVisible, 'Nav Media activates media panel in right sidebar');

  // Click Planner -> opens schedule modal
  await evaluate(`document.getElementById('nav-item-planner').click()`);
  const plannerState = await evaluate(`document.getElementById('schedule-modal').style.display === 'flex'`);
  assert(plannerState, 'Nav Planner opens schedule modal');
  // Close schedule modal
  await evaluate(`document.getElementById('schedule-modal').style.display = 'none'`);

  // Workspaces: click add workspace
  await evaluate(`(() => {
    // Mock prompt
    const origPrompt = window.prompt;
    window.prompt = () => 'Web Development';
    document.getElementById('add-workspace-btn').click();
    window.prompt = origPrompt;
  })()`);

  const workspaceState = await evaluate(`(() => {
    const items = Array.from(document.querySelectorAll('.studio-workspace-item'));
    const newItem = items.find(i => i.textContent.includes('Web Development'));
    return {
      found: !!newItem,
      isActive: newItem ? newItem.classList.contains('active') : false
    };
  })()`);
  assert(workspaceState.found && workspaceState.isActive, 'Add Workspace button creates new workspace item and activates it');

  console.log('\n--- TEST 9: Writing Goal Modal & Circular Progress Gauge ---');
  // Open goal modal
  await evaluate(`document.getElementById('studio-writing-goal-card').click()`);
  await new Promise(r => setTimeout(r, 400));
  const goalModalOpen = await evaluate(`document.getElementById('word-count-modal').style.display === 'flex'`);
  assert(goalModalOpen, 'Writing goal card opens word count modal');

  // Click 1000 preset and save
  await evaluate(`(() => {
    const preset1000 = Array.from(document.querySelectorAll('.word-goal-preset')).find(b => b.dataset.words === '1000');
    if (preset1000) preset1000.click();
    document.getElementById('modal-save-goal-btn').click();
  })()`);
  await new Promise(r => setTimeout(r, 400));

  const goalUpdated = await evaluate(`(() => {
    const targetText = document.getElementById('goal-words-target')?.textContent.trim();
    const modalClosed = document.getElementById('word-count-modal').style.display === 'none';
    return { targetText, modalClosed };
  })()`);
  assert(goalUpdated.targetText === '1,000' || goalUpdated.targetText === '1000', 'Goal modal updates target words to 1,000');
  assert(goalUpdated.modalClosed, 'Goal modal closes upon saving');

  console.log('\n--- TEST 10: AI Writing Assistant Panel ---');
  await evaluate(`document.getElementById('open-ai-panel-btn').click()`);
  await new Promise(r => setTimeout(r, 400));

  const aiPanelOpen = await evaluate(`document.getElementById('studio-ai-panel').classList.contains('is-open')`);
  assert(aiPanelOpen, 'AI button in toolbar opens AI Writing Assistant panel');

  // Trigger improve writing action
  await evaluate(`(() => {
    const card = document.querySelector('.studio-ai-action-card[data-action="improve_writing"]');
    if (card) card.click();
  })()`);
  await new Promise(r => setTimeout(r, 1500));

  const aiResultState = await evaluate(`(() => {
    const resCard = document.getElementById('studio-ai-result-card');
    const content = document.getElementById('ai-result-content')?.textContent;
    return {
      visible: resCard ? window.getComputedStyle(resCard).display !== 'none' : false,
      hasContent: !!content && content.length > 5
    };
  })()`);
  console.log('AI result state:', aiResultState);
  assert(aiResultState.visible && aiResultState.hasContent, 'AI Writing Assistant returns actionable improvement suggestion');

  // Close AI panel
  await evaluate(`document.getElementById('close-ai-panel-btn').click()`);
  await takeScreenshot('studio_part2_ai_panel_verified.png');

  console.log('\n--- TEST 11: Distraction-Free Focus Mode ---');
  // Toggle focus mode ON
  await evaluate(`(() => {
    const toggle = document.getElementById('studio-focus-mode-toggle');
    toggle.checked = true;
    toggle.dispatchEvent(new Event('change'));
  })()`);
  await new Promise(r => setTimeout(r, 400));

  const focusState = await evaluate(`(() => {
    const container = document.getElementById('infinity-writing-studio');
    const isFocus = container.classList.contains('is-focus-mode');
    const leftSidebar = document.getElementById('studio-left-sidebar');
    const leftVis = window.getComputedStyle(leftSidebar).visibility;
    return { isFocus, leftVis };
  })()`);
  assert(focusState.isFocus, 'Focus Mode toggles .is-focus-mode class on container');
  assert(focusState.leftVis === 'hidden', 'Focus Mode sets left sidebar visibility to hidden');
  await takeScreenshot('studio_part2_focus_mode_active.png');

  // Test ESC key exits focus mode
  await send('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Escape', windowsVirtualKeyCode: 27 });
  await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', windowsVirtualKeyCode: 27 });
  await new Promise(r => setTimeout(r, 400));

  const focusExited = await evaluate(`!document.getElementById('infinity-writing-studio').classList.contains('is-focus-mode')`);
  assert(focusExited, 'Pressing Escape exits Focus Mode');

  console.log('\n--- TEST 12: Live Reader Preview Toggle ---');
  await evaluate(`document.getElementById('studio-preview-toggle-btn').click()`);
  await new Promise(r => setTimeout(r, 1200));

  const previewState = await evaluate(`(() => {
    const previewContainer = document.getElementById('studio-preview-container');
    const rendered = document.getElementById('studio-preview-rendered');
    const btnLabel = document.getElementById('studio-preview-btn-text')?.textContent;
    return {
      containerVisible: previewContainer ? window.getComputedStyle(previewContainer).display !== 'none' : false,
      renderedVisible: rendered ? window.getComputedStyle(rendered).display !== 'none' : false,
      btnLabel
    };
  })()`);
  assert(previewState.containerVisible && previewState.renderedVisible, 'Live Reader Preview renders article content cleanly');
  assert(previewState.btnLabel === 'Edit', 'Preview button label toggles to "Edit"');
  await takeScreenshot('studio_part2_live_reader_preview.png');

  // Toggle back to Edit
  await evaluate(`document.getElementById('studio-preview-toggle-btn').click()`);
  await new Promise(r => setTimeout(r, 400));

  console.log('\n--- TEST 13: Cover Removal & Isolated Draft State ---');
  // Set a temporary cover path to simulate attached cover
  await evaluate(`(() => {
    const banner = document.getElementById('studio-cover-banner');
    const pathInput = document.getElementById('post-cover-image-path');
    pathInput.value = '/uploads/covers/test_sample_cover.jpg';
    banner.style.display = 'block';
  })()`);

  // Click Remove Cover
  await evaluate(`document.getElementById('remove-cover-banner-btn').click()`);
  const coverRemoved = await evaluate(`(() => {
    const banner = document.getElementById('studio-cover-banner');
    const pathInput = document.getElementById('post-cover-image-path');
    return {
      bannerDisplay: banner.style.display,
      pathVal: pathInput.value
    };
  })()`);
  assert(coverRemoved.bannerDisplay === 'none', 'Remove cover button hides cover banner');
  assert(coverRemoved.pathVal === '', 'Remove cover button clears cover image path');

  console.log('\n=======================================================');
  console.log(`TOTAL TESTS: ${passed + failed} | PASSED: ${passed} | FAILED: ${failed}`);
  console.log('=======================================================\n');

  ws.close();
  edge.kill();
  process.exit(failed > 0 ? 1 : 0);
}

run().catch(err => {
  console.error('Fatal test error:', err);
  process.exit(1);
});
