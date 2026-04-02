---
name: pac-cli
description: Push Pages as Code .html page files to WordPress via WP-CLI. Use when running wp pac push, troubleshooting push errors, finding admin users for capability checks, working with GridPane hosting (gp wp), or managing the file-to-WordPress publishing workflow.
---

# PAC CLI — Push Pages to WordPress

Push `.html` page files from `wp-content/pages/` into WordPress via WP-CLI.

## Command

```bash
wp pac push <file> [--format=<format>] [--user=<id>]
```

- `<file>` — path relative to `wp-content/pages/` (e.g., `about.html`, `landing/product-a.html`)
- `--format` — `human` (default) or `json`
- `--user` — WordPress user ID with `edit_pages` capability (required in most environments)

## Push behavior

1. Parse file (front matter + block markup body)
2. Compute SHA-256 hash of file contents
3. Look up existing page by slug
4. **No existing page** → `wp_insert_post()` → reports "created"
5. **Existing page, hash matches** → skip (no-op) → reports "unchanged"
6. **Existing page, hash differs** → `wp_update_post()` → WordPress creates revision → reports "updated"
7. Write tracking meta: `_pac_managed`, `_pac_source`, `_pac_hash`, `_pac_last_push_gmt`
8. Write user-defined meta from front matter

## Finding admin users

The push requires `edit_pages` capability. Find an admin user ID first:

```bash
# Standard WP-CLI
wp user list --role=administrator --fields=ID,user_login

# GridPane
gp wp <site> user list --role=administrator --fields=ID,user_login
```

## Hosting environments

### Standard WP-CLI

```bash
wp pac push about.html --user=1
wp pac push landing/product-a.html --format=json --user=1
```

### GridPane

WP-CLI runs through the `gp wp` wrapper. Always include the site domain:

```bash
gp wp staging.myrvann.no pac push about.html --user=1
gp wp staging.myrvann.no pac push landing/product-a.html --format=json --user=1
```

### Verifying a push

```bash
# List PAC-managed pages
wp post list --post_type=page --meta_key=_pac_managed --fields=ID,post_title,post_name,post_status

# GridPane
gp wp staging.myrvann.no post list --post_type=page --meta_key=_pac_managed --fields=ID,post_title,post_name,post_status --user=1
```

## Multi-page push ordering

Push parent pages before children. If `product-a.html` has `parent: landing`, push `landing.html` first:

```bash
wp pac push landing.html --user=1
wp pac push landing/product-a.html --user=1
wp pac push landing/product-b.html --user=1
```

Re-pushing unchanged files is safe — the hash check makes it a no-op.

## Output

### Human-readable (default)

```
Success: Created page "About" (ID 42, slug: about).
Success: Updated page "About" (ID 42, slug: about).
Success: Page "About" unchanged, skipping.
Error: File not found: landing/missing.html
Error: Front matter parse error in about.html: missing title field.
Error: Parent page "company" not found.
Error: Path outside managed root: ../../etc/passwd
```

### JSON (`--format=json`)

```json
{"status":"created","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"updated","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"unchanged","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"error","message":"File not found","file":"landing/missing.html"}
```

## Common errors

| Error | Cause | Fix |
|---|---|---|
| "You do not have permission to edit pages" | Missing `--user` flag or user lacks `edit_pages` | Add `--user=<admin_id>` |
| "Invalid page template" | `template` field references non-existent template | Remove `template` from front matter or use a valid slug |
| "Parent page not found" | Parent page doesn't exist yet | Push parent page first |
| "Path outside managed root" | Path traversal attempt (`../`) | Use paths relative to `wp-content/pages/` only |
| "missing title field" | No `title` in front matter | Add `title: Your Title` to front matter |
