# Security Threat Model and Mitigation Plan

## Infinity Blog

**Version:** 1.0  
**Purpose:** Identify realistic threats and the controls required before release. This is a planning document, not a claim that the code has already passed a security audit.

---

## 1. Assets to protect

- Administrator and author credentials.
- Session cookies and authenticated sessions.
- Draft and published article content.
- User accounts, roles, and category configuration.
- Article summaries and AI-provider credentials.
- Database integrity and availability.
- Audit history and deployment configuration.

## 2. Trust boundaries

1. Browser to PHP API.
2. Public visitor to protected author/admin functions.
3. Author to another author's resources.
4. Application to MySQL.
5. Application to external AI provider.
6. Trusted provider output back into the application.
7. Repository/developer environment to production secrets.

## 3. Threat register

| Threat | Example impact | Required mitigation | Verification |
|---|---|---|---|
| SQL injection | Read/change data through crafted input | Prepared statements, typed validation, least-privilege DB user | Malicious values in login, search, post/category IDs |
| Stored/reflected XSS | Script executes in a reader's or admin's browser | Contextual output encoding; explicit sanitizer if rich HTML is supported; CSP defense in depth | Script-like title/content/summary/category values |
| Broken access control / IDOR | Author edits/deletes another author's post | Server-side role and ownership checks for every operation | Swap post IDs across users |
| Privilege escalation | Author calls admin endpoints or changes own role | Central role policy; ignore client-supplied role fields; audit role changes | Author sends requests to every admin route |
| CSRF | Authenticated browser submits unwanted publish/delete/category change | CSRF tokens, SameSite cookie, Origin checks as extra defense | Missing/invalid token tests |
| Brute force / credential stuffing | Account takeover | Login rate limit, generic errors, secure password hashes, monitoring | Repeated bad logins |
| Session fixation/hijack | Attacker reuses a known session | Session ID regeneration, HttpOnly/Secure/SameSite cookies, idle expiry, logout invalidation, HTTPS | Session ID changes and post-logout tests |
| Draft data leakage | Public sees unpublished article | Public queries enforce published status in server/database query | Try public detail/list with draft ID |
| Category tampering | Author assigns inactive/nonexistent category or mutates category | Admin-only category mutations; validate IDs/status on server | Direct endpoint and crafted category IDs |
| AI prompt injection | Article text attempts to override task or extract secrets | Treat article as untrusted data, no tools/privileges, minimal context, fixed task boundary | Articles containing instruction-like strings |
| AI output injection | Malicious output executes or affects downstream behavior | Store/render plain text, validate and bound output, never evaluate or use in SQL/commands | Mock provider returning HTML/scripts/JSON commands |
| AI cost/resource abuse | Repeated summary calls create unexpected cost or load | Per-user/resource/IP rate limit as appropriate, input/output limits, timeout, concurrency limit, monitoring | Repeated requests, oversized article, provider timeout |
| Provider key exposure | API key leaked to frontend or Git | Server-side secret config, ignore `.env`, redact logs, no key in response | Repository/log/response scan |
| SSRF/config abuse | User forces server to call arbitrary URL | Provider endpoint is deployment configuration only, allowlisted; no URL field from article/request | Submit URL-like input and inspect network behavior |
| Error information disclosure | SQL paths, stack traces, secrets sent to visitor | Generic errors; private structured logs; production debug disabled | Force DB/provider failures |
| CORS misconfiguration | Untrusted origin makes credentialed requests | Strict origin allowlist; no wildcard with credentials; same-origin deployment preferred | Origin/preflight tests |
| Rate-limit bypass | Repeated requests from concurrent sessions | Atomic limiter, account + resource limits, deployment-aware storage | Parallel request test |
| Audit log injection/leak | Fake event data or secrets appear in logs | Server-generated actor/action, allowlisted events, minimal safe metadata | Crafted metadata, verify no secret fields |
| Dependency/config compromise | Vulnerable library or default credentials | Minimal maintained dependencies, updates, secure setup, least privilege | Dependency scan and config review |

## 4. AI-specific security boundary

The summarizer must have no tools and no application authority. It must not be able to execute SQL, call application functions, read files, run shell commands, browse URLs, alter permissions, or select arbitrary providers.

The backend must:

