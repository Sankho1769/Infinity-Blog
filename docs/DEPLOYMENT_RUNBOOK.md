# Deployment and Operations Runbook

## Infinity Blog

**Version:** 1.0  
**Status:** Baseline; adjust commands to the actual repository/runtime.

---

## 1. Before deployment

- Inspect and confirm the application's PHP version/dependencies.
- Create a dedicated MySQL database and a least-privilege application account.
- Configure HTTPS and production session cookie settings.
- Configure environment variables outside version control.
- Apply database migrations to an empty/staged database first.
- Provision administrator accounts using the secure setup procedure.
- Configure the AI provider only if the summarization feature will be enabled.
- Run `TEST_PLAN.md` and review the security threat model.

## 2. Configuration categories

Use environment variables or protected server config for settings such as:

```text
APP_ENV
APP_DEBUG=false
APP_BASE_URL
APP_KEY
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
DB_SSL_CA
SESSION_SECURE_COOKIE=true
ALLOWED_ORIGINS
AI_PROVIDER
AI_MODEL
AI_API_KEY
AI_TIMEOUT_SECONDS
AI_MAX_INPUT_CHARS
AI_MAX_OUTPUT_CHARS
AI_SUMMARY_RATE_LIMIT
```

The current application requires `APP_ENV`, `APP_DEBUG`, `APP_BASE_URL`, `SESSION_SECURE_COOKIE`, a unique 32-byte `APP_KEY` encoded as 64 hexadecimal characters, and the `DB_*` connection values. Generate a key with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`; keep it in protected environment configuration and do not reuse the example placeholder. It HMACs rate-limit identifiers and source addresses. The migration CLI accepts optional `MIGRATION_DB_*` overrides, including `MIGRATION_DB_SSL_CA`. AI and CORS values remain future-phase settings. Login rate thresholds are currently fixed in code (5 failures per identifier and 20 per source address in 15 minutes). Do not commit a real `.env` file.

## 2.1 Database TLS

- Loopback development (`localhost`, `127.0.0.1`, or `::1`) may leave `DB_SSL_CA` empty. This local exception does not apply to production.
- All production connections require `DB_SSL_CA` to point to a readable trusted CA PEM file. Remote database hosts require the same setting in every environment. PHP must use `pdo_mysql` with `mysqlnd`, which provides the PDO server-certificate verification option used by this connection factory.
- PDO passes the CA file and enables `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT`; there is no configuration switch that disables verification. Use a database DNS name covered by the server certificate and keep the CA file outside the repository with restricted read access.
- Configure MySQL to require encrypted transport (`require_secure_transport=ON`) for the production server/account. Client-side TLS verification and server-side transport enforcement are complementary controls.
- For remote migration connections, configure `MIGRATION_DB_SSL_CA` alongside any migration-account overrides. Verify the server certificate/CA rotation process before changing the deployed CA bundle.

The AI endpoint must come from trusted deployment configuration/provider adapter, not from a user request parameter.

## 3. Local development setup

1. Install PHP 8.0+ with `pdo_mysql` and Oracle MySQL 8.0.16+; MariaDB is not the supported target.
2. Copy `.env.example` to `.env` and set local values.
3. Create a dedicated MySQL database and separate runtime/migration users through an interactive database administration session. The runtime user requires DML access to `users` and `login_rate_limits`; the migration account is used only by `bin/migrate.php`.
4. Apply migrations with `php bin/migrate.php up`; inspect them with `php bin/migrate.php status`. Apply `202610090003_add_auth_version_to_users` before deploying code that reads `users.auth_version`; sessions created before that migration are rejected and require users to sign in again.
5. Run `php tests/run.php`. For MySQL integration, configure a fresh, empty, dedicated database named `*_test` and the explicit `TEST_DB_*` settings described in `TEST_PLAN.md`; the suite refuses nonempty databases and runs a confirmed rollback only on that disposable schema.

To serve the implemented API, configure Apache's virtual host document root to the repository's `public/` directory and enable `mod_rewrite`/`.htaccess`. Set `APP_BASE_URL` to the origin used by that virtual host. Never expose the repository root as the document root. The router exposes authentication, category, author post lifecycle, public published-post reads, admin post/author-account management, and admin audit-log reads documented in `API_SPEC.md`; AI routes remain unimplemented.

## 4. Administrator provisioning and rotation

The CLI creates the two required initial admin identities in one transaction and refuses to run if either already exists:

| Display name | Canonical login identifier |
|---|---|
| Shubham Banerjee | `shubham banerjee` |
| Sounak Sikdar | `sounak sikdar` |

The login identifier is trimmed and lowercased before lookup; the password is not normalized. From PowerShell, run `.\bin\admin-credentials.ps1 -Mode provision`; it prompts securely for separate confirmed passwords and passes them only to the local PHP CLI process. To rotate one account, run `.\bin\admin-credentials.ps1 -Mode rotate -Identifier "Shubham Banerjee"` or use `Sounak Sikdar`. Passwords must be 12–72 bytes because the current `PASSWORD_DEFAULT` implementation may use bcrypt, which only processes the first 72 bytes. Passwords are stored only as `password_hash()` output. The command is CLI-only and there is no public registration or administrator-creation endpoint. Never place passwords in migrations, source code, documents, command arguments, shell history, or committed environment files.

For another platform, supply `ADMIN_SHUBHAM_PASSWORD` and `ADMIN_SOUNAK_PASSWORD` to `php bin/admin-credentials.php provision` through a protected secret-injection mechanism; for rotation, use `ADMIN_NEW_PASSWORD` and the allowlisted name argument. The PHP command clears these variables on exit and never prints the values. Use distinct passwords and rotate any temporary bootstrap credential before production.

Password rotation updates the stored hash and increments `users.auth_version` in one statement. `AuthGuard` compares that database value on each protected request and clears any older session for the rotated account; users must sign in again. Future password reset/change operations must use the same atomic version increment. The CLI does not print passwords, hashes, session IDs, or version values. A request already authorized before a concurrent rotation can finish.

Sessions use server-side PHP storage, `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS, 30-minute idle expiry, and 12-hour absolute expiry. PHP's session storage path must be writable only by the application account and outside the public document root; the configured garbage-collection lifetime is at least the absolute session limit. `APP_BASE_URL` with an HTTPS scheme enables `Secure`; production configuration also requires `SESSION_SECURE_COOKIE=true`.

