# API Specification

## Infinity Blog - REST JSON Contract

**Version:** `/api/v1`  
**Status:** Authentication, category management, author post lifecycle, public post reads, admin post management, admin author-account management, safe admin audit-log queries, and authorized article-summary generation are implemented. Oracle MySQL integration remains unverified.

---

## 1. General conventions

- Base path: `/api/v1`.
- Request/response format: JSON, except where the server-rendered repository already defines another approved standard.
- Authentication: PHP session cookie for browser clients.
- State-changing browser requests: valid session-bound `X-CSRF-Token` required, including login and logout.
- For state-changing requests, a supplied `Origin` must match the configured `APP_BASE_URL` origin and `Sec-Fetch-Site: cross-site` is rejected before session or database work. Missing browser metadata does not replace the CSRF requirement.
- Session cookie: server-side PHP session; `HttpOnly`, `SameSite=Lax`, and `Secure` for HTTPS/production. Sessions expire after 30 minutes idle or 12 hours absolute. The server-side session stores the user ID and the database `auth_version`; protected requests reload the account from MySQL and reject a version mismatch. Clients cannot set or override this value.
- Production transport: HTTPS.
- The server is authoritative for roles, ownership, post status, category state, and summary generation.
- All request-controlled SQL values must use prepared statements.

### Standard success response

```json
{
  "data": {},
  "meta": {}
}
```

`meta` may be omitted when unnecessary. List responses should include pagination information where pagination is supported. Authenticated author/admin post-list responses also include `dashboard_counts`, computed from MySQL records and independent of the active list filters and page. This uses the existing list operations; no dashboard endpoint is required.

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 10,
    "total": 23,
    "dashboard_counts": {
      "total": 23,
      "draft": 15,
      "published": 8
    }
  }
}
```

For `/author/posts`, counts include only posts owned by the authenticated author. For `/admin/posts`, counts cover all posts system-wide. `meta.total` is the number matching the list filters; `dashboard_counts` is the full unfiltered count within the caller's authorized scope. Public post lists expose only published posts and do not expose management or private counts. The schema has no view tracking, so no view count is returned.

The SRS also keeps a Should requirement for displaying admin user and category totals. The implemented `/admin/users` and `/admin/categories` lists return database-derived `meta.total` values. These optional totals do not replace the required post buckets and do not require a dashboard endpoint.

### Standard error response

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "One or more fields need attention.",
    "fields": {
      "title": "Title is required."
    }
  },
  "request_id": "correlation-id"
}
```

Messages must not contain stack traces, SQL details, filesystem paths, API keys, provider responses, or other internal data. Unexpected server failures are written to the protected PHP error log as a JSON record containing the event name, response request ID, and exception class only; exception messages and request/article contents are omitted.

## 2. Authentication and CSRF

The following routes are implemented under `/api/v1`. The username for the initial administrators is their full name normalized by trimming leading/trailing ASCII spaces and applying lowercase. Their stored login identifiers are `shubham banerjee` and `sounak sikdar`; MySQL's case-insensitive collation supports case-insensitive lookup. The application ignores client-supplied role and user ID fields. There is no public registration route.

Before login, fetch `/auth/csrf` and send the returned token in `X-CSRF-Token`. After login, use the rotated token returned with the safe user identity. Send that token on logout and all future state-changing browser requests. CSRF failures return `403`; missing or malformed login fields return `422`; credential failures use the same generic `401` response for unknown, inactive, and incorrect-password accounts. Login attempts are limited to 5 failed attempts per normalized identifier and 20 failed attempts per source address in a 15-minute fixed window. A global cap of 100,000 failures per 15 minutes fails closed with `429`. The source address comes from the socket's `REMOTE_ADDR`; forwarded headers are ignored. Identifiers and addresses are stored only as keyed HMACs.

### `POST /auth/login`

Implemented. Requires JSON object fields:

```json
{"identifier":"author name","password":"user secret"}
```

