=== Pages as Code ===
Contributors: lassejellum
Tags: pages, cli, gutenberg, blocks, developer-tools
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

File-backed Gutenberg pages for WordPress. Author page content as .html files with YAML front matter and block markup, push to WordPress via WP-CLI.

== Description ==

Pages as Code is a one-way file-to-WordPress workflow for developers and coding agents. Author your page content as `.html` files with YAML front matter and Gutenberg block markup, then push them to WordPress using WP-CLI.

**Key features:**

- Write pages as `.html` files with YAML front matter (title, slug, type, status, template, parent, css, js, meta)
- Push to WordPress with `wp pac push <file>` — pages or any post type registered via the `pac_post_types` filter
- Pull from WordPress with `wp pac pull <slug>` or `wp pac pull <type>/<slug>` — revision tracking, subfolder targeting, collision protection
- Validate block markup with `wp pac validate <file>` — structured JSON diagnostic reports
- SHA-256 content hashing skips unchanged posts automatically
- Sibling CSS/JS asset resolution with three-tier fallback (front matter > sibling > shared directory)
- Per-post CSS enqueued on frontend and block editor; JS enqueued frontend only
- Parent page resolution by slug (page-only)
- Plugin tracking meta (`_pac_managed`, `_pac_source`, `_pac_hash`, `_pac_css`, `_pac_js`)
- Path traversal protection and per-type capability checks
- Filterable pages root (`pac_pages_root`) and post-type registry (`pac_post_types`)
- JSON output format support (`--format=json`)
- Built-in Claude Code skill with progressive disclosure for AI-assisted page creation

Pages as Code requires WP-CLI 2.0 or later.

== Installation ==

1. Upload the `pages-as-code` folder to `/wp-content/plugins/`, or install via WP-CLI:
   ```bash
   wp plugin install pages-as-code --activate
   ```
