# Product and UI Design Specification

## Content Management System (CMS)

**Version:** 1.0  
**Design Goal:** Simple, credible, readable, and practical.

---

## 1. Design Direction

The interface should look like a small professional publishing tool rather than a college form collection.

The design should be:

- Clean.
- Content-first.
- Easy to scan.
- Consistent across pages.
- Comfortable for long article text.
- Responsive enough for normal desktop and mobile browser use.

Avoid excessive cards, decorative gradients, animated backgrounds, and unnecessary dashboards. The article and the actions around it are the important parts.

## 2. Information Architecture

```text
Public
├── Home / Blog
└── Article

Author
├── Login
├── Dashboard
├── Create Post
├── Edit Post
└── Logout
```

## 3. Public Experience

### Home / Blog

Recommended order:

```text
Header
  ├── Blog title
  └── Author Login

Main
  ├── Page heading
  ├── Published article list
  │    ├── Title
  │    ├── Author
  │    ├── Published date
  │    └── Short excerpt
  └── Pagination if required
```

The homepage should make the newest published posts easy to find.

### Article Page

Recommended structure:

```text
Back to Blog

Article Title

By Author Name
Published Date
Last Updated Date

--------------------------------

Article content

--------------------------------

Back to Blog
```

Use readable line length and generous spacing around paragraphs.

## 4. Author Experience

### Login

```text
+--------------------------------+
|           Author Login         |
|                                |
| Username / Email               |
| [__________________________]   |
|                                |
| Password                       |
| [__________________________]   |
|                                |
| [        Login        ]        |
|                                |
| Error message when required    |
+--------------------------------+
```

Keep the form focused. Do not mix management information into the login screen.

### Dashboard

The dashboard should answer three questions immediately:

1. What posts do I have?
2. What state are they in?
3. What can I do next?

Suggested structure:

```text
Dashboard                              [Create New Post]

Posts
-----------------------------------------------------------
Title                    Status       Updated      Actions
-----------------------------------------------------------
Getting Started with PHP Published    08 Oct       Edit Delete
MySQL Basics             Draft        08 Oct       Edit Delete
```

A small count summary may be shown above the table:

```text
All Posts | Published | Drafts
```

These counts are useful, but the post list remains the primary element.

### Create / Edit Post

```text
Title
[____________________________________________]

Content
[                                            ]
[                                            ]
[                                            ]
[                                            ]

Status
[ Draft v ]

[Save Post]   [Cancel]
```

For edit mode:

```text
[Save Changes]
```

For publication:

```text
[Publish]
```

Buttons should make the consequence clear.

## 5. Component Guidelines

### Header

- Blog name at left.
- Context-appropriate navigation.
- Authentication state on author pages.

### Buttons

Primary button: one clear main action per screen.

Secondary button: navigation or non-primary action.

Danger button: delete.

Avoid using the same visual treatment for primary and destructive actions.

### Forms

- Label every field.
- Show required fields clearly.
- Keep validation close to the field when practical.
- Preserve entered values after recoverable validation errors.

### Tables

Use a table on the author dashboard when several posts must be compared.

Columns should be kept concise:

```text
Title | Status | Updated | Actions
```

## 6. Status Presentation

Use clear text labels:

```text
Draft
Published
```

Do not rely on color alone to communicate status.

## 7. Typography

Recommended defaults:

- Headings: strong, clear sans-serif.
- Body: highly readable sans-serif or system font.
- Article body: approximately 16px on desktop.
- Line height: approximately 1.5-1.7 for long-form text.
- Avoid more than two font families.

The final type scale should be set in CSS variables so it can be changed centrally.

## 8. Color

Use a restrained neutral palette with one primary accent.

Example design tokens:

```css
--color-bg: #f7f8fa;
--color-surface: #ffffff;
--color-text: #1f2937;
--color-muted: #6b7280;
--color-border: #e5e7eb;
--color-primary: #1d4ed8;
--color-danger: #b91c1c;
```

These are starting values, not mandatory final colors.

## 9. Responsive Behavior

At smaller widths:

- Stack form fields vertically.
- Let the article list switch from wide table layout to stacked rows or cards.
- Keep buttons reachable without horizontal scrolling.
- Preserve readable article text.
- Avoid fixed-width containers that force the page wider than the viewport.

## 10. Accessibility

The interface should:

- Use semantic HTML where practical.
- Use labels associated with form fields.
- Provide visible keyboard focus.
- Maintain sufficient contrast.
- Avoid color-only status indicators.
- Use meaningful link/button text.
- Keep error messages understandable.

## 11. Empty and Error States

The product should handle empty states explicitly.

Example:

```text
No posts yet.

Create your first article to get started.
[Create Post]
```

For public pages:

```text
No published articles are available.
```

For failures:

```text
We couldn't save the post.
Please check the required fields and try again.
```

Do not expose raw SQL errors to ordinary users.

## 12. Design Principle

The CMS should feel like a tool for writing and maintaining content.

The design should support the workflow rather than compete with it.
