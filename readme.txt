=== Pages as Code ===
Contributors: lassejellum
Tags: pages, cli, gutenberg, blocks, developer-tools
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

File-backed Gutenberg pages for WordPress. Author page content as .html files with YAML front matter and block markup, push to WordPress via WP-CLI.

== Description ==

Pages as Code is a one-way file-to-WordPress workflow for developers and coding agents. Author your page content as `.html` files with YAML front matter and Gutenberg block markup, then push them to WordPress using WP-CLI.

**Key features:**

- Write pages as `.html` files with YAML front matter (title, slug, status, template, parent, meta)
- Push pages to WordPress with the `wp pac push <file>` WP-CLI command
- SHA-256 content hashing skips unchanged pages automatically
- Parent page resolution by slug
- Plugin tracking meta (`_pac_managed`, `_pac_source`, `_pac_hash`, `_pac_last_push_gmt`)
- Path traversal protection and capability checks (`edit_pages`)
- JSON output format support (`--format=json`)
- Built-in Claude Code skill with progressive disclosure for AI-assisted page creation

Pages as Code requires WP-CLI 2.0 or later.

== Installation ==

1. Upload the `pages-as-code` folder to `/wp-content/plugins/`, or install via WP-CLI:
   ```bash
   wp plugin install pages-as-code --activate
   ```
2. Activate the plugin. On activation it creates `wp-content/pages/` with a `.gitkeep` and copies the Claude Code skill and instructions for AI agents.
3. Create `.html` files in `wp-content/pages/`.
4. Push: `wp pac push <file> --user=<admin_id>`

== Usage ==

= CLI reference =

```bash
wp pac push <file> [--format=<format>] [--user=<id>]
```

| Argument | Description |
|----------|-------------|
| `<file>` | Path relative to `wp-content/pages/` |
| `--format` | `human` (default) or `json` |
| `--user` | WordPress user ID with `edit_pages` capability |

= Push behavior =

| Scenario | Action | Output |
|----------|--------|--------|
| Page doesn't exist | `wp_insert_post()` | `Created page "About" (ID 42, slug: about).` |
| Page exists, file unchanged | Skip (no-op) | `Page "About" unchanged, skipping.` |
| Page exists, file changed | `wp_update_post()` + revision | `Updated page "About" (ID 42, slug: about).` |

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
No. Pages as Code currently supports pages only. Post and custom post type support may be added in future versions.

= What happens if I edit a page in WordPress after pushing? =
The next `wp pac push` for that file will overwrite any changes made in WordPress. Pages as Code is a one-way file-to-WordPress workflow. The file is always the source of truth at push time.

= How does the skip-if-unchanged behavior work? =
Pages as Code computes a SHA-256 hash of the file content and stores it as post meta (`_pac_hash`). On subsequent pushes, if the hash matches, the push is skipped. This avoids unnecessary database writes.

= What YAML front matter fields are supported? =
`title` (required), `slug`, `status`, `template`, `parent`, and `meta`.

= Does it require WP-CLI? =
Yes. Pages as Code is a CLI-only tool with no admin UI. It requires WP-CLI 2.0 or later.

== Screenshots ==

No screenshots. Pages as Code is a CLI-only tool with no admin interface.

== Changelog ==

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