## 5. Production settings

- `APP_DEBUG=false`.
- PHP `display_errors=Off` and `log_errors=On`; keep protected server logs outside the public document root.
- HTTPS enabled.
- Unique `APP_KEY` of 32 cryptographically random bytes, supplied as 64 hexadecimal characters; protect it as a secret.
- Session cookie `Secure`, `HttpOnly`, and appropriate `SameSite` setting.
- Strict CORS allowlist if origins are separate.
- Restrictive CSP and other security headers.
- Database credentials limited to required schema operations; do not use database root as the app account.
- Use a DML-only runtime account and a separate migration account for schema changes. Keep migration credentials out of the web process where practical.
- Directory permissions restrict write access.
- Provider key available only to the backend process.
- Logs stored outside public document root and protected from public access.

## 6. Release procedure

1. Back up database and relevant configuration securely.
2. Deploy the reviewed source version.
3. Apply migrations in the planned order.
4. For the current implementation, smoke-test CSRF-token retrieval, login, `/auth/me`, logout, expiry, rate-limit responses, category routes, author ownership on post routes, public published-only reads, and admin-only post/author management. AI checks remain a future release gate.
5. Confirm drafts and unpublished posts are not publicly visible through list or detail routes.
6. Confirm logs and responses contain no secrets.
7. Monitor error/rate-limit logs after release.

## 7. Backup and recovery

- Define backup frequency and retention according to the expected importance of content.
- Encrypt backups at rest and restrict access.
- Store backups separate from the live host where feasible.
- Test restore regularly into an isolated environment.
- Do not store production backups in the repository.

## 8. AI provider operations

- Set per-request timeout and input/output limits.
- Establish rate and cost limits with the provider where available.
- Monitor summary success/failure counts and latency without logging full article text or API keys.
- Ensure failure does not stop normal CMS features.
- Rotate exposed keys immediately; updating the current config is not enough if the secret entered Git history or logs.
- Disable summarization through configuration if abuse or provider compromise is suspected; post CRUD and public reading should continue.

## 9. Rollback

If a release causes critical errors:

1. Put the application into maintenance/read-only mode if necessary.
2. Preserve logs without exposing them publicly.
3. Roll back code to the last known good version.
4. Restore database only if the migration caused data corruption and a tested recovery plan permits it.
5. Never blindly roll back migrations that may destroy data.
6. Document the cause and corrective action before redeploying.

## 10. Operational checklist

- [ ] HTTPS and production cookies verified.
- [ ] No secrets committed.
- [ ] Admin credentials uniquely provisioned.
- [ ] Database backup and restore tested.
- [ ] All required tests passed.
- [ ] Logs protected and redacted.
- [ ] AI provider has sensible limits or is disabled.
- [ ] Security gaps and limitations documented.

The current PHP file session handler is local to a host. A multi-node deployment must configure a secure shared session handler before use. Behind a reverse proxy, configure the web server to provide a trustworthy `REMOTE_ADDR`; application code ignores caller-controlled forwarding headers. MySQL integration must be run on Oracle MySQL 8.0.16 or newer; the available MariaDB environment is not a substitute.
