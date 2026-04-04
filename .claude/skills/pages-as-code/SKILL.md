---
name: pages-as-code
description: Create and publish WordPress pages as code. Use when generating .html page files with Gutenberg block markup and YAML front matter, pushing pages to WordPress via WP-CLI (wp pac push), troubleshooting push errors, working with GridPane hosting, or managing the file-to-WordPress content workflow. Covers page file creation, block editor syntax, CLI publishing, and deployment verification.
---

# Pages as Code

One-way file-to-WordPress workflow. Create `.html` page files with front matter and block markup, push them to WordPress via WP-CLI.

## Shared foundations

Before either workflow, read [references/shared/page-standards.md](references/shared/page-standards.md) for file format, front matter fields, and naming rules.

Key principles:
- **Every HTML element in the body must be wrapped in block comments** — bare HTML is silently discarded by WordPress
- Creating a file and publishing it are **separate actions**
- `title` is the only required front matter field; slug falls back to filename
- `template` must exist in the active theme — omit if unsure
- Push parents before children
- Re-pushing unchanged files is a no-op (SHA-256 hash check)
- `--user=<admin_id>` is required in most hosting environments

## Route by intent

### Generate a page

Read [references/generate/workflow.md](references/generate/workflow.md) for the step-by-step creation process (7 steps, including reading context and writing CSS).

For block editor syntax and the complete core block table (50+ blocks), read [references/generate/block-editor.md](references/generate/block-editor.md).

For CSS principles — scoping, cascade, core block behavior — read [references/generate/styling.md](references/generate/styling.md) and [references/generate/block-css.md](references/generate/block-css.md).

Use the starter template at [templates/page-shell.html](templates/page-shell.html) as a starting point.

### Project context

Before generating, check for context files in `.claude/`:

- **`.claude/brand.md`** — brand identity and writing voice
- **`.claude/theme.md`** — theme design system, custom properties, layout patterns

These steer content and styling so output looks and sounds native to the site.

### Publish a page

Read [references/publish/workflow.md](references/publish/workflow.md) for the push process, multi-page ordering, and verification.

For error diagnosis and hosting-specific commands, read [references/publish/troubleshooting.md](references/publish/troubleshooting.md).

### Both (generate + publish)

Read shared standards first, then generate workflow, then publish workflow in sequence.

## Quick reference

```bash
# Create file
wp-content/pages/about.html

# Push
wp pac push about.html --user=1

# Verify
wp post list --post_type=page --meta_key=_pac_managed --fields=ID,post_title,post_name,post_status
```

Always try standard `wp` first. If it fails or the environment is GridPane (check: `command -v gp` or `/usr/local/bin/gp` exists), fall back to `gp wp <site>` syntax. See [references/publish/troubleshooting.md](references/publish/troubleshooting.md) for detection and fallback.

## Validation

Run `scripts/validate-page.sh <file>` to check front matter and basic block structure before pushing.
