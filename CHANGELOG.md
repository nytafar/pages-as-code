# Changelog

All notable changes to Pages as Code will be documented in this file.

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
