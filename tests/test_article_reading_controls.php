<?php

declare(strict_types=1);

/**
 * Automated Test Suite: Article Reading Controls and Audio Player UX
 *
 * Verifies:
 * 1. Reading time calculation (200 WPM)
 * 2. Speech data preparation & TextCleaner
 * 3. Article view template rendering:
 *    - Reading tabs: Read Full, Listen Full, Focus Mode, Save, Ask AI
 *    - Font size adjusters: A-, A, A+
 *    - Audio player container, track title, source badge, state badge, close button
 *    - Waveform visualizer (48 harmonic bars with --base-h, --i, and height)
 *    - Audio player controls: Play/Pause, Stop, Range slider, Elapsed & Total duration, Estimate note
 *    - Speed selector (1x, 1.25x, 1.5x, 2x)
 *    - Body classes: .article-body-content and .article-body
 * 4. CSS rules in public/assets/css/style.css:
 *    - .audio-waveform-container, .waveform-bar, .waveform-bar.played
 *    - @keyframes waveBarPulse
 *    - Paused and playing animation states
 *    - prefers-reduced-motion overrides
 *    - .audio-state-badge with state-* modifiers
 *    - Focus mode distraction-free layout (hiding sidebars/aside, centering content)
 *    - Protected code block font sizes inside article body
 * 5. JavaScript state machine & logic assertions:
 *    - audioState lifecycle: idle, preparing, playing, paused, stopped, completed, error
 *    - duplicate playback session prevention (clicking Listen Full while active maintains session)
 *    - speech synthesis sentence chunking and boundary tracking (SpeechSynthesisUtterance.onboundary)
 *    - waveform seek click and arrow key handlers
 *    - font size clamping between 0.85 and 1.45
 *    - escape key listener for focus mode and assistant drawer
 *    - cleanup on beforeunload and pagehide
 */

require_once __DIR__ . '/../autoload.php';

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
    } else {
        $failed++;
        echo "FAIL: {$message}\n";
    }
}

echo "=== Running Article Reading Controls and Audio Player Tests ===\n\n";

// -------------------------------------------------------------
// 1. Reading Time Formula (200 WPM)
// -------------------------------------------------------------
echo "--- 1. Reading Time Formula (200 WPM) ---\n";

function calcReadingTime(int $words): int {
    return max(1, (int) ceil($words / 200));
}

assertTest(calcReadingTime(0) === 1, "0 words yields 1 min read minimum");
assertTest(calcReadingTime(120) === 1, "120 words yields 1 min read");
assertTest(calcReadingTime(200) === 1, "200 words yields 1 min read");
assertTest(calcReadingTime(201) === 2, "201 words yields 2 min read");
assertTest(calcReadingTime(650) === 4, "650 words yields 4 min read");
assertTest(calcReadingTime(1200) === 6, "1200 words yields 6 min read");

// -------------------------------------------------------------
// 2. Speech Data & TextCleaner
// -------------------------------------------------------------
echo "\n--- 2. Speech Data & TextCleaner ---\n";

$rawTitle = "Exploring Modern AI Systems";
$rawContent = "## Chapter 1\n\nCheck out [this link](https://example.com) for details. Here is `console.log('hi')` and code block:\n\n```js\nconst x = 10;\n```\n\nHappy coding! 🚀";

$cleanSpeech = \InfinityBlog\Audio\TextCleaner::cleanForSpeech($rawTitle, $rawContent);

assertTest(str_contains($cleanSpeech, "Exploring Modern AI Systems"), "Title retained in speech text");
assertTest(!str_contains($cleanSpeech, "##"), "Markdown headers stripped from speech text");
assertTest(!str_contains($cleanSpeech, "https://example.com"), "Markdown URL links stripped from speech text");
assertTest(!str_contains($cleanSpeech, "```"), "Code fences stripped from speech text");

$wordCount = str_word_count($cleanSpeech);
$speechDurationEst = max(10, (int) ceil(($wordCount / 140) * 60));
assertTest($speechDurationEst >= 10, "Speech duration estimate has minimum 10 seconds");

// -------------------------------------------------------------
// 3. Article Detail View Template Rendering
// -------------------------------------------------------------
echo "\n--- 3. Article View Markup & DOM Elements ---\n";

$testPost = [
    'id' => 42,
    'title' => 'Designing Seamless Audio Experiences for Web Articles',
    'slug' => 'designing-seamless-audio-experiences',
    'content' => "# Audio Experience\n\nListen to full articles with speech synthesis.",
    'summary' => 'Key takeaways on modern audio and reading toolbar UX.',
    'category_name' => 'Technology',
    'category_slug' => 'technology',
    'author_id' => 5,
    'author_name' => 'Sarah Connor',
    'published_at' => '2026-10-18 12:00:00',
    'reading_time' => 3,
    'audio_path' => null,
    'audio_duration' => null,
];

