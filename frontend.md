# Frontend Architecture and UI Implementation Specification

## Content Management System (CMS) — Infinity-Blog

**Document Version:** 1.0  
**Status:** In Progress / Feature Branch (`frontend`)  
**Primary Stack:** Semantic HTML5, Modern CSS3, Vanilla JavaScript, PHP View Templates  
**Target Audience:** Frontend Developers, Project Reviewers, Collaborators  

---

## 1. Overview & Objectives

This document establishes the frontend architecture, component layout, and UI design standards for **Infinity-Blog CMS**. 

The goal is to deliver a clean, readable, responsive, and accessible user interface for both public readers and authenticated blog authors without relying on heavy frontend frameworks.

### Core Objectives
- **Content-First Presentation:** Optimal typography, readability, and comfortable reading layout for blog posts.
- **Responsive Design:** Seamless layout adaptation for mobile, tablet, and desktop screens.
- **Separation of Concerns:** Clear demarcation between public views and author management views.
- **Fast & Lightweight:** Minimal external dependencies, leveraging modern CSS and native browser APIs.
- **Accessible & Usable:** Semantic HTML5 landmarks, clear focus states, and straightforward form validation.

---

## 2. Information Architecture & Page Structure

```text
infinity-blog/
├── assets/
│   ├── css/
│   │   ├── style.css           # Global typography, reset, variables, utilities
│   │   ├── public.css          # Public blog feed and article reading styles
│   │   └── admin.css           # Author dashboard, editor, and control styles
│   └── js/
│       ├── main.js             # Shared interactions (alerts, modals, mobile nav)
│       └── editor.js           # Post editor helpers (character counts, validation)
├── includes/
│   ├── header.php              # Global navigation and meta tags
│   ├── footer.php              # Footer copyright, links, and closing tags
│   └── alerts.php              # Session flash messages (success/error banners)
├── views/ (or root scripts)
│   ├── index.php               # Public homepage / article feed
│   ├── post.php                # Public single article reading view
│   ├── login.php               # Author authentication form
│   ├── dashboard.php           # Author posts management table
│   ├── post-create.php         # New article drafting form
│   └── post-edit.php           # Article editing form
```

---

## 3. View Specifications

### 3.1 Public Views

#### 1. Home / Blog Feed (`index.php`)
- **Header:** Site title (`Infinity-Blog`), brief tagline, and link to Author Login.
- **Featured / Recent Posts:** Chronological list of published articles.
- **Article Card Structure:**
  - Post Title (`<h2>` linked to `post.php?id=...`)
  - Metadata row: Author name, publication date, reading time estimate.
  - Excerpt: First 150–200 characters of content.
  - "Read More" link/button.
- **Empty State:** Friendly fallback message when no published posts exist.

#### 2. Single Article View (`post.php`)
- **Hero / Header:** Full article title, author byline, formatted publication date.
- **Content Body:** Formatted long-form text with generous line-height (`1.7`), legible font size (`1.125rem`), and responsive media elements.
- **Footer Navigation:** Back link to the main feed.

---

### 3.2 Author (Admin) Views

#### 1. Author Login (`login.php`)
- **Centered Card:** Clean, distraction-free container.
- **Form Controls:**
  - Email / Username field with autofocus.
  - Password field with show/hide toggle.
  - Submit button with active/loading state.
- **Feedback:** Clear inline error messages on invalid authentication.

#### 2. Author Dashboard (`dashboard.php`)
- **Toolbar:** Welcome greeting, "Create New Post" primary action button, and Logout button.
- **Posts Table / List:**
  - Columns: Title, Status badge (`Published` in green, `Draft` in amber), Created Date, Actions.
  - Actions: Edit button, Delete button (with JavaScript confirmation modal or prompt).
- **Empty State:** Call-to-action prompting the author to write their first post.

#### 3. Post Editor (`post-create.php` & `post-edit.php`)
- **Fields:**
  - Title input (`<input type="text">`, required).
  - Content area (`<textarea>` with auto-expand or clean multi-line formatting).
  - Status selector (`<select>`: `Draft` or `Published`).
- **Action Bar:** "Save as Draft", "Publish Post", and "Cancel" buttons.

---

## 4. Design System & CSS Standards

### 4.1 Design Tokens (CSS Custom Properties)

```css
:root {
  /* Color Palette */
  --color-primary: #2563eb;
  --color-primary-hover: #1d4ed8;
  --color-bg: #f8fafc;
  --color-surface: #ffffff;
  --color-text-main: #0f172a;
  --color-text-muted: #64748b;
  --color-border: #e2e8f0;

  /* Status Colors */
  --color-success: #16a34a;
  --color-success-bg: #f0fdf4;
  --color-danger: #dc2626;
  --color-danger-bg: #fef2f2;
  --color-warning: #d97706;
  --color-warning-bg: #fffbeb;

  /* Typography */
  --font-family-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
  --font-family-serif: "Merriweather", Georgia, Cambria, "Times New Roman", serif;

  /* Spacing Scale */
  --space-1: 0.25rem;
  --space-2: 0.5rem;
  --space-3: 0.75rem;
  --space-4: 1rem;
  --space-6: 1.5rem;
  --space-8: 2rem;

  /* Layout */
  --max-width-content: 720px;
  --max-width-page: 1040px;
  --radius-sm: 4px;
  --radius-md: 8px;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
```

### 4.2 Typography & Readability Guidelines
- **Body Font:** High-legibility system sans-serif or clean serif for article reading.
- **Reading Column:** Constrained to `--max-width-content` (`65ch`–`75ch`) to prevent eye fatigue on wide screens.
- **Heading Scale:** Consistent hierarchy from `h1` down to `h4`.

---

## 5. Client-Side Validation & Interactions

1. **Delete Confirmation:** Every delete action triggers a client-side confirmation prompt before submitting the deletion request to prevent accidental data loss.
2. **Form Validation:** All forms use native HTML5 validation attributes (`required`, `type="email"`, `minlength`) augmented with clear visual states on `:invalid` and `:focus`.
3. **Flash Message Auto-Dismiss:** Success alerts can be dismissed manually or fade out automatically after 4 seconds.

---

## 6. Implementation Checklist

- [ ] Set up `assets/css/` directory and create base reset & variables.
- [ ] Implement responsive global header and navigation bar.
- [ ] Build reusable card component for public blog index.
- [ ] Create reading layout template for individual post pages.
- [ ] Build author login layout with form styling.
- [ ] Build author dashboard table with status badges and action controls.
- [ ] Implement create/edit post editor layout.
- [ ] Add lightweight client-side confirmation scripts and form validation.
