# API Specification

## Infinity Blog - REST JSON Contract

**Version:** `/api/v1`  
**Status:** The four authentication routes in Section 2 are implemented. All post, category, user-management, audit, and summary routes remain planned and are not exposed by the current router.

---

## 1. General conventions

- Base path: `/api/v1`.
- Request/response format: JSON, except where the server-rendered repository already defines another approved standard.
- Authentication: PHP session cookie for browser clients.
- State-changing browser requests: valid session-bound `X-CSRF-Token` required, including login and logout.
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

The SRS also keeps a Should requirement for displaying admin user and category totals where existing admin-list data provides them. The planned `/admin/users` and `/admin/categories` list operations may return a database-derived `meta.total` for that use. These optional totals do not replace the required post buckets and do not require a dashboard endpoint.

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

Messages must not contain stack traces, SQL details, filesystem paths, API keys, provider responses, or other internal data.

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

Public. Returns published posts only. Optional query parameters:

- `q`: keyword, length-limited and safely parameterized.
- `category_id`: valid category ID; only active public categories are selectable for new filtering.
- `page`: integer >= 1.
- `per_page`: bounded integer, default 10, maximum 50.

Each list item may include `id`, `title`, `slug` if implemented, author display name, category summary, `summary` only when current, safe excerpt, `published_at`, and timestamps suitable for public display.

### `GET /posts/{id}`

Public. Returns the requested published post only. Draft, deleted, missing, or otherwise unpublished posts must not be exposed. It may return the current stored summary, full article body, category, author display name, and publication metadata.

## 4. Author post endpoints

All endpoints require an active authenticated author or admin, as appropriate. The current user ID comes from the session and is never accepted as an authoritative client field.

### `GET /author/posts`

Returns the authenticated author's own posts with scoped `dashboard_counts`. This route does not grant an author access to another author's posts. Admins use `/admin/posts` for system-wide listings. Optional status/category/search filters and pagination are validated; `dashboard_counts` is independent of these filters.

### `POST /author/posts`

Creates a post. Example request:

```json
{
  "title": "Introduction to PHP",
  "content": "Article text...",
  "category_id": 3,
  "status": "draft"
}
```

The server validates title/content and status. Drafts may have a missing category only if the product implementation allows it; publication requires a valid active category. The server sets `author_id` from session.

### `GET /author/posts/{id}`

Returns a post only if the requester owns it or is an authorized admin.

### `PATCH /author/posts/{id}`

Updates allowed fields after ownership/role check. On title/body changes, mark an existing summary stale. Category assignment must validate that the category exists and is active under the applicable rule.

### `POST /author/posts/{id}/publish`

Publishes the authorized post only when required fields and an active category are valid. Sets `published_at` according to the implementation policy and records an audit event.

### `POST /author/posts/{id}/unpublish`

Moves a post out of public visibility. CSRF required.

### `DELETE /author/posts/{id}`

Deletes the authorized post after UI confirmation. Server-side authorization and CSRF validation are mandatory. Audit logging must not rely on a surviving post foreign key.

### `POST /author/posts/{id}/summary`

Generates or regenerates a summary for a post that the requester may manage. Requires an active authenticated author who owns the post or an admin with permission, plus CSRF, rate limiting, and size limits. The server loads canonical title/content from MySQL after authorization; it does not trust client-submitted article text. The backend operation stores the validated plain-text result and summary metadata in MySQL, which public reads reuse when current. The provider adapter receives bounded text only and has no database credentials, tools, or application privileges. Provider errors return a safe error and must not break post creation, editing, publication, or reading; the post itself remains usable.

## 5. Admin post endpoints

All require an active admin role.

- `GET /admin/posts` - system-wide list, filterable and paginated; includes system-wide `dashboard_counts` in response metadata.
- `GET /admin/posts/{id}` - view a post for moderation/management.
- `PATCH /admin/posts/{id}` - perform explicitly permitted administrative edits.
- `POST /admin/posts/{id}/publish` - publish after validation.
- `POST /admin/posts/{id}/unpublish` - remove from public visibility.
- `DELETE /admin/posts/{id}` - delete with audit event and CSRF protection.

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

Category mutation endpoints require admin role and CSRF. Names must be unique after normalization. The server generates lowercase URL-safe slugs from category names; if a generated base slug is already used, it appends a deterministic numeric suffix and retries a concurrent uniqueness collision. The database unique constraint remains authoritative. Clients must not supply a slug as an authoritative value. Deactivation preserves existing post links. Hard deletion is not a normal operation.

## 7. Admin user endpoints

- `GET /admin/users` - paginated user list without password hashes; includes a database-derived `meta.total` for the optional admin user count.
- `POST /admin/users` - provision an author account with a securely established password or setup flow.
- `PATCH /admin/users/{id}` - update permitted profile/account fields.
- `POST /admin/users/{id}/deactivate` - deactivate an account.
- `POST /admin/users/{id}/activate` - reactivate an account.
- Role changes must be separately authorized and audited. Do not permit a user to promote themselves.

Bootstrap the two requested admin accounts through a trusted CLI/setup command, not public registration or a committed default password.

## 8. Audit endpoints

- `GET /admin/audit-logs` - admin-only, paginated safe event list. Filters may include actor, action, entity type, and date range. Do not return secrets, password hashes, full provider payloads, or full article bodies.

## 9. HTTP status expectations

| Status | Meaning |
|---|---|
| 200 | Successful read/update/action |
| 201 | Resource created |
| 204 | Successful delete with no response body, if used consistently |
| 400 | Malformed request |
| 401 | Not authenticated |
| 403 | Authenticated but not permitted or CSRF failure, according to chosen consistent policy |
| 404 | Resource not found or intentionally concealed by authorization policy |
| 409 | Conflict, such as duplicate category slug |
| 422 | Valid JSON but validation failed |
| 429 | Rate limit exceeded |
| 500 | Generic unexpected server failure |
| 502/503/504 | Safe AI-provider upstream/service failure, where appropriate |

## 10. API security checklist

- [x] Implemented authentication routes check active server-side sessions where required; future protected routes remain to be implemented.
- [x] Implemented authentication derives identity and role from the MySQL user record; future resource ownership checks remain to be implemented.
- [x] CSRF is checked for implemented login/logout operations; future state-changing routes must also check it.
- [ ] CORS is restricted to known origins if required.
- [x] Implemented login request size and field lengths are bounded.
- [ ] IDs and status values are validated.
- [ ] Query parameters are parameterized.
- [x] Authentication responses omit secrets and password hashes.
- [ ] Public endpoints return published content only.
- [ ] Summary requests are rate-limited and never run on public views.
- [ ] Author post lists and dashboard counts are scoped to that author's records; admin counts are system-wide and database-derived.
- [ ] Category slugs are generated server-side and protected by the database unique constraint.
- [ ] The provider adapter has no database credentials or application tools/privileges.
