# Pages-as-Code — MVP Specification

File-backed Gutenberg pages for WordPress. Author page content as files with front matter and block markup, push to WordPress via WP-CLI.

## Concept

Pages-as-Code is a one-way, file-to-WordPress content workflow. Page content is authored as `.html` files containing YAML front matter plus native Gutenberg block markup, then pushed into normal WordPress pages through WP-CLI. WordPress remains the runtime. Files are a deliberate authoring layer for coding agents and developers.

The plugin does not replace WordPress storage. It provides a controlled way to create or update pages from files, preserving normal editing, revisions, templates, and publishing behavior. Gutenberg stores content as serialized block markup (`<!-- wp:... -->` comments in HTML), which makes raw block markup a valid file format for import.

## Content root

Managed page files live under `WP_CONTENT_DIR . '/pages'`. This is a site-owned, mutable content layer separate from WordPress core and plugin code. The path resolves from `WP_CONTENT_DIR`, never hardcoded as `wp-content`.

```
wp-content/
  pages/
    about.html
    contact.html
    landing/
      product-a.html
      product-b.html
```

Subdirectories are organizational only. They have no effect on WordPress page hierarchy, slugs, or URLs.

## File format

Each file contains:

1. YAML front matter between `---` delimiters.
2. Body: raw Gutenberg block markup.

### Front matter fields

| Field      | Required | Type   | Maps to              | Notes                                    |
|------------|----------|--------|----------------------|------------------------------------------|
| `title`    | yes      | string | `post_title`         |                                          |
| `slug`     | no       | string | `post_name`          | Primary key. Falls back to filename without extension. |
| `status`   | no       | string | `post_status`        | Default: `draft`                         |
| `template` | no       | string | `page_template`      | Block theme template slug.               |
| `parent`   | no       | string | `post_parent`        | Slug of parent page. Must exist at push time. |
| `meta`     | no       | map    | post meta (as-is)    | Minimal set. Keys written directly, not namespaced. |

### Slug resolution

1. If front matter contains `slug`, use it.
2. Otherwise, derive from filename: `product-a.html` becomes `product-a`.
3. If neither yields a valid slug, fail with error.

### Example file

```html
---
title: About
slug: about
status: publish
template: page-no-title
parent: company
meta:
  seo_title: About us
---
<!-- wp:group -->
<div class="wp-block-group">
  <!-- wp:heading {"level":1} -->
  <h1 class="wp-block-heading">About</h1>
  <!-- /wp:heading -->

  <!-- wp:paragraph -->
  <p>Hello world.</p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
```

The body is saved directly into `post_content`.

## CLI command

### `wp pac push <file>`

Push one page file to WordPress. `<file>` is relative to the pages root (`WP_CONTENT_DIR/pages/`). Absolute paths and paths outside the pages root are rejected.

```bash
wp pac push about.html
wp pac push landing/product-a.html
wp pac push about.html --format=json
```

#### Behavior

1. Resolve full path: `WP_CONTENT_DIR/pages/<file>`.
2. Validate path is inside the pages root. Reject path traversal.
3. Read file. Fail on missing file or malformed front matter.
4. Parse YAML front matter and block markup body.
5. Resolve slug (front matter or filename).
6. Compute SHA-256 hash of the entire file contents.
7. Look up existing page by slug (`post_name` where `post_type = page`).
8. **If page exists:**
   - Compare stored `_pac_hash` with computed hash.
   - If identical, skip update (no-op). Report "unchanged".
   - If different, call `wp_update_post()`. WordPress creates a revision automatically.
9. **If page does not exist:**
   - Call `wp_insert_post()`.
10. If `parent` is specified, resolve parent page by slug. Fail if parent does not exist.
11. Write front matter `meta` keys to post meta (stored as-is, not namespaced).
12. Write plugin tracking meta (see below).
13. Report result.

#### Plugin post meta

Written on every successful push:

| Meta key              | Value                                         |
|-----------------------|-----------------------------------------------|
| `_pac_managed`        | `1` (flags this page as PAC-managed)          |
| `_pac_source`         | Relative file path, e.g. `landing/product-a.html` |
| `_pac_hash`           | SHA-256 hash of file contents at push time    |
| `_pac_last_push_gmt`  | ISO 8601 UTC timestamp of push                |

