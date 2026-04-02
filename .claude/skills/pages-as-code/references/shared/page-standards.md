# Page Standards

## File format

Every `.html` page file has two parts:

1. YAML front matter between `---` delimiters
2. Body: Gutenberg block markup

```html
---
title: Page Title
slug: page-slug
status: draft
---
<!-- wp:paragraph -->
<p>Content.</p>
<!-- /wp:paragraph -->
```

## Front matter fields

| Field | Required | Default | Maps to | Notes |
|---|---|---|---|---|
| `title` | yes | — | `post_title` | Always required. Push fails without it. |
| `slug` | no | filename | `post_name` | Primary key. `about.html` becomes `about`. Must be unique across all page files. |
| `status` | no | `draft` | `post_status` | `draft`, `publish`, `pending`, `private`, `future` |
| `template` | no | default | `page_template` | Must exist in the active theme. Omit if unsure. |
| `parent` | no | — | `post_parent` | Slug of parent page. Parent must exist before push. |
| `meta` | no | — | post meta | One-level key-value map. Written as-is, not namespaced. |

## Slug resolution

1. If front matter contains `slug`, use it
2. Otherwise derive from filename: `product-a.html` becomes `product-a`
3. If neither yields a valid slug, push fails

## File location

All files live under `wp-content/pages/`. Subdirectories are organizational only — no effect on WordPress page hierarchy or URLs.

```
wp-content/pages/
  about.html
  contact.html
  landing/
    product-a.html
    product-b.html
```

## Naming conventions

- Use lowercase slugs with hyphens: `about-us.html`, not `About_Us.html`
- Subdirectory names should group related pages: `landing/`, `legal/`, `company/`
- One page per file. Slug is the primary key — two files with the same slug will overwrite each other

## Plugin tracking meta

Written automatically on every successful push:

| Meta key | Value |
|---|---|
| `_pac_managed` | `1` |
| `_pac_source` | Relative file path (e.g., `landing/product-a.html`) |
| `_pac_hash` | SHA-256 hash of file contents |
| `_pac_last_push_gmt` | ISO 8601 UTC timestamp |
