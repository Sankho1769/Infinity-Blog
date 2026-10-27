# Infinity Blog — Full-Stack CMS & Integration Architecture

> **A secure, accessible, full-stack Content Management System for blogs, built with Semantic HTML5, Modern CSS3, Modular JavaScript, PHP 8, and MySQL / MariaDB.**

---

## 1. Project Overview

**Infinity Blog** is a web-based Content Management System (CMS) designed for public readers, content authors, and system administrators. It solves core content publishing needs:
* **Public Visitors** can discover published articles, search by keywords, filter by active categories, and read full articles alongside stored AI-generated summaries.
* **Authors** can authenticate, manage their own posts, create drafts, assign administrator-created categories, publish/unpublish, and request on-demand AI article summaries.
* **Administrators** have complete oversight through a dedicated panel to moderate posts, maintain categories (creation, editing, activation, deactivation), manage author accounts, and inspect system audit logs.

---

## 2. Software Requirements Specification (SRS) Compliance

Infinity Blog fully implements the official **Content Management System (CMS) Software Requirements Specification (SRS Version 1.0)** for PHP and MySQL while preserving advanced production capabilities:

### Functional Requirements Matrix (FR-01 to FR-20)
| SRS ID | Requirement Name | Specification Description | Infinity Blog Implementation Status |
|:---|:---|:---|:---|
| **FR-01** | Author authentication | Provide a login form for registered authors | [`login.html`](login.html) & `POST /api/v1/auth/login` (supports username or email) — **Implemented** |
| **FR-02** | Login validation | Validate credentials and deny invalid access | Secure `password_verify` comparison, dual rate limiting, generic error messages — **Implemented** |
| **FR-03** | Session management | Maintain authenticated session until logout/expiry | Native PHP sessions with `HttpOnly`, `SameSite=Lax`, active status verification — **Implemented** |
| **FR-04** | Logout | Terminate author session securely | `POST /api/v1/auth/logout` revokes session, unsets cookie, logs event — **Implemented** |
| **FR-05** | Create post | Authenticated author creates new article | [`create-post.html`](create-post.html) & `POST /api/v1/author/posts` (title & content) — **Implemented** |
| **FR-06** | Store post | Save post in MySQL associated with author | MySQL `posts` table with foreign key `fk_posts_author_id` — **Implemented** |
| **FR-07** | Edit post | Author edits their own existing post | [`edit-post.html`](edit-post.html) & `PATCH /api/v1/author/posts/{id}` (ownership validated) — **Implemented** |
| **FR-08** | Update post | Persist edits without creating duplicate posts | In-place atomic SQL `UPDATE` preserving record ID — **Implemented** |
| **FR-09** | Publish post | Mark post as published | `POST /api/v1/author/posts/{id}/publish` sets `status = 'published'`, `published_at = NOW()` — **Implemented** |
| **FR-10** | Public visibility | Only published posts shown publicly; drafts private | `GET /posts` & `GET /posts/{id}` strictly isolate drafts (404 for drafts) — **Implemented** |
| **FR-11** | Delete post | Author deletes post they manage | `DELETE /api/v1/author/posts/{id}` checks ownership and removes record — **Implemented** |
| **FR-12** | Deletion confirmation | Request confirmation before irreversible delete | Accessible confirmation modal (`showConfirmModal`) on dashboard and post views — **Implemented** |
| **FR-13** | Public post listing | Display published posts in structured list | [`index.html`](index.html) responsive article cards with title, author, date, excerpt — **Implemented** |
| **FR-14** | Article view | Public view for reading full article content | [`post.html`](post.html) reading layout rendering complete content safely — **Implemented** |
| **FR-15** | Database relationship | Author-to-post relationship in MySQL | Relational foreign key `author_id` referencing `users.id` with cascade updates — **Implemented** |
| **FR-16** | Access control | Prevent unauthenticated access to author pages/APIs | Server-side role middleware `SessionManager::requireRole('author', 'admin')` (HTTP 401/403) — **Implemented** |
| **FR-17** | Validation | Validate required post fields before saving | Server-side validation of title (1-255 chars), content, status, with field error JSON — **Implemented** |
| **FR-18** | Output safety | Safely render user-controlled text without XSS | Client-side DOM `textContent` injection and HTML entity encoding (`escapeHtml`) — **Implemented** |
| **FR-19** | Error handling | Meaningful errors when operations cannot complete | Standardized JSON envelopes with clear error codes (`VALIDATION_ERROR`, etc.) — **Implemented** |
| **FR-20** | Post ordering | Display recent published posts before older posts | Public feed sorted `ORDER BY p.published_at DESC, p.id DESC` (deterministic) — **Implemented** |

