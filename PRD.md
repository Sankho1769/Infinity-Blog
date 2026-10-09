# Product Requirements Document

## Infinity Blog

**Version:** 1.0

**Status:** Baseline for implementation

**Product:** Blog Content Management System

**Primary stack:** PHP and MySQL

---

## 1. Product summary

Infinity Blog is a web-based CMS for managing blog articles. Authors manage their own posts. Administrators manage all posts, author accounts, and the categories available to authors. Public visitors can browse published articles, filter by category, search, and read article pages. An AI-assisted feature can generate and store a concise summary for an article when an authorized user requests it.

## 2. Problem statement

A blog needs a central place to create, revise, publish, and remove content. Managing article content manually or through direct database edits becomes error-prone, while unrestricted access makes it difficult to protect drafts and maintain clear ownership. Readers also need a structured way to discover published articles. Infinity Blog brings article management, category administration, and public reading into one application.

## 3. Product goals

1. Satisfy the supplied CMS problem statement using PHP and MySQL.
2. Make author post management understandable and efficient.
3. Keep public readers separate from authenticated management capabilities.
4. Give administrators controlled authority over accounts, categories, and all posts.
5. Let authors use administrator-created categories without granting authors category administration.
6. Add article summaries without making AI a critical dependency for ordinary publishing or reading.
7. Establish security and testability as implementation requirements from the start.

## 4. Target users

### 4.1 Public visitor

Needs to discover and read published articles without an account. May search by keyword and filter by category.

### 4.2 Author

Needs to sign in, create drafts, edit their own posts, assign an active category, publish or unpublish their own posts, request an article summary, and delete their own posts.

### 4.3 Administrator

Needs a separate protected panel to review system-wide posts, manage author accounts, create and maintain categories, perform authorized post-management actions, and inspect important activity records.

## 5. Roles and authorization summary

| Capability | Public visitor | Author | Admin |
|---|---:|---:|---:|
| Read published posts | Yes | Yes | Yes |
| Search/filter published posts | Yes | Yes | Yes |
| Read drafts | No | Own drafts only in management area | Yes, in admin area |
| Create post | No | Yes | Yes, where needed for administration |
| Edit/delete own post | No | Yes | Yes |
| Edit/delete another author's post | No | No | Yes |
| Request summary for a post | No | Own post | Any permitted post |
| Create/edit/deactivate category | No | No | Yes |
| Manage author accounts | No | No | Yes |
| Assign administrator role | No | No | Restricted to secure administrative procedure / explicitly authorized admin operation |

## 6. Version 1.0 scope

### Must have

- PHP backend and MySQL persistence.
- Secure authentication and session handling.
- At least `admin` and `author` roles.
- Two administrator accounts provisioned through secure setup: `Shubham Banerjee` and `Sounak Sikdar`.
- Admin dashboard.
- Author dashboard.
- Database-derived post counts: total, draft, and published counts for an author's own posts and system-wide counts for admins.
- Create, read, update, publish/unpublish, and delete posts.
- Category creation and maintenance restricted to admins.
- Author category selection from active categories.
- Public article list and detail view.
- Public category filtering and keyword search.
- Stored article summaries generated only after an authorized request.
- Security controls and automated/manual tests for the sensitive routes.

### Should have

- Pagination.
- Summary status display (`none`, `ready`, `stale`, `failed`).
- Admin dashboard user and category totals where available from existing admin data.
- Audit trail for key administrative and publishing actions.
- Clear empty, loading, validation-error, and server-error states.

### Out of scope for Version 1.0

- Comments and user reactions.
- Social networking, followers, direct messages, forums, or campus ERP functions.
- Payments, subscriptions, advertising, or monetization.
- Multiple organizations/tenants.
- Native mobile application.
- Public author self-registration with automatic role assignment.
- View tracking and view-count metrics.
- Post tags.
- Featured-image uploads and other user file uploads.
- Public author profile pages.
- Remember-me login sessions.
- Password recovery/reset workflows.
- Trash/restore workflows. Deleting a post requires explicit confirmation and permanently removes it.
- AI tools, web browsing, code execution, or agentic actions.

## 7. Main workflows

### Author content workflow

1. Author signs in.
2. Author opens the dashboard and chooses **Create Post**.
3. Author enters title and body and selects an active category when ready to publish.
4. Author saves a draft or publishes.
5. Author may request a summary; the backend validates and stores the result.
6. Author edits or deletes only content they are allowed to manage.

### Admin category workflow

1. Admin signs in to the Admin Panel.
2. Admin creates a category with a unique name; the server generates a unique URL-safe slug.
3. The category becomes available to authors while active.
4. Authors can assign it to drafts/posts according to the publishing rules.
5. When a category is no longer needed, admin deactivates it. Existing posts retain the relationship; inactive categories cannot be assigned to new or newly published content.

### Public reading workflow

1. Visitor opens the homepage.
2. The application returns published posts only.
3. Visitor filters by category or searches for a keyword.
4. Visitor opens an article detail page.
5. The page displays the stored summary if it is current; otherwise it may display a plain-text excerpt or omit the summary.

## 8. AI summary product requirements

- An authorized author/admin can request a summary for a post they may manage.
- Summary generation uses a configured server-side provider. No secret is delivered to browser code.
- The source article is treated as untrusted text, not instructions.
- The model receives no tools or application privileges.
- The application validates the response and stores it as plain text.
- Public page views never trigger a new AI request.
- The authorized backend operation stores generated summaries in MySQL; public pages reuse the current stored summary.
- When title/body changes, the prior summary is marked stale and must not be presented as current.
- AI errors must not prevent saving, editing, publishing, or reading the article.
- The provider adapter receives bounded article text only and has no database credentials, tools, or application privileges.
- Generation is subject to input/output limits, timeout, and rate limits.

## 9. Success criteria

Version 1.0 is acceptable when all of the following are demonstrated:

1. Admin and author sign-in work with role enforcement.
2. Authors can create, edit, publish/unpublish, and delete their own posts.
3. An author cannot modify another author's post by changing an ID in a request.
4. Admins can manage posts and categories.
5. Authors can assign an active admin-created category.
6. Public queries show published posts only.
7. Category filters and keyword search return correct public results.
8. An authorized summary request stores a valid summary; public views reuse the stored result.
9. Failed AI requests leave core post operations available.
10. The test plan's core security and functional checks pass.

## 10. Product decisions

- The application uses a simple PHP/MySQL architecture; avoid unnecessary distributed infrastructure.
- The frontend and backend communicate through the API contract in `API_SPEC.md` unless the existing repository already uses a server-rendered pattern that has been explicitly approved.
- Authentication uses PHP server-side sessions for browser flows, with CSRF protections for state-changing requests.
- The two initial administrator login identifiers are their names, normalized by trimming leading/trailing ASCII spaces and lowercasing to `shubham banerjee` and `sounak sikdar`; credentials are provisioned through a trusted CLI workflow only.
- The public blog never displays draft content.
- Category deletion should be conservative: deactivate categories in normal operation; do not orphan or silently reassign posts.
- Category create/edit requests do not accept a client-authoritative slug; the server generates a normalized slug and resolves collisions with a numeric suffix while enforcing database uniqueness.
- Author post operations and dashboard counts are scoped to the authenticated author's own records; admin counts are system-wide.
- No Version 1.0 operation tracks article views.
- The AI provider remains configurable and replaceable.
- Visual styling, color, and visual effects are intentionally not specified in this document.
