# Implementation Task Plan

## Content Management System (CMS)

**Version:** 1.0  
**Status:** Implementation Baseline

---

## 1. Task Status Legend

- `[ ]` Not started
- `[-]` In progress
- `[x]` Completed
- `[!]` Blocked

---

## 2. Phase 0 — Project Setup

### T-001 [ ] Create project repository
- Create the PHP project directory.
- Initialize Git.
- Add `.gitignore`.
- Add `README.md`.

### T-002 [ ] Prepare local environment
- Start Apache.
- Start MySQL.
- Verify PHP execution.
- Verify database connection.

### T-003 [ ] Create project structure
- Add configuration directory.
- Add author pages.
- Add public pages.
- Add shared includes.
- Add assets directory.
- Add database directory.

**Depends on:** T-001, T-002

---

## 3. Phase 1 — Database

### T-010 [ ] Create database
- Create CMS database.
- Document database name and local setup.

### T-011 [ ] Create users table
- Primary key.
- Username/email field.
- Password hash field.
- Creation timestamp.
- Appropriate uniqueness constraint.

### T-012 [ ] Create posts table
- Primary key.
- Author foreign key.
- Title.
- Content.
- Status.
- Creation timestamp.
- Update timestamp.

### T-013 [ ] Add sample data for development
- Create a test author through the application where practical.
- Add a small number of test posts.
- Include both draft and published examples.

**Depends on:** T-010

---

## 4. Phase 2 — Database Access

### T-020 [ ] Build database connection
- Centralize credentials/configuration.
- Handle connection failure.
- Set the expected character set.

### T-021 [ ] Build reusable query patterns
- Prepared statement examples.
- Error handling.
- Result handling.

**Depends on:** T-020, T-012

---

## 5. Phase 3 — Authentication

### T-030 [ ] Build author login page
- Username/email field.
- Password field.
- Validation.
- Error feedback.

### T-031 [ ] Implement password hashing
- Hash password during account creation.
- Never store raw passwords.

### T-032 [ ] Implement login verification
- Retrieve author by login identifier.
- Verify using `password_verify()`.

### T-033 [ ] Implement session management
- Start session.
- Store current author ID.
- Regenerate session ID after successful login.

### T-034 [ ] Implement logout
- Clear session data.
- Destroy session.
- Redirect to login page.

### T-035 [ ] Build authentication guard
- Block unauthenticated dashboard access.
- Reuse the guard across protected pages.

**Depends on:** T-021, T-011

---

## 6. Phase 4 — Author Dashboard

### T-040 [ ] Build dashboard layout
- Page title.
- Current author identity.
- Create Post action.
- Logout action.

### T-041 [ ] List author's posts
- Query posts for current author.
- Sort recent posts first.
- Display title, status, date, and actions.

### T-042 [ ] Add empty state
- Display a useful message when the author has no posts.

**Depends on:** T-035, T-012

---

## 7. Phase 5 — Create Post

### T-050 [ ] Build create-post form
Fields:
- Title.
- Content.
- Status.

### T-051 [ ] Validate post input
- Title required.
- Content required.
- Status restricted to supported values.

### T-052 [ ] Insert post
- Use prepared statement.
- Associate post with current author.

### T-053 [ ] Add save feedback
- Redirect after success.
- Show useful feedback on failure.

**Depends on:** T-041, T-021

---

## 8. Phase 6 — Edit Post

### T-060 [ ] Load editable post
- Read post by ID.
- Confirm current author owns or may manage it.
- Reject unauthorized access.

### T-061 [ ] Build edit form
- Pre-fill current values.
- Preserve validation feedback.

### T-062 [ ] Update post
- Validate.
- Execute prepared `UPDATE`.
- Update modification timestamp.

### T-063 [ ] Verify public result
- Confirm published post changes are reflected on the public page.

**Depends on:** T-060, T-061

---

## 9. Phase 7 — Delete Post

### T-070 [ ] Add delete action
- Place action on dashboard.
- Use clear wording.

### T-071 [ ] Add delete confirmation
- Prevent accidental deletion.

### T-072 [ ] Implement ownership-safe delete
- Confirm post belongs to current author.
- Execute prepared `DELETE`.

### T-073 [ ] Verify removal
- Post disappears from dashboard.
- Post cannot be viewed publicly.

**Depends on:** T-041, T-021

---

## 10. Phase 8 — Public Blog

