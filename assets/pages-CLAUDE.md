# Pages as Code — Agent Instructions

You are working with the **Pages as Code** (PAC) WordPress plugin. You create `.html` page files with YAML front matter and **WordPress Block Editor markup**, then push them to WordPress via WP-CLI.

## The one rule that matters most

> **Every piece of HTML in the body MUST be wrapped in WordPress block comments.**
> Bare HTML outside block comments is silently discarded by WordPress.

This is the most common mistake. WordPress stores content as blocks. If you write `<p>Hello</p>` without `<!-- wp:paragraph -->…<!-- /wp:paragraph -->` around it, WordPress will strip it on save.

```html
<!-- WRONG — bare HTML, will be lost -->
<p>Hello world</p>
<div class="hero"><h1>Title</h1></div>

<!-- CORRECT — every element wrapped in block comments -->
<!-- wp:paragraph -->
<p>Hello world</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"hero"} -->
<div class="wp-block-group hero">
  <!-- wp:heading {"level":1} -->
  <h1 class="wp-block-heading">Title</h1>
  <!-- /wp:heading -->
</div>
<!-- /wp:group -->
```

This rule applies to **all** HTML — headings, paragraphs, images, divs, lists, separators, everything. If you need custom HTML that doesn't map to a core block, wrap it in `<!-- wp:html -->…<!-- /wp:html -->`.

## Custom layouts with `wp:html`

For specific layouts that cannot be easily achieved with core blocks — or where core blocks would introduce too much of their own wrapper HTML — use `<!-- wp:html -->` with a scoped class. Always wrap the html block inside a parent layout block (`wp:group`, `wp:column`, `wp:cover`, etc.) with the appropriate `align` and `className` attributes so it integrates correctly with the theme's layout flow.

```html
<!-- wp:group {"align":"full","className":"feature-grid"} -->
<div class="wp-block-group alignfull feature-grid">

<!-- wp:html -->
<div class="fg-cards">
  <div class="fg-card">
    <h3>Card Title</h3>
    <p>Card description.</p>
  </div>
  <div class="fg-card">
    <h3>Card Title</h3>
    <p>Card description.</p>
  </div>
</div>
<!-- /wp:html -->

</div>
<!-- /wp:group -->
```

The scoped class (`fg-cards`, `fg-card`) keeps custom styles isolated. The parent group block ensures theme compatibility — alignment, max-width, spacing — all flow through the group's standard CSS.

When writing CSS for these pages, scope selectors to the block's `className` (e.g., `.feature-grid`), not an outer page class — the CSS file is already page-scoped by the enqueue. See `.claude/skills/pages-as-code/references/generate/styling.md` for full principles.

## Project context files

Two files in `.claude/` steer content and styling. **Read both before generating any page.**

- **`.claude/brand.md`** — brand identity, values, writing voice, tone, vocabulary. Use this to write content that sounds like the brand.
- **`.claude/theme.md`** — design system pointers: custom properties, class naming conventions, spacing scale, templates, and where to study the theme source. Use this to write CSS that harmonizes with the theme.

If either file is empty or contains only placeholders, fall back to studying existing page files in `wp-content/pages/` for established patterns.

## File format

```html
---
title: Page Title
slug: page-slug
status: publish
---
<!-- wp:paragraph -->
<p>Every HTML element is inside a block comment pair.</p>
<!-- /wp:paragraph -->
```

- YAML front matter between `---` delimiters
- Body: WordPress Block Editor markup (block comments + HTML)
- `title` is the only required front matter field

For the complete file format spec, front matter fields, and body rules, read `.claude/skills/pages-as-code/references/shared/page-standards.md`.

## Skill

Invoke `/pages-as-code` for the full workflow. It loads detailed references by intent:

- **Generate a page** → page standards + generate workflow + block editor reference (50+ blocks)
- **Publish a page** → page standards + publish workflow + troubleshooting
- **Both** → all references in sequence

## Key rules

- **Creating a file and publishing it are separate actions.** Writing to `wp-content/pages/` does nothing in WordPress until you run `wp pac push`.
- **`template` must exist in the active theme.** Omit it if unsure — WordPress uses the default page template.
- **Push parents before children.** If a page has `parent: company`, the `company` page must already exist.
- **Re-pushing an unchanged file is a no-op.** The plugin tracks a SHA-256 hash and skips identical content.
- **`--user` flag is required** in most hosting environments.

## WP-CLI

Always try standard `wp` first. If it fails, detect hosting environment:

```bash
# Standard WP-CLI
wp pac push about.html --user=1

# If GridPane detected (command -v gp or /usr/local/bin/gp exists)
gp wp <site-domain> pac push about.html --user=1
```

See `.claude/skills/pages-as-code/references/publish/troubleshooting.md` for detection logic and common errors.

## What not to do

- Do not output bare HTML outside block comments — it will be silently lost.
- Do not invent block names — only use names from the block editor reference.
- Do not edit WordPress pages directly in the database — use `wp pac push`.
- Do not assume a template exists — check first or omit the `template` field.
- Do not push child pages before their parents exist.
- Do not use path traversal (`../`) — the plugin rejects it.
