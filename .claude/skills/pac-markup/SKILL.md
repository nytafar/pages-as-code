---
name: pac-markup
description: Create compliant Pages as Code .html page files with YAML front matter and Gutenberg block editor markup. Use when generating WordPress page content as files, writing block markup, or creating pages for the wp pac push workflow. Covers front matter fields (title, slug, status, template, parent, meta) and native Gutenberg block syntax.
---

# PAC Markup — Page File Creation

Create `.html` page files for the Pages as Code plugin.

## File format

Every page file has two parts: YAML front matter between `---` delimiters, then Gutenberg block markup body.

```html
---
title: Page Title
slug: page-slug
status: draft
template: template-slug
parent: parent-page-slug
meta:
  seo_title: SEO override
  custom_key: value
---
<!-- wp:paragraph -->
<p>Content here.</p>
<!-- /wp:paragraph -->
```

## Front matter fields

| Field | Required | Default | Notes |
|---|---|---|---|
| `title` | yes | — | Always required. Push fails without it. |
| `slug` | no | filename | Primary key. `about.html` → `about`. Must be unique. |
| `status` | no | `draft` | `draft`, `publish`, `pending`, `private`, `future` |
| `template` | no | default | Must exist in active theme. Omit if unsure. |
| `parent` | no | — | Slug of parent page. Parent must exist before push. |
| `meta` | no | — | One-level key-value map. Written as post meta. |

## File location

Save files under `wp-content/pages/`. Subdirectories are organizational only — no effect on WordPress hierarchy.

```
wp-content/pages/
  about.html
  landing/
    product-a.html
    product-b.html
```

## Block markup rules

For the complete Gutenberg block reference table and syntax rules, read [references/block-editor.md](references/block-editor.md).

Summary of critical rules:

- Block names in comments use `wp:` prefix, not `core/`. `core/paragraph` → `wp:paragraph`
- Attributes must be valid JSON in the opening comment
- **Static blocks**: `<!-- wp:name {"attr":"val"} --><html><!-- /wp:name -->`
- **Dynamic blocks**: `<!-- wp:name {"attr":"val"} /-->`
- Inner blocks nest inside parent's saved HTML
- Never invent block names — use only canonical names from the reference

### Common blocks quick reference

```html
<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Text</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">Heading</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":42} -->
<figure class="wp-block-image"><img src="..." alt="..."/></figure>
<!-- /wp:image -->

<!-- wp:group {"align":"full"} -->
<div class="wp-block-group alignfull">
  <!-- inner blocks here -->
</div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
  <!-- wp:button -->
  <div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/link/">Label</a></div>
  <!-- /wp:button -->
</div>
<!-- /wp:buttons -->

<!-- wp:columns -->
<div class="wp-block-columns">
  <!-- wp:column -->
  <div class="wp-block-column"><!-- inner blocks --></div>
  <!-- /wp:column -->
  <!-- wp:column -->
  <div class="wp-block-column"><!-- inner blocks --></div>
  <!-- /wp:column -->
</div>
<!-- /wp:columns -->

<!-- wp:html -->
<div class="custom-markup">Any raw HTML here.</div>
<!-- /wp:html -->
```

### Custom/plugin blocks

Same pattern, keep the namespace:
```html
<!-- wp:my-plugin/block-name {"attr":"val"} -->
<div class="wp-block-my-plugin-block-name">...</div>
<!-- /wp:my-plugin/block-name -->
```

Treat plugin blocks as static unless documentation says otherwise.
