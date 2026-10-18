<?php
declare(strict_types=1);

$cssPath = __DIR__ . '/../public/assets/css/style.css';
if (!file_exists($cssPath)) {
    echo "FAIL: style.css not found\n";
    exit(1);
}

$css = file_get_contents($cssPath);

$passed = 0;
$failed = 0;

function assertRule(bool $condition, string $msg): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS: {$msg}\n";
    } else {
        $failed++;
        echo "FAIL: {$msg}\n";
    }
}

echo "=== Testing CSS Layout Fixes & Root Cause Elimination ===\n";

// 1. Check root cause: percentage flex/width on .homepage-sidebar inside CSS grid must be completely removed
assertRule(!preg_match('/\.homepage-sidebar\s*\{[^}]*width:\s*calc\(\s*27%/s', $css), "Eliminated faulty percentage width calc(27% - 16px) on .homepage-sidebar");
assertRule(!preg_match('/\.homepage-sidebar\s*\{[^}]*flex:\s*0\s+0\s+27%/s', $css), "Eliminated faulty flex: 0 0 27% on .homepage-sidebar");

// 2. Check stable CSS Grid on .homepage-two-column-layout
assertRule(preg_match('/\.homepage-two-column-layout\s*\{[^}]*display:\s*grid/s', $css) === 1, ".homepage-two-column-layout uses display: grid");
assertRule(preg_match('/\.homepage-two-column-layout\s*\{[^}]*grid-template-columns:[^;]*minmax\(0,\s*1fr\)[^;]*minmax\(310px,\s*350px\)/s', $css) === 1, ".homepage-two-column-layout uses minmax(0, 1fr) minmax(310px, 350px)");

// 3. Check min-width: 0 on both children to prevent flex/grid blowouts
assertRule(preg_match('/\.homepage-main-column\s*\{[^}]*min-width:\s*0/s', $css) === 1, ".homepage-main-column has min-width: 0");
assertRule(preg_match('/\.homepage-sidebar\s*\{[^}]*min-width:\s*0/s', $css) === 1, ".homepage-sidebar has min-width: 0");

// 4. Check Desktop permanent navigation sidebar width is ~280px
assertRule(preg_match('/\.app-left-sidebar\s*\{[^}]*width:\s*280px/s', $css) === 1, ".app-left-sidebar has width: 280px on desktop");
assertRule(preg_match('/\.app-left-sidebar\s*\{[^}]*min-width:\s*280px/s', $css) === 1, ".app-left-sidebar has min-width: 280px");

// 5. Check sidebar card and rows two-part flex layout
assertRule(preg_match('/\.sidebar-row\s*\{[^}]*display:\s*flex/s', $css) === 1, ".sidebar-row has display: flex");
assertRule(preg_match('/\.sidebar-row-info\s*\{[^}]*flex:\s*1\s+1\s+auto/s', $css) === 1, ".sidebar-row-info is flexible with flex: 1 1 auto");
assertRule(preg_match('/\.sidebar-row-info\s*\{[^}]*min-width:\s*0/s', $css) === 1, ".sidebar-row-info has min-width: 0 for clean ellipsis");
assertRule(preg_match('/\.sidebar-row-action\s*\{[^}]*flex-shrink:\s*0/s', $css) === 1, ".sidebar-row-action has flex-shrink: 0 to prevent squishing");
assertRule(preg_match('/\.btn-community-join\s*\{[^}]*white-space:\s*nowrap/s', $css) === 1, ".btn-community-join has white-space: nowrap");

// 6. Check Feed styles from Screenshot 2
assertRule(preg_match('/\.feed-header-section\s*\{/s', $css) === 1, "Contains .feed-header-section styles");
assertRule(preg_match('/\.feed-composer-card\s*\{/s', $css) === 1, "Contains .feed-composer-card styles");
assertRule(preg_match('/\.feed-tabs-bar\s*\{/s', $css) === 1, "Contains .feed-tabs-bar styles");
assertRule(preg_match('/\.feed-card\s*\{/s', $css) === 1, "Contains .feed-card styles");
assertRule(preg_match('/\.feed-card-main\s*\{/s', $css) === 1, "Contains .feed-card-main styles");
assertRule(preg_match('/\.feed-card-actions\s*\{/s', $css) === 1, "Contains .feed-card-actions styles");
assertRule(preg_match('/\.feed-card-media\s*\{/s', $css) === 1, "Contains .feed-card-media styles");

// 7. Check responsive breakpoints (tablet & mobile)
preg_match('/@media\s*\(max-width:\s*1024px\)\s*\{(.*?)\}\s*@media/s', $css, $tabletMatch);
$tabletCss = $tabletMatch[1] ?? '';
assertRule(str_contains($tabletCss, '.homepage-two-column-layout') && str_contains($tabletCss, 'grid-template-columns: 1fr'), "Tablet breakpoint collapses layout into single column");
assertRule(str_contains($css, 'flex-direction: column-reverse'), "Mobile breakpoint stacks feed card elements gracefully");

echo "\n=== CSS Layout Verification Summary: {$passed} passed, {$failed} failed. ===\n";
if ($failed > 0) exit(1);
