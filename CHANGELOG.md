# Changelog

All notable changes to Pages as Code will be documented in this file.

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
