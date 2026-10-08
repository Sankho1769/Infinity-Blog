# Project Memory

## Content Management System (CMS)

**Memory Version:** 1.0  
**Purpose:** Persistent project context for future development sessions.

---

## 1. Project Identity

**Project Name:** Content Management System (CMS)

**Primary Purpose:**  
Provide a web-based system for authors to manage blog posts and for public visitors to read published articles.

**Primary Technologies:**  
PHP + MySQL

**Academic Context:**  
BCA 5th Semester mini-project.

## 2. Official Problem Statement

A blog site requires a CMS that allows authors to write, edit, and publish articles.

The system must use PHP and MySQL, allow authors to log in, create new posts, edit existing posts, and delete posts, and display posts to the public in a structured format.

This statement is the scope anchor for Version 1.0.

## 3. Product Decision

The project is a **blog CMS**, not a campus ERP, social network, forum, e-commerce system, or general communication platform.

Any idea that moves the core product away from article management should be treated as a separate project or a future extension.

## 4. Core Actors

### Author

Authenticated user who manages blog posts.

Core capabilities:

- Login.
- Create post.
- Edit post.
- Publish post.
- Delete post.
- View own posts in the dashboard.
- Logout.

### Public Visitor

Unauthenticated reader.

Core capabilities:

- View published posts.
- Open a full published article.

## 5. Core Data

### User

```text
id
username/email
password_hash
created_at
```

### Post

```text
id
author_id
title
content
status
created_at
updated_at
```

### Status

```text
draft
published
```

## 6. Important Design Decisions

1. MySQL is the persistent data store.
2. PHP handles server-side processing.
3. PHP sessions handle author authentication.
4. Passwords are hashed.
5. Prepared statements are used for database queries influenced by user input.
6. Only published posts are public.
7. Post ownership is checked for management operations.
8. Public rendering escapes untrusted output unless a deliberate sanitized rich-text policy is introduced.
9. Version 1.0 favors simplicity over an elaborate framework architecture.

## 7. Current Scope

### Required

- Login/logout.
- Create.
- Read.
- Update.
- Delete.
- Publish/unpublish state handling as required to keep drafts private.
- Public article listing.
- Public article detail view.
- MySQL schema.
- Validation.
- Basic security.

### Later

- Categories.
- Tags.
- Search.
- Filtering.
- Revision history.
- Editor/Admin roles.
- Scheduled publication.
- Media library.
- Analytics.
- Comments.

## 8. Non-Negotiable Security Expectations

The project must not:

- Store raw passwords.
- Concatenate untrusted input directly into SQL queries.
- Trust a client-supplied author ID when deciding ownership.
- Expose draft content publicly.
- Expose raw database errors to ordinary visitors.

## 9. Expected User Experience

Author flow:

```text
Login
  v
Dashboard
  v
Create/Edit
  v
Save Draft or Publish
  v
Manage Posts
```

Public flow:

```text
Blog Home
  v
Published Post List
  v
Article Detail
```

## 10. Implementation Guidance

Keep the code understandable enough for an academic viva.

A reviewer should be able to trace:

```text
Form
  v
PHP request
  v
Validation
  v
Prepared SQL
  v
MySQL
  v
Response / redirect
```

Avoid hiding the main logic behind unnecessary abstraction during the first version.

## 11. Documentation State

The project should maintain these planning documents:

- `PRD.md` — product requirements and scope.
- `architecture.md` — technical structure.
- `rules.md` — development and security rules.
- `design.md` — UI and UX decisions.
- `memory.md` — stable project context.
- `task.md` — implementation checklist and progress.

These documents should remain aligned with the actual codebase.

## 12. Change Control

When a future requirement is proposed:

1. Check whether it is already in the PRD.
2. Decide whether it belongs to Version 1.0 or a later version.
3. Update the relevant planning document.
4. Add implementation tasks.
5. Keep the core problem statement unchanged unless there is an explicit project change.
