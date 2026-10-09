# System Architecture

## Infinity Blog

**Version:** 1.0  
**Architecture style:** Modular PHP monolith with JSON API and MySQL persistence

---

## 1. Architecture goals

- Keep the codebase understandable and maintainable for a small project.
- Separate HTTP handling, authentication/authorization, business rules, database access, and AI provider integration.
- Define a stable contract for an independently developed frontend.
- Ensure the AI summarizer cannot perform application actions.
- Make security-sensitive behavior testable.

## 2. Recommended stack

| Layer | Recommendation |
|---|---|
| Backend runtime | A supported PHP 8.x release compatible with hosting |
| HTTP layer | PHP controllers/routes returning JSON under `/api/v1` |
| Database | MySQL 8.x or compatible supported release |
| Browser auth | PHP sessions with secure cookie settings |
| Data access | PDO or mysqli with prepared statements; use one consistently |
| Frontend | Existing agreed frontend stack; integrate against `API_SPEC.md` |
| Local environment | XAMPP or equivalent for the academic setup |
| AI | Provider adapter configured server-side; provider/model chosen in environment configuration |

The repository is the first source of truth. Codex must inspect existing files and dependencies before choosing or replacing frameworks.

## 3. High-level architecture

```text
Browser Frontend
   | HTTPS / JSON, session cookie, CSRF token
   v
PHP HTTP/API Layer (/api/v1)
   |
   +--> Authentication & Session Service
   |
   +--> Authorization Policy (admin / author / ownership)
   |
   +--> Post Service -------------------+
   |                                    |
   +--> Category Service                v
   |                                  MySQL
   +--> Admin/User Service       users, posts, categories,
   |                             audit_logs, rate limits
   +--> Article Summary Service
             |
             v
       Provider Adapter
             |
             v
      Configured AI Provider
      (text input/output only; no tools)
```

## 4. Module responsibilities

### HTTP/API layer

- Parse requests and return consistent JSON responses.
- Validate request method and content type.
- Validate session and CSRF token as appropriate.
- Map application errors to safe HTTP status codes.
- Generate/request a correlation ID for diagnostics.

### Authentication service

- Verify username/password.
- Create and destroy sessions.
- Regenerate session ID after authentication.
- Update last-login information where used.
- Apply MySQL-backed login rate limits.
- Use generic credential failures and never return password hashes.

Current implementation is in `app/Auth/`: `AuthenticationService` coordinates verification and limiting, `UserRepository` uses prepared PDO statements, `SessionManager` owns the server-side session and CSRF token, `AuthGuard` reloads active role data from MySQL, and `AdminProvisioner` is CLI-only. `DatabaseLoginAttemptLimiter` stores only HMAC bucket hashes in the additive `login_rate_limits` migration.

### Authorization policy

- Enforce `admin` versus `author` roles server-side.
- Enforce post ownership for author operations.
- Enforce active-account status.
- Avoid authorization logic based solely on frontend visibility.

### Post service

- Create, edit, publish/unpublish, retrieve, and delete posts.
- Validate post state and category selection.
- Ensure public listing/detail operations return published posts only.
- Calculate dashboard counts from `posts` records: author queries are constrained to the authenticated author's `author_id`; admin queries cover all posts. Do not report view counts.
- Mark a summary stale when the title or body changes.
- Write audit records for sensitive operations.

### Category service

- Admin-only create/update/activate/deactivate operations.
- Generate normalized, unique category slugs on the server from category names; resolve collisions with a deterministic numeric suffix and retain the database unique constraint.
- List active categories for author forms and public filtering.
- Preserve existing relationships when a category is deactivated.

### Admin/user service

- List and manage author accounts.
- Enable/deactivate accounts.
- Permit role changes only through explicitly authorized operations.
- Never expose password hashes in API responses.

### Article summary service