Requires a valid session CSRF token in `X-CSRF-Token`. The identifier is trimmed and lowercased before lookup; it is limited to 190 bytes. Passwords are accepted as supplied and limited to 1024 bytes. Only active accounts with a valid stored password hash can authenticate. On success, the server regenerates the session ID, records `last_login_at`, rotates the CSRF token, and returns `data.user` (`id`, `username`, `display_name`, `role`) and `data.csrf_token`. Failed credentials do not establish an authenticated session. Responses never include hashes.

Expected errors: `400` malformed JSON, `413` request body over 16 KiB, `415` non-JSON request, `422` missing/malformed fields, `401` generic invalid credentials, `403` invalid CSRF token, `429` rate limit/capacity reached, and `500` generic server failure.

### `POST /auth/logout`

Implemented. Requires an active authenticated account and valid `X-CSRF-Token`. Invalidates the server-side session and expires the session cookie. Returns `200` with `data.message`; anonymous callers receive `401` and CSRF failures receive `403`.

### `GET /auth/me`

Implemented. Requires an active session, reloads the active account, role, and authentication version from MySQL on each call, and returns `data.user` with `id`, username/display name, and role only. Never returns a password hash or secret configuration. Expired, inactive, or revoked sessions receive `401` and lose authorization.

### `GET /auth/csrf`

Implemented. Returns `data.csrf_token` bound to the current server session. Responses are `Cache-Control: no-store, private`. Do not place the token in a URL.

## 3. Public post endpoints

### `GET /posts`

Implemented. Public and read-only. Returns published posts only. Optional query parameters:

- `q`: keyword, length-limited and safely parameterized.
- `category_id`: valid category ID; only active public categories are selectable for new filtering.
- `page`: integer >= 1.
- `per_page`: bounded integer, default 10, maximum 50.

Each list item returns `id`, `title`, author display name, category summary, current stored `summary` when its source hash matches, a safe text excerpt, `published_at`, and `created_at`. It omits the full body, author account ID, management fields, and private counts. Search text is at most 100 bytes; page size defaults to 10 and is capped at 50. Category filters accept active categories only. The query is parameterized and searches title/body text literally.

### `GET /posts/{id}`

Implemented. Returns the requested published post only. Draft, deleted, missing, or otherwise unpublished posts return the same `404`. It returns the full article text, category, author display name, and publication metadata; a summary is returned only when its stored source hash matches the current title/body. Public reads never call the AI provider.

## 4. Author post endpoints

All endpoints require an active authenticated author or admin, as appropriate. The current user ID comes from the session and is never accepted as an authoritative client field.

### `GET /author/posts`

Implemented. Requires the `author` role and returns that author's posts only, with scoped database-derived `dashboard_counts`. Other authors' post IDs are concealed with `404`. Admins use the implemented `/admin/posts` route for system-wide listings. Optional status/category/search filters and pagination are validated; `dashboard_counts` is independent of these filters.

### `POST /author/posts`

Implemented. Creates a draft. The request accepts `title`, `content`, and optional nullable `category_id`; an optional status must be `draft`. The server ignores no privileged author ID: supplying `author_id` is rejected. Article request bodies are capped at 1 MiB, and content at 512 KiB. Example request:

```json
{
  "title": "Introduction to PHP",
  "content": "Article text...",
  "category_id": 3,
  "status": "draft"
}
```

The server validates title/content and category assignment. Drafts may be uncategorized. A supplied category must be active. The server sets `author_id` from the authenticated session. Create and update require CSRF.

### `GET /author/posts/{id}`

Implemented. Returns a post only if the requester owns it or is an authorized admin. Other authors receive a concealed `404`.

### `PATCH /author/posts/{id}`

Implemented for the owning author. Updates only accept `title`, `content`, and `category_id`; status changes use the explicit publish/unpublish routes. On title/body changes, an existing summary is marked stale. A new category assignment must reference an active category; existing historical assignments can be retained after deactivation.

