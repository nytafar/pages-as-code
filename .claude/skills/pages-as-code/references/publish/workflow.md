# Publish Workflow

Push `.html` page files from `wp-content/pages/` into WordPress via WP-CLI.

## Command

```bash
wp pac push <file> [--format=<format>] [--user=<id>]
```

- `<file>` — path relative to `wp-content/pages/`
- `--format` — `human` (default) or `json`
- `--user` — WordPress user ID with `edit_pages` capability

## Steps

### 1. Find an admin user

```bash
wp user list --role=administrator --fields=ID,user_login
```

Note the ID for `--user`.

### 2. Push the page

```bash
wp pac push about.html --user=1
```

### 3. Verify

```bash
wp post list --post_type=page --meta_key=_pac_managed --fields=ID,post_title,post_name,post_status
```

## Push behavior

1. Parse file (front matter + body)
2. Compute SHA-256 hash
3. Look up existing page by slug
4. **No match** → `wp_insert_post()` → "created"
5. **Match, same hash** → skip → "unchanged"
6. **Match, different hash** → push guard: the post's current content must equal what pac last saved (`_pac_content_hash`) or the file's `expect_hash:`. Otherwise **refused, nothing written**
7. Guard passes (or `--force`) → `wp_update_post()` → revision created → "updated"
8. Write tracking meta (`_pac_managed`, `_pac_source`, `_pac_hash`, `_pac_content_hash`, `_pac_last_push_gmt`)
9. Write user-defined meta from front matter

A refused push means someone edited the page in WordPress. Pull it (`wp pac pull <slug> --revision-suffix`), merge the edits and the pulled `expect_hash:` line into your file, then push again. `--force` discards their edits: only on the site owner's word.

## Multi-page ordering

Push parent pages before children:

```bash
wp pac push company.html --user=1
wp pac push company/about.html --user=1
wp pac push company/team.html --user=1
```

Re-pushing unchanged files is safe — hash check makes it a no-op.

## Output

### Human-readable (default)

```
Success: Created page "About" (ID 42, slug: about).
Success: Updated page "About" (ID 42, slug: about).
Success: Page "About" unchanged, skipping.
```

### JSON (`--format=json`)

```json
{"status":"created","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"unchanged","id":42,"slug":"about","title":"About","file":"about.html"}
```

## Environment detection

Always try standard `wp` CLI first. If it fails with "command not found" or permission errors, detect the hosting environment:

```bash
# Check for GridPane
if command -v gp &>/dev/null || [ -x /usr/local/bin/gp ]; then
  # GridPane detected — use gp wp <site> wrapper
  SITE=$(basename "$(wp eval 'echo ABSPATH;' 2>/dev/null)" || hostname -f)
  gp wp "$SITE" pac push about.html --user=1
fi
```

See [troubleshooting.md](troubleshooting.md) for environment-specific details.