### T-080 [ ] Build public homepage
- No login required.
- Query only published posts.
- Show title, author, date, and excerpt/basic content.

### T-081 [ ] Build public article page
- Retrieve one published post.
- Display title, author, publication information, and full content.

### T-082 [ ] Add not-found handling
- Unknown post ID.
- Unpublished post.
- Deleted post.

### T-083 [ ] Add navigation
- Blog home.
- Article page.
- Author login link where appropriate.

**Depends on:** T-012

---

## 11. Phase 9 — Security Hardening

### T-090 [ ] Review all SQL queries
- Replace string-concatenated user input with prepared statements.

### T-091 [ ] Review all HTML output
- Escape untrusted values before rendering.

### T-092 [ ] Review author access
- Test dashboard without login.
- Test edit/delete with another author's post ID.

### T-093 [ ] Review session handling
- Login.
- Logout.
- Session regeneration.
- Direct URL access after logout.

### T-094 [ ] Review error exposure
- Do not reveal raw SQL/database details to public users.

---

## 12. Phase 10 — UI Polish

### T-100 [ ] Build shared page shell
- Header.
- Footer.
- Shared navigation.
- Consistent spacing.

### T-101 [ ] Style author dashboard
- Readable table.
- Clear status labels.
- Consistent action buttons.

### T-102 [ ] Style public blog
- Readable article list.
- Comfortable article typography.
- Clear metadata.

### T-103 [ ] Add responsive behavior
- Test narrower desktop windows.
- Test phone-sized browser viewport.

### T-104 [ ] Add accessible form states
- Labels.
- Focus states.
- Visible errors.
- Keyboard-friendly controls.

---

## 13. Phase 11 — Testing

### T-110 [ ] Authentication tests
- Valid login.
- Invalid login.
- Logout.
- Protected route without login.

### T-111 [ ] Create-post tests
- Valid submission.
- Missing title.
- Missing content.
- Invalid status.

### T-112 [ ] Edit-post tests
- Valid edit.
- Missing post.
- Unauthorized edit.

### T-113 [ ] Delete tests
- Valid delete.
- Cancel delete.
- Unauthorized delete.
- Deleted post not public.

### T-114 [ ] Public-page tests
- Published post visible.
- Draft not visible.
- Unknown post handled safely.

### T-115 [ ] Security tests
- Basic SQL injection attempts.
- Script-like input rendered safely.
- Session behavior checked.
- Direct URL access checked.

---

## 14. Phase 12 — Documentation and Submission

### T-120 [ ] Update README
Include:
- Project overview.
- Features.
- Requirements.
- Installation steps.
- Database setup.
- Local run instructions.

### T-121 [ ] Verify planning documents
- PRD.
- Architecture.
- Rules.
- Design.
- Memory.
- Task list.

### T-122 [ ] Prepare database script
- Clean schema.
- Reproducible setup.
- Sample data only when appropriate.

### T-123 [ ] Final end-to-end check
Run the complete flow:

```text
Login
  v
Dashboard
  v
Create Post
  v
Publish
  v
Public Blog
  v
Edit
  v
Public Blog
  v
Delete
```

### T-124 [ ] Final submission package
Verify:
- Source code present.
- Database script present.
- Documentation present.
- No secrets present.
- Project starts on a clean local setup.

---

## 15. Suggested Build Order

Build in this order to reduce rework:

```text
Database
   v
Database connection
   v
Authentication
   v
Dashboard
   v
Create
   v
Edit
   v
Delete
   v
Public blog
   v
Security review
   v
UI polish
   v
Testing
   v
Documentation
```

## 16. Version 1.0 Completion Gate

Version 1.0 is complete only when:

- All required problem-statement capabilities work.
- Authentication is enforced.
- Post ownership is enforced.
- Drafts are private.
- Published posts are public.
- CRUD operations work against MySQL.
- Main security checks pass.
- Public and author interfaces are usable.
- The application can be reproduced from the documented setup steps.

## 17. Post-Version-1 Backlog

These are deliberately deferred:

- `[ ]` Categories.
- `[ ]` Tags.
- `[ ]` Search.
- `[ ]` Filtering.
- `[ ]` Revision history.
- `[ ]` Editor/Admin roles.
- `[ ]` Scheduled publishing.
- `[ ]` Media library.
- `[ ]` Analytics.
- `[ ]` Comments and moderation.
