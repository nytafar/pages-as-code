# Changelog

All notable changes to Pages as Code will be documented in this file.

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