- Expose generation only through an authenticated, authorized backend operation; the backend loads canonical post text after checking ownership/admin permission and persists validated output through the normal data-access layer.
- Authorize requester before generation.
- Enforce per-article size limits, timeout, and rate limits.
- Build the provider request with a fixed summarization instruction and the article clearly delimited as untrusted data.
- Call only the configured provider endpoint.
- Validate plain-text output, bound length, store summary metadata, and handle failure without failing the article operation.
- Give the provider adapter only bounded text input and access to the configured provider. It receives no database credentials, tools, shell execution, filesystem access, arbitrary URL access, or other application privileges.
- Treat provider failure as an isolated summary failure; post creation, editing, publication, and public reading continue to work.

### Data access layer

- Centralize database creation/configuration.
- Use prepared statements.
- Implement transactions for multi-step updates where needed.
- Apply foreign keys and indexes.
- Never include internal query errors in public responses.

## 5. Deployment shape

Preferred production arrangement:

- Frontend and API are served from one origin, or are placed behind a reverse proxy under the same site.
- HTTPS is enabled.
- Session cookies are `Secure`, `HttpOnly`, and use an appropriate `SameSite` setting.
- If separate origins are unavoidable, configure a strict CORS allowlist and credentials handling; never use wildcard origins with credentialed requests. Maintain CSRF and Origin checks.
- Secrets are configured in environment variables or a protected server configuration outside version control.

## 6. Authentication and request security

- Browser session cookie identifies the authenticated user.
- A CSRF token is required for state-changing requests.
- The session stores user ID and expiry timestamps; each protected request reloads the active role from the trusted user record.
- Login regenerates the session ID and rotates the session-bound CSRF token.
- Logout destroys the server session and expires the cookie.
- Cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` whenever the configured or request transport is HTTPS. Idle expiry is 30 minutes; absolute expiry is 12 hours.
- Login and logout both require a valid CSRF token. Failed logins are limited to 5 per identifier and 20 per socket source in a 15-minute window.
- Request origin validation is an additional layer, not a replacement for CSRF tokens.
- Role and resource authorization are checked for every protected route.

The current HTTP entry point is `public/index.php`; it exposes authentication, admin-only category management, author post lifecycle, public published-post reads, system-wide admin post management, and admin author-account management. Audit queries and summary routes remain later phases. `AuthGuard` is the reusable role boundary; author post operations enforce ownership in parameterized repository queries, while admin operations use separate guarded routes.

## 7. Data flow: create post

1. Frontend submits title, content, selected category, status, and CSRF token.
2. API authenticates the session.
3. Server validates input and status.
4. Server validates any category ID against the categories table and current category state.
5. Server derives `author_id` from session; the client cannot choose it.
6. Prepared insert saves the post.
7. Audit event is stored where applicable.
8. API returns the post representation without sensitive fields.

## 8. Data flow: summarize article

1. Frontend requests summary generation for a post.
2. API authenticates and confirms requester is the owner or authorized admin.
3. Rate limiter checks account, resource, and provider limits.
4. Backend loads current title/body from MySQL; never trusts client-supplied article text as the canonical source.
5. Backend passes a size-limited payload to the configured provider.
6. Provider output is validated as bounded plain text.
7. Backend stores summary, source content hash, status, and generation timestamp.
8. On any failure, summary status becomes `failed` or remains stale; the existing post remains usable.

## 9. Key design constraints

- No public AI generation endpoint.
- No summary regeneration on public article reads.
- No AI model authority over application actions.
- No arbitrary provider URL configured by an ordinary user.
- No permanent category deletion that breaks referenced posts.
- No public access to draft posts.
- No shared admin password hardcoded in code or migrations.
- Do not introduce queues, Redis, microservices, Docker, or other infrastructure without a clear need and an approved scope change.

## 10. Extensibility

The initial design allows categories, search, summaries, roles, and audit history to evolve independently. New modules must follow the same rules: clear contract, server-side authorization, prepared statements, output handling, and tests.