### Acceptance Criteria Verification (10 / 10 Passing)
1. **Author Login:** Given valid credentials, author logs in and accesses the dashboard (`/auth/me`).
2. **Post Storage:** Author creates post with title and content; persisted in MySQL with author ID.
3. **Post Editing:** Author updates post; MySQL updates the record in-place without duplicate entries.
4. **Publishing:** Author publishes post; post becomes visible on public feed and detail view.
5. **Draft Privacy:** Unpublished drafts are inaccessible to public requests (HTTP 404).
6. **Confirmed Deletion:** Authorized author confirms deletion; post is permanently removed from MySQL.
7. **Access Control:** Unauthenticated visitors cannot access author management APIs (HTTP 401).
8. **Structured Listing:** Public site displays published posts in structured, readable cards.
9. **Error Handling:** Invalid inputs return understandable field-level error messages (HTTP 422).
10. **Safe Queries & Passwords:** Passwords hashed with `PASSWORD_DEFAULT` (bcrypt); all SQL queries use PDO prepared statements.

---

## 3. System Architecture & Tech Stack

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

## 4. Directory Layout

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
│   │   ├── Database.php            # PDO singleton database connection
│   │   └── Env.php                 # Zero-dependency environment loader
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
│   └── integration_test.php        # Automated 124-assertion end-to-end test suite
├── index.html                      # Public homepage & article feed
├── post.html                       # Public single article reading view
├── signup.html                     # Author self-registration portal
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

## 5. Quick Start & Local Setup

### Step 1: Start Services in XAMPP
Ensure **Apache** (Port 80) and **MySQL** (Port 3306) are running via the XAMPP Control Panel.

### Step 2: Initialize Database and Provision Administrators Safely
Copy `.env.example` to `.env` if local configuration overrides are needed (e.g. `DB_PASS`, `ADMIN_DEFAULT_PASSWORD`, `GEMINI_API_KEY`).

Run the CLI provisioning script:
```bash
php database/setup.php
```
> [!NOTE]
> `database/setup.php` enforces **CLI-only execution**. Direct web browser access is blocked with HTTP 403 Forbidden.
> Provisioning is **fully idempotent**: it never resets existing account passwords, roles, or active/inactive statuses.
> In production environments (`APP_ENV=production`), `ADMIN_DEFAULT_PASSWORD` must be provided as an environment variable (minimum 12 characters).

Default development accounts configured if not already present:
* **`Shubham Banerjee`** (admin)
* **`Sounak Sikdar`** (admin)
* **`john_doe`** (author, development only)
* Initial active categories (`Technology`, `Education`, `Lifestyle`, `Photography`) and sample posts are verified.

### Step 3: Access the Application

Infinity Blog can be run using any of the following 3 methods:

#### Method A: 1-Click Windows Launcher (Recommended for Local Testing)
Simply double-click [`run-infinity-blog.bat`](run-infinity-blog.bat) in the project root.
- Automatically locates your local PHP executable.
- Launches the built-in web server with full REST API routing at `http://localhost:8000/`.
- Opens your default web browser automatically.

