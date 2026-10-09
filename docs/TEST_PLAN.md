# Test Plan and Acceptance Suite

## Infinity Blog

**Version:** 1.0  
**Purpose:** Define how functional behavior and security boundaries are verified.

---

## 1. Test strategy

Use a combination of unit tests for validators/services, integration tests for database and API behavior, and manual end-to-end checks for the frontend. Security-sensitive tests should include both permitted and denied behavior. Keep test data synthetic and never use production credentials.

## 2. Test environment

- Supported PHP runtime matching production.
- MySQL test database separate from development/production.
- Test administrator and author accounts with unique passwords.
- AI provider mocked for deterministic error/output tests; use a real provider only in controlled integration tests with strict budget/rate limits.
- Debug logs must not disclose passwords or provider keys.

### Foundation and authentication unit checks

Run `php tests/run.php` for configuration validation, production TLS checks, `.env` parsing/override behavior, generic PDO connection failure handling, migration rollback protection, and secret-file ignore checks. The isolated SQLite/service-double cases also verify successful/failed/unknown/inactive login, missing and malformed fields, role derivation despite client-supplied role/user ID, session ID regeneration, idle expiry, logout invalidation, CSRF rejection/acceptance, limiter thresholds, administrator provisioning/rotation, database-backed auth-version revocation, unrelated-session preservation, and duplicate refusal. These unit tests do not verify MySQL syntax, constraints, or concurrency behavior.

MySQL integration checks use Oracle MySQL 8.0.16 or newer and require `APP_ENV=test`, `INFINITY_BLOG_RUN_DB_TESTS=1`, and explicit `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_DATABASE`, `TEST_DB_USERNAME`, and `TEST_DB_PASSWORD`. The database must be fresh/empty, its name must end exactly in `_test`, and its account must be dedicated and non-root. Remote test hosts also require `TEST_DB_SSL_CA`. Integration never reads `DB_*` as a fallback. If integration is enabled but settings are missing or invalid, the runner fails before making a connection. If integration is not enabled, the runner reports it as skipped; a skip is not a passing schema verification.

### Reproducible MySQL foundation run

1. Start a supported Oracle MySQL server and confirm its version with `SELECT VERSION();`. Do not substitute MariaDB.
2. Through a private MySQL administration session, create the disposable test schema and a separate test account. Replace the sample password before execution:

   ```sql
   CREATE DATABASE infinity_blog_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'blog_test'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_PRIVATE_TEST_SECRET';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP
     ON infinity_blog_test.* TO 'blog_test'@'127.0.0.1';
   ```

3. In PowerShell, set the non-secret test settings. Omit `TEST_DB_SSL_CA` only when connecting to loopback MySQL:

   ```powershell
   $env:APP_ENV = 'test'
   $env:INFINITY_BLOG_RUN_DB_TESTS = '1'
   $env:TEST_DB_HOST = '127.0.0.1'
   $env:TEST_DB_PORT = '3306'
   $env:TEST_DB_DATABASE = 'infinity_blog_test'
   $env:TEST_DB_USERNAME = 'blog_test'
   # For remote MySQL, set this to the protected trusted-CA PEM path:
   # $env:TEST_DB_SSL_CA = 'C:\protected\mysql-ca.pem'
   ```

   Supply `TEST_DB_PASSWORD` through a private environment/secret mechanism. Do not put it in a command argument or shell history.
4. Run `& 'C:\xampp\php\php.exe' tests/run.php`. The suite refuses any nonempty test schema before migration. It verifies the supported server version, clean execution and no-op rerun of all four migrations, the `auth_version` column, required indexes, uniqueness/check/foreign-key behavior, category repository persistence/slugs/audit/deactivation/deletion rules, MySQL post persistence/transitions/ownership scopes, author-account creation/password/session revocation, MySQL summary persistence/source-race handling and HMAC rate limits, administrator provisioning, login-limiter behavior, and rollback confirmation. It performs the confirmed destructive rollback only after the clean-schema check, then reapplies all four migrations to leave the schema reproducible.
5. If tests stop with a nonempty database message, do not run a drop/reset against it. Create a new empty database ending in `_test` and point `TEST_DB_*` to that database.

## 3. Functional tests

### Authentication implemented in Phase 2

