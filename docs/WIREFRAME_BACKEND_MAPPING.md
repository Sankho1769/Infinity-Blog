# Infinity Blog Wireframe to Backend Mapping

**Status:** Requirements mapping for future frontend/backend phases

**Reference:** [Structural wireframe](wireframes/infinity-blog-flow.jpeg)

**API contract:** [`API_SPEC.md`](API_SPEC.md)

**Database contract:** [`DATABASE_DESIGN.md`](DATABASE_DESIGN.md)

## Implementation status

The repository has the configuration, PDO, error-envelope, and migration foundation, authentication, category APIs, author post lifecycle, public published-post APIs, admin post/author-account APIs, safe admin audit-log reads, and authorized article-summary APIs. No frontend pages are present. Oracle MySQL integration for the implemented SQL remains unverified.

The PHP filenames shown in the wireframe are reference page names, not files present in the repository. Page status refers to implementation, not whether the wireframe depicts the page.

## Page mapping

| Wireframe page and route label | Access | Data and tables | Actions and planned API operations | Authorization, validation, and errors | Status |
|---|---|---|---|---|---|
| **1. Public Homepage** — `index.php` | Public | Published `posts`; `users.display_name`; active `categories` | List/search/filter/page: `GET /api/v1/posts`; category options: `GET /api/v1/categories`; open article: `GET /api/v1/posts/{id}`; open login page | The post query selects published rows only. Bounded `q`, `category_id`, `page`, and `per_page`; no draft counts or view tracking. | Backend API implemented; frontend page remains planned; MySQL integration unverified |
| **2. Single Post** — `post.php?id={id}` | Public | Published post title/content, current summary, category, author display name, publication metadata from `posts`, `categories`, and `users` | `GET /api/v1/posts/{id}`; return to list | Numeric `{id}`. Missing, deleted, draft, and unpublished posts return 404. Render summary/content as untrusted text. | Backend API implemented; frontend page remains planned; MySQL integration unverified |
| **3. Login** — `login.php` | Public | `users.username`, `password_hash`, `role`, and `status` are backend authentication data; only safe identity fields return | Submit credentials to `POST /api/v1/auth/login`; post-login identity may be checked with `GET /api/v1/auth/me`; send the user to the appropriate author/admin surface using the returned role; logout uses `POST /api/v1/auth/logout` | The contract accepts `identifier` and `password`, not specifically an email address. Return generic credential failures, rate-limit attempts, regenerate the session ID, and never return password hashes. Apply the API's state-changing request/CSRF policy consistently. | Authentication API implemented; UI remains planned |
| **4. Author Dashboard** — `dashboard.php` | Authenticated author; an admin uses separate admin surfaces | The authenticated author's permitted `posts` plus safe `users` identity. The schema has no view counter. | Own posts and counts: `GET /api/v1/author/posts`; identity: `GET /api/v1/auth/me`; navigate to create/edit/manage pages | Database-derived counts are scoped to the session author and independent of list filters/page. No view tracking. | Backend API implemented; frontend page remains planned; MySQL integration unverified |
| **5. Create Post** — `create-post.php` | Authenticated author | `posts`; active category options from `categories` | Load categories: `GET /api/v1/categories`; create draft: `POST /api/v1/author/posts`; publish after save: `POST /api/v1/author/posts/{id}/publish`; generate summary after save: `POST /api/v1/author/posts/{id}/summary` | The session supplies `author_id`. Drafts may omit a category; publishing requires an active category. Mutations require CSRF. Article request bodies are bounded. Summary requests send no client article text. | Backend API implemented; frontend page remains planned; MySQL integration unverified |
| **6. Edit Post** — `edit-post.php?id={id}` | Author who owns the post; admins use authorized admin operations | `posts`, active or historical `categories`, and `users` display name | Load/update: `GET/PATCH /api/v1/author/posts/{id}`; publish/unpublish: corresponding `POST` actions; summary: `POST /api/v1/author/posts/{id}/summary`; admin summary: `POST /api/v1/admin/posts/{id}/summary`; delete: `DELETE /api/v1/author/posts/{id}`; optional public view: `GET /api/v1/posts/{id}` only if published | Ownership is verified on every implemented operation. Mutations require CSRF and delete confirmation. Summary generation reloads canonical text, applies limits, stores only validated output, and discards results if the source changes. | Backend API implemented; frontend page remains planned; MySQL integration unverified |
| **7. Manage Posts** — `posts.php` | Author sees own posts; admin sees system-wide posts on an admin surface | `posts`, `categories`, and safe author display names from `users` | Author list: `GET /api/v1/author/posts`; admin list: `GET /api/v1/admin/posts`; admin detail/edit/publish/unpublish/delete through `/api/v1/admin/posts/{id}` routes | “All Posts” in an author view means only that author's posts. Admin lists/counts span all records. Deletion is permanent and requires confirmation, authorization, and CSRF. Trash/restore is out of scope. | Backend APIs implemented; frontend page remains planned; MySQL integration unverified |
| **8. Categories** — `categories.php` | Public/authors may read active categories; only admins may manage them | `categories`; `posts.category_id` preserves existing assignments | Read active categories: `GET /api/v1/categories`; admin list/create: `GET/POST /api/v1/admin/categories`; edit: `PATCH /api/v1/admin/categories/{id}`; activate/deactivate: `POST /api/v1/admin/categories/{id}/activate` and `/deactivate`; optional unreferenced delete: `DELETE /api/v1/admin/categories/{id}` | Category mutations are admin-only even if the page appears in an author navigation bar. Map the wireframe's delete control to deactivation; the API permits hard deletion only when no posts reference the category, with the restrictive foreign key as final enforcement. The server generates a lowercase URL-safe slug from the name and resolves collisions with numeric suffixes; clients do not supply it. The `created_by` foreign key does not enforce the actor's admin role; the application does. | Backend API implemented; frontend page remains planned; MySQL integration unverified |
| **9. Author Profile** — `author.php?id={id}` | Public author profiles are deferred from Version 1.0 | Current schema has `users.username`, `display_name`, role/status, timestamps, and `posts`; it has no `bio`, avatar, or view counts | No public author-profile endpoint is defined. `GET /api/v1/auth/me` is private to the authenticated user and is not a public profile route. | Do not expose email or other private account fields. Do not create a public directory/profile from the wireframe. | Deferred; not implemented |

