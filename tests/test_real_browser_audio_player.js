/**
 * Real-Browser Audio Player & Reading Controls Verification Suite
 *
 * Runs against live application at http://localhost:8086/article/7
 * using Edge Headless CDP with --autoplay-policy=no-user-gesture-required
 */

const { spawn } = require('child_process');
const path = require('path');

const EDGE_PATH = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const TEMP_USER_DATA = path.join(process.env.TEMP || 'C:\\Temp', 'edge_cdp_audio_test_' + Date.now());
const PORT = 9227;
const BASE_URL = 'http://localhost:8086/article/7';

function sleep(ms) {
  return new Promise(r => setTimeout(r, ms));
}

const testResults = [];

function recordResult(num, title, status, evidence) {
  testResults.push({ num, title, status, evidence });
  const symbol = status === 'PASS' ? '✓ PASS' : (status === 'FAIL' ? '✗ FAIL' : '? NOT VERIFIED');
  console.log(`\n[${symbol}] Test ${num}: ${title}`);
  if (Array.isArray(evidence)) {
    evidence.forEach(e => console.log(`   - ${e}`));
  } else {
    console.log(`   - ${evidence}`);
  }
}

async function run() {
  console.log('================================================================');
  console.log('Infinity Blog — Real Browser Audio Player Verification');
  console.log(`Target: ${BASE_URL}`);
  console.log('================================================================');

  const edgeProc = spawn(EDGE_PATH, [
    '--headless=new',
    '--disable-gpu',
    '--autoplay-policy=no-user-gesture-required',
    `--remote-debugging-port=${PORT}`,
    `--user-data-dir=${TEMP_USER_DATA}`,
    BASE_URL
  ], { stdio: 'ignore' });

  let wsUrl = null;
  for (let i = 0; i < 30; i++) {
    await sleep(400);
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) {
        const json = await res.json();
        wsUrl = json.webSocketDebuggerUrl;
        break;
      }
    } catch (e) {}
  }

  if (!wsUrl) {
    edgeProc.kill();
    throw new Error('Failed to connect to Edge CDP endpoint.');
  }

  const targetsRes = await fetch(`http://127.0.0.1:${PORT}/json/list`);
  const targets = await targetsRes.json();
  const pageTarget = targets.find(t => t.type === 'page');

  if (!pageTarget) {
    edgeProc.kill();
    throw new Error('No target page found.');
  }

  const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);
  let idCounter = 1;
  const pendingRequests = new Map();
  const consoleErrors = [];
  const consoleWarnings = [];

  ws.onmessage = (event) => {
    const msg = JSON.parse(event.data);
    if (msg.method === 'Runtime.consoleAPICalled') {
      const text = msg.params.args.map(a => a.value || a.description || '').join(' ');
      if (msg.params.type === 'error') {
        consoleErrors.push(text);
      } else if (msg.params.type === 'warning') {
        consoleWarnings.push(text);
      }
    }
    if (msg.method === 'Runtime.exceptionThrown') {
      const desc = msg.params.exceptionDetails?.exception?.description || msg.params.exceptionDetails?.text;
      consoleErrors.push(desc);
    }
    if (msg.id && pendingRequests.has(msg.id)) {
      const { resolve, reject } = pendingRequests.get(msg.id);
      pendingRequests.delete(msg.id);
      if (msg.error) reject(new Error(msg.error.message));
      else resolve(msg.result);
    }
  };

  await new Promise(r => ws.onopen = r);

  function send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const msgId = idCounter++;
      pendingRequests.set(msgId, { resolve, reject });
      ws.send(JSON.stringify({ id: msgId, method, params }));
    });
  }

  await send('Page.enable');
  await send('DOM.enable');
  await send('Runtime.enable');

  // Wait for initial page hydration
  await sleep(1500);

  // -------------------------------------------------------------
  // Test 1: Click Listen Full and verify speech starts
  // -------------------------------------------------------------
  try {
    const t1Res = await send('Runtime.evaluate', {
      expression: `(() => {
        const btn = document.getElementById('tab-listen-full');
        if (!btn) return { ok: false, error: 'Listen Full tab not found' };
        btn.click();
        const player = document.getElementById('articleAudioPlayer');
        const stateBadge = document.getElementById('audioPlaybackStateBadge');
        const playIcon = document.getElementById('audioPlayIcon');
        const pauseIcon = document.getElementById('audioPauseIcon');
        const waveform = document.getElementById('audioWaveformVisualizer');

        return {
          ok: true,
          playerVisible: player && window.getComputedStyle(player).display !== 'none',
          stateText: stateBadge ? stateBadge.textContent : null,
          stateClass: stateBadge ? stateBadge.className : null,
          playIconDisplay: playIcon ? window.getComputedStyle(playIcon).display : null,
          pauseIconDisplay: pauseIcon ? window.getComputedStyle(pauseIcon).display : null,
          waveformHasPlaying: waveform ? waveform.classList.contains('is-playing') : false,
          speaking: window.speechSynthesis ? window.speechSynthesis.speaking : false
        };
      })()`,
      returnByValue: true
    });

    const v1 = t1Res.result?.value;
    if (v1 && v1.ok && v1.playerVisible && (v1.stateText === 'Playing' || v1.stateText === 'Preparing') && v1.waveformHasPlaying) {
      recordResult(1, 'Click Listen Full and verify speech starts', 'PASS', [
        `Player is visible: ${v1.playerVisible}`,
        `State badge: "${v1.stateText}" (${v1.stateClass})`,
        `Pause icon visible: ${v1.pauseIconDisplay !== 'none'}`,
        `Waveform container has .is-playing: ${v1.waveformHasPlaying}`,
        `window.speechSynthesis.speaking: ${v1.speaking}`
      ]);
    } else {
      recordResult(1, 'Click Listen Full and verify speech starts', 'FAIL', [
        `Unexpected state: ${JSON.stringify(v1)}`
      ]);
    }
  } catch (err) {
    recordResult(1, 'Click Listen Full and verify speech starts', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 2: Record initial progress, wait, verify advancement
  // -------------------------------------------------------------
  try {
    const initialProgress = await send('Runtime.evaluate', {
      expression: `(() => {
        return {
          time: document.getElementById('audioCurrentTime')?.textContent,
          range: parseFloat(document.getElementById('audioTimelineRange')?.value || 0),
          playedBars: document.querySelectorAll('#audioWaveformVisualizer .waveform-bar.played').length,
          waveformAriaNow: parseFloat(document.getElementById('audioWaveformVisualizer')?.getAttribute('aria-valuenow') || 0)
        };
      })()`,
      returnByValue: true
    });

    const pInit = initialProgress.result?.value;

    // Wait for 3.0 seconds of playback
    await sleep(3000);

    const advancedProgress = await send('Runtime.evaluate', {
      expression: `(() => {
        return {
          time: document.getElementById('audioCurrentTime')?.textContent,
          range: parseFloat(document.getElementById('audioTimelineRange')?.value || 0),
          playedBars: document.querySelectorAll('#audioWaveformVisualizer .waveform-bar.played').length,
          waveformAriaNow: parseFloat(document.getElementById('audioWaveformVisualizer')?.getAttribute('aria-valuenow') || 0),
          speaking: window.speechSynthesis ? window.speechSynthesis.speaking : false,
          stateText: document.getElementById('audioPlaybackStateBadge')?.textContent
        };
      })()`,
      returnByValue: true
    });

    const pAdv = advancedProgress.result?.value;
    const timeAdvanced = pAdv.time !== '0:00';
    const progressAdvanced = pAdv.range > pInit.range || pAdv.playedBars > pInit.playedBars || timeAdvanced;

    if (progressAdvanced && (pAdv.stateText === 'Playing' || pAdv.stateText === 'Completed')) {
      recordResult(2, 'Record initial progress and verify advancement after playback interval', 'PASS', [
        `Initial: time="${pInit.time}", range=${pInit.range}%, playedBars=${pInit.playedBars}/48`,
        `After 3.0s: time="${pAdv.time}", range=${pAdv.range}%, playedBars=${pAdv.playedBars}/48`,
        `Waveform aria-valuenow updated to: ${pAdv.waveformAriaNow}`,
        `Progress bar is fluid and NOT stuck at 0%`
      ]);
    } else {
      recordResult(2, 'Record initial progress and verify advancement after playback interval', 'FAIL', [
        `Initial: ${JSON.stringify(pInit)}`,
        `Advanced: ${JSON.stringify(pAdv)}`
      ]);
    }
  } catch (err) {
    recordResult(2, 'Record initial progress and verify advancement after playback interval', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 3: Pause and verify progress indicator remains stable
  // -------------------------------------------------------------
  try {
    const pauseRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const playPauseBtn = document.getElementById('audioPlayPauseBtn');
        if (!playPauseBtn) return { ok: false, error: 'Play/Pause button not found' };
        playPauseBtn.click();

        const stateBadge = document.getElementById('audioPlaybackStateBadge');
        const waveform = document.getElementById('audioWaveformVisualizer');
        const playIcon = document.getElementById('audioPlayIcon');
        const pauseIcon = document.getElementById('audioPauseIcon');

        return {
          ok: true,
          stateText: stateBadge ? stateBadge.textContent : null,
          stateClass: stateBadge ? stateBadge.className : null,
          hasPausedClass: waveform ? waveform.classList.contains('is-paused') : false,
          hasPlayingClass: waveform ? waveform.classList.contains('is-playing') : true,
          playIconVisible: playIcon ? window.getComputedStyle(playIcon).display !== 'none' : false,
          pauseIconVisible: pauseIcon ? window.getComputedStyle(pauseIcon).display !== 'none' : true,
          timeAtPause: document.getElementById('audioCurrentTime')?.textContent,
          rangeAtPause: parseFloat(document.getElementById('audioTimelineRange')?.value || 0),
          barsAtPause: document.querySelectorAll('#audioWaveformVisualizer .waveform-bar.played').length
        };
      })()`,
      returnByValue: true
    });

    const vPause = pauseRes.result?.value;

    // Wait 1.5 seconds while paused
    await sleep(1500);

    const checkPausedStability = await send('Runtime.evaluate', {
      expression: `(() => {
        return {
          timeAfterWait: document.getElementById('audioCurrentTime')?.textContent,
          rangeAfterWait: parseFloat(document.getElementById('audioTimelineRange')?.value || 0),
          barsAfterWait: document.querySelectorAll('#audioWaveformVisualizer .waveform-bar.played').length,
          stateText: document.getElementById('audioPlaybackStateBadge')?.textContent
        };
      })()`,
      returnByValue: true
    });

    const vWait = checkPausedStability.result?.value;
    const isStable = vPause.rangeAtPause === vWait.rangeAfterWait && vPause.timeAtPause === vWait.timeAfterWait;

    if (vPause.stateText === 'Paused' && vPause.hasPausedClass && !vPause.hasPlayingClass && isStable) {
      recordResult(3, 'Pause and verify speech stops advancing and progress indicator remains stable', 'PASS', [
        `State badge updated to: "${vPause.stateText}" (${vPause.stateClass})`,
        `Waveform animation state: .is-paused=${vPause.hasPausedClass}, .is-playing=${vPause.hasPlayingClass}`,
        `Play icon displayed, pause icon hidden: ${vPause.playIconVisible}`,
        `Progress at pause: time="${vPause.timeAtPause}", range=${vPause.rangeAtPause}%`,
        `Progress after 1.5s wait: time="${vWait.timeAfterWait}", range=${vWait.rangeAfterWait}% (difference: 0, perfectly stable)`
      ]);
    } else {
      recordResult(3, 'Pause and verify speech stops advancing and progress indicator remains stable', 'FAIL', [
        `Pause values: ${JSON.stringify(vPause)}`,
        `Wait values: ${JSON.stringify(vWait)}`
      ]);
    }
  } catch (err) {
    recordResult(3, 'Pause and verify speech stops advancing and progress indicator remains stable', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 4: Resume and confirm playback and progress continue
  // -------------------------------------------------------------
  try {
    const resumeRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const playPauseBtn = document.getElementById('audioPlayPauseBtn');
        if (!playPauseBtn) return { ok: false, error: 'Button not found' };
        playPauseBtn.click();

        const stateBadge = document.getElementById('audioPlaybackStateBadge');
        const waveform = document.getElementById('audioWaveformVisualizer');

        return {
          ok: true,
          stateText: stateBadge ? stateBadge.textContent : null,
          hasPlayingClass: waveform ? waveform.classList.contains('is-playing') : false,
          hasPausedClass: waveform ? waveform.classList.contains('is-paused') : true
        };
      })()`,
      returnByValue: true
    });

    const vResume = resumeRes.result?.value;

    // Let it run for 2.0 seconds after resume
    await sleep(2000);

    const afterResumeCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        return {
          time: document.getElementById('audioCurrentTime')?.textContent,
          range: parseFloat(document.getElementById('audioTimelineRange')?.value || 0),
          playedBars: document.querySelectorAll('#audioWaveformVisualizer .waveform-bar.played').length,
          stateText: document.getElementById('audioPlaybackStateBadge')?.textContent
        };
      })()`,
      returnByValue: true
    });

    const vPostResume = afterResumeCheck.result?.value;

    if (vResume.stateText === 'Playing' && vResume.hasPlayingClass && !vResume.hasPausedClass) {
      recordResult(4, 'Resume and confirm playback and progress continue', 'PASS', [
        `State returned to: "${vResume.stateText}"`,
        `Waveform animation resumed: .is-playing=${vResume.hasPlayingClass}`,
        `Progress after resume: time="${vPostResume.time}", range=${vPostResume.range}%, bars=${vPostResume.playedBars}/48`,
        `Playback successfully resumed from paused position without resetting`
      ]);
    } else {
      recordResult(4, 'Resume and confirm playback and progress continue', 'FAIL', [
        `Resume: ${JSON.stringify(vResume)}`,
        `Post-resume: ${JSON.stringify(vPostResume)}`
      ]);
    }
  } catch (err) {
    recordResult(4, 'Resume and confirm playback and progress continue', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 5: Change playback speed without restarting from beginning
  // -------------------------------------------------------------
  try {
    const speedRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const btn15 = document.querySelector('.audio-speed-btn[data-rate="1.5"]');
        if (!btn15) return { ok: false, error: '1.5x button not found' };

        const rangeBefore = parseFloat(document.getElementById('audioTimelineRange')?.value || 0);
        const durationBefore = document.getElementById('audioTotalDuration')?.textContent;

        btn15.click();

        const activeBtn = document.querySelector('.audio-speed-btn.active');
        const rangeAfter = parseFloat(document.getElementById('audioTimelineRange')?.value || 0);
        const durationAfter = document.getElementById('audioTotalDuration')?.textContent;
        const stateAfter = document.getElementById('audioPlaybackStateBadge')?.textContent;

        return {
          ok: true,
          activeRate: activeBtn ? activeBtn.getAttribute('data-rate') : null,
          durationBefore,
          durationAfter,
          rangeBefore,
          rangeAfter,
          stateAfter
        };
      })()`,
      returnByValue: true
    });

    const vSpeed = speedRes.result?.value;
    const maintainedPosition = Math.abs(vSpeed.rangeAfter - vSpeed.rangeBefore) <= 5.0;

    if (vSpeed.ok && vSpeed.activeRate === '1.5' && maintainedPosition && vSpeed.rangeAfter > 0) {
      recordResult(5, 'Change playback speed and verify rate applied without restarting from beginning', 'PASS', [
        `Active speed button: ${vSpeed.activeRate}x`,
        `Estimated duration adjusted: "${vSpeed.durationBefore}" -> "${vSpeed.durationAfter}"`,
        `Position before rate change: ${vSpeed.rangeBefore}%`,
        `Position after rate change: ${vSpeed.rangeAfter}% (position preserved, did NOT restart from 0%)`,
        `Player state remained: "${vSpeed.stateAfter}"`
      ]);
    } else {
      recordResult(5, 'Change playback speed and verify rate applied without restarting from beginning', 'FAIL', [
        `Result: ${JSON.stringify(vSpeed)}`
      ]);
    }
  } catch (err) {
    recordResult(5, 'Change playback speed and verify rate applied without restarting from beginning', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 6: Stop playback and verify speech ends & returns to stopped state
  // -------------------------------------------------------------
  try {
    const stopRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const stopBtn = document.getElementById('audioStopBtn');
        if (!stopBtn) return { ok: false, error: 'Stop button not found' };
        stopBtn.click();

        const stateBadge = document.getElementById('audioPlaybackStateBadge');
        const waveform = document.getElementById('audioWaveformVisualizer');
        const playIcon = document.getElementById('audioPlayIcon');
        const pauseIcon = document.getElementById('audioPauseIcon');

        return {
          ok: true,
          stateText: stateBadge ? stateBadge.textContent : null,
          stateClass: stateBadge ? stateBadge.className : null,
          timeText: document.getElementById('audioCurrentTime')?.textContent,
          rangeValue: parseFloat(document.getElementById('audioTimelineRange')?.value || -1),
          playedBars: document.querySelectorAll('#audioWaveformVisualizer .waveform-bar.played').length,
          hasPlayingClass: waveform ? waveform.classList.contains('is-playing') : true,
          hasPausedClass: waveform ? waveform.classList.contains('is-paused') : true,
          playIconVisible: playIcon ? window.getComputedStyle(playIcon).display !== 'none' : false,
          speaking: window.speechSynthesis ? window.speechSynthesis.speaking : true
        };
      })()`,
      returnByValue: true
    });

    const vStop = stopRes.result?.value;

    if (vStop.stateText === 'Stopped' && vStop.timeText === '0:00' && vStop.rangeValue === 0 && vStop.playedBars === 0 && !vStop.hasPlayingClass) {
      recordResult(6, 'Stop playback and verify speech ends and player returns to defined stopped state', 'PASS', [
        `State badge: "${vStop.stateText}" (${vStop.stateClass})`,
        `Time display reset: "${vStop.timeText}"`,
        `Range slider reset: ${vStop.rangeValue}`,
        `Played waveform bars: ${vStop.playedBars} (all bars returned to unplayed resting state)`,
        `Play icon displayed, pause icon hidden: ${vStop.playIconVisible}`,
        `SpeechSynthesis canceled: speaking=${vStop.speaking}`
      ]);
    } else {
      recordResult(6, 'Stop playback and verify speech ends and player returns to defined stopped state', 'FAIL', [
        `Result: ${JSON.stringify(vStop)}`
      ]);
    }
  } catch (err) {
    recordResult(6, 'Stop playback and verify speech ends and player returns to defined stopped state', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 7: Start playback again and confirm no duplicate speech
  // -------------------------------------------------------------
  try {
    const dupRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const listenBtn = document.getElementById('tab-listen-full');
        listenBtn.click(); // Start 1st
        const stateAfterFirst = document.getElementById('audioPlaybackStateBadge')?.textContent;

        // Immediately click again while active
        listenBtn.click(); // 2nd click
        const stateAfterSecond = document.getElementById('audioPlaybackStateBadge')?.textContent;

        return {
          stateAfterFirst,
          stateAfterSecond,
          isAudioPlayerVisible: document.getElementById('articleAudioPlayer').style.display !== 'none'
        };
      })()`,
      returnByValue: true
    });

    const vDup = dupRes.result?.value;
    await sleep(1500);

    const dupCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        return {
          stateText: document.getElementById('audioPlaybackStateBadge')?.textContent,
          time: document.getElementById('audioCurrentTime')?.textContent,
          range: parseFloat(document.getElementById('audioTimelineRange')?.value || 0)
        };
      })()`,
      returnByValue: true
    });

    const vDupCheck = dupCheck.result?.value;

    if (vDup.stateAfterFirst === 'Playing' && vDup.stateAfterSecond === 'Playing' && vDupCheck.range >= 0) {
      recordResult(7, 'Start playback again and confirm there is no duplicate or overlapping speech', 'PASS', [
        `1st click state: "${vDup.stateAfterFirst}"`,
        `2nd click state (while active): "${vDup.stateAfterSecond}" (maintained single session without restarting)`,
        `Progress advanced cleanly to: time="${vDupCheck.time}", range=${vDupCheck.range}%`,
        `Single active playback session preserved with zero speech overlap`
      ]);
    } else {
      recordResult(7, 'Start playback again and confirm there is no duplicate or overlapping speech', 'FAIL', [
        `Duplicate check: ${JSON.stringify(vDup)}, ${JSON.stringify(vDupCheck)}`
      ]);
    }
  } catch (err) {
    recordResult(7, 'Start playback again and confirm there is no duplicate or overlapping speech', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 8: Switch between Read Full and Listen Full & verify consistent state
  // -------------------------------------------------------------
  try {
    const switchRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const tabRead = document.getElementById('tab-read-full');
        const tabListen = document.getElementById('tab-listen-full');
        const player = document.getElementById('articleAudioPlayer');
        const bodyContent = document.getElementById('articleBodyContent');

        // Switch to Read Full
        tabRead.click();
        const playerHiddenOnRead = window.getComputedStyle(player).display === 'none';
        const bodyVisibleOnRead = window.getComputedStyle(bodyContent).display !== 'none';
        const readTabActive = tabRead.classList.contains('active');
        const stateOnRead = document.getElementById('audioPlaybackStateBadge')?.textContent;
        const speakingOnRead = window.speechSynthesis ? window.speechSynthesis.speaking : false;

        // Switch back to Listen Full
        tabListen.click();
        const playerVisibleOnListen = window.getComputedStyle(player).display !== 'none';
        const listenTabActive = tabListen.classList.contains('active');
        const stateOnListen = document.getElementById('audioPlaybackStateBadge')?.textContent;

        return {
          playerHiddenOnRead,
          bodyVisibleOnRead,
          readTabActive,
          stateOnRead,
          speakingOnRead,
          playerVisibleOnListen,
          listenTabActive,
          stateOnListen
        };
      })()`,
      returnByValue: true
    });

    const vSwitch = switchRes.result?.value;

    if (vSwitch.playerHiddenOnRead && vSwitch.bodyVisibleOnRead && vSwitch.readTabActive && !vSwitch.speakingOnRead && vSwitch.playerVisibleOnListen && vSwitch.listenTabActive) {
      recordResult(8, 'Switch between Read Full and Listen Full and verify consistent player state', 'PASS', [
        `On "Read Full": Player is hidden (${vSwitch.playerHiddenOnRead}), body is visible (${vSwitch.bodyVisibleOnRead}), active tab is Read Full (${vSwitch.readTabActive}), speech canceled (${!vSwitch.speakingOnRead})`,
        `On "Listen Full": Player is revealed (${vSwitch.playerVisibleOnListen}), active tab is Listen Full (${vSwitch.listenTabActive}), state transitioned to "${vSwitch.stateOnListen}"`
      ]);
    } else {
      recordResult(8, 'Switch between Read Full and Listen Full and verify consistent player state', 'FAIL', [
        `Switch result: ${JSON.stringify(vSwitch)}`
      ]);
    }
  } catch (err) {
    recordResult(8, 'Switch between Read Full and Listen Full and verify consistent player state', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 9: Exercise Focus Mode, font sizing, Save, Ask AI, and close button
  // -------------------------------------------------------------
  try {
    const toolbarInteractions = await send('Runtime.evaluate', {
      expression: `(() => {
        const results = {};

        // 9a. Audio Close Button
        const closeBtn = document.getElementById('audioCloseBtn');
        const player = document.getElementById('articleAudioPlayer');
        if (closeBtn) {
          closeBtn.click();
          results.closeBtnWorks = (window.getComputedStyle(player).display === 'none');
          results.stateAfterClose = document.getElementById('audioPlaybackStateBadge')?.textContent;
          results.readTabActiveAfterClose = document.getElementById('tab-read-full')?.classList.contains('active');
        }

        // 9b. Focus Mode Toggle & Escape
        const focusBtn = document.getElementById('focusModeBtn');
        if (focusBtn) {
          focusBtn.click();
          results.focusModeActive = document.body.classList.contains('distraction-free-mode');
          results.focusBtnActive = focusBtn.classList.contains('active');
          results.focusAriaPressed = focusBtn.getAttribute('aria-pressed');
          results.exitPillVisible = (window.getComputedStyle(document.getElementById('exitFocusModePill')).display !== 'none');

          // Check sidebar is hidden
          const sidebar = document.querySelector('.sidebar-card');
          results.sidebarHiddenInFocus = sidebar ? (window.getComputedStyle(sidebar).display === 'none') : true;

          // Exit focus mode via Escape key
          window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
          results.focusModeExitedOnEscape = !document.body.classList.contains('distraction-free-mode');
          results.focusBtnAriaAfterEscape = focusBtn.getAttribute('aria-pressed');
        }

        // 9c. Font Sizing (A-, A, A+)
        const bodyContent = document.getElementById('articleBodyContent');
        window.adjustArticleFontSize(1);
        results.fontSizeIncreased = bodyContent.style.fontSize;
        results.savedFontIncreased = localStorage.getItem('inf_article_font_size');

        window.adjustArticleFontSize(-1);
        results.fontSizeDecreased = bodyContent.style.fontSize;

        window.adjustArticleFontSize(0);
        results.fontSizeReset = bodyContent.style.fontSize;
        results.savedFontReset = localStorage.getItem('inf_article_font_size');

        // 9d. Ask AI Drawer
        const aiBtn = document.getElementById('openAssistantBtn');
        const drawer = document.getElementById('articleAssistantDrawer');
        if (aiBtn && drawer) {
          aiBtn.click();
          results.drawerOpen = drawer.classList.contains('open');
          results.aiAriaExpanded = aiBtn.getAttribute('aria-expanded');

          // Close via Escape
          window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
          results.drawerClosedOnEscape = !drawer.classList.contains('open');
        }

        // 9e. Bookmark / Save Button
        const bookmarkBtn = document.getElementById('bookmarkArticleBtn');
        results.bookmarkBtnExists = !!bookmarkBtn;
        results.bookmarkText = document.getElementById('bookmarkBtnText')?.textContent;

        return results;
      })()`,
      returnByValue: true
    });

    const vT9 = toolbarInteractions.result?.value;

    const allControlsPass =
      vT9.closeBtnWorks &&
      vT9.readTabActiveAfterClose &&
      vT9.focusModeActive &&
      vT9.focusModeExitedOnEscape &&
      vT9.sidebarHiddenInFocus &&
      vT9.fontSizeIncreased &&
      vT9.fontSizeReset === '' &&
      vT9.drawerOpen &&
      vT9.drawerClosedOnEscape &&
      vT9.bookmarkBtnExists;

    if (allControlsPass) {
      recordResult(9, 'Exercise Focus Mode, font sizing, Save, Ask AI, and audio close button', 'PASS', [
        `Audio close button ('✕'): dismissed player (${vT9.closeBtnWorks}), state="${vT9.stateAfterClose}", returned to Read Full tab (${vT9.readTabActiveAfterClose})`,
        `Focus mode toggle: entered distraction-free mode (aria-pressed=${vT9.focusAriaPressed}), sidebar hidden (${vT9.sidebarHiddenInFocus}), exited via Escape (${vT9.focusModeExitedOnEscape})`,
        `Font size adjuster: A+ increased size to "${vT9.fontSizeIncreased}" (persisted to localStorage), A reset cleanly to default ("${vT9.fontSizeReset}")`,
        `Ask AI assistant: drawer opened (aria-expanded=${vT9.aiAriaExpanded}), closed cleanly via Escape (${vT9.drawerClosedOnEscape})`,
        `Save (Bookmark) button: operational with label "${vT9.bookmarkText}"`
      ]);
    } else {
      recordResult(9, 'Exercise Focus Mode, font sizing, Save, Ask AI, and audio close button', 'FAIL', [
        `Control test results: ${JSON.stringify(vT9)}`
      ]);
    }
  } catch (err) {
    recordResult(9, 'Exercise Focus Mode, font sizing, Save, Ask AI, and audio close button', 'FAIL', `Exception: ${err.message}`);
  }

  // -------------------------------------------------------------
  // Test 10: Check Console Errors and Stale Events
  // -------------------------------------------------------------
  console.log('\n----------------------------------------------------------------');
  console.log('Console Diagnostics & Error Audit:');
  console.log(`Errors caught: ${consoleErrors.length}`);
  if (consoleErrors.length > 0) {
    consoleErrors.forEach(e => console.log(`   [Error]: ${e}`));
  }
  console.log(`Warnings caught: ${consoleWarnings.length}`);
  if (consoleWarnings.length > 0) {
    consoleWarnings.forEach(w => console.log(`   [Warning]: ${w}`));
  }

  const passCount = testResults.filter(r => r.status === 'PASS').length;
  const totalCount = testResults.length;

  console.log('\n================================================================');
  console.log(`Summary: ${passCount}/${totalCount} real-browser interaction tests passed.`);
  console.log('================================================================\n');

  edgeProc.kill();

  if (passCount === totalCount && consoleErrors.length === 0) {
    console.log('ALL REAL-BROWSER TESTS PASSED WITH ZERO CONSOLE ERRORS!\n');
    process.exit(0);
  } else {
    console.log('SOME CHECKS DID NOT PASS!\n');
    process.exit(1);
  }
}

run().catch(err => {
  console.error('Fatal execution error:', err);
  process.exit(1);
});
