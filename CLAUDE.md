# Pages as Code — Agent Instructions

You are working with the **Pages as Code** WordPress plugin. This plugin provides a one-way file-to-WordPress workflow: you create `.html` page files with YAML front matter and Gutenberg block markup, then push them to WordPress via WP-CLI.

## Quick start

1. Create a `.html` file in `wp-content/pages/`
2. Push it: `wp pac push <filename> --user=<admin_id>`

## Skill reference

For detailed instructions on creating page files and using the CLI, load the skill:

```
/pac-page
```

The skill is located at: `wp-content/plugins/pages-as-code/skills/pac-page.md`

It covers three submodules:
- **Markup**: File format, front matter fields, slug resolution, validation rules
- **CLI**: Push command, output formats, GridPane hosting specifics
- **Block Editor Reference**: Complete Gutenberg block syntax and core block table

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

## File structure

```
wp-content/
  pages/                     ← Page files live here
    about.html
    landing/
      product-a.html
  plugins/
    pages-as-code/
      pages-as-code.php      ← Plugin bootstrap
      includes/
        class-pac-file.php   ← File parsing
        class-pac-pusher.php ← Create/update logic
        class-pac-cli.php    ← WP-CLI command
      skills/
        pac-page.md          ← Full skill reference
```

## What not to do

- Do not edit WordPress pages directly in the database — use `wp pac push` instead.
- Do not use absolute paths in the push command — always use paths relative to `wp-content/pages/`.
- Do not assume a template exists — check first or omit the `template` field.
- Do not push child pages before their parents exist.
- Do not use path traversal (`../`) — the plugin rejects it.
