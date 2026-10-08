# Engineering and Project Rules

## Content Management System (CMS)

**Version:** 1.0  
**Purpose:** Keep implementation decisions consistent across the project.

---

## 1. Scope Rules

1. The official problem statement is the source of truth for Version 1.0.
2. The core product must remain a PHP + MySQL blog CMS.
3. Required operations are login, create, edit, publish, delete, and public display.
4. New features must not compromise the core workflow.
5. Features listed as future enhancements must not silently become mandatory Version 1.0 requirements.

## 2. Coding Rules

1. Write readable PHP rather than clever PHP.
2. Use meaningful names for variables, functions, files, and database columns.
3. Keep database connection code centralized.
4. Keep authentication checks centralized where practical.
5. Avoid repeating the same SQL or validation logic across many pages.
6. Validate all required server-side inputs even when client-side validation exists.
7. Fail safely and show useful user-facing error messages.
8. Do not place credentials directly inside source code when the deployment environment supports environment configuration.
9. Do not commit passwords, API keys, or database secrets to Git.

## 3. Database Rules

1. Use primary keys for every main table.
2. Use foreign keys for relationships that require referential integrity.
3. Store password hashes, never raw passwords.
4. Use prepared statements for user-influenced queries.
5. Avoid ambiguous column names.
6. Store creation and update timestamps.
7. Do not store the same piece of data unnecessarily in multiple tables.
8. Database schema changes must be captured in versioned SQL files or migrations.
9. Test destructive SQL operations carefully.

## 4. Authentication Rules

1. Author management pages require an authenticated session.
2. Login credentials must be verified against the stored password hash.
3. The session must identify the authenticated author.
4. Session state must be cleared on logout.
5. Do not trust author IDs supplied by forms or query parameters for ownership decisions.
6. Regenerate the session ID after successful login.

## 5. Authorization Rules

Version 1.0 uses an author-only management model.

For an edit or delete request:

```text
current_session_user_id
        +
requested_post_id
        v
 database ownership check
```

The request is allowed only when the post belongs to the current author or when the later authorization model explicitly grants the user permission.

## 6. Content Rules

1. A post must have a non-empty title.
2. A post must have non-empty content.
3. The status must be one of the supported states.
4. Only `published` posts are visible on the public blog.
5. Draft posts remain private.
6. Public pages must show consistent article metadata.
7. HTML entered by an author must not execute unintentionally. Rich-text support requires an explicit sanitization policy.

## 7. Security Rules

### Never do this

```php
$sql = "SELECT * FROM users WHERE username = '$username'";
```

### Prefer this

```php
$stmt = $conn->prepare(
    "SELECT id, password_hash FROM users WHERE username = ?"
);
$stmt->bind_param("s", $username);
$stmt->execute();
```

Also:

- Escape output.
- Avoid detailed database errors in public responses.
- Do not expose stack traces to visitors.
- Keep dependencies updated.
- Use HTTPS in production.

## 8. UI Rules

1. Every author page must clearly show where the user is.
2. Primary actions should have obvious labels.
3. Destructive actions require confirmation.
4. Success and error states must be visible.
5. Forms should preserve understandable labels.
6. Public readers should never see author management controls unless they are authenticated and the page is intentionally an author page.
7. The UI should remain usable on common desktop viewport sizes.

## 9. Git Rules

Recommended commit format:

```text
feat: add post creation
fix: restrict post deletion by author
refactor: centralize database connection
docs: update setup instructions
test: cover login validation
```

Rules:

- One logical change per commit.
- Do not commit generated secrets.
- Do not commit large temporary files.
- Keep `main` stable.
- Use a feature branch for substantial changes when working in a team.

## 10. Testing Rules

Before considering a feature complete:

1. Test the successful path.
2. Test missing input.
3. Test invalid input.
4. Test unauthenticated access.
5. Test unauthorized post access.
6. Test database failure handling where practical.
7. Verify the public page exposes only published content.

## 11. Documentation Rules

Documentation should answer:

- What the project does.
- How to install it.
- How to configure MySQL.
- How to create an author.
- How to run it locally.
- What the main folders contain.
- What security measures are implemented.

Do not document features that are not actually implemented.

## 12. Definition of Done

A feature is done only when:

- Code is implemented.
- Relevant database changes are complete.
- Validation exists.
- Access rules are enforced.
- Normal and failure cases have been tested.
- The UI gives understandable feedback.
- Documentation is updated where necessary.
- The change does not break existing core flows.
