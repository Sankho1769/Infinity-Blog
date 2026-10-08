# Product Requirements Document

## Content Management System (CMS)

**Document Version:** 1.0  
**Status:** Baseline  
**Product Type:** Web-based blog CMS  
**Primary Stack:** PHP, MySQL, HTML, CSS, JavaScript  
**Audience:** Student developers, faculty evaluators, project reviewers

---

## 1. Product Summary

The Content Management System is a web application for managing articles on a blog.

The system gives authenticated authors a central place to create, edit, publish, and delete posts. Public visitors can browse published articles through a structured blog interface without accessing the author management area.

The product is deliberately focused. It is not intended to become a social network, forum, e-commerce platform, or general-purpose publishing suite in Version 1.0.

## 2. Problem

A small blog needs a reliable way to manage articles without editing website files manually or depending on direct database changes.

Without a CMS, common problems include:

- Content is difficult to maintain as the number of posts grows.
- Authors need a clear place to manage their own articles.
- Draft content can be mixed with published content.
- Updating an existing article is unnecessarily manual.
- Published content needs a consistent public presentation.
- Author access must be separated from public viewing.
- Repeated database operations should be handled by application code rather than ad-hoc edits.

## 3. Product Goal

Provide a simple, secure, maintainable CMS that covers the complete basic article lifecycle:

**Login -> Create -> Edit -> Publish -> View Publicly -> Delete**

## 4. Success Criteria

Version 1.0 is successful when:

1. An author can log in using valid credentials.
2. An author can create a new post and save it to MySQL.
3. An author can edit an existing post.
4. An author can publish a post.
5. An author can delete a post.
6. A visitor can see published posts without logging in.
7. Draft or unpublished posts are not exposed publicly.
8. Author-only pages cannot be accessed by unauthenticated users.
9. Passwords are stored as hashes rather than plain text.
10. Database operations use safe query handling.

## 5. Target Users

### 5.1 Author

An author writes and maintains blog articles.

Typical needs:

- Log in quickly.
- See existing posts.
- Create a new post.
- Update a post.
- Decide when a post becomes public.
- Remove a post that is no longer needed.

### 5.2 Public Visitor

A visitor reads the blog.

Typical needs:

- Open the blog without an account.
- See published articles.
- Select an article.
- Read the complete article.
- Understand the title, author, and publication information.

## 6. Product Scope

### 6.1 Version 1.0

Version 1.0 includes:

- Author authentication.
- Session-based access control.
- Author dashboard.
- Post creation.
- Post editing.
- Post publication.
- Post deletion.
- Published/unpublished status.
- Public post listing.
- Public article detail page.
- MySQL persistence.
- Input validation.
- Secure password storage.
- Prepared/parameterized database queries.
- Safe output rendering.

### 6.2 Explicitly Out of Scope

The following are not part of the baseline:

- Comments.
- Likes and reactions.
- Followers.
- Private messaging.
- Online payments.
- Multi-tenant organization management.
- Native mobile apps.
- Advanced approval chains.
- Advertising management.
- Full analytics platform.

These may be considered in a later version.

## 7. Core User Flows

### 7.1 Author Login

1. Author opens the login page.
2. Author enters credentials.
3. System validates credentials.
4. System creates an authenticated session.
5. System redirects to the dashboard.

### 7.2 Create and Publish Post

1. Author opens the create-post page.
2. Author enters title and article content.
3. Author saves the post.
4. System stores the post in MySQL.
5. Author chooses to publish it.
6. System marks the post as published.
7. The article appears on the public blog.

### 7.3 Edit Post

1. Author opens the dashboard.
2. Author selects a post.
3. System loads the post into an edit form.
4. Author changes the content.
5. System validates and stores the updated values.
6. Updated content is used on the next public view when the post is published.

### 7.4 Delete Post

1. Author selects a post to delete.
2. System requests confirmation.
3. Author confirms the action.
4. System deletes the post.
5. The post is no longer shown publicly.

### 7.5 Public Reading

1. Visitor opens the blog.
2. System retrieves published posts.
3. System displays posts in a consistent format.
4. Visitor selects an article.
5. System displays the full article.

## 8. Functional Product Requirements

| ID | Requirement | Priority |
|---|---|---|
| PR-01 | Authors shall authenticate before accessing management functions. | Must |
| PR-02 | Authors shall be able to create posts. | Must |
| PR-03 | Authors shall be able to edit posts they are permitted to manage. | Must |
| PR-04 | Authors shall be able to publish posts. | Must |
| PR-05 | Authors shall be able to delete posts they are permitted to manage. | Must |
| PR-06 | The system shall store users and posts in MySQL. | Must |
| PR-07 | The public blog shall display published posts. | Must |
| PR-08 | Unpublished posts shall not be publicly visible. | Must |
| PR-09 | The system shall validate required fields. | Must |
| PR-10 | The system shall protect author-only pages with authentication. | Must |
| PR-11 | The system should show recent published posts first. | Should |
| PR-12 | The system should provide meaningful error messages for failed operations. | Should |

## 9. Content Model

### Author

- ID
- Username or email
- Password hash
- Account creation timestamp

### Post

- ID
- Author ID
- Title
- Content
- Status
- Creation timestamp
- Last updated timestamp

### Status

Version 1.0 uses:

- `draft`
- `published`

The status controls whether an article is visible on the public blog.

## 10. Product Principles

1. **Keep the core workflow obvious.** Authors should not need technical knowledge to manage a post.
2. **Public and author areas stay separate.** Reading content should not expose management controls.
3. **The database is the source of record.** Posts should not be maintained as hard-coded HTML files.
4. **Security is part of the implementation, not an afterthought.**
5. **Prefer small, understandable components over a complicated framework structure.**
6. **Build Version 1.0 around the provided problem statement.**

## 11. Future Direction

After Version 1.0 is stable, useful additions can include:

- Categories and tags.
- Keyword search.
- Filtering by category.
- Featured posts.
- Revision history.
- Separate editor/admin roles.
- Scheduled publishing.
- Image/media management.
- Article analytics.
- Comments with moderation.

Future features must not weaken the simple author-to-public workflow.

## 12. Release Definition

A release is ready when all Version 1.0 acceptance criteria pass, the main author and public flows work end-to-end, database operations are verified, and no known critical security or data-integrity issue remains.
