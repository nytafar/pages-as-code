---
name: pages-as-code
description: Create and publish WordPress pages using the Pages as Code plugin. Use when creating .html page files with Gutenberg block markup, pushing pages to WordPress via WP-CLI, or working with the wp pac push workflow. Triggers on requests to create WordPress pages from files, generate block editor markup, or push content to WordPress.
---

# Pages as Code

Create WordPress pages as `.html` files with YAML front matter and Gutenberg block markup, then push them to WordPress via WP-CLI.

## Workflow

1. **Create markup** → Use `/pac-markup` to generate a compliant `.html` page file
2. **Push to WordPress** → Use `/pac-cli` to push the file and verify

Creating a file and publishing it are **separate actions**. The file on disk has no effect on WordPress until explicitly pushed.

## Quick start

```html
---
title: About Us
slug: about
status: publish
---
<!-- wp:paragraph -->
<p>Hello world.</p>
<!-- /wp:paragraph -->
```

Save to `wp-content/pages/about.html`, then push:

```bash
wp pac push about.html --user=1
```

## Sub-module skills

### /pac-markup — Page file creation

Invoke when creating or editing `.html` page files. Covers:

- YAML front matter format and field reference
- Gutenberg block markup syntax rules
- Complete core block reference table (50+ blocks)
- Nesting rules, static vs dynamic blocks

### /pac-cli — Push and verify

Invoke when pushing pages to WordPress or troubleshooting the CLI. Covers:

- `wp pac push` command usage and flags
- GridPane hosting specifics (`gp wp` wrapper)
- Finding admin users for `--user` flag
- Output formats and error handling
- Multi-page push ordering (parents first)

## Key rules

- `title` is the only required front matter field
- `slug` falls back to filename if omitted
- `template` must exist in the active theme — omit if unsure
- Push parents before children when using `parent` field
- Re-pushing unchanged files is a no-op (SHA-256 hash check)
- `--user=<admin_id>` is required in most hosting environments