### `POST /author/posts/{id}/publish`

Implemented for the owning author. Publishes only after title, content, and an active category are present. Sets `published_at` and records an audit event.

### `POST /author/posts/{id}/unpublish`

Implemented for the owning author. Moves a post to draft, clears `published_at`, and records an audit event. CSRF required.

### `DELETE /author/posts/{id}`

Implemented for the owning author. Requires JSON `{"confirm":true}`, server-side ownership validation, and CSRF. The delete and audit event are committed in one transaction; the audit record does not depend on the post surviving.

### `POST /author/posts/{id}/summary`

**Implemented for the owning author.** Requires an active author session, CSRF token, and an empty JSON object. The server loads canonical title/content from MySQL after ownership authorization; no client article fields are accepted. `AI_PROVIDER=openai`, `AI_MODEL`, and `AI_API_KEY` enable the fixed OpenAI Responses API adapter. `AI_TIMEOUT_SECONDS`, `AI_MAX_INPUT_CHARS`, `AI_MAX_OUTPUT_CHARS`, and `AI_SUMMARY_RATE_LIMIT` set bounded runtime limits. Missing or invalid provider configuration returns a safe `503` after authorization and does not block ordinary post operations.

The backend passes bounded title/body text to the provider as untrusted source material, with no tools or database handle. It validates bounded plain-text output, persists summary/status/source hash/timestamp in MySQL, and writes a minimal outcome audit event. Public reads reuse a stored summary only if its status is ready and its source hash matches the current title/body; public reads never call the provider. Provider errors mark a summary failed (or stale if an earlier summary exists), return a safe error, and do not interrupt post creation, editing, or publication. Output for a changed source revision is discarded and returns `409 SUMMARY_SOURCE_CHANGED`.

### `POST /admin/posts/{id}/summary`

**Implemented for admins.** Uses the same body, CSRF, rate-limit, source validation, persistence, and error contract as the author summary operation, with system-wide post access after the admin role check.

## 5. Admin post endpoints

All require an active admin role.

- `GET /admin/posts` - **Implemented.** System-wide list, filterable and paginated; includes database-derived system-wide `dashboard_counts` in response metadata.
- `GET /admin/posts/{id}` - **Implemented.** View a post for moderation/management.
- `PATCH /admin/posts/{id}` - **Implemented.** Edit title, body, or category; ownership cannot be changed through this route.
- `POST /admin/posts/{id}/publish` - **Implemented.** Publish only after validating title, body, and active category.
- `POST /admin/posts/{id}/unpublish` - **Implemented.** Remove from public visibility.
- `POST /admin/posts/{id}/summary` - **Implemented.** Generate a stored summary for any post after admin authorization.
- `DELETE /admin/posts/{id}` - **Implemented.** Requires explicit `{"confirm":true}`, admin authorization, CSRF, and an audit event.

The implementation may reuse application services with explicit permission policy instead of duplicating post logic.

## 6. Category endpoints

### Public/author read

- `GET /categories` - returns active categories with public-safe fields.

### Admin management

- `GET /admin/categories` - list categories and post counts; may include database-derived `meta.total` for the optional admin category count.
- `POST /admin/categories` - create category from `name` and optional `description`; the server generates and returns the unique slug.
- `PATCH /admin/categories/{id}` - edit name and description; when the name changes, the server regenerates and returns the unique slug.
- `POST /admin/categories/{id}/activate` - activate category.
- `POST /admin/categories/{id}/deactivate` - deactivate category.
- `DELETE /admin/categories/{id}` - delete only when no posts reference the category; otherwise return `409 CATEGORY_IN_USE`.

Category mutation endpoints require an active admin session and a valid CSRF token. Names must be unique after whitespace normalization and the database collation. The server generates lowercase URL-safe slugs from category names; if a generated base slug is already used, it appends a deterministic numeric suffix and retries a concurrent slug collision. The database unique constraint remains authoritative. Clients must not supply a slug as an authoritative value. Deactivation preserves existing post links. Deletion is allowed only when no post references the category; the foreign key remains restrictive, and deactivation is preferred.

