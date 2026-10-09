# Functional UI and Interaction Specification

## Infinity Blog

**Version:** 1.0  
**Purpose:** Define which pages exist, what information appears on them, which actions connect to which pages/endpoints, and what data is needed. This document intentionally does not prescribe colors, visual style, effects, typography, or the final design language.

---

## 1. Navigation model

### Public navigation

- Home / Blog
- Category browsing/filtering
- Search
- Login
- Article detail

### Author navigation

- Dashboard
- My Posts
- Create Post
- Profile/account information if provided
- Public Blog
- Logout

### Admin navigation

- Admin Dashboard
- All Posts
- Users
- Categories
- Activity Log
- Public Blog
- Logout

The frontend may present these links differently. It must not rely on navigation visibility for authorization; the API is the authority.

## 2. Page inventory

| Page | Access | Main information | Main actions | Data / API dependency |
|---|---|---|---|---|
| Public Home | Public | Latest published posts, category, author, date, summary/excerpt | Search, filter, open article, login | `GET /api/v1/posts`, `GET /api/v1/categories` |
| Article Detail | Public | Title, author, category, dates, summary, full body | Back to list | `GET /api/v1/posts/{id}` |
| Login | Public | Login identifier, password, error message | Login | `POST /api/v1/auth/login` |
| Author Dashboard | Author | Database-derived own-post total/draft/published counts and recent posts | Create, edit, publish, delete, summary | `GET /api/v1/author/posts` |
| My Posts | Author | Own posts with status and timestamps | Search/filter, edit, delete, publish | Author post endpoints |
| Create Post | Author | Title, content, active category, draft/publish intent | Save Draft, Publish, Cancel, Generate Summary after save | `POST /api/v1/author/posts` and summary endpoint |
| Edit Post | Author owner or Admin | Existing fields, status, summary status | Save, Publish/Unpublish, Generate/Regenerate Summary, Delete | `GET/PATCH/POST/DELETE /api/v1/...` |
| Admin Dashboard | Admin | Database-derived system-wide post total/draft/published counts, user/category totals where available, and recent events | Open posts/users/categories/activity | Existing admin post/user/category lists and audit endpoint |
| Admin Posts | Admin | All posts, author, status, category | Create own-account draft, edit, publish/unpublish, delete, view | `/api/v1/admin/posts` |
| Admin Users | Admin | User name, role, active state, timestamps | Create/deactivate, role actions if authorized | `/api/v1/admin/users` |
| Admin Categories | Admin | Name, server-generated slug, active state, post count | Create, edit, activate/deactivate | `/api/v1/admin/categories` |
| Admin Activity | Admin | Timestamp, actor, action, entity | Filter/view details within policy | `/api/v1/admin/audit-logs` |

## 3. Login page

Required fields:

- User ID / username
- Password

Actions:

- Login

Behavior:

- Show a generic failure message for invalid credentials.
- On success, navigate to the correct dashboard based on the server-returned role.
- Never decide the user's role based only on the entered name.
- Do not show whether a username exists in the failure message.

## 4. Author dashboard

Show a summary of the author's own content only:

- Total posts.
- Drafts.
- Published posts.
- Recent posts.
- Status, category, last updated date, actions.

Actions and destinations:

- **Create Post** opens the creation form.
- **Edit** opens the selected post, after authorization is checked by the backend.
- **Publish / Unpublish** calls the relevant status action.
- **Delete** opens a confirmation step and then sends a CSRF-protected delete request.
- **Generate Summary** is available only for a post the author can manage.

## 5. Create/Edit Post form

Fields:

- Title (required).
- Article body (required to save a post).
- Category selector sourced from active categories.
- Status where the author is allowed to set it.
- Summary status and summary preview when present.

Actions:

- Save Draft.
- Publish or Update.
- Cancel / return to posts.
- Generate Summary after the post has been saved; a draft may be summarized if it has content.
- Regenerate Summary when an existing summary is stale or a permitted manual regeneration is requested.
- Delete as a separate, confirmed destructive action.

Validation:

- Show errors next to affected fields where possible.
- Retain safe field values after validation errors.
- Server responses are authoritative.
- The frontend must not render arbitrary summary HTML.

## 6. Public article list

Each listing item may show:

- Title.
- Author display name.
- Category.
- Published date.
- Current summary or a plain-text excerpt.
- Link to article details.

Actions:

- Search by keyword.
- Filter by category.
- Clear filters.
- Move between pages.

Only published posts are returned by the public API. Search and filter controls must not expose draft information through counts or error messages.

## 7. Admin panel

### Dashboard

Show total, draft, and published post counts from database records and a short list of recent audit events. Author counts include only the authenticated author's posts; admin counts are system-wide. The admin dashboard should also show user and category totals where those values are available from existing admin-list data. Do not display view counts. These values use existing list API operations, not a new endpoint.

### Category management

Fields: name, optional description, active status. The backend generates and returns a unique slug from the name; the client does not supply an authoritative slug.

Actions: create, edit, activate/deactivate.

Do not offer a casual hard-delete when posts reference the category.

### User management

Show account identifier, display name, role, active status, creation date, and last login where appropriate. Password hashes must never be displayed. Account creation/role changes must use protected operations.

### All-post management

Allow admins to find posts across authors, view status/category, and perform permitted edit/publish/unpublish/delete operations. Require confirmation for destructive actions.

### Activity log

Display actor, action, entity, and timestamp. Avoid showing secret values or private content in the audit UI.

## 8. Required interface states

Every data-driven page should consider:

- Loading.
- Success.
- Empty result.
- Validation error.
- Unauthorized/forbidden response.
- Not found.
- Rate limited.
- Server/dependency failure.

## 9. Confirmation and navigation rules

- Destructive actions require confirmation.
- After successful create/update/delete, return to a sensible page and show status feedback.
- Failed actions must not be presented as successful.
- Logout ends the server session before showing the signed-out state.
- A stale summary must be clearly indicated to authors and must not be presented publicly as current.

## 10. Visual styling boundary

This specification defines information architecture and behavior only. Colors, typography, shadows, gradients, glass/clay effects, iconography, animation, and final spacing are intentionally left to the separate visual design decision. Do not infer a visual theme from this document.
