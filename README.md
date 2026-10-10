# Infinity Blog — Full-Stack CMS & Integration Architecture

> **A secure, accessible, full-stack Content Management System for blogs, built with Semantic HTML5, Modern CSS3, Modular JavaScript, PHP 8, and MySQL / MariaDB.**

---

## 1. Project Overview

**Infinity Blog** is a web-based Content Management System (CMS) designed for public readers, content authors, and system administrators. It solves core content publishing needs:
* **Public Visitors** can discover published articles, search by keywords, filter by active categories, and read full articles alongside stored AI-generated summaries.
* **Authors** can authenticate, manage their own posts, create drafts, assign administrator-created categories, publish/unpublish, and request on-demand AI article summaries.
* **Administrators** have complete oversight through a dedicated panel to moderate posts, maintain categories (creation, editing, activation, deactivation), manage author accounts, and inspect system audit logs.

---

## 2. System Architecture & Tech Stack

```text
┌────────────────────────────────────────────────────────┐
│                   Browser Client                       │
│  Semantic HTML5 Views  •  Modern CSS3  •  Vanilla JS   │
└───────────────────────────┬────────────────────────────┘
                            │ Fetch API (JSON, Cookie Auth, CSRF)
                            ▼
┌────────────────────────────────────────────────────────┐
│                   PHP 8 Web Layer                      │
│   /api/v1 Router (Apache URL Rewrite & Session Auth)   │
│  Controllers  •  Rate Limiter  •  CSRF & Audit Service │
└─────────────┬────────────────────────────┬─────────────┘
              │                            │
              ▼                            ▼
┌───────────────────────────┐ ┌──────────────────────────┐
│      MySQL / MariaDB      │ │ Google Gemini / AI Model │
│ Users • Posts • Categories│ │ Canonical Text Summarizer│
│  Audit Logs • Rate Limits │ │  (Extractive Fallback)   │
└───────────────────────────┘ └──────────────────────────┘
```

* **Frontend:** Semantic HTML5, Modern CSS custom properties (design tokens), ES Modules (Vanilla JS). No bloated heavy frameworks.
* **Backend:** PHP 8.2+ with PDO prepared statements and structured JSON envelopes (`API_SPEC.md`).
* **Database:** MySQL 8.x / MariaDB 10.4 with strict foreign keys, cascade updates, and query indexing.
* **Security:** PHP session cookies (`HttpOnly`, `SameSite=Lax`), CSRF token verification on all mutations (`POST`, `PATCH`, `DELETE`), login & AI rate limiting, and output encoding.

---

## 3. Directory Layout

```text
infinity-blog/
├── api/
│   └── v1/
│       ├── .htaccess               # Apache URL rewrite to index.php
│       └── index.php               # REST JSON API router (/api/v1/*)
├── assets/
│   ├── css/
│   │   ├── style.css               # Design tokens, variables, global reset & UI shell
│   │   ├── public.css              # Hero banner, cards grid, reading layout (720px)
│   │   └── admin.css               # Dashboard stats, split editor, moderation tables
│   └── js/
│       ├── config.js               # Centralized API base URL resolver
│       ├── api-client.js           # Reusable fetch client (JSON, CSRF, credentials)
│       ├── auth.js                 # Authentication service & session management
│       ├── public-blog.js          # Public post & category service
│       ├── author-posts.js         # Author CRUD & AI summarizer service
│       ├── admin.js                # System stats, moderation, user & category service
│       └── main.js                 # Global app shell (nav, auth state, toasts, modals)
├── backend/
│   ├── config/
│   │   └── Database.php            # PDO singleton database connection
│   └── src/
│       ├── Response.php            # Standard JSON success and error envelopes
│       ├── SessionManager.php      # Session handling, cookie flags & CSRF checks
│       ├── RateLimiter.php         # Sliding-window rate limiting
│       ├── AuditLogger.php         # Audit trail event recorder
│       └── AISummarizer.php        # Server-side plain-text article summarizer
├── database/
│   ├── schema.sql                  # MySQL schema definition (5 tables)
│   └── setup.php                   # Database setup & administrator provisioning CLI
├── tests/
│   └── integration_test.php        # Automated 57-assertion end-to-end test suite
├── index.html                      # Public homepage & article feed
├── post.html                       # Public single article reading view
├── login.html                      # Author & Administrator login portal
├── dashboard.html                  # Author dashboard with publication metrics
├── posts.html                      # Author post management interface
├── create-post.html                # Post creation studio
├── edit-post.html                  # Post editor with AI summary generation
├── categories.html                 # Categories browser & administration
├── admin.html                      # System administrator console
├── .env.example                    # Environment template
└── .gitignore                      # Git exclusion rules
```

---

## 4. Quick Start & Local Setup

### Step 1: Start Services in XAMPP
Ensure **Apache** (Port 80) and **MySQL** (Port 3306) are running via the XAMPP Control Panel.

