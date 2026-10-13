# Antigravity prompt — integrate Infinity Blog mobile claymorphism assets

Work inside the existing Infinity Blog codebase. Use the reference images in this pack to guide the mobile UI; do not insert screenshots as the application UI and do not use screenshots as button/card backgrounds.

## Goal
- Mobile 320–767px: polished claymorphism using pastel blue, lavender, and white.
- Tablet 768–1023px: balanced hybrid layout.
- Desktop 1024px and above: preserve the existing glassmorphism appearance.
- Keep the current functionality, routes, authentication, permissions, backend, database, and branding intact.

## Reference images to inspect
- `reference/screens/01_home_feed.png`
- `reference/screens/02_article_view_report_button.png`
- `reference/screens/03_report_post_modal.png`
- `reference/screens/04_notifications.png`
- `reference/screens/05_profile_page.png`
- `reference/screens/06_mobile_navigation_drawer.png`
- `reference/components/` for buttons, fields, chips, status tags, article cards, mobile nav, icon style, and surfaces.

## Required implementation
1. Inspect the actual project structure, CSS architecture, breakpoints, and existing shared components first.
2. Build a small, consistent mobile-only set of reusable CSS classes/design tokens; avoid duplicated component implementations.
3. Apply clay surfaces to cards, buttons, inputs, chips, tabs, menus, modal containers, notification items, profile panels, and admin report cards.
4. Recreate icons with the project's existing icon library or clean inline SVG. The cropped PNG tiles are references, not mandatory UI images.
5. Ensure a tactile pressed state, visible keyboard focus, disabled/loading states, accessible contrast, and touch targets around 44×44 CSS pixels where practical.
6. The report post action/modal and admin Reported Posts area must remain fully functional and responsive.
7. Avoid page-wide horizontal overflow. Use responsive cards or contained table scrolling for admin tables.
8. Honor `prefers-reduced-motion`, keep shadows modest, and do not add heavy blur effects on mobile.
9. Do not alter database or server behavior as part of this visual change.
10. Preserve all existing changes and user data. Do not push to GitHub. Do not overwrite `.env` or print credentials.

## Verification
Test at 320, 360, 375, 390, 414, 430, 768, 1024, 1280, and 1440 CSS px. Run existing relevant regression tests and PHP lint. Report only viewports/tests actually checked, including failures and skipped tests. Review Git status before editing; do not discard pre-existing changes. Commit locally only if consistent with the repository workflow; never push.

## Final report
Summarize files changed, visual changes, viewport verification, tests passed/failed/skipped, remaining defects, Git status/commit hash, overall Infinity Blog progress estimate, and realistic remaining-time estimate. Do not claim production readiness based only on lint or mocked screenshots.
