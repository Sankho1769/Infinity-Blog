# Project Memory and Decision Record

## Infinity Blog

**Version:** 1.0

**Purpose:** Preserve stable context across implementation sessions and coding-agent handoffs.

---

## 1. Project identity

- Product: **Infinity Blog**.
- Product type: web-based Content Management System for a blog.
- Core technology: PHP + MySQL.
- Frontend integrates with a backend API contract unless the existing repository has a documented approved alternative.
- Main actors: public visitor, author, admin.

## 2. Official problem statement

A blog site requires a CMS that allows authors to write, edit, and publish articles. The application must use PHP and MySQL, allow authors to log in, create new posts, edit existing posts, and delete posts, and display posts to the public in a structured format.

This remains the mandatory core scope.

## 3. Product boundaries

Infinity Blog is not a campus ERP, social network, forum, marketplace, or e-commerce platform. Features must remain tied to article management and public blog reading.

## 4. Role model

Initial administrator identifiers are normalized names (`shubham banerjee`, `sounak sikdar`): trim leading/trailing ASCII spaces and lowercase the identifier; passwords remain unchanged. Initial accounts are created or rotated only through the trusted CLI credential workflow. Authentication routes exist, but the MySQL integration suite remains unverified until run against Oracle MySQL 8.0.16+.

### Public visitor
- Read published posts.
- Search and filter published posts.
- View current stored summary where available.

### Author
- Log in and log out.
- Create and manage own posts.
- Select admin-created active categories.
- Publish/unpublish own posts.
- Request a summary for a post they may manage.

### Admin
- Access a protected admin panel.
- Manage all posts.
- Create/edit/deactivate categories.
- Create/deactivate author accounts and perform explicitly authorized account/role management.
- View permitted audit events.
- Request summaries for posts they are permitted to manage.

## 5. Initial administrator setup

Two initial administrator identifiers are requested:
- `Shubham Banerjee`
- `Sounak Sikdar`

Provision them through a secure setup/CLI process. Keep passwords separate, strong, and out of source code, Markdown, SQL seed files, and Git. Do not preserve any plaintext credential in this memory file. Rotate temporary setup credentials before deployment.

## 6. Main data entities

- `users`: identity, password hash, role, active status, timestamps.
- `categories`: name, unique server-generated slug, description, active status, creator and timestamps.
- `posts`: author, optional/required category according to publication rules, title, content, status, summary metadata, publication and timestamps.
- `audit_logs`: actor, action, entity type/ID, safe metadata, timestamp.
- `rate_limit_buckets` or an equivalent rate-limit mechanism, if the chosen implementation stores limits in MySQL.

## 7. Category decisions

- Admins are the only role allowed to create, edit, activate, or deactivate categories.
- Authors can assign active categories to posts.
- Deactivation preserves existing references and history.
- Category slugs are generated from the category name by the server; normalized collisions use numeric suffixes and the database unique constraint remains authoritative.
- A post requires an active category to be published. Drafts may be saved without a category if that keeps drafting easy, but this behavior must be consistent in UI and tests.

## 8. AI summarization decisions

- Summary generation is explicit and authorized, not automatic on every public page view.
- Backend loads the canonical article content from MySQL.
- AI provider is selected/configured on the server and can be replaced through a service adapter.
- The model gets article text only as untrusted content, with no tools or application privileges.
- Summary is stored as plain text and safely rendered.
- Summary has state (`none`, `ready`, `stale`, `failed`) and generation timestamp/source hash as implemented.
- On title/body edits, mark a previous summary stale.
- AI failure must not interrupt post creation, editing, publishing, or reading.
- The backend performs authorized MySQL reads/writes; the provider adapter receives bounded text only and has no database credentials or application privileges.
- Rate limit summary generation and enforce input/output/time limits.

## 9. Version 1.0 scope decisions

- Author dashboard counts are database-derived and limited to the signed-in author's posts; admin post counts are system-wide. There is no view tracking.
- Tags, featured-image uploads, public author profiles, remember-me, password recovery, and trash/restore are deferred. Post deletion is permanent after confirmation.

## 10. Security decisions

- PHP sessions for browser authentication.
- Session ID regeneration after login.
- CSRF tokens for state-changing requests.
- Prepared statements.
- Role and post ownership checked server-side on every relevant request.
- Public endpoints return published content only.
- Output encoding by context; AI summary is plain text.
- Secrets live outside version control.
- No claim that the application is 100% secure.

## 11. Integration contract

- `API_SPEC.md` is the frontend/backend integration contract.
- `DATABASE_DESIGN.md` defines logical data fields and relationship expectations.
- If an API payload or database field changes, update both documents and associated tests.
- The frontend must not invent successful states when the backend returns an error.

## 12. Implementation approach

1. Inspect the repository before modifying code.
2. Preserve existing work unless a change is justified.
3. Implement schema and configuration first.
4. Add auth/authorization before protected CRUD routes.
5. Add post and category management.
6. Add public list/detail and search/filter.
7. Add the isolated summary service.
8. Test negative and malicious input cases.
9. Keep the task board honest and update the docs with actual decisions.

## 13. Change control

When a new feature or change is requested:

1. Check the SRS and PRD.
2. Decide whether it is in scope or a separately approved extension.
3. Update relevant docs and tests before implementation.
4. Document the reason for material architecture/security changes.