All meta keys are prefixed `_pac_` (underscore-prefixed = hidden from custom fields UI).

#### Output

**Default (human-readable):**

```
Success: Created page "About" (ID 42, slug: about).
Success: Updated page "About" (ID 42, slug: about).
Success: Page "About" unchanged, skipping.
Error: File not found: landing/missing.html
Error: Front matter parse error in about.html: missing title field.
Error: Parent page "company" not found.
Error: Path outside managed root: ../../etc/passwd
```

**With `--format=json`:**

```json
{"status":"created","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"updated","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"unchanged","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"error","message":"File not found: landing/missing.html","file":"landing/missing.html"}
```

Uses WP-CLI's built-in `--format` flag pattern.

#### Duplicate slug protection

If two files declare the same slug, the second push overwrites the first. This is intentional — slug is the primary key. To avoid accidental overwrites, agents should use distinct slugs per file.

## Data flow

```
File on disk ──wp pac push──▶ WordPress page (post_content)
                                    │
                              Block editor ◀── humans edit normally
                                    │
                              Revisions ◀── automatic on update
```

One-way, explicit. No hidden sync. No runtime file rendering. WordPress is the source of truth for the live page. The file is a source of truth for the intended content at push time.

Editors can edit the page in the block editor after a push. Those edits remain in WordPress until a later push overwrites them deliberately.

## Safety rules

1. **Path restriction.** All file paths resolve under `WP_CONTENT_DIR/pages/`. Path traversal (`../`) is rejected after resolution.
2. **Capability check.** Commands require `edit_pages` capability (Administrator or Editor role).
3. **Post type restriction.** Only `page` post type. No posts, no CPTs.
4. **Front matter validation.** Fail fast on malformed YAML or missing `title`.
5. **Parent validation.** If `parent` is specified, the parent page must exist.
6. **No auto-sync.** No filesystem watchers, no hooks on save, no background jobs.
7. **No auto-pull.** File is never written by the plugin in v1.

## Plugin structure

```
pages-as-code/
  pages-as-code.php        # Plugin bootstrap, activation hook
  includes/
    class-pac-file.php     # File parsing: front matter + body extraction
    class-pac-pusher.php   # Create/update logic, hash comparison, meta writes
    class-pac-cli.php      # WP-CLI command registration and argument handling
```

### Bootstrap

- Plugin header in `pages-as-code.php`.
- On activation: create `WP_CONTENT_DIR/pages/` if it does not exist, with a `.gitkeep`.
- WP-CLI command registered only when `WP_CLI` constant is defined.
- No admin UI in v1. No settings page. No dashboard widgets.

### Dependencies

- WordPress 6.0+ (block editor assumed).
- WP-CLI 2.0+ (for command registration).
- PHP 7.4+ (match WordPress minimum).
- YAML parsing: `symfony/yaml` via Composer, or a minimal bundled parser. Keep dependency count at one or zero.

## Explicitly deferred (not in v1)

| Feature           | Why deferred                                                |
|-------------------|-------------------------------------------------------------|
| `wp pac pull`     | Two-way sync adds conflict resolution complexity.           |
| `wp pac sync`     | Batch push with directory scanning. Needs hash-based diffing at scale. |
| `wp pac status`   | Alignment reporting (file vs DB). Useful but not essential for push-only MVP. |
| Block markup linter | Validates Gutenberg syntax pre-push. On roadmap for v1.2 — will validate block comment pairing, JSON attribute syntax, and HTML fragment structure before push. |
| Admin UI          | No settings needed in v1. CLI-only is correct for agent workflows. |
| Multi-post-type   | Posts, CPTs. Adds scope without adding insight.             |
| Git integration   | Commit-on-push. Better handled by the agent workflow externally. |

## Success criteria

The MVP works when:

1. A coding agent generates a valid `.html` file with front matter and block markup.
2. `wp pac push <file>` creates the page in WordPress with correct title, slug, status, template, parent, content, and meta.
3. Pushing the same unchanged file is a no-op.
4. Pushing a modified file updates the page and WordPress creates a revision.
5. The page opens cleanly in the block editor with no validation errors.
6. An editor can modify the page normally after a push.
7. A subsequent push overwrites editor changes (intentionally, with a revision saved).
8. Invalid files, missing parents, and path traversal attempts produce clear errors.