1. Authorize the user before generation.
2. Load the canonical article from MySQL after authorization.
3. Bound article size and request frequency.
4. Send only necessary text to the configured provider.
5. Treat article text as untrusted data even if it contains instructions.
6. Validate returned data as plain text and enforce a maximum length.
7. Store the result with source hash and timestamp.
8. Mark summaries stale when title/body changes.
9. Return safe errors and preserve normal post behavior when the provider fails.
10. Never generate summaries as a side effect of public page views.

Prompt wording is not a complete defense against prompt injection. Privilege isolation and strict output handling are mandatory.

## 5. Required application controls

- Password hashing and verification.
- Session ID regeneration on login.
- Server-side session expiry and logout invalidation.
- CSRF tokens on state-changing browser operations.
- Prepared SQL statements.
- Strict server-side input validation.
- Contextual output encoding.
- Role checks and resource ownership checks.
- Admin-only user/category management.
- Public-only published-post queries.
- Authors can read/manage only their own private posts; admin post access is system-wide and protected by the admin role.
- Dashboard counts are database-derived and scoped to the authenticated author's records or the admin's system-wide scope. No view tracking is performed.
- Rate limiting for login and summary generation.
- Secure headers and HTTPS in production.
- Secrets outside the repository.
- Safe logging and generic production errors.
- Regular backup/restore test and dependency review.

## 6. Incident and failure handling

- If an AI provider fails, show a non-sensitive message and retain the article.
- If the database is unavailable, fail safely and do not report writes as successful.
- If a session expires, ask the user to authenticate again.
- If a protected route receives an unauthorized request, deny it and do not reveal resource details unnecessarily.
- If an exposed credential is discovered, rotate it, review logs/access, and remove it from the active configuration. Removing a value from a Git commit alone does not make a leaked credential safe.

## 7. Release security gate

Do not declare the application ready for public deployment until the security checks in `TEST_PLAN.md` have been executed, the remaining gaps are recorded, production secrets and HTTPS are configured, and backup/restore procedures are known. Do not use the phrase “100% secure” or “attack-proof.”

## 8. Wireframe integration review

Version 1.0 scope decisions: category management remains admin-only; category slugs are generated server-side and protected by a unique database constraint; post deletion is permanent and requires explicit confirmation. View tracking, tags, featured-image uploads, public author profiles, remember-me, password recovery, and trash/restore are deferred. Map category removal to admin-only deactivation, do not expose account data through a public author page, and do not display view counts. Authors must never access another author's private posts. Public requests return published posts only.

AI summarization is in scope only through an authenticated and authorized backend operation. The backend loads canonical article text after permission checks and stores validated summaries in MySQL. The provider adapter receives bounded text and fixed instructions without database credentials, tools, or other application privileges; provider failures must not interrupt post creation, editing, or publication. The page-by-page risk and permission mapping is in `docs/WIREFRAME_BACKEND_MAPPING.md`.

## 9. Phase 2 implementation status and limits

Implemented authentication routes are limited to `/auth/csrf`, `/auth/login`, `/auth/logout`, and `/auth/me`. The session stores only the authenticated user ID and expiry timestamps plus a CSRF token; `AuthGuard` reloads the account status and role from MySQL on protected requests. The named initial administrators are provisioned and rotated through the CLI-only credential tool; no public registration or role-assignment route exists. Login buckets store HMACs keyed by `APP_KEY`, not raw identifiers/IPs, with limits of 5 failures per identifier, 20 per socket address, and 100,000 system-wide per 15 minutes.

The default PHP session handler is local to a host. Multi-node deployments need a configured secure shared session handler before they can provide consistent login behavior. When behind a reverse proxy, `REMOTE_ADDR` must be normalized only by trusted web-server configuration; the app deliberately ignores caller-supplied forwarding headers, so multiple users may share the proxy address limit. Atomic SQL increments limit races, but requests already in flight can exceed a threshold by the concurrent batch size. Unit checks do not replace Oracle MySQL integration; MySQL migrations and limiter behavior remain unverified until tested on MySQL 8.0.16+.

The CLI password-rotation path changes the stored hash but does not revoke sessions already issued for the account. Those sessions expire under the configured limits; incident response needs the host's controlled session-store invalidation procedure until automated revocation is designed and tested.
