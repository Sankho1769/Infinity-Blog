# Infinity Blog - Project Documentation

**Document set version:** 1.0  
**Application:** Infinity Blog  
**Project type:** PHP + MySQL Content Management System (CMS)

## Overview

Infinity Blog is a web-based blog CMS. Authors sign in to create, edit, publish, and delete their own articles. Administrators manage all posts, author accounts, and categories. Public visitors can browse published articles, filter by category, and read a stored article summary where one is available.

AI summarization is a limited text-transformation feature. The authorized backend operation loads the article and stores the validated summary in MySQL; the provider adapter receives bounded text only and has no database credentials, tools, shell commands, file access, or application privileges. Core CMS functionality must remain available if the configured AI provider is unavailable.

## Current implementation status

The Phase 1 configuration, PDO, and versioned migration foundation is in place. Phase 2 now implements PHP session authentication, logout, `/auth/me`, session-bound CSRF, MySQL-backed login limiting, reusable author/admin guards, and a restricted CLI workflow for the two initial administrators. The API entry point is `public/index.php`; no post, public-content, category-management, Admin Panel, or AI routes are implemented yet.

The initial database migration creates `users`, `categories`, `posts`, and `audit_logs`. The additive second migration creates `login_rate_limits`. Category references use `ON DELETE RESTRICT`; deactivating a category leaves existing post links intact. Drafts can have no category, while a published post must have a category and publication timestamp. The application layer for post/category features remains future work.

## Requirements

- PHP 8.0 or newer with `pdo_mysql`/`mysqlnd` enabled (the TLS verification option requires mysqlnd).
- MySQL 8.0.16 or newer. The schema uses enforced `CHECK` constraints, which MySQL began enforcing in 8.0.16.
- Apache/XAMPP with `mod_rewrite` is needed to serve the authentication API; configure the document root to `public/`.
- No Composer packages are required. The project uses a small PSR-4-style autoloader in `autoload.php`.

The available XAMPP database binary reports MariaDB 10.4.32, which is not the specified MySQL target. No Oracle MySQL server is available here, so MySQL integration remains unverified; the migration and database limiter must still be tested against a dedicated Oracle MySQL 8.0.16+ `_test` database.

## Local foundation setup

From PowerShell in the repository root:

```powershell
Copy-Item .env.example .env
```

Edit `.env` with local settings, including a generated `APP_KEY`. Configure Apache's virtual host/document root to `C:\xampp\htdocs\Infinity_Blog\public` (or an equivalent protected mapping) and set `APP_BASE_URL` to that site's origin. Do not serve the repository root, which contains source and operational files. The file loader supports simple `KEY=value` entries, optional matching single or double quotes, and comments on their own lines. Process environment values take precedence. Values are not interpolated or executed. Keep real credentials only in `.env` or protected deployment configuration.

Create the database and separate runtime/migration users through an interactive MySQL administration session. For a local database, the SQL shape is:

```sql
CREATE DATABASE infinity_blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'blog_runtime'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_LOCAL_RUNTIME_SECRET';
GRANT SELECT, INSERT, UPDATE, DELETE ON infinity_blog.* TO 'blog_runtime'@'127.0.0.1';
CREATE USER 'blog_migrator'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_LOCAL_MIGRATION_SECRET';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP ON infinity_blog.* TO 'blog_migrator'@'127.0.0.1';
```

Replace both sample secret strings before executing and enter secrets only in your private local configuration. The runtime account has only DML privileges. The migration account is separate and should be supplied with `MIGRATION_DB_USERNAME` and `MIGRATION_DB_PASSWORD` when applying migrations. Do not use MySQL `root` for production.

Apply or inspect migrations with the PHP executable:

```powershell
& 'C:\xampp\php\php.exe' bin/migrate.php up
& 'C:\xampp\php\php.exe' bin/migrate.php status
```

`bin/migrate.php down --confirm-data-loss` removes the initial tables and all CMS data. Use it only against a disposable database after a backup; it is intentionally guarded.

## Configuration

Required settings include `APP_ENV`, `APP_DEBUG`, `APP_BASE_URL`, `SESSION_SECURE_COOKIE`, `APP_KEY`, and the `DB_*` connection values. Generate `APP_KEY` with `& 'C:\xampp\php\php.exe' -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`; keep the unique 64-hex-character value in protected local/deployment configuration. It HMACs limiter identifiers and source addresses. `DB_SSL_CA` is optional only for loopback development; production requires a readable trusted CA file, and remote DB hosts always require it. The PDO connection enables MySQL server-certificate verification whenever a CA is configured; there is no setting to disable verification. Configure `DB_HOST` to the DNS name covered by the server certificate. `MIGRATION_DB_USERNAME`, `MIGRATION_DB_PASSWORD`, and `MIGRATION_DB_SSL_CA` can override migration-only settings; other `MIGRATION_DB_*` fields can also override their matching `DB_*` fields. Production validation rejects debug mode, non-HTTPS base URLs, insecure session cookies, `root`, an empty database password, and missing verified database TLS.

The `.env.example` file contains placeholders only. `.gitignore` excludes `.env`, other `.env.*` files, logs, local dumps, and generated directories while allowing the example file.

`ApiErrorResponder` provides the documented safe error payload and request IDs. Authentication controllers do not expose exception text, credentials, password hashes, or database details. Production PHP configuration must disable displayed errors and send diagnostics only to protected server logs.

## Authentication setup

After applying both migrations and configuring the database runtime account with DML access, use the secure PowerShell prompt to provision administrators:

