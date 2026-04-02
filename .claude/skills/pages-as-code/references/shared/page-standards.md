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
| `css` | no | auto-resolved | `_pac_css` | Explicit CSS asset path relative to `wp-content/`. Overrides sibling resolution. |
| `js` | no | auto-resolved | `_pac_js` | Explicit JS asset path relative to `wp-content/`. Overrides sibling resolution. |
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

## Sibling CSS/JS assets

Each page file can have optional matching CSS and JS files. These are resolved automatically during push.

### Resolution order

For a page file like `about.html`:

**CSS:**
1. Front matter `css:` path (relative to `wp-content/`)
2. Sibling file: `about.css` in the same directory
3. Shared directory: `pages/css/about.css`

**JS:**
1. Front matter `js:` path (relative to `wp-content/`)
2. Sibling file: `about.js` in the same directory
3. Shared directory: `pages/js/about.js`

### File layout examples

Sibling pattern (recommended):
```
wp-content/pages/
  about.html
  about.css
  about.js          (only if interaction needed)
  landing/
    product-a.html
    product-a.css
```

Shared directory pattern:
```
wp-content/pages/
  about.html
  css/
    about.css
  js/
    about.js
```

Front matter override:
```yaml
---
title: About Us
css: themes/mytheme/custom/about-override.css
js: pages/js/about.js
---
```

### Safety rules

- All resolved asset paths must be under `wp-content/`
- Paths outside `wp-content/` are rejected
- If a front matter path points to a non-existent file, no asset is stored
- If no asset is found via any resolution step, the meta field is cleared

### Enqueue behavior

- **CSS** is loaded on the frontend and in the block editor (page-specific only)
- **JS** is loaded on the frontend only (not in the block editor)
- Assets use `filemtime` for cache-busting version strings
- Handles are derived from the post ID: `pac-page-{id}`, `pac-page-{id}-js`

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
| `_pac_css` | Absolute path to resolved CSS asset (cleared if none) |
| `_pac_js` | Absolute path to resolved JS asset (cleared if none) |
| `_pac_css_hash` | SHA-256 hash of CSS file (cleared if none) |
| `_pac_js_hash` | SHA-256 hash of JS file (cleared if none) |
