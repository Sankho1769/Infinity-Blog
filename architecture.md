# System Architecture

## Content Management System (CMS)

**Version:** 1.0  
**Status:** Baseline Architecture

---

## 1. Architecture Objective

The architecture should be simple enough for a BCA mini-project while following sound application structure.

The system separates:

- Web requests and page rendering.
- Authentication and authorization.
- Post management logic.
- Database access.
- Persistent data.

The architecture should also leave room for later features such as categories, search, revisions, or additional roles.

## 2. Recommended Stack

| Layer | Technology |
|---|---|
| Client | HTML5, CSS3, JavaScript |
| Server | PHP 8.x |
| Database | MySQL 8.x |
| Web Server | Apache |
| Local Development | XAMPP or equivalent |
| Authentication | PHP sessions |
| Password Storage | `password_hash()` / `password_verify()` |

Exact runtime versions may be adjusted to the available lab environment, but the application should be written for a currently supported PHP/MySQL combination.

## 3. High-Level Architecture

```text
+---------------------------+
|        Public Visitor     |
|  Blog List / Article View |
+-------------+-------------+
              |
              v
+---------------------------+
|       PHP Web Layer       |
| Routes / Pages / Forms    |
+-------------+-------------+
              |
      +-------+--------+
      |                |
      v                v
+-----------+    +-------------+
| Auth      |    | Post Logic  |
| Sessions  |    | CRUD        |
+-----+-----+    +------+------+
      |                 |
      +--------+--------+
               |
               v
       +---------------+
       | MySQL Database|
       | users / posts |
       +---------------+
```

## 4. Responsibility Boundaries

### Presentation Layer

Responsible for:

- HTML output.
- Form rendering.
- User feedback.
- Basic client-side validation.
- Public post display.

It should not contain raw database connection logic scattered across templates.

### Application Layer

Responsible for:

- Authentication checks.
- Input validation.
- Post ownership checks.
- Create/update/delete operations.
- Deciding whether a post is eligible for public display.

### Data Layer

Responsible for:

- Database connection.
- Prepared statements.
- Query execution.
- Fetching records.
- Maintaining database relationships.

## 5. Suggested Project Structure

```text
cms/
│
├── config/
│   └── database.php
│
├── includes/
│   ├── auth.php
│   ├── header.php
│   └── footer.php
│
├── public/
│   ├── index.php
│   └── post.php
│
├── author/
│   ├── login.php
│   ├── logout.php
│   ├── dashboard.php
│   ├── create_post.php
│   ├── edit_post.php
│   └── delete_post.php
│
├── assets/
│   ├── css/
│   └── js/
│
├── database/
│   └── schema.sql
│
└── README.md
```

The exact directory arrangement can be adapted to the selected hosting setup. The important rule is to keep database, authentication, public pages, and management pages logically separated.

## 6. Database Design

### users

```text
users
--------------------------------
id              INT PK
username        VARCHAR(...)
password_hash   VARCHAR(...)
created_at      TIMESTAMP
```

### posts

```text
posts
--------------------------------
id              INT PK
author_id       INT FK
title           VARCHAR(...)
content         TEXT
status          VARCHAR/ENUM
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

Relationship:

```text
users 1 -------- N posts
```

A foreign key from `posts.author_id` to `users.id` maintains the relationship.

## 7. Main Request Flows

### Login

```text
Browser
  -> login form
  -> PHP
  -> validate input
  -> SELECT author
  -> password_verify()
  -> create session
  -> dashboard
```

### Create Post

```text
Author Form
  -> PHP
  -> validate
  -> INSERT
  -> MySQL
  -> dashboard
```

### Edit Post

```text
Dashboard
  -> edit request
  -> load post for current author
  -> validate
  -> UPDATE
  -> MySQL
  -> dashboard
```

### Delete Post

```text
Dashboard
  -> delete confirmation
  -> ownership check
  -> DELETE
  -> MySQL
  -> dashboard
```

### Public Article

```text
Visitor
  -> public page
  -> SELECT published post
  -> render escaped content
```

## 8. Authentication and Authorization

Authentication answers:

> Who is logged in?

Authorization answers:

> What is this logged-in user allowed to do?

Version 1.0 uses a single author role.

Rules:

- Login is required for author management pages.
- The current user's ID is stored in the session.
- Post management queries should include the current author ID where ownership matters.
- A public visitor must not receive author dashboard functionality.
- Logout must destroy the active session.

## 9. Security Architecture

### Passwords

Never store passwords in plain text.

Use:

```php
password_hash($password, PASSWORD_DEFAULT);
```

Verify with:

```php
password_verify($password, $hash);
```

### SQL Injection

Use prepared/parameterized statements instead of concatenating user input into SQL.

### Output Encoding

Escape stored values before placing them into HTML when rich HTML is not intentionally supported.

### Session Security

Use server-side sessions. Regenerate the session ID after successful authentication.

### Input Validation

Validate required fields and constrain values such as post status to known allowed values.

### Ownership

For edit/delete operations, identify the current author from the session rather than trusting a client-supplied author ID.

## 10. Scalability Considerations

The initial deployment is intended for a small blog.

The design nevertheless keeps future changes possible:

- Search can be added without replacing the post model.
- Categories can be added through a separate table and foreign key.
- Additional roles can be introduced through a role field or role table.
- Media storage can be separated from article text.
- Pagination can be added to public and author listings.

The baseline should not introduce premature infrastructure such as microservices, message brokers, or distributed storage.

## 11. Architectural Constraints

- PHP is the server-side implementation language.
- MySQL is the primary database.
- Version 1.0 is web-based.
- The application should be understandable and demonstrable in a college laboratory environment.
- The system must remain within the supplied CMS problem statement.
