# Pages as Code — Agent Instructions

You are working with the **Pages as Code** WordPress plugin. This plugin provides a one-way file-to-WordPress workflow: you create `.html` page files with YAML front matter and Gutenberg block markup, then push them to WordPress via WP-CLI.

## Quick start

1. Create a `.html` file in `wp-content/pages/`
2. Push it: `wp pac push <filename> --user=<admin_id>`

## Skills

Three skills are available in `.claude/skills/`:

| Skill | Invoke | Use when |
|---|---|---|
| **pages-as-code** | `/pages-as-code` | Overview, workflow orchestration, quick reference |
| **pac-markup** | `/pac-markup` | Creating `.html` page files, block editor markup, front matter |
| **pac-cli** | `/pac-cli` | Pushing pages, troubleshooting errors, GridPane hosting |

The markup skill includes a full [block editor reference](../.claude/skills/pac-markup/references/block-editor.md) with 50+ core blocks.

## Key rules

- **Creating a file and publishing it are separate actions.** Writing to `wp-content/pages/` does nothing in WordPress until you run `wp pac push`.
- **`title` is the only required front matter field.** Slug falls back to filename.
- **`template` must exist in the active theme.** Omit it if unsure — WordPress will use the default page template.
- **Push parents before children.** If a page has `parent: company`, the `company` page must already exist in WordPress.
- **Re-pushing an unchanged file is a no-op.** The plugin tracks a SHA-256 hash and skips identical content.
- **`--user` flag is required** in most hosting environments (GridPane, etc.) to pass the `edit_pages` capability check.

## Hosting: GridPane

This site runs on GridPane. WP-CLI commands go through `gp wp`:

```bash
# Find admin users (needed for --user flag)
gp wp staging.myrvann.no user list --role=administrator --fields=ID,user_login

# Push a page
gp wp staging.myrvann.no pac push about.html --user=1

# Verify page was created
gp wp staging.myrvann.no post list --post_type=page --fields=ID,post_title,post_name,post_status --user=1
```

## What not to do

- Do not edit WordPress pages directly in the database — use `wp pac push` instead.
- Do not use absolute paths in the push command — always use paths relative to `wp-content/pages/`.
- Do not assume a template exists — check first or omit the `template` field.
- Do not push child pages before their parents exist.
- Do not use path traversal (`../`) — the plugin rejects it.
