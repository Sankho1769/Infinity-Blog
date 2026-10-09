# Infinity Blog Wireframe to Backend Mapping

**Status:** Requirements mapping for future frontend/backend phases

**Reference:** [Structural wireframe](wireframes/infinity-blog-flow.jpeg)

**API contract:** [`API_SPEC.md`](../API_SPEC.md)

**Database contract:** [`DATABASE_DESIGN.md`](../DATABASE_DESIGN.md)

## Implementation status

The repository has the Phase 1 configuration, PDO, error-envelope, and migration foundation. It has no frontend pages, HTTP router, or live API endpoints. Every `/api/v1` operation named below is **planned** unless explicitly marked optional; the existence of an API contract does not mean its routes are implemented.

The PHP filenames shown in the wireframe are reference page names, not files present in the repository. Page status refers to implementation, not whether the wireframe depicts the page.

## Page mapping

| Wireframe page and route label | Access | Data and tables | Actions and planned API operations | Authorization, validation, and errors | Status |
|---|---|---|---|---|---|
| **1. Public Homepage** — `index.php` | Public | Published `posts`; `users.display_name`; active `categories` | List/search/filter/page: `GET /api/v1/posts`; category options: `GET /api/v1/categories`; open article: `GET /api/v1/posts/{id}`; open login page | The post query must select published rows only. Bound `q`, `category_id`, `page`, and `per_page`; never reveal draft counts or content. Handle empty results, invalid filters, and service errors safely. | Planned |
| **2. Single Post** — `post.php?id={id}` | Public | Published post title/content, current summary, category, author display name, publication metadata from `posts`, `categories`, and `users` | `GET /api/v1/posts/{id}`; return to list | The API contract uses a numeric `{id}`. Do not infer a post slug from the wireframe. Missing, deleted, draft, and unpublished posts must not disclose article data. Render summary/content as untrusted text according to the eventual content format. | Planned |
| **3. Login** — `login.php` | Public | `users.username`, `password_hash`, `role`, and `status` are backend authentication data; only safe identity fields return | Submit credentials to `POST /api/v1/auth/login`; post-login identity may be checked with `GET /api/v1/auth/me`; send the user to the appropriate author/admin surface using the returned role; logout uses `POST /api/v1/auth/logout` | The contract accepts `identifier` and `password`, not specifically an email address. Return generic credential failures, rate-limit attempts, regenerate the session ID, and never return password hashes. Apply the API's state-changing request/CSRF policy consistently. | Planned |
| **4. Author Dashboard** — `dashboard.php` | Authenticated author; an admin uses separate admin surfaces | The author's permitted `posts` plus safe `users` identity. The schema has no view counter. | Own posts: `GET /api/v1/author/posts`; identity: `GET /api/v1/auth/me`; navigate to create/edit/manage pages | The server derives the user from the session and returns only that author's posts. Dashboard post totals are not specified in the current API response contract; the `Total Views` card has no schema or API support and is out of scope pending a product decision. | Planned; metrics gap |
| **5. Create Post** — `create-post.php` | Authenticated author | `posts`; active category options from `categories` | Load categories: `GET /api/v1/categories`; save draft/create: `POST /api/v1/author/posts`; publish after save: `POST /api/v1/author/posts/{id}/publish`; navigate back to dashboard | The session supplies `author_id`. Drafts may omit a category; publishing requires an existing active category. Validate title/content/status server-side and use CSRF for state changes. Summary generation is a later feature and is not available in Phase 1. | Planned |
| **6. Edit Post** — `edit-post.php?id={id}` | Author who owns the post; admins use authorized admin operations | `posts`, active or historical `categories`, and `users` display name | Load: `GET /api/v1/author/posts/{id}`; update: `PATCH /api/v1/author/posts/{id}`; publish/unpublish: corresponding `POST` actions; delete: `DELETE /api/v1/author/posts/{id}`; optional public view: `GET /api/v1/posts/{id}` only if published | Verify ownership on every operation, including reads. Do not trust the ID or hidden buttons as permission checks. Require CSRF on mutations. Published time is controlled by the backend; no scheduled-publishing contract exists. Summary actions depend on the later AI phase. | Planned |
| **7. Manage Posts** — `posts.php` | Author sees own posts; admin sees system-wide posts on an admin surface | `posts`, `categories`, and safe author display names from `users` | Author list: `GET /api/v1/author/posts`; admin list: `GET /api/v1/admin/posts`; edit/delete through the corresponding authorized routes; view a published post through `GET /api/v1/posts/{id}` and a draft through the authorized management `GET`; filters and pagination follow the API contract | “All Posts” in an author view means all posts that author may manage, not every author's posts. Admin access requires the `admin` role. Delete requires confirmation, authorization, and CSRF. The wireframe's “Trashed” tab is unsupported: the schema has no trash state and the API defines deletion, not restore. | Planned |
| **8. Categories** — `categories.php` | Public/authors may read active categories; only admins may manage them | `categories`; `posts.category_id` preserves existing assignments | Read active categories: `GET /api/v1/categories`; admin list/create: `GET/POST /api/v1/admin/categories`; edit: `PATCH /api/v1/admin/categories/{id}`; activate/deactivate: `POST /api/v1/admin/categories/{id}/activate` and `/deactivate` | Category mutations are admin-only even if the page appears in an author navigation bar. Map the wireframe's delete control to deactivation; hard delete is not a normal API operation and referenced categories use restrictive deletion. The database requires a unique slug; the future form must collect or generate one. The `created_by` foreign key does not enforce the actor's admin role; the application must. | Planned |
| **9. Author Profile** — `author.php?id={id}` | Optional public profile; no public profile access is approved | Current schema has `users.username`, `display_name`, role/status, timestamps, and `posts`; it has no `bio`, avatar, or view counts | No public author-profile endpoint is defined. `GET /api/v1/auth/me` is private to the authenticated user and is not a public profile route. | Do not expose email or other private account fields. A public author page remains optional and requires an explicit product/API decision. `Total Views` is unsupported. | Optional; not implemented |