| ID | Scenario | Expected result |
|---|---|---|
| AU-001 | Active author submits correct password | Session ID rotates; response contains safe author identity and a new CSRF token |
| AU-002 | Incorrect, unknown, or inactive account | Same generic credential failure; no authenticated session established |
| AU-003 | Missing or malformed credentials | Bounded validation response; no authentication |
| AU-004 | Authenticated request after idle/absolute expiry | Session authorization cleared; `401` |
| AU-005 | Logout with valid CSRF | Session server data destroyed and cookie expired; later protected access receives `401` |
| AU-006 | Login/logout with missing or invalid CSRF | Rejected with `403` |
| AU-007 | Author supplies `role=admin` or another `user_id` | Those request fields are ignored; role/user ID come from the database and session |
| AU-008 | Author calls admin role guard | Denied; active admin account passes |
| AU-009 | Public request attempts registration | `404`; no public registration or admin-creation route exists |
| AU-010 | Provision initial admins twice | First CLI operation stores distinct password hashes; duplicate run refuses without partial changes |
| AU-011 | Five failures for one identifier or 20 for one source in 15 minutes | Subsequent login is limited; changing identifier does not bypass the source bucket |
| AU-012 | Rotate a user's credentials through trusted provisioning | The old session is rejected on its next guarded request; old password fails; new login succeeds; another user's session remains valid |

MySQL login and summary limiter state and all four migrations require the MySQL integration run. The suite also exercises MySQL-backed author accounts, audit queries, summary persistence/source hashes, and session revocation when enabled. Unit tests use SQLite for application behavior and HMAC rate-limit persistence plus provider/test doubles; these are not evidence of MySQL integration success.

| ID | Scenario | Expected result |
|---|---|---|
| F-001 | Valid author login | Session established; author dashboard allowed |
| F-002 | Invalid password | Login denied with generic message |
| F-003 | Logout | Session invalidated; protected route denied |
| F-004 | Author creates draft | Post stored with current author ID, draft status, and optional active category |
| F-005 | Author edits own post | Allowed fields save to the same post; an existing summary becomes stale on title/body change |
| F-006 | Author publishes valid post with active category | Status becomes published; public post is visible |
| F-007 | Author tries to publish without required category | Request rejected with validation error |
| F-008 | Author confirms deletion of own post | API requires `{"confirm":true}`; post is permanently removed and no restore path exists |
| F-009 | Public lists posts | Published posts only are returned with bounded pagination |
| F-010 | Public requests draft/unpublished post ID | Same `404`; no private content is returned |
| F-011 | Public filters by category | Only published posts for an active category are returned |
| F-012 | Public searches keyword | Matching published title/body returned; malformed or unsupported query safely rejected |
| F-013 | Admin creates category | Category appears as active in author selector |
| F-014 | Author attempts category mutation | Request denied |
| F-015 | Author requests another author's private post by changing its ID | Denied or concealed; no post content or private metadata is returned |
| F-016 | Author and admin dashboard counts | Author counts include only their own total/draft/published posts; admin counts include all database records in those buckets; neither response includes views |
| F-017 | Admin creates or renames a category | Server generates a normalized URL-safe slug; slug collisions receive a unique numeric suffix; duplicate normalized names are rejected |
| F-018 | Admin deactivates category | Existing posts retain relation; category is not offered for new assignments |
| F-019 | Admin manages all posts | Permitted actions work and are audited |
| F-020 | Author requests own summary | Valid plain-text summary stored and status becomes ready |
| F-021 | Article title/body changes after summary | Summary marked stale and not shown publicly as current |
| F-022 | AI provider fails | Post remains readable/manageable; safe summary error returned |
| F-023 | Empty and not-found states | UI/API returns clear safe responses |
| F-024 | Admin dashboard optional user/category totals | When available from existing admin-list metadata, totals are database-derived; no dashboard endpoint is needed |
| F-025 | Public/admin category list | Public list contains active categories only and omits private counts; admin list includes active and inactive categories with associated post counts |
| F-026 | Admin category create/update | Names and descriptions are bounded and validated; clients cannot choose slugs; name duplicates are rejected and slug collisions get unique numeric suffixes |
| F-027 | Admin category state/delete | Deactivation preserves post references, hides the category from public/author selection, and deletion is blocked while posts reference it |
| F-028 | Author post list and counts | Filters affect list `meta.total`; `dashboard_counts` still cover all of the authenticated author's posts only |
| F-029 | Public summary display | Only a stored summary with a matching current source hash is returned; public reads do not call the provider |
| F-030 | Admin creates/manages author accounts | Only author roles can be created; hashes are stored, secret fields are never returned or audited, and credential/status changes revoke sessions |
| F-031 | Admin manages all posts | Admin list and counts span all records; moderation actions require admin role and CSRF and write audit events |
| F-032 | Admin reviews audit events | Only admins may read; filters and pagination work; response omits metadata and any secret/article content |
| F-033 | Author/admin requests a summary | Canonical database text is summarized only after permission checks; a ready summary/hash/time is stored and audited |
| F-034 | Provider fails, output is invalid, or article changes while provider runs | Safe error; no stale result is stored as current; post operations remain usable |

## 4. Authorization tests