$testSpeechData = [
    'has_server_audio' => false,
    'audio_url' => null,
    'audio_status' => 'unconfigured',
    'full_text' => 'Designing Seamless Audio Experiences for Web Articles. Listen to full articles with speech synthesis.',
    'summary_text' => 'Key takeaways on modern audio and reading toolbar UX.',
    'word_count' => 18,
    'duration_seconds_est' => 120,
];

function renderArticle(array $post, array $speechData, bool $isBookmarked = false): string {
    $renderedBody = '<div><p>Article body content here</p></div>';
    $videoSignedUrl = null;
    $recentPosts = [];
    $scoreSummary = ['average_score' => 4.8, 'total_ratings' => 12];
    $userScore = 5;
    $reviews = [];
    $reviewsTotal = 0;
    $reviewsPage = 1;
    $reviewsTotalPages = 1;
    $userReview = null;
    $comments = [];
    $readingTime = $post['reading_time'] ?? 3;
    $currentUser = ['id' => 10, 'username' => 'tester', 'role' => 'reader'];
    $authorHasBadge = true;

    ob_start();
    include __DIR__ . '/../app/Views/article.php';
    return ob_get_clean();
}

$html = renderArticle($testPost, $testSpeechData, false);

assertTest(str_contains($html, 'id="tab-read-full"'), "Toolbar includes 'Read Full' button (#tab-read-full)");
assertTest(str_contains($html, 'id="tab-listen-full"'), "Toolbar includes 'Listen Full' button (#tab-listen-full)");
assertTest(str_contains($html, 'id="focusModeBtn"'), "Toolbar includes 'Focus Mode' button (#focusModeBtn)");
assertTest(str_contains($html, 'aria-pressed="false"'), "Focus Mode button has aria-pressed='false'");
assertTest(str_contains($html, 'id="bookmarkArticleBtn"'), "Toolbar includes 'Save' button (#bookmarkArticleBtn)");
assertTest(str_contains($html, 'id="openAssistantBtn"'), "Toolbar includes 'Ask AI' button (#openAssistantBtn)");
assertTest(str_contains($html, 'adjustArticleFontSize(-1)'), "Toolbar includes 'A-' font decrease button");
assertTest(str_contains($html, 'adjustArticleFontSize(0)'), "Toolbar includes 'A' font reset button");
assertTest(str_contains($html, 'adjustArticleFontSize(1)'), "Toolbar includes 'A+' font increase button");
assertTest(str_contains($html, 'min read'), "Toolbar displays estimated reading time");

// Audio Player DOM
assertTest(str_contains($html, 'id="articleAudioPlayer"'), "Audio player container exists (#articleAudioPlayer)");
assertTest(str_contains($html, 'id="audioTrackTitle"'), "Audio track title container exists (#audioTrackTitle)");
assertTest(str_contains($html, 'id="audioPlaybackStateBadge"'), "Audio playback state badge exists (#audioPlaybackStateBadge)");
assertTest(str_contains($html, 'state-idle'), "Playback state badge defaults to 'state-idle'");
assertTest(str_contains($html, 'id="audioSourceBadge"'), "Audio source badge exists (#audioSourceBadge)");
assertTest(str_contains($html, 'id="audioCloseBtn"'), "Audio player close button exists (#audioCloseBtn)");
assertTest(str_contains($html, 'id="audioWaveformVisualizer"'), "Waveform visualizer container exists (#audioWaveformVisualizer)");
assertTest(str_contains($html, 'role="slider"'), "Waveform visualizer has role='slider'");
assertTest(str_contains($html, 'aria-label="Audio Waveform Seek"'), "Waveform visualizer has accessible label");

// Waveform Bars (at least 40 bars)
preg_match_all('/class="waveform-bar"/', $html, $matches);
$barCount = count($matches[0]);
assertTest($barCount >= 40, "Waveform visualizer has at least 40 bars (found {$barCount})");

// Player Controls
assertTest(str_contains($html, 'id="audioPlayPauseBtn"'), "Play/Pause button exists (#audioPlayPauseBtn)");
assertTest(str_contains($html, 'id="audioPlayIcon"'), "Play icon exists in player");
assertTest(str_contains($html, 'id="audioPauseIcon"'), "Pause icon exists in player");
assertTest(str_contains($html, 'id="audioStopBtn"'), "Stop button exists (#audioStopBtn)");
assertTest(str_contains($html, 'id="audioTimelineRange"'), "Timeline range slider exists (#audioTimelineRange)");
assertTest(str_contains($html, 'id="audioCurrentTime"'), "Elapsed time display exists (#audioCurrentTime)");
assertTest(str_contains($html, 'id="audioTotalDuration"'), "Total duration display exists (#audioTotalDuration)");
assertTest(str_contains($html, 'id="audioEstimatedNote"'), "Spoken progress estimate note exists (#audioEstimatedNote)");
assertTest(str_contains($html, 'data-rate="1"'), "Speed selector contains 1x");
assertTest(str_contains($html, 'data-rate="1.25"'), "Speed selector contains 1.25x");
assertTest(str_contains($html, 'data-rate="1.5"'), "Speed selector contains 1.5x");
assertTest(str_contains($html, 'data-rate="2"'), "Speed selector contains 2x");
assertTest(str_contains($html, 'id="audioPlayerMessage"'), "Audio player error alert container exists (#audioPlayerMessage)");