All endpoints in this table are planned. The current foundation contains no route handlers.

Use the existing API error contract: `401` for an unauthenticated request, `403` for a denied role/CSRF check, `404` for missing or intentionally concealed resources, `409` for uniqueness conflicts, `422` for field validation, `429` for rate limits, and a generic `500` for unexpected failures. Responses must not expose SQL, stack traces, filesystem paths, password hashes, provider details, or secrets.

## Database reconciliation

| Wireframe concept | Current schema | Alignment |
|---|---|---|
| User name, login, password, role | `users.username`, `display_name`, `password_hash`, `role`, `status` | Use `identifier` at login and return `display_name`; never expose `password_hash`. No public email login is specified. |
| Post ownership | `posts.author_id` → `users.id` | Supported by a foreign key. The foreign key does not ensure that the user has the `author` role; authorization still belongs in the future application layer. |
| Category assignment | Nullable `posts.category_id` → `categories.id` | Supports uncategorized drafts. Publication and new assignment must check category activity in application logic. Deactivation preserves existing links. |
| Article lifecycle | `posts.status` (`draft`, `published`), `published_at`, `created_at`, `updated_at` | Supports draft/publication timestamps; no trash or scheduled status exists. |
| Article summary | `summary`, `summary_status`, `summary_source_hash`, `summary_generated_at` on `posts` | Supports the future summary workflow; no provider or endpoint is implemented. |
| Categories | `name`, `slug`, `description`, `is_active`, `created_by`, timestamps | Supports admin-managed categories. Database uniqueness applies to name and slug. |
| Administrative activity | `audit_logs.actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `created_at` | Supports audit records without requiring the target post to remain after deletion. |
| Views, tags, featured image, author bio/avatar | No columns, media table, or related API contract | Not supported by the written product/database baseline. Do not add them from the wireframe alone. Arbitrary file uploads are explicitly out of scope unless separately specified and threat-modeled. |

The wireframe's post identifier route (`post.php?id=...`) is compatible with the documented `{id}` API path. There is no post slug column in the current migration. The category page's visible name/description fields are incomplete for the schema because a unique slug is also required.

## Admin Panel coverage gap

The supplied wireframe does not provide a distinct Admin Panel. The separate Admin Panel remains a product requirement and needs a later frontend-design/wireframe phase covering:

- An admin dashboard with server-derived totals and recent permitted audit events.
- System-wide post management.
- Author account listing and activation/deactivation, with restricted and audited role changes.
- Admin-only category management.
- A safe, paginated audit activity view.

The API contract already lists planned admin post, user, category, and audit-log operations. It does not define an admin dashboard summary endpoint or aggregate response shape. Do not invent one during frontend integration; decide and document the contract before building dashboard counters.

## Article-summary workflow gap

The wireframe does not show summary controls or summary states. A later wireframe should cover:

- A deliberate **Generate summary** action after a post has been saved, available only to a user authorized to manage that post.
- `none`, `generating`, `ready`, `failed`, and `stale` display states, plus safe failure and rate-limit feedback.
- A **Regenerate summary** action for authorized users, with appropriate request limits.
- Display of a current stored summary on public article detail; stale or failed summaries must not be presented as current.

The planned operation is `POST /api/v1/author/posts/{id}/summary`. It must load canonical article content on the backend after authorization, treat the content and provider output as untrusted, store bounded plain text, and never run from a public page view. The AI service must not receive database, shell, filesystem, or administrative privileges. None of this workflow is implemented yet.

## Additional wireframe-only items and security notes

- **Remember me / forgot password:** no matching API, session policy, or recovery requirement is in the current contract. Leave out until specified.
- **Authors / About navigation:** no corresponding page or API is defined; treat as optional and do not expose user records through an invented directory.
- **Post tags and featured-image upload:** no schema or API support. Uploads add file-validation, storage, and serving risks; arbitrary uploads are out of scope in the PRD.
- **View counters:** no collection, authorization, retention, or response contract exists. Do not display fabricated counts.
- **Admin-only actions:** frontend visibility is not authorization. Server-side role, ownership, active-account, CSRF, and input checks remain mandatory for every mutation.
- **Draft privacy:** public list/detail/search/category operations return published posts only; unauthorized draft IDs must not reveal content.
- **XSS and AI output:** encode article text and summaries for the output context; never render summary HTML as trusted markup.
- **Private data:** public responses must not expose email, password hashes, role-management fields, or audit metadata.

## Contract decisions to resolve before frontend implementation

1. Decide whether author/admin dashboard aggregates are needed and define their API response. A total-views metric additionally needs an approved measurement requirement and data model.
2. Confirm that tags, featured-image uploads, public author pages, password recovery, remember-me behavior, and a trash/restore workflow are excluded or separately specified.
3. Design the missing Admin Panel and article-summary workflow before integrating those screens.
4. Decide how the UI obtains or generates the category slug.

No new endpoint, database field, or feature is approved by this mapping. Keep the current `API_SPEC.md` and migration as the contract until these decisions are made.
