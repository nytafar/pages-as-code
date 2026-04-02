=== Pages as Code ===
Contributors: lassejellum
Tags: pages, cli, gutenberg, blocks, developer-tools
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

File-backed Gutenberg pages for WordPress. Author page content as .html files with YAML front matter and block markup, push to WordPress via WP-CLI.

== Description ==

Pages as Code is a one-way file-to-WordPress workflow for developers and coding agents. Author your page content as `.html` files with YAML front matter and Gutenberg block markup, then push them to WordPress using WP-CLI.

**Key features:**

* Write pages as `.html` files with YAML front matter (title, slug, status, template, parent, meta)
* Push pages to WordPress with the `wp pac push <file>` WP-CLI command
* SHA-256 content hashing skips unchanged pages automatically
* Parent page resolution by slug
* Plugin tracking meta (`_pac_managed`, `_pac_source`, `_pac_hash`, `_pac_last_push_gmt`)
* Path traversal protection and capability checks (`edit_pages`)
* JSON output format support (`--format=json`)

Pages as Code requires WP-CLI 2.0 or later.

== Installation ==

1. Upload the `pages-as-code` folder to the `/wp-content/plugins/` directory, or install from the WordPress plugin screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. The plugin automatically creates a `wp-content/pages/` directory on activation. You can also create it manually.
4. Create `.html` files in `wp-content/pages/` with YAML front matter and Gutenberg block markup.
5. Push a page to WordPress by running: `wp pac push <file>`

**Example file (`wp-content/pages/about.html`):**

    ---
    title: About Us
    slug: about
    status: publish
    template: default
    ---
    <!-- wp:paragraph -->
    <p>Welcome to our about page.</p>
    <!-- /wp:paragraph -->

== Usage ==

**Publishing workflow**

The `wp pac push` command requires the `edit_pages` capability. In most hosting environments, WP-CLI runs without a logged-in user context, so you need to pass `--user=<admin_id>` to run as an admin user.

To find admin users on your site:

    wp user list --role=administrator --fields=ID,user_login

On GridPane hosting, WP-CLI runs through the `gp wp` wrapper:

    gp wp staging.myrvann.no pac push about.html --user=1

**Template validation**

The `template` front matter field must reference a template slug that exists in the active theme. If the template does not exist, WordPress will reject the post. Omit the field entirely to use the default page template.

**Parent pages**

If a page has a `parent` field, the parent page must already exist in WordPress before you push the child page. Push parent pages first.

**Re-pushing unchanged files**

The plugin computes a SHA-256 hash of the file contents and stores it as post meta. On subsequent pushes, if the hash matches, the update is skipped. This avoids unnecessary database writes and revision creation.

**AI agent support**

The plugin ships a `CLAUDE.md` instructions file and a `pac-page` skill reference for AI coding agents. These are copied to the pages directory on activation and provide block editor markup guidance, front matter reference, and CLI usage documentation.

== Frequently Asked Questions ==

= What file format does Pages as Code use? =

Pages use `.html` files with YAML front matter at the top (delimited by `---`). The body contains standard Gutenberg block markup.

= Does it support posts or custom post types? =

No. Pages as Code currently supports pages only. Post and custom post type support may be added in future versions.

= What happens if I edit a page in WordPress after pushing? =

The next `wp pac push` for that file will overwrite any changes made in WordPress. Pages as Code is a one-way file-to-WordPress workflow. The file is always the source of truth.

= How does the skip-if-unchanged behavior work? =

Pages as Code computes a SHA-256 hash of the file content and stores it as post meta (`_pac_hash`). On subsequent pushes, if the hash matches, the push is skipped. This avoids unnecessary database writes.

= What YAML front matter fields are supported? =

The supported fields are: `title`, `slug`, `status`, `template`, `parent`, and `meta`.

= Does it require WP-CLI? =

Yes. Pages as Code provides a WP-CLI command (`wp pac push`) and requires WP-CLI 2.0 or later. It does not add any admin UI.

== Screenshots ==

No screenshots. Pages as Code is a CLI-only tool with no admin interface.

== Changelog ==

= 1.1.0 =
* Added CLAUDE.md agent instructions file, copied to pages directory on activation
* Added `pac-page` skill reference with block editor markup guide, front matter reference, and CLI usage
* Added Usage documentation in readme with publishing workflow, GridPane specifics, and troubleshooting
* Added block markup linter to development roadmap
* Plugin activation now copies CLAUDE.md to wp-content/pages/ for AI agent discovery
* Version bump to 1.1.0

= 1.0.0 =
* Initial release
* `wp pac push <file>` WP-CLI command for pushing page files to WordPress
* YAML front matter parsing (title, slug, status, template, parent, meta)
* SHA-256 content hashing for skip-if-unchanged behavior
* Parent page resolution by slug
* Plugin tracking meta (_pac_managed, _pac_source, _pac_hash, _pac_last_push_gmt)
* Auto-creation of wp-content/pages/ directory on activation
* Path traversal protection
* Capability checks (edit_pages)
* JSON output format support (--format=json)