| ID | Attack/test | Expected result |
|---|---|---|
| A-001 | Unauthenticated user calls author routes | 401/redirect; no protected data |
| A-002 | Author calls admin category create | 403; no category created |
| A-003 | Author A edits Author B's post by ID manipulation | Denied; B's data unchanged |
| A-004 | Author A deletes Author B's post | Denied; post remains |
| A-005 | Author A generates summary for B's post | Denied; no provider call should be made |
| A-006 | Request body includes `author_id` for another user | Ignored/rejected; server uses session identity |
| A-007 | Request body includes `role=admin` | Ignored/rejected unless performed through an explicitly authorized admin operation |
| A-008 | Inactive account uses existing session | Protected actions denied according to active-account policy |
| A-009 | Unauthenticated user or author calls admin category routes | 401 or 403; no category mutation; state changes without valid CSRF are rejected |
| A-010 | Author A requests, edits, publishes, or deletes Author B's post ID | 404; no private data or mutation; B's post remains unchanged |
| A-011 | Author or anonymous visitor calls admin post/user routes | 403 or 401; no data or account/post mutation |
| A-012 | Request supplies `role=admin` to author-account creation or update | 422; no role change or administrator account is created |
| A-013 | Anonymous user or author requests audit logs | 401 or 403; no audit data is returned |
| A-014 | Author requests a summary for another author's post | 404 before any limiter reservation or provider call |
| A-015 | Admin requests an authorized summary for any post | Allowed only through the admin route with a valid CSRF token |

## 5. Injection and request-security tests

| ID | Scenario | Expected result |
|---|---|---|
| S-001 | SQL metacharacters in login | No query structure change; generic result |
| S-002 | SQL payload in search/title/category ID | No SQL execution beyond intended query |
| S-003 | Script tag in title | Displayed as inert text or rejected safely |
| S-004 | Script-like summary from mocked provider | Stored/rendered safely as text; no execution |
| S-005 | State change without CSRF token | Denied |
| S-006 | State change with invalid CSRF token | Denied |
| S-007 | State change with valid session and CSRF token | Allowed only with required authorization |
| S-008 | State-changing request with a foreign or malformed Origin, or `Sec-Fetch-Site: cross-site` | Rejected with 403 before session/database work; missing browser metadata remains subject to CSRF |
| S-009 | Oversized title/body/search request | Rejected with bounded validation response |
| S-010 | Excessive login attempts | Rate limited; no account enumeration details |
| S-011 | Excessive summary requests | Rate limited; cost/resource boundaries respected |
| S-012 | Forced database/provider error | Safe error to client; internal details only in protected logs |
| S-013 | Inspect provider adapter capabilities/configuration | Receives bounded article text and provider credentials only; no database credentials, tools, filesystem/shell access, or unrelated application privileges |

## 6. AI-specific tests

1. Article contains “ignore previous instructions” and fake system prompts. Expected: provider is still asked only to summarize the article; no secrets/tools/actions exist to expose.
2. Article contains HTML, JavaScript, SQL, shell snippets, and URL-like strings. Expected: treated as article text only.
3. Provider returns empty response. Expected: summary marked failed; create/edit/publish operations remain available and article data remains usable.
4. Provider returns output above the maximum. Expected: rejected or safely truncated according to a documented deterministic policy.
5. Provider returns HTML/script markup. Expected: summary is plain text and rendered safely.
6. Provider times out or returns 429/5xx. Expected: safe failure, no false success, bounded retry policy.
7. Repeated public article reads. Expected: no calls to the AI provider.
8. Requester does not own the post and is not an admin. Expected: denied before provider call.
9. Source article changes after generation. Expected: summary source hash no longer matches and summary status is stale.
10. Provider key absent. Expected: summary endpoint returns a safe configuration error and other CMS functionality works.

## 7. Data integrity tests

- Duplicate username is rejected.
- Duplicate normalized category name is rejected.
- Category slug is generated server-side; normalized slug collisions resolve to a unique suffixed slug, with the database unique constraint guarding concurrent requests.
- Category delete is rejected while `posts.category_id` references the category; deactivation preserves the reference.
- Category create, update, activation, deactivation, and deletion write audit events in the same database transaction.
- Post author/category foreign keys remain valid.
- Dashboard aggregate counts use database records and enforce author ownership scope.
- Deactivated category does not break historic posts.
- Published timestamp is set consistently.
- Update timestamp changes on edit.
- Delete behavior follows the documented policy.
- No password hash appears in any user API response.
- Audit events do not include secrets or complete article payloads.

## 8. Regression workflow

Before each release:

1. Apply migrations to a clean test database.
2. Run all automated tests.
3. Run the authorization matrix.
4. Run input/output security tests.
5. Run AI provider failure tests with a mock.
6. Complete a manual end-to-end workflow.
7. Review server logs for sensitive data leakage.
8. Record failures and known limitations.

## 9. Exit criteria

- All must-have functional tests pass.
- No known critical/high-severity authorization or injection issue remains unresolved.
- AI failure isolation and public-summary caching tests pass.
- All exceptions are documented and accepted before deployment.
- Test results are recorded with date, code version, and environment.
