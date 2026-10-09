# Engineering, Security, and Repository Rules

## Infinity Blog

**Version:** 1.0

---

## 1. Scope control

1. The supplied CMS problem statement is the mandatory baseline.
2. Do not turn the product into a social network, forum, ERP, or e-commerce system.
3. Implement the Admin Panel, role boundaries, categories, and summarization only as defined in the requirements.
4. Do not add dependencies or infrastructure without a documented reason.
5. A planned feature is not complete until implemented and tested.

## 2. Repository discipline

1. Inspect the whole repository before modifying files.
2. Do not overwrite working code without understanding its purpose.
3. Preserve existing conventions unless there is a documented reason to change them.
4. Use small, coherent commits.
5. Keep local secrets, `.env`, database dumps with real data, provider keys, and credentials out of Git.
6. Never commit generated admin passwords or password hashes generated from known sample credentials.
7. Keep migration/schema changes versioned.
8. Update the docs when an API, schema, security, or workflow contract changes.

## 3. PHP rules

1. Use a supported PHP version and strict typing where it improves clarity.
2. Keep HTTP handling, business logic, and persistence responsibilities separate.
3. Centralize configuration and database connections.
4. Prefer explicit return values and small functions over hidden global state.
5. Handle exceptions at the HTTP boundary; log details privately and return safe errors.
6. Never use `eval`, dynamic inclusion from user input, shell execution based on request values, or unsafe deserialization.
7. Do not build filesystem paths from untrusted input.

## 4. Database rules

1. Use primary keys and foreign keys for entities and relationships.
2. Use prepared/parameterized statements for all values influenced by requests.
3. Never concatenate untrusted input into SQL.
4. Add indexes for common filters and relationships where justified.
5. Use transactions for multi-step state changes.
6. Use least-privilege database credentials for the application runtime.
7. Do not return password hashes or internal audit metadata to ordinary users.
8. Do not permanently delete a category if doing so would orphan referenced posts.
9. Avoid logging article bodies, passwords, provider secrets, or complete prompts containing private content.

## 5. Authentication rules

1. Store only password hashes generated using `password_hash()` or an equivalent approved password-hashing function.
2. Verify passwords using `password_verify()`.
3. Regenerate the session ID after successful login.
4. Use `HttpOnly`, `Secure` in production, and suitable `SameSite` session-cookie settings.
5. Implement idle/absolute session expiry appropriate to the deployment.
6. Invalidate the session on logout.
7. Apply login rate limiting and generic failure messages to reduce username enumeration.
8. Provision initial admin accounts through a secure CLI/setup process; never hardcode passwords.
9. Use separate admin credentials. Any temporary setup credential must be rotated before production.

## 6. Authorization rules

1. Every protected API route must authorize requests on the server.
2. Admin-only routes must require `role=admin` and an active account.
3. Authors may manage only their own posts unless a separate permission explicitly permits otherwise.
4. Derive the acting user ID from the authenticated session.
5. Never trust client-supplied `author_id`, `role`, `is_admin`, or ownership claims.
6. Category management is admin-only.
7. Authors may assign only existing active categories.
8. Public read routes return published posts only.
9. Admin actions must not be implemented as client-side-only checks.
10. Author dashboard post counts must be scoped to the authenticated author's own database records; admin counts may be system-wide.
11. Do not add view tracking to Version 1.0.

## 7. CSRF, CORS, and HTTP rules

1. Require CSRF protection for state-changing session-authenticated requests.
2. Validate Origin/Referer where appropriate as defense in depth.
3. Do not enable wildcard CORS for credentialed requests.
4. If origins are separate, use a strict allowlist and test preflight/credentials behavior.
5. Use HTTPS in production.
6. Apply appropriate response headers, including Content-Security-Policy, `X-Content-Type-Options: nosniff`, and `Referrer-Policy`.
7. Avoid exposing stack traces, filesystem paths, SQL messages, provider responses, or secrets.

## 8. Input and output rules

1. Validate all inputs on the server even if the frontend validates them.
2. Enforce title, body, search, category, ID, and pagination limits.
3. Validate status values against a strict allowlist.
4. Escape untrusted text for the output context.
5. Treat AI-generated summaries as untrusted plain text.
6. If rich HTML is introduced, use a vetted allowlist sanitizer; output-escape alone is not an adequate policy for trusted rich HTML.
7. Never insert AI output into SQL, PHP, JavaScript, shell commands, URL destinations, or file paths.

## 9. AI summarization rules

1. The model is a text transformer, not an application agent.
2. Article content is untrusted data and may contain prompt-injection text.
3. Separate fixed task instructions from the article payload, but do not treat prompt wording as the only defense.
4. Give the provider/model no database credentials, tools, browser, filesystem, shell, or function-calling authority; the authorized backend operation owns database access.
5. Use only a server-configured, trusted provider endpoint.
6. Bound input size, output size, timeout, concurrency, and request frequency.
7. Store summary output as plain text.
8. Do not send unrelated user data or secrets to the provider.
9. Public article views must never trigger provider calls.
10. AI failures must not break ordinary create/edit/publish/read operations.
11. Log status and safe diagnostics, not API keys or full sensitive payloads.
12. Do not claim that prompt injection can be eliminated completely; use privilege isolation and output validation.

## 10. Category lifecycle rules

1. Admins create, edit, and deactivate categories.
2. Authors cannot create, rename, deactivate, or delete categories.
3. Category names must be unique under a documented normalization rule; the server generates unique URL-safe slugs from names, resolving base-slug collisions with numeric suffixes and relying on the database unique constraint.
4. Deactivated categories remain attached to existing posts for historical integrity.
5. Inactive categories cannot be assigned to new posts or used for a new publication unless the product owner explicitly changes that policy.
6. Publish requests require a valid active category; drafts may temporarily have no category if allowed by the UI flow.

## 11. Audit rules

Log significant events such as successful login (at an appropriate level), post create/update/publish/delete, category changes, user activation/deactivation, role changes, and summary request outcomes. Avoid storing secrets, password values, provider keys, full article bodies, or excessive personal data in audit metadata.

## 12. Testing rules

Before marking a feature complete, test:

- Happy path.
- Invalid/missing input.
- Unauthenticated access.
- Incorrect role.
- Cross-author resource access.
- CSRF failure for state-changing requests.
- Relevant SQL injection and XSS cases.
- Error path and dependency failure.
- Data integrity after changes.

## 13. Definition of done

A change is complete only when code, schema, authorization, validation, tests, documentation, and error handling are aligned; existing tests pass; new tests cover sensitive behavior; and the final diff has been reviewed. Never claim a system is 100% secure or attack-proof.
