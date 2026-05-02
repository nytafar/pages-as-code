# Changelog

All notable changes to Pages as Code will be documented in this file.

## [1.9.0] - 2026-04-29

### Changed
- **Portable asset paths.** `_pac_css` / `_pac_js` post meta now store paths relative to `WP_CONTENT_DIR` (e.g. `pages/cacao.css`, `pac/origin.css`) instead of absolute filesystem paths. Stored values now survive filesystem moves and coexist cleanly across pages-root variants (legacy `wp-content/pages/` and custom `wp-content/pac/`).
- New `PAC_Asset_Path` helper centralises conversion between relative, absolute, and URL forms. All read sites tolerate legacy absolute values transparently — existing pages keep rendering with no migration step. Subsequent pushes naturally rewrite meta to the relative form.

## [1.8.0] - 2026-04-27

### Added
- **`pac_post_types` filter** — themes and plugins can register additional post types managed by pac (e.g. WooCommerce `product`, custom CPTs). Per-type config supports `dir` (default landing dir for pull) and `capability` (required cap for push/pull). `page` is always implicitly registered.
- **`type:` front matter field** — declares the post type for a file. Defaults to `page` when absent. Validated against the registered registry.
- **`--post-type=<slug>` CLI flag** on `wp pac push` and `wp pac pull`. Wins over front-matter `type:` and over the path-style shorthand.
- **Path-style pull shorthand** — `wp pac pull product/cacao-200g` is equivalent to `wp pac pull cacao-200g --post-type=product`.
- **`pac_pages_root` filter** — themes can override the managed-pages directory (e.g. `wp-content/pac/` instead of `wp-content/pages/`). When the filter is registered, activation skips scaffolding so the consumer can manage its own `CLAUDE.md` / `.claude/skills/`.
- Internal helpers `pac_pages_root()`, `pac_post_types()`, `pac_post_type_config()`, `pac_resolve_post_type()`, `pac_post_type_capability()` exposed for theme/plugin consumers.
- `post_type` field in push/pull result payloads.

