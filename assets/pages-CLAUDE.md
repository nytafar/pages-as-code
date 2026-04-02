# Pages as Code — Agent Instructions

You are working with the **Pages as Code** WordPress plugin. This plugin provides a one-way file-to-WordPress workflow: you create `.html` page files with YAML front matter and Gutenberg block markup, then push them to WordPress via WP-CLI.

## Quick start

1. Create a `.html` file in `wp-content/pages/`
2. Push it: `wp pac push <filename> --user=<admin_id>`

## Skill

One skill with progressive disclosure is available at `.claude/skills/pages-as-code/`:

Invoke `/pages-as-code` for the full workflow. It routes by intent:

- **Generate a page** → loads page standards + generate workflow + block editor reference
- **Publish a page** → loads page standards + publish workflow + troubleshooting
- **Both** → loads all in sequence

The skill includes a validation script, starter template, and complete block editor reference (50+ core blocks).

## Key rules

- **Creating a file and publishing it are separate actions.** Writing to `wp-content/pages/` does nothing in WordPress until you run `wp pac push`.
- **`title` is the only required front matter field.** Slug falls back to filename.
- **`template` must exist in the active theme.** Omit it if unsure — WordPress will use the default page template.
- **Push parents before children.** If a page has `parent: company`, the `company` page must already exist in WordPress.
- **Re-pushing an unchanged file is a no-op.** The plugin tracks a SHA-256 hash and skips identical content.
- **`--user` flag is required** in most hosting environments to pass the `edit_pages` capability check.

## WP-CLI

Always try standard `wp` first. If it fails (command not found or permission error), detect hosting environment:

```bash
# Standard WP-CLI
wp pac push about.html --user=1

# If GridPane detected (command -v gp or /usr/local/bin/gp exists)
gp wp <site-domain> pac push about.html --user=1
```

See `.claude/skills/pages-as-code/references/publish/troubleshooting.md` for full detection logic and common errors.

## What not to do

- Do not edit WordPress pages directly in the database — use `wp pac push` instead.
- Do not use absolute paths in the push command — always use paths relative to `wp-content/pages/`.
- Do not assume a template exists — check first or omit the `template` field.
- Do not push child pages before their parents exist.
- Do not use path traversal (`../`) — the plugin rejects it.