### Step 2: Initialize Database and Provision Users
Run the CLI provisioning script:
```bash
php database/setup.php
```
This automatically:
1. Executes `database/schema.sql` to build `users`, `categories`, `posts`, `audit_logs`, and `rate_limit_buckets`.
2. Provisions the initial administrators:
   * **`Shubham Banerjee`** (admin)
   * **`Sounak Sikdar`** (admin)
   * *Default setup password:* `Admin@Infinity2026!`
3. Provisions a test author:
   * **`john_doe`** (author)
   * *Default setup password:* `Author@Infinity2026!`
4. Seeds initial active categories (`Technology`, `Education`, `Lifestyle`, `Photography`) and sample articles.

### Step 3: Access the Application
Open your browser and navigate to:
* **Public Blog:** [http://localhost/infinity-blog/index.html](http://localhost/infinity-blog/index.html)
* **Login Portal:** [http://localhost/infinity-blog/login.html](http://localhost/infinity-blog/login.html)
* **Author Dashboard:** [http://localhost/infinity-blog/dashboard.html](http://localhost/infinity-blog/dashboard.html)
* **Admin Console:** [http://localhost/infinity-blog/admin.html](http://localhost/infinity-blog/admin.html)

---

## 5. Automated Integration Testing

Run the test suite directly from your terminal:
```bash
php tests/integration_test.php
```

### Test Coverage (57 Assertions — 100% Passing):
1. **Authentication:** Valid/invalid logins, session restoration (`/auth/me`), CSRF token retrieval, and post-logout rejection.
2. **Public Blog:** Published posts retrieval, draft isolation (drafts return 404 to public queries), keyword search, and category filtering.
3. **Author Post Operations:** Post drafting, update lifecycle, validation requiring active categories before publishing, unpublishing, and confirmed deletion.
4. **Authorization & IDOR:** Role escalation prevention, Insecure Direct Object Reference (IDOR) protection preventing cross-author post tampering, and CSRF requirement on mutations.
5. **Administrative Capabilities:** System statistics, category creation, activation/deactivation, author user provisioning, and audit log generation.
6. **AI Article Summarization:** Authorized generation, database storage, stale summary detection upon article modification, and omission of stale summaries from public views.

---

## 6. API Reference (`/api/v1`)

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `POST` | `/auth/login` | Public | Authenticates credentials and starts PHP session |
| `POST` | `/auth/logout` | Authenticated | Destroys session and clears session cookie |
| `GET` | `/auth/me` | Authenticated | Returns current safe user identity & role |
| `GET` | `/auth/csrf` | Browser Session | Returns session-bound CSRF token |
| `GET` | `/posts` | Public | Lists published articles (supports `q`, `category_id`, `page`) |
| `GET` | `/posts/{id}` | Public | Fetches published article and current summary |
| `GET` | `/categories` | Public | Lists active categories |
| `GET` | `/author/posts` | Author / Admin | Lists posts manageable by author with status counts |
| `POST` | `/author/posts` | Author / Admin | Creates a new post (draft or published) |
| `GET` | `/author/posts/{id}` | Author / Admin | Retrieves post for editing (ownership checked) |
| `PATCH` | `/author/posts/{id}` | Author / Admin | Updates post title, body, status, category |
| `POST` | `/author/posts/{id}/publish` | Author / Admin | Publishes article (requires active category) |
| `POST` | `/author/posts/{id}/unpublish` | Author / Admin | Moves article to draft |
| `DELETE` | `/author/posts/{id}` | Author / Admin | Deletes article permanently |
| `POST` | `/author/posts/{id}/summary` | Author / Admin | Generates/regenerates plain-text AI summary |
| `GET` | `/admin/stats` | Admin Only | System overview metrics |
| `GET` | `/admin/posts` | Admin Only | System-wide post moderation |
| `GET` | `/admin/categories` | Admin Only | All categories with post counts |
| `POST` | `/admin/categories` | Admin Only | Creates a new category |
| `PATCH` | `/admin/categories/{id}` | Admin Only | Edits category name, slug, description |
| `POST` | `/admin/categories/{id}/activate` | Admin Only | Activates category for publication |
| `POST` | `/admin/categories/{id}/deactivate` | Admin Only | Deactivates category without breaking posts |
| `GET` | `/admin/users` | Admin Only | Lists users without password hashes |
| `POST` | `/admin/users` | Admin Only | Provisions author/admin account |
| `POST` | `/admin/users/{id}/deactivate` | Admin Only | Deactivates user account |
| `POST` | `/admin/users/{id}/activate` | Admin Only | Reactivates user account |
| `GET` | `/admin/audit-logs` | Admin Only | Paginated audit trail records |

---

## 7. License & Credits
Developed as part of the **Infinity Blog CMS** initiative.
* Architecture & Frontend Implementation: **Sounak Sikdar**
* Baseline Specifications: **Sankho1769**
