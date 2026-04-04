# Page Standards

## File format

Every `.html` page file has two parts:

1. **YAML front matter** between `---` delimiters
2. **Body**: WordPress Block Editor markup — HTML wrapped in block comments

```html
---
title: Page Title
slug: page-slug
status: draft
---
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Page Title</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Every HTML element must be inside a block comment pair.</p>
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
| `css` | no | — | enqueued stylesheet | Path relative to `wp-content/`. Enqueued only on this page. e.g., `themes/myrvann/css/about.css` |
| `js` | no | — | enqueued script | Path relative to `wp-content/`. Enqueued only on this page. e.g., `themes/myrvann/js/about.js` |
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

## Body rules — Block Editor markup

WordPress stores page content as **blocks**. Every HTML element in the body must be wrapped in block comment delimiters. Bare HTML outside block comments is silently discarded.

### The block comment pattern

```
<!-- wp:blockname {"optional":"attributes"} -->
<HTML content>
<!-- /wp:blockname -->
```

- Opening comment: `<!-- wp:blockname -->` or `<!-- wp:blockname {"key":"value"} -->`
- Closing comment: `<!-- /wp:blockname -->`
- Self-closing (dynamic blocks): `<!-- wp:blockname {"key":"value"} /-->`

### DO — correct block markup

```html
<!-- wp:paragraph -->
<p>A paragraph of text.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Section Title</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":42,"sizeSlug":"full","align":"full"} -->
<figure class="wp-block-image alignfull size-full">
<img src="/wp-content/uploads/photo.jpg" alt="Description" class="wp-image-42"/>
</figure>
<!-- /wp:image -->

<!-- wp:group {"align":"full","className":"my-section"} -->
<div class="wp-block-group alignfull my-section">
  <!-- wp:paragraph -->
  <p>Nested content inside a group.</p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:html -->
<div class="custom-widget" data-count="5">Custom HTML that has no core block.</div>
<!-- /wp:html -->
```

### DON'T — bare HTML (will be silently lost)

```html
<!-- WRONG: bare paragraph -->
<p>This text will be stripped by WordPress.</p>

<!-- WRONG: bare div with content -->
<div class="hero">
  <h1>This heading will be lost.</h1>
</div>

<!-- WRONG: bare image -->
<img src="/photo.jpg" alt="Gone"/>

<!-- WRONG: bare list -->
<ul>
  <li>Lost item</li>
</ul>
```

### Key rules

1. **Every** HTML element needs a block wrapper — paragraphs, headings, images, divs, lists, separators, everything
2. Nest inner blocks inside the parent block's HTML element (e.g., paragraphs inside a group's `<div>`)
3. Every opening `<!-- wp:name -->` must have a matching `<!-- /wp:name -->`
4. Use `<!-- wp:html -->` as a catch-all for custom HTML that doesn't fit a core block
5. Only use block names from the [block editor reference](../generate/block-editor.md) — never invent names

## Naming conventions

- Use lowercase slugs with hyphens: `about-us.html`, not `About_Us.html`
- Subdirectory names should group related pages: `landing/`, `legal/`, `company/`
- One page per file. Slug is the primary key — two files with the same slug will overwrite each other

## Project context

Before generating any page, check for context files in `.claude/`:

- **`.claude/brand.md`** — brand identity, values, writing voice, tone, vocabulary
- **`.claude/theme.md`** — theme design system: custom properties, class naming, spacing scale, templates

If found, **read them first**. `brand.md` steers how content sounds; `theme.md` steers how pages are styled. Together they ensure output looks and reads native to the site.

If these files are empty or missing, study existing page files in `wp-content/pages/` for class prefixes, section patterns, and styling conventions already in use.

## Plugin tracking meta

Written automatically on every successful push:

| Meta key | Value |
|---|---|
| `_pac_managed` | `1` |
| `_pac_source` | Relative file path (e.g., `landing/product-a.html`) |
| `_pac_hash` | SHA-256 hash of file contents |
| `_pac_last_push_gmt` | ISO 8601 UTC timestamp |