Authentication, category, author-post, public-article, admin post/author-account operations, admin audit-log reads, and summary generation noted above have route handlers. Their database integration remains subject to the Oracle MySQL verification gate.

Use the existing API error contract: `401` for an unauthenticated request, `403` for a denied role/CSRF check, `404` for missing or intentionally concealed resources, `409` for uniqueness conflicts, `422` for field validation, `429` for rate limits, and a generic `500` for unexpected failures. Responses must not expose SQL, stack traces, filesystem paths, password hashes, provider details, or secrets.

## Database reconciliation

| Wireframe concept | Current schema | Alignment |
|---|---|---|
| User name, login, password, role | `users.username`, `display_name`, `password_hash`, `role`, `status` | Use `identifier` at login and return `display_name`; never expose `password_hash`. No public email login is specified. |
| Post ownership | `posts.author_id` → `users.id` | Supported by a foreign key. The foreign key does not ensure that the user has the `author` role; authorization still belongs in the future application layer. |
| Category assignment | Nullable `posts.category_id` → `categories.id` | Supports uncategorized drafts. Publication and new assignment must check category activity in application logic. Deactivation preserves existing links. |
| Article lifecycle | `posts.status` (`draft`, `published`), `published_at`, `created_at`, `updated_at` | Supports draft/publication timestamps; no trash or scheduled status exists. |
| Article summary | `summary`, `summary_status`, `summary_source_hash`, `summary_generated_at` on `posts`; rate buckets in `summary_rate_limits` | Implemented through protected author/admin operations. The first provider adapter uses a fixed OpenAI Responses API endpoint. The authorized backend stores in MySQL; the provider adapter receives bounded article text only, with no database handle or tools. |
| Categories | `name`, `slug`, `description`, `is_active`, `created_by`, timestamps | Supports admin-managed categories. The server generates slugs; database uniqueness applies to name and slug. |
| Administrative activity | `audit_logs.actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `created_at` | Supports audit records without requiring the target post to remain after deletion. |
| Views, tags, featured image, author bio/avatar | No columns, media table, or related API contract | Explicitly deferred in Version 1.0; no tracking, tag, media/upload, or public-profile schema/API is part of this scope. |

The wireframe's post identifier route (`post.php?id=...`) is compatible with the documented `{id}` API path. There is no post slug column in the current migration. The category form submits name/description; the backend generates and returns the unique category slug.

## Admin Panel coverage gap

The supplied wireframe does not provide a distinct Admin Panel. The separate Admin Panel remains a product requirement and needs a later frontend-design/wireframe phase covering:

- An admin dashboard with server-derived totals and recent permitted audit events.
- System-wide post management.
- Author account listing and activation/deactivation, with restricted and audited role changes.
- Admin-only category management.
- A safe, paginated audit activity view.

The admin post and author-account API routes carry database-derived list totals. `GET /api/v1/admin/posts` returns system-wide `dashboard_counts` (`total`, `draft`, `published`) independent of list filters. No dashboard endpoint is needed. The author post-list response carries counts scoped to the current author. View counts are deferred.

## Article-summary frontend gap

AI summaries remain in Version 1.0 scope even though the supplied wireframe does not show summary controls or summary states. Backend operations are implemented; a frontend-design pass should cover:

- A deliberate **Generate summary** action after a post has been saved, available only to a user authorized to manage that post.
- `none`, `generating`, `ready`, `failed`, and `stale` display states, plus safe failure and rate-limit feedback.
- A **Regenerate summary** action for authorized users, with appropriate request limits.
- Display of a current stored summary on public article detail; stale or failed summaries must not be presented as current.

The author operation is `POST /api/v1/author/posts/{id}/summary`; admins use `POST /api/v1/admin/posts/{id}/summary`. The server loads canonical article text after authorization, treats content and provider output as untrusted, stores bounded plain text in MySQL, and never runs on a public page view. A provider failure does not interrupt post creation, editing, or publication. Frontend controls and lifecycle states remain unimplemented.

## Additional wireframe-only items and security notes

- **Remember me and password recovery:** deferred from Version 1.0; do not add session or recovery flows.
- **Public author profiles:** deferred; do not expose user records through an invented directory. The supplied About/Authors navigation is not a public profile requirement.
- **Post tags and featured-image uploads:** deferred. No schema or API support exists; do not add uploads from the wireframe.
- **View counters:** deferred. No collection or response contract exists; show database-derived post counts only and do not display fabricated views.
- **Trash/restore:** deferred. Post deletion is permanent after confirmation; do not show a trash tab or add a restore path.
- **Admin-only actions:** frontend visibility is not authorization. Server-side role, ownership, active-account, CSRF, and input checks remain mandatory for every mutation.
- **Draft privacy:** public list/detail/search/category operations return published posts only; unauthorized draft IDs must not reveal content.
- **XSS and AI output:** encode article text and summaries for the output context; never render summary HTML as trusted markup.
- **Private data:** public responses must not expose email, password hashes, role-management fields, or audit metadata.

## Finalized Version 1.0 decisions

- Author and admin dashboards display database-derived `total`, `draft`, and `published` post counts. These use response metadata on the already-planned post-list operations; no endpoint or aggregate table is added.
- View tracking, tags, featured-image uploads, public author profiles, remember-me, password recovery, and trash/restore are deferred. Post deletion is permanent after an explicit confirmation.
- Category slugs are generated server-side from category names and remain unique through normalized validation, collision suffixes, and the existing database uniqueness constraint.
- AI summaries remain in scope. An authorized backend operation loads canonical post text, stores validated summaries in MySQL, and public requests reuse only the current stored result. Provider failure must not interrupt post CRUD or publication; the provider adapter has no database credentials or application privileges.
- The agreed Admin Panel remains required; the supplied wireframe does not replace it. Category management stays admin-only, authors see/manage only their own private posts, and public requests return published posts only.

Authentication, category, author post lifecycle, public post reads, admin post management, admin author-account management, safe admin audit queries, and authorized summary endpoints are implemented. Frontend pages remain planned. The wireframe integration decisions remain unchanged.