2. Activate the plugin. On activation it creates `wp-content/pages/` with a `.gitkeep` and copies the Claude Code skill and instructions for AI agents. (If a theme registers the [`pac_pages_root` filter](#filter-pac_pages_root), only the directory is created — the scaffolding is left to the theme.)
3. Create `.html` files in `wp-content/pages/` (or your configured pages root).
4. Push: `wp pac push <file> --user=<admin_id>`

== Usage ==

= CLI reference =

```bash
wp pac push <file> [--post-type=<slug>] [--format=<format>] [--user=<id>]
wp pac pull <slug> [--post-type=<slug>] [--dir=<dir>] [--force] [--revision-suffix] [--format=<format>] [--user=<id>]
wp pac validate <file> [--strict] [--user=<id>]
```

| Command | Description |
|---------|-------------|
| `wp pac push <file>` | Push a file to WordPress as a post |
| `wp pac pull <slug>` | Pull a WordPress post to a local file. Accepts `<type>/<slug>` shorthand |
| `wp pac validate <file>` | Validate block markup and return a JSON diagnostic report |

| Argument | Description |
|----------|-------------|
| `<file>` | Path relative to the pages root (push, validate) |
| `<slug>` | Post slug, or `<type>/<slug>` shorthand (pull) |
| `--post-type` | Post type slug. Wins over front-matter `type:` and over the path-style shorthand. Defaults to `page`. |
| `--format` | `human` (default) or `json` |
| `--dir` | Subdirectory to write pulled file into. Overrides per-type default. (pull only) |
| `--force` | Overwrite existing file (pull only) |
| `--revision-suffix` | Append revision ID to filename, e.g. `about.r123.html` (pull only) |
| `--strict` | Treat warnings as fatal for exit code (validate only) |
| `--user` | WordPress user ID with the post type's required capability |

= Push behavior =

| Scenario | Action | Output |
|----------|--------|--------|
| Post doesn't exist | `wp_insert_post()` | `Created page "About" (ID 42, slug: about).` |
| Post exists, file unchanged | Skip (no-op) | `Page "About" unchanged, skipping.` |
| Post exists, file changed | `wp_update_post()` + revision | `Updated page "About" (ID 42, slug: about).` |

The output substitutes the actual post type — `Created product "…"`, `Updated attribute_page "…"`, etc. Lookup is `(slug, post_type)`, so the same slug can exist under different post types without collision.

= Validate block markup =

```bash
# Validate a page file — always outputs JSON
wp pac validate about.html --user=1

# Strict mode — warnings also cause exit code 1
wp pac validate about.html --user=1 --strict

# Filter issues with jq
wp pac validate about.html --user=1 | jq '.issues[] | {path, blockName, rule, message}'
```

The validator checks for:
- Bare HTML outside block comments (silently lost in the editor)
- Invalid nesting (e.g. `core/button` outside `core/buttons`)
- Missing wrapper elements and classes (e.g. image without `wp-block-image`)
- Heading level attribute vs HTML tag mismatches
- Unknown/unsupported block types (warning, not fatal)

Exit codes: `0` = ok, `1` = fatal issues (or warnings with `--strict`).

= Pull a post from WordPress =

```bash
# Pull a page by slug — writes to <pages-root>/about.html
wp pac pull about --user=1

# Pull a non-page post — path-style shorthand selects the type
wp pac pull product/cacao-200g --user=1
# Writes <pages-root>/products/cacao-200g.html (per-type default dir)

# Equivalent with the explicit flag
wp pac pull cacao-200g --post-type=product --user=1

# Pull into a subdirectory (overrides the per-type default)
wp pac pull about --dir=drafts/ --user=1

# Pull with revision ID in filename (versioned snapshot)
wp pac pull about --revision-suffix --user=1
# Writes about.r456.html — pushable back with slug 'about'

# Force overwrite existing file
wp pac pull about --force --user=1

# JSON output for scripting
wp pac pull about --force --format=json --user=1
```

Pulled files include `pulled_revision` and `pulled_gmt` in front matter for revision tracking. These fields are ignored on push. The `type:` front-matter field is only emitted for non-page posts so that pulled `page` files round-trip byte-for-byte unchanged.

= Finding admin users =

The `--user` flag is required in most hosting environments:

```bash
wp user list --role=administrator --fields=ID,user_login
```

= Multi-page ordering =

Push parent pages before children:

```bash
wp pac push company.html --user=1
wp pac push company/about.html --user=1
wp pac push company/team.html --user=1
```

== Frequently Asked Questions ==

= What file format does Pages as Code use? =
Pages use `.html` files with YAML front matter at the top (delimited by `---`). The body contains standard Gutenberg block markup.

= Does it support posts or custom post types? =
Yes. As of 1.8.0, themes and plugins can register additional post types via the `pac_post_types` filter — see [Custom post types](#custom-post-types). Each entry can override the default landing directory and required capability. `page` is always implicitly registered. The post type for a file is declared in the `type:` front-matter field or via the `--post-type=<slug>` CLI flag. Scope is currently `title` / `slug` / `status` / `excerpt` / `post_content`; CPT-specific fields like product price, SKU, and taxonomies stay in WP admin.

= What happens if I edit a page in WordPress after pushing? =
The next `wp pac push` for that file will overwrite any changes made in WordPress. Pages as Code is a one-way file-to-WordPress workflow. The file is always the source of truth at push time. To capture human edits, run `wp pac pull <slug>` first.

= How does the skip-if-unchanged behavior work? =
Pages as Code computes a SHA-256 hash of the file content and stores it as post meta (`_pac_hash`). On subsequent pushes, if the hash matches, the push is skipped. This avoids unnecessary database writes.

= What YAML front matter fields are supported? =
`title` (required), `slug`, `type`, `status`, `template` (page-only), `parent` (page-only), `css`, `js`, and `meta`. See the [front matter table](#front-matter-fields) for defaults and validation rules.

= Can I move the managed-files directory somewhere other than `wp-content/pages/`? =
Yes — register the [`pac_pages_root` filter](#filter-pac_pages_root) and return any absolute path. All commands and the asset enqueue follow the filter. Note that registering it also tells the plugin to skip activation scaffolding, so you take ownership of `CLAUDE.md` and the `.claude/skills/` tree.

= Does it require WP-CLI? =
Yes. Pages as Code is a CLI-only tool with no admin UI. It requires WP-CLI 2.0 or later.

== Screenshots ==

No screenshots. Pages as Code is a CLI-only tool with no admin interface.

== Changelog ==

= 1.8.0 =

* `pac_post_types` filter — register additional post types (e.g. WooCommerce `product`, custom CPTs) for management via `wp pac push`/`pull`. Per-type config supports `dir` (default landing dir for pull) and `capability` (required cap). `page` is always implicit.
* `type:` front matter field — declares the post type (default `page`). Validated against the registry.
* `--post-type=<slug>` flag on `wp pac push` and `wp pac pull`. Wins over front-matter `type:` and the path-style shorthand.
* Path-style pull shorthand — `wp pac pull product/cacao-200g` is equivalent to `wp pac pull cacao-200g --post-type=product`.
* `pac_pages_root` filter — themes can override the managed files directory. Registering the filter signals consent that the consumer manages its own `CLAUDE.md` / `.claude/skills/`, so activation skips that scaffolding (the directory itself is still created).
* Internal helpers exposed for theme/plugin consumers: `pac_pages_root()`, `pac_post_types()`, `pac_post_type_config()`, `pac_resolve_post_type()`, `pac_post_type_capability()`.
* Asset enqueue and capability checks now apply per-post-type (no more hardcoded `is_singular('page')` / `edit_pages`).
* Existing `page` files continue to push/pull byte-for-byte unchanged — no `type:` line is emitted on pulled pages, and bare files default to type `page` with no migration required.

= 1.7.0 =

* `wp pac pull <slug>` command to extract WordPress pages into `.html` files
* Revision tracking via `pulled_revision` and `pulled_gmt` front matter fields
* `--dir`, `--force`, `--revision-suffix` flags for flexible pull workflows
* `PAC_Serializer` class for reusable YAML + body serialization
* User-defined meta round-trip via `_pac_meta_keys` tracking on push
* Revision-suffixed filenames (`about.r123.html`) push back with correct slug

= 1.6.0 =

* `wp pac validate <file>` command for block markup validation with JSON diagnostic reports
* `PAC_Validator` service with grammar, nesting, wrapper, and per-block validation rules
* Detects bare HTML outside blocks, heading level mismatches, invalid nesting, missing wrapper classes
* `--strict` flag treats warnings as fatal
* Designed for agent tuning feedback loops and future push integration

= 1.5.0 =

* Sibling CSS/JS asset support with three-tier resolution
* Page-specific CSS enqueued on frontend and block editor
* Page-specific JS enqueued on frontend only
* New `PAC_Assets` class for WordPress-native enqueue
* Asset path safety validation under `WP_CONTENT_DIR`
* CLI reports resolved asset paths after push

= 1.3.0 =

* Consolidated into one orchestrator skill with progressive disclosure
* Added shared page standards, generate workflow, and publish workflow references
* Added validation script and starter page template
* GridPane commands now fallback-only when standard `wp` CLI fails

= 1.2.0 =

* Restructured skills into proper Claude Code `.claude/skills/` format
* Activation scaffolds full skill tree in pages directory

= 1.1.0 =

* Added CLAUDE.md agent instructions and skill reference
* Added publishing workflow documentation

= 1.0.0 =

* Initial release: `wp pac push <file>` command
* YAML front matter parsing, SHA-256 hashing, parent resolution
* Path traversal protection, capability checks, JSON output