#### Method B: Full XAMPP / Apache Web Server
If running via XAMPP with Apache and MySQL started:
* **Public Blog:** [http://localhost/infinity-blog/index.html](http://localhost/infinity-blog/index.html)
* **Sign Up Portal:** [http://localhost/infinity-blog/signup.html](http://localhost/infinity-blog/signup.html)
* **Login Portal:** [http://localhost/infinity-blog/login.html](http://localhost/infinity-blog/login.html)
* **Author Dashboard:** [http://localhost/infinity-blog/dashboard.html](http://localhost/infinity-blog/dashboard.html)
* **Admin Console:** [http://localhost/infinity-blog/admin.html](http://localhost/infinity-blog/admin.html)

#### Method C: Instant Zero-Setup Offline Preview
If you send the project folder to someone without a web server or PHP, they can directly double-click [`index.html`](index.html).
- The built-in Standalone Preview Engine activates seamlessly on `file:///` URLs.
- Renders all buttons, category chips, sample articles, search filters, metrics, and navigation pages without requiring any server.

---

## 6. Automated Integration Testing

Run the test suite directly from your terminal:
```bash
php tests/integration_test.php
```

### Test Coverage (144 Assertions across 17 Domains — 100% Passing):
1. **Web Access Controls & File Protections:** Verifies that `.git/HEAD`, `.git/config`, `.env`, `database/setup.php`, `backend/`, and `tests/` return HTTP 403 Forbidden.
2. **Security HTTP Headers & Transport Policies:** X-Content-Type-Options (`nosniff`), X-Frame-Options (`DENY`), Referrer-Policy, CSP, and CORS preflight.
3. **Atomic Rate Limiting Engine:** Tests atomic sliding-window throttling and verified removal of test-runner bypass headers.
4. **Payload Thresholds & JSON Safety:** Enforces 2MB maximum payload limit (HTTP 413) and JSON maximum recursion depth of 32 (HTTP 400).
5. **Authentication Security:** Credential verification, session cookies (`HttpOnly`, `SameSite=Lax`), `/auth/me` identity retrieval, CSRF issuance, and post-logout session destruction.
6. **Fail-Closed Authorization & Account Lifecycle:** Deactivated accounts cannot log in (HTTP 403 `ACCOUNT_INACTIVE`), and admin self-deactivation is strictly prohibited.
7. **CSRF Token Verification:** Enforces anti-CSRF validation across all mutating methods (`POST`, `PATCH`, `DELETE`).
8. **Password Policy Hardening:** 12–128 character policy, dictionary denylist, and malformed email format rejection.
9. **Role Access & IDOR Defenses:** Prevents author privilege escalation to admin routes and prevents cross-author post tampering.
10. **SQL Injection Resistance:** Prepared statements protect all search parameters and category filters.
11. **XSS Output Sanitization:** User-controlled content rendered safely via DOM `textContent` and HTML escaping.
12. **AI Summarizer Containment & Freshness:** Adversarial prompt injection isolation, HTML script removal, and automatic stale summary tracking upon article modification.
13. **Audit Logger Secret Redaction:** Plaintext passwords, tokens, API keys, and session IDs recursively redacted from audit logs.
14. **Full Blog & Administration End-to-End Regression:** Public feeds, category management, and administrative moderation lifecycle.
15. **SRS Document Compliance & Acceptance Criteria:** Automated verification of all 10 SRS acceptance criteria (login, create, edit, publish, draft isolation, delete, access control, structured listing, error handling, password hashing, and foreign keys).
16. **User Registration & Lifecycle Verification (20 Criteria):** Verification of `POST /auth/register`, username/email immediate login, invalid password rejection (401), missing/duplicate field validations (422), gender and date of birth validation, password hashing, unstored confirm password, and existing record preservation.

---

## 7. API Reference (`/api/v1`)

| Method | Endpoint | Access | Description |
|---|---|---|---|
| `POST` | `/auth/register` | Public (CSRF & Rate Limited) | Registers new author account with username, email, gender, DOB, password |
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
| `POST` | `/author/posts/{id}/publish` | Author / Admin | Publishes article (active category validated if assigned) |
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

## 8. License & Credits
Developed as part of the **Infinity Blog CMS** initiative.
* Architecture & Frontend Implementation: **Sounak Sikdar**
* Baseline Specifications: **Sankho1769**