## 7. Admin user endpoints

- `GET /admin/users` - **Implemented.** Paginated author-account list without password hashes; `meta.total` is the database-derived author count.
- `POST /admin/users` - **Implemented.** Create an author with username, display name, and a 12-to-72-byte password; only `password_hash()` output is stored.
- `PATCH /admin/users/{id}` - **Implemented.** Update display name or set a replacement password. Password changes increment `auth_version` and revoke existing sessions.
- `POST /admin/users/{id}/deactivate` - **Implemented.** Deactivate an author; status and `auth_version` change atomically.
- `POST /admin/users/{id}/activate` - **Implemented.** Reactivate an author and increment `auth_version`.
- Role changes must be separately authorized and audited. Do not permit a user to promote themselves.

All user-management routes require an active admin session; state changes require CSRF. These routes manage author accounts only and reject role, username-update, and status-update fields. Initial administrator provisioning remains a separate trusted CLI operation. No public registration or HTTP admin-creation endpoint exists.

Bootstrap the two requested admin accounts through a trusted CLI/setup command, not public registration or a committed default password.

## 8. Audit endpoints

- `GET /admin/audit-logs` - **Implemented.** Admin-only paginated event list. Accepts `actor_id`, `action`, `entity_type`, inclusive `from`/`to` calendar dates (`YYYY-MM-DD`), `page`, and `per_page` (default 20, maximum 50). Returns only event ID, actor ID, action, entity type, entity ID, and timestamp. Audit metadata is intentionally omitted from API responses.

## 9. HTTP status expectations

| Status | Meaning |
|---|---|
| 200 | Successful read/update/action |
| 201 | Resource created |
| 204 | Successful delete with no response body, if used consistently |
| 400 | Malformed request |
| 401 | Not authenticated |
| 403 | Authenticated but not permitted, invalid CSRF, or a state-changing browser request with a disallowed origin |
| 404 | Resource not found or intentionally concealed by authorization policy |
| 409 | Conflict, such as duplicate category slug |
| 422 | Valid JSON but validation failed |
| 429 | Rate limit exceeded |
| 500 | Generic unexpected server failure |
| 503 | AI summary provider is disabled, unavailable, or returned an invalid result |

## 10. API security checklist

- [x] Implemented protected routes check active server-side sessions; user identity and role come from the trusted database record.
- [x] Implemented identity/role derives from the MySQL user record; author post queries enforce owner scope.
- [x] CSRF is checked for login/logout and all implemented category, author-post, admin-post, and admin-user mutations.
- [x] Category mutations require the admin role and session-bound CSRF; public category reads expose active categories only.
- [x] Admin post and author-account operations require the admin role; state changes require CSRF.
- [x] The API emits no `Access-Control-Allow-Origin` header; state-changing requests with browser metadata must be same-origin, and cross-site Fetch Metadata is rejected. If a separate frontend origin is introduced, configure an explicit origin allowlist before enabling credentialed CORS.
- [x] Implemented login request size and field lengths are bounded.
- [x] IDs and status values are validated for implemented category/post routes.
- [x] Query parameters are validated and parameterized for implemented post lists.
- [x] Authentication responses omit secrets and password hashes.
- [x] Public post endpoints return published content only; draft/unpublished IDs are concealed.
- [x] Summary requests are authorized, CSRF-protected, bounded, rate-limited in MySQL, and never run on public views.
- [x] Author post lists/counts are owner-scoped; admin post lists/counts span database records.
- [x] Admin author management never returns password hashes, only creates `author` roles, and increments `auth_version` on password/status changes.
- [x] Admin audit-log reads require the admin role, use parameterized filters, and omit metadata from responses.
- [x] Category slugs are generated server-side and protected by the database unique constraint.
- [x] The provider adapter receives bounded article text only and has no database handle, tools, or application API access.