### Changed
- Asset enqueue (`PAC_Assets`) now fires for every registered post type, not just `page`.
- Capability checks are per-post-type (resolved via the registered config or the post-type object's `edit_posts` cap), not the hardcoded `edit_pages`.
- `PAC_Pusher::find_page_by_slug()` → `find_post_by_slug( $slug, $post_type )`. Parent resolution is now scoped to the same post type.
- `PAC_Puller::pull()` now takes `( $slug, $post_type = 'page', $options = array() )`. Default landing directory comes from the per-type `dir` config (falls back to the post-type slug; `page` still lands at the pages root).
- Page-only fields (`template`, `parent`) are stripped from the post payload for non-page post types.
- Existing `page` files round-trip byte-for-byte unchanged: `type:` is only emitted in pulled front matter when `post_type !== 'page'`.

### Backwards compatibility
- Files at the pages root with no `type:` field continue to push as `page` — no migration required.
- All existing CLI invocations (`wp pac push <file>`, `wp pac pull <slug>`, `wp pac validate <file>`) work unchanged.

## [1.7.0] - 2026-04-04

### Added
- `wp pac pull <slug>` WP-CLI command to extract WordPress pages into `.html` files with YAML front matter
- `PAC_Puller` service class for page extraction, path resolution, and file writing
- `PAC_Serializer` reusable class for YAML front matter + block body serialization
- `pulled_revision` and `pulled_gmt` front matter fields for revision tracking
- `_pac_pulled_revision` and `_pac_pulled_gmt` post meta for server-side drift detection
- `_pac_meta_keys` post meta on push for user-defined meta round-trip
- `--dir=<path>` flag to pull into a subdirectory under pages root
- `--force` flag to overwrite existing files
- `--revision-suffix` flag to write versioned snapshots (e.g. `about.r123.html`)
- `--format=json` support matching push command pattern
- Revision suffix stripping in `PAC_File` slug resolution (`about.r123.html` → slug `about`)
- File collision protection: refuses to overwrite by default
- `docs/pull-roadmap.md` with design decisions and future concerns

### Design decisions
- Pull is a simple CLI building block — workflow logic lives in the agent
- Revision ID from `wp_get_post_revisions()` used as drift anchor, falls back to `post_modified_gmt`
- No conflict resolution in v1 — user manages pull → edit → push cycle
- Content normalization accepted: pulled content reflects WordPress state, not original push
- PAC_Serializer separated for reuse by future template generation and export tools

## [1.6.0] - 2026-04-04

### Added
- `wp pac validate <file>` WP-CLI command for block markup validation
- `PAC_Validator` stateless service class with structured JSON diagnostic reports
- Grammar validation: bare HTML detection (null_block), empty document check
- Nesting validation: parent constraints (list-item→list, button→buttons, column→columns) and child type checks
- Per-block validators for 11 core blocks: paragraph, heading, list, list-item, image, buttons, button, group, columns, column, cover
- Wrapper element checks (e.g. paragraph needs `<p>`, image needs `<img>`)
- Wrapper class checks (e.g. image needs `wp-block-image`, group needs `wp-block-group`)
- Heading level attribute vs HTML tag mismatch detection
- Unknown/unsupported block warnings for blocks outside the supported set
- Stable tree-position path notation (e.g. `3/1/0`) for mapping issues to block location
- `autoFixable` and `suggestedRepair` fields in issue schema for future `--fix` support
- `--strict` flag to treat warnings as fatal for exit code
- JSON-only output designed for agent consumption and diagnostic feedback loops

### Design decisions
- Validator is a standalone service (`PAC_Validator::validate_document()`) decoupled from CLI and push flow
- Loaded unconditionally (not behind `WP_CLI` guard) so `PAC_Pusher` can call it in future
- Whitespace-only null blocks from `parse_blocks()` are filtered as parser artifacts, not flagged
- Unknown blocks are warnings, not fatal — allows third-party blocks to pass through
- No `--fix` mode in v1; repair hints are report-only
- Output is always JSON — consumer (agent or human) handles presentation

## [1.5.0] - 2026-04-03

### Added
- Sibling CSS/JS asset support for page files
- Three-tier asset resolution: front matter path > sibling file > shared `pages/css/` or `pages/js/` directory
- `css` and `js` front matter fields for explicit asset path overrides (relative to `wp-content/`)
- Frontend enqueue: page-specific CSS and JS loaded only on the corresponding page
- Editor enqueue: page-specific CSS loaded in the block editor for styling parity
- Post meta: `_pac_css`, `_pac_js`, `_pac_css_hash`, `_pac_js_hash` written on push
- `PAC_Assets` class for WordPress-native enqueue via `wp_enqueue_scripts` and `enqueue_block_editor_assets`
- `PAC_File::resolve_asset()` and `PAC_File::validate_asset_path()` public helpers
- CLI reports resolved CSS/JS paths after push
- Asset path safety: all resolved paths validated under `WP_CONTENT_DIR`
- filemtime-based versioning for cache busting
- Claude skill updated with CSS/JS asset conventions and style philosophy

### Changed
- `PAC_Pusher::push()` results now include `css` and `js` keys
- Page standards reference updated with asset file convention
- Generate workflow updated with asset creation guidance
- Version bump to 1.5.0

### Design decisions
- CSS loads on both frontend and block editor for styling parity
- JS loads on frontend only (editor scripts often break in the editor iframe)
- Assets cleared from meta if file is missing at push time
- No inline `<style>`/`<script>` parsing from HTML body — assets are always separate files

## [1.4.0] - 2026-04-03

### Added
- Rich README.md as canonical documentation source for GitHub
- `readme.meta.json` for WordPress-specific metadata
- `tools/generate-readme.php` to auto-generate readme.txt from README.md
- Pre-commit hook to regenerate readme.txt when README.md changes
- Claude Code project hook (`.claude/settings.json`) for live readme regeneration
- `assets/pages-CLAUDE.md` for user-facing agent instructions (separate from plugin dev)
- Plugin development CLAUDE.md with architecture docs and conventions

### Changed
- readme.txt is now auto-generated — do not edit directly
- Activation hook copies from `assets/pages-CLAUDE.md` instead of root `CLAUDE.md`
- Root `CLAUDE.md` is now plugin development instructions only
- Version bump to 1.4.0

## [1.3.0] - 2026-04-03

### Added
- Progressive disclosure skill structure with intent-based routing (generate / publish / both)
- Shared page standards reference (`references/shared/page-standards.md`)
- Generate workflow reference (`references/generate/workflow.md`)
- Publish workflow reference (`references/publish/workflow.md`)
- Troubleshooting guide with environment detection (`references/publish/troubleshooting.md`)
- Page validation script (`scripts/validate-page.sh`)
- Starter page template (`templates/page-shell.html`)

### Changed
- Consolidated three separate skills (pages-as-code, pac-markup, pac-cli) into one orchestrator skill
- GridPane commands now used only as fallback when standard `wp` CLI fails or GridPane environment detected
- CLAUDE.md simplified to reference single skill with progressive loading
- Version bump to 1.3.0

### Removed
- Separate `pac-markup` and `pac-cli` skill directories

## [1.2.0] - 2026-04-02

### Added
- Master skill `pages-as-code` for workflow orchestration (`/pages-as-code`)
- Sub-module skill `pac-markup` for page file creation and block editor reference (`/pac-markup`)
- Sub-module skill `pac-cli` for WP-CLI push workflow and troubleshooting (`/pac-cli`)
- Block editor reference with 50+ core blocks at `pac-markup/references/block-editor.md`
- Recursive directory copy helper `pac_copy_directory()` for skill deployment

### Changed
- Skills moved from `skills/` to `.claude/skills/` for Claude Code auto-discovery
- Activation hook now scaffolds full `.claude/skills/` tree in pages directory
- CLAUDE.md updated to reference new skill structure
- Version bump to 1.2.0

### Removed
- Old `skills/pac-page.md` monolithic skill file

## [1.1.0] - 2026-04-02

### Added
- CLAUDE.md agent instructions file, copied to pages directory on activation
- `pac-page` skill reference with block editor markup guide, front matter reference, and CLI usage
- Usage documentation in readme with publishing workflow, GridPane specifics, and troubleshooting
- Block markup linter added to development roadmap

### Changed
- Plugin activation now copies CLAUDE.md to wp-content/pages/ for AI agent discovery
- Version bump to 1.1.0

## [1.0.0] - 2026-04-02

### Added
- Initial release
- `wp pac push <file>` WP-CLI command for pushing page files to WordPress
- YAML front matter parsing (title, slug, status, template, parent, meta)
- SHA-256 content hashing for skip-if-unchanged behavior
- Parent page resolution by slug
- Plugin tracking meta (_pac_managed, _pac_source, _pac_hash, _pac_last_push_gmt)
- Auto-creation of wp-content/pages/ directory on activation
- Path traversal protection
- Capability checks (edit_pages)
- JSON output format support (--format=json)