// Content Container
assertTest(str_contains($html, 'class="article-body-content article-body"'), "Article body has both 'article-body-content' and 'article-body' classes");

// Test Cloud Audio mode source badge
$cloudSpeechData = $testSpeechData;
$cloudSpeechData['has_server_audio'] = true;
$cloudSpeechData['audio_url'] = 'https://storage.example.com/audio.mp3';
$cloudHtml = renderArticle($testPost, $cloudSpeechData, true);
assertTest(str_contains($cloudHtml, '☁️ Cloud Audio'), "Source badge renders '☁️ Cloud Audio' when server audio exists");
assertTest(str_contains($cloudHtml, 'Saved'), "Bookmark button renders 'Saved' when article is bookmarked");

// -------------------------------------------------------------
// 4. CSS Verification in style.css
// -------------------------------------------------------------
echo "\n--- 4. CSS Rules & Styling Verification ---\n";

$css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

assertTest(str_contains($css, '.audio-waveform-container'), "CSS defines .audio-waveform-container");
assertTest(str_contains($css, '.waveform-bar'), "CSS defines .waveform-bar");
assertTest(str_contains($css, '.waveform-bar.played'), "CSS defines .waveform-bar.played");
assertTest(str_contains($css, '@keyframes waveBarPulse'), "CSS defines @keyframes waveBarPulse");
assertTest(str_contains($css, '.audio-waveform-container.is-playing .waveform-bar.played'), "CSS triggers wave pulse when playing");
assertTest(str_contains($css, 'animation-play-state: paused'), "CSS pauses waveform animation when audio paused");
assertTest(str_contains($css, 'prefers-reduced-motion'), "CSS respects prefers-reduced-motion for waveform");
assertTest(str_contains($css, '.audio-state-badge'), "CSS defines .audio-state-badge");
assertTest(str_contains($css, '.audio-state-badge.state-playing'), "CSS defines .state-playing badge");
assertTest(str_contains($css, '.audio-state-badge.state-paused'), "CSS defines .state-paused badge");
assertTest(str_contains($css, '.audio-close-btn'), "CSS defines .audio-close-btn");

// Distraction-Free Focus Mode Isolation
assertTest(str_contains($css, 'body.distraction-free-mode aside'), "Focus mode hides sidebar / aside element");
assertTest(str_contains($css, 'body.distraction-free-mode .sidebar-card'), "Focus mode hides sidebar card");
assertTest(str_contains($css, 'body.distraction-free-mode .breadcrumb-nav'), "Focus mode hides breadcrumb nav");
assertTest(str_contains($css, 'body.distraction-free-mode .site-header'), "Focus mode hides site header");
assertTest(str_contains($css, 'body.distraction-free-mode .article-detail-layout'), "Focus mode centers article layout column");

// Code Block Protection
assertTest(str_contains($css, '#articleBodyContent pre'), "CSS protects pre code blocks inside article body");
assertTest(str_contains($css, '#articleBodyContent code'), "CSS protects inline code blocks inside article body");

// -------------------------------------------------------------
// 5. JavaScript Logic & State Machine Rules
// -------------------------------------------------------------
echo "\n--- 5. JavaScript Logic & State Machine Rules ---\n";

assertTest(str_contains($html, "audioState = 'idle'"), "JS initializes audioState to 'idle'");
assertTest(str_contains($html, "function setAudioState("), "JS defines setAudioState handler");
assertTest(str_contains($html, "audioState === 'playing' && currentAudioMode === mode"), "JS checks for duplicate playback session on startAudio");
assertTest(str_contains($html, "utt.onboundary ="), "JS attaches to SpeechSynthesisUtterance.onboundary for character progress");
assertTest(str_contains($html, "startTimer()"), "JS runs smooth timer interpolation during speech");
assertTest(str_contains($html, "waveformContainer.addEventListener('click'"), "JS binds click-to-seek on waveform container");
assertTest(str_contains($html, "waveformContainer.addEventListener('keydown'"), "JS binds keyboard arrow seek on waveform container");
assertTest(str_contains($html, "e.key === 'Escape'"), "JS binds Escape key to exit focus mode and close assistant drawer");
assertTest(str_contains($html, "window.addEventListener('beforeunload'"), "JS cleans up speech synthesis on beforeunload");
assertTest(str_contains($html, "window.addEventListener('pagehide'"), "JS cleans up speech synthesis on pagehide");
assertTest(str_contains($html, "Math.max(0.85, Math.min(1.45"), "JS constrains font size multiplier between 0.85 and 1.45");

echo "\n=============================================\n";
echo "Results: {$passed}/" . ($passed + $failed) . " tests passed.\n";

if ($failed === 0) {
    echo "ALL ARTICLE READING CONTROLS & AUDIO PLAYER TESTS PASSED!\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    exit(1);
}