```powershell
.\bin\admin-credentials.ps1 -Mode provision
.\bin\admin-credentials.ps1 -Mode rotate -Identifier "Shubham Banerjee"
```

The first command provisions both configured identities atomically with separate passwords. Identifiers are trimmed and lowercased; passwords are unchanged. The PHP CLI hashes passwords before storage and provides no public account-creation endpoint. Authentication routes and their CSRF/session behavior are described in `API_SPEC.md`.

## Tests

Run the unit and safe-failure checks, including SQLite-backed authentication, session, CSRF, limiter-double, and administrator-provisioning cases:

```powershell
& 'C:\xampp\php\php.exe' tests/run.php
```

Use Oracle MySQL 8.0.16 or newer for integration checks; MariaDB is not a substitute. Create a fresh, empty database named `infinity_blog_test` and a dedicated non-root test account. The suite checks that the database is empty before it migrates, requires the name to end exactly in `_test`, and never falls back to `DB_*`. It applies migrations twice, checks keys/constraints/indexes and category deactivation, verifies an unconfirmed rollback leaves the schema intact, performs a confirmed destructive rollback only against that empty disposable test schema, and migrates again to verify reproducibility. Do not point it at development or production data.

In PowerShell, set `APP_ENV=test`, `INFINITY_BLOG_RUN_DB_TESTS=1`, and `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_DATABASE`, `TEST_DB_USERNAME`, and `TEST_DB_PASSWORD` in the process environment. Set `TEST_DB_SSL_CA` as well when the test server is remote. Supply passwords through a private environment/secret mechanism rather than command history. Then run:

```powershell
& 'C:\xampp\php\php.exe' tests/run.php
```

If the integration flag is enabled with missing, invalid, non-test, root-account, or non-`_test` settings, the runner fails before connecting. If the flag is not enabled, the integration group is reported as skipped, not passed. The test database must be empty; an existing table causes the suite to stop before migration or rollback.

## Documentation map

| File | Purpose |
|---|---|
| `SRS.md` | Formal software requirements specification and acceptance criteria |
| `PRD.md` | Product goals, users, scope, release criteria, and product decisions |
| `architecture.md` | Application architecture, module boundaries, data flow, and deployment shape |
| `rules.md` | Coding, security, Git, data, and implementation rules |
| `design.md` | Functional page structure and interaction requirements; visual styling is intentionally not specified |
| `memory.md` | Stable project context and decisions for future work sessions |
| `task.md` | Phased implementation checklist |
| `API_SPEC.md` | Frontend/backend HTTP API contract |
| `DATABASE_DESIGN.md` | Logical schema, keys, indexes, and data lifecycle |
| `SECURITY_THREAT_MODEL.md` | Threats, mitigations, and security review checklist |
| `TEST_PLAN.md` | Functional, authorization, security, and AI failure tests |
| `DEPLOYMENT_RUNBOOK.md` | Local setup, environment configuration, release, and operations |
| `docs/WIREFRAME_BACKEND_MAPPING.md` | Wireframe page/data/action mapping, implementation status, and integration gaps |
| `docs/wireframes/infinity-blog-flow.jpeg` | Supplied structural wireframe reference |

## Source of truth and precedence

1. The official project problem statement establishes the required CMS core: author login; create, edit, publish, and delete posts; and structured public display using PHP and MySQL.
2. `SRS.md` defines testable system requirements.
3. `PRD.md` defines product scope and release priorities.
4. `API_SPEC.md` and `DATABASE_DESIGN.md` define interface and persistence contracts.
5. `SECURITY_THREAT_MODEL.md` and `rules.md` define mandatory security expectations.
6. `task.md` tracks implementation progress. Update it to reflect actual code, not planned work.

If implementation reveals a conflict, record the decision in `memory.md` and update affected documents before implementing the conflicting behavior.

## Version 1.0 scope

- Role-based authentication: `admin` and `author`.
- Admin panel and author dashboard.
- Admin-managed users and categories.
- Author post CRUD and publish/unpublish behavior.
- Public published-post list and detail pages.
- Public category filtering and keyword search.
- Stored AI-generated article summaries, generated only on an authorized request and not on every public view.
- Database-derived total/draft/published post counts on author and admin dashboards; author counts are limited to the current author's records.
- Server-generated, unique category slugs.
- CSRF protection, prepared statements, output encoding, session controls, rate limiting, safe errors, and audit events.

Version 1.0 defers view tracking, tags, featured-image or other user uploads, public author profiles, remember-me, password recovery, and trash/restore. Post deletion is permanent after explicit confirmation.

## Credentials and secrets

No password, API key, session secret, or production credential belongs in this repository or this documentation. Provision the two initial administrator accounts through a secure setup command or deployment procedure. The initial administrator identifiers are `Shubham Banerjee` and `Sounak Sikdar`; configure separate strong passwords securely and change any temporary credential before deployment. Never use a shared plaintext password as a committed default.

## Quick implementation order

1. Inspect the existing repository and preserve working code.
2. Apply schema/migration files.
3. Implement configuration, database access, and error handling.
4. Implement authentication and server-side role/ownership checks.
5. Implement categories and post lifecycle.
6. Implement public read APIs/pages.
7. Implement article summarization behind an isolated provider service.
8. Integrate frontend against `API_SPEC.md`.
9. Run `TEST_PLAN.md`, review the security threat model, and document known limitations.

## Important limitation

This documentation is a specification, not evidence that implementation or security testing has already been completed. Mark requirements and tasks complete only after code exists and tests have been run.
