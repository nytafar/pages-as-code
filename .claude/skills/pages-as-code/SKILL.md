---
name: pages-as-code
description: Create and publish WordPress pages as code. Use when generating .html page files with Gutenberg block markup and YAML front matter, pushing pages to WordPress via WP-CLI (wp pac push), troubleshooting push errors, working with GridPane hosting, or managing the file-to-WordPress content workflow. Covers page file creation, block editor syntax, CLI publishing, and deployment verification.
---

# Pages as Code

One-way file-to-WordPress workflow. Create `.html` page files with front matter and block markup, push them to WordPress via WP-CLI.

## Shared foundations

Before either workflow, read [references/shared/page-standards.md](references/shared/page-standards.md) for file format, front matter fields, and naming rules.

Key principles:
- Creating a file and publishing it are **separate actions**
- `title` is the only required front matter field; slug falls back to filename
- `template` must exist in the active theme — omit if unsure
- Push parents before children
- Re-pushing unchanged files is a no-op (SHA-256 hash check)
- `--user=<admin_id>` is required in most hosting environments
- Optional sibling `.css` and `.js` files are auto-resolved during push
- CSS loads on frontend + block editor; JS loads on frontend only

## Route by intent

### Generate a page

Read [references/generate/workflow.md](references/generate/workflow.md) for the step-by-step creation process.

For block editor syntax and the complete core block table (50+ blocks), read [references/generate/block-editor.md](references/generate/block-editor.md).

Use the starter template at [templates/page-shell.html](templates/page-shell.html) as a starting point.

### Publish a page

Read [references/publish/workflow.md](references/publish/workflow.md) for the push process, multi-page ordering, and verification.

For error diagnosis and hosting-specific commands, read [references/publish/troubleshooting.md](references/publish/troubleshooting.md).

### Both (generate + publish)

Read shared standards first, then generate workflow, then publish workflow in sequence.

## CSS and JS assets

Pages support optional sibling CSS and JS files that are auto-resolved during push.

### Convention

```
wp-content/pages/
  about.html          # required: Gutenberg block markup
  about.css           # optional: page-specific styles
  about.js            # optional: page-specific scripts (only when interaction needed)
```

### Resolution order (CSS example)

1. Front matter `css:` path (relative to `wp-content/`)
2. Sibling: `about.css` in the same directory as `about.html`
3. Shared: `pages/css/about.css`

Same for JS with `js:` front matter field.

### Style philosophy

- **Start from the theme.** Analyze the active theme's typography, spacing, colors, and block defaults before writing CSS.
- **Additive, not replacement.** Don't reset global styles. Add page-specific treatments.
- **Page-scoped selectors.** Use class names on your page sections, not global element selectors.
- **Block-editor friendly.** CSS that styles inside-block content works in both frontend and editor.
- **No JS by default.** Only create JS when the page requires client-side interaction that blocks/theme don't provide.

### Editor behavior

- CSS loads in the block editor for styling parity with the frontend
- JS is **frontend-only** — editor scripts often break in the editor iframe
- The editor will reflect your page-specific CSS automatically

### Future: Git workflow

When working with page triplets (`about.html` + `about.css` + `about.js`):
- Commit all related files together
- The push command resolves assets from disk, so all files must exist before push
- Treat the `.html` as the primary file; `.css` and `.js` are supporting assets

## Quick reference

```bash
# Create files
wp-content/pages/about.html
wp-content/pages/about.css    # optional

# Push (resolves assets automatically)
wp pac push about.html --user=1

# Verify
wp post list --post_type=page --meta_key=_pac_managed --fields=ID,post_title,post_name,post_status
```

Always try standard `wp` first. If it fails or the environment is GridPane (check: `command -v gp` or `/usr/local/bin/gp` exists), fall back to `gp wp <site>` syntax. See [references/publish/troubleshooting.md](references/publish/troubleshooting.md) for detection and fallback.

## Validation

Run `scripts/validate-page.sh <file>` to check front matter and basic block structure before pushing.
