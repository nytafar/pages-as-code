<?php
/**
 * Plugin Name: Pages as Code
 * Plugin URI:  https://github.com/nytafar/pages-as-code
 * Description: File-backed Gutenberg pages for WordPress. Author page content as .html files with front matter and block markup, push to WordPress via WP-CLI.
 * Version:     1.9.0
 * Author:      Lasse Jellum
 * Author URI:  https://jellum.net
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pages-as-code
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PAC_VERSION', '1.9.0' );
define( 'PAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAC_PAGES_ROOT_DEFAULT', WP_CONTENT_DIR . '/pages' );

require_once PAC_PLUGIN_DIR . 'includes/class-pac-asset-path.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-file.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-pusher.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-assets.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-preview.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-validator.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-serializer.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-puller.php';

// Initialize frontend/editor asset enqueue.
PAC_Assets::init();
PAC_Preview::init();

/**
 * Get the managed pages root directory.
 *
 * Themes and plugins can override the location with the `pac_pages_root`
 * filter. When the filter is registered, activation will not scaffold
 * agent instructions or skills — the consumer manages those itself.
 *
 * @return string Absolute path to the pages root, no trailing slash.
 */
function pac_pages_root() {
	return rtrim( (string) apply_filters( 'pac_pages_root', PAC_PAGES_ROOT_DEFAULT ), '/' );
}

/**
 * Get the registry of post types managed by pac.
 *
 * Themes and plugins extend the registry with the `pac_post_types` filter.
 * The filter receives an associative array keyed by post-type slug; each
 * value is an optional config array with these keys:
 *
 *  - dir        (string) Default subdirectory under the pages root for
 *               `wp pac pull <type>/<slug>`. Falls back to the post-type
 *               slug when omitted.
 *  - capability (string) Capability required to push/pull this post type.
 *               Falls back to the post-type object's `cap->edit_posts`
 *               (e.g. `edit_pages` for `page`, `edit_posts` for `product`).
 *
 * `page` is always present — the filter cannot remove it.
 *
 * Example:
 *
 *     add_filter( 'pac_post_types', function ( $types ) {
 *         $types['product']        = array(
 *             'dir'        => 'products',
 *             'capability' => 'manage_woocommerce',
 *         );
 *         $types['attribute_page'] = array();
 *         return $types;
 *     } );
 *
 * @return array<string,array> Map of post_type => config array.
 */
function pac_post_types() {
	$types = apply_filters( 'pac_post_types', array( 'page' => array() ) );

	if ( ! is_array( $types ) ) {
		$types = array();
	}

	// Guarantee `page` is always present and config is an array.
	if ( ! isset( $types['page'] ) || ! is_array( $types['page'] ) ) {
		$types['page'] = array();
	}

	foreach ( $types as $slug => $config ) {
		if ( ! is_array( $config ) ) {
			$types[ $slug ] = array();
		}
	}

	return $types;
}

/**
 * Get the merged config for a single registered post type.
 *
 * @param string $post_type Post-type slug.
 * @return array|null Config array (possibly empty), or null if not registered.
 */
function pac_post_type_config( $post_type ) {
	$types = pac_post_types();
	return isset( $types[ $post_type ] ) ? $types[ $post_type ] : null;
}

/**
 * Resolve the post type for a push/pull operation.
 *
 * Resolution order: explicit CLI flag > front-matter `type:` > 'page'.
 *
 * @param PAC_File|string|null $file_or_type PAC_File object, post_type string,
 *                                           or null when only the flag matters.
 * @param string|null          $cli_flag     Value of the --post-type CLI flag.
 * @return string|WP_Error Resolved post-type slug, or error if not registered.
 */
function pac_resolve_post_type( $file_or_type = null, $cli_flag = null ) {
	$post_type = 'page';

	if ( $file_or_type instanceof PAC_File ) {
		if ( ! empty( $file_or_type->post_type ) ) {
			$post_type = $file_or_type->post_type;
		}
	} elseif ( is_string( $file_or_type ) && '' !== $file_or_type ) {
		$post_type = $file_or_type;
	}

	if ( is_string( $cli_flag ) && '' !== $cli_flag ) {
		$post_type = $cli_flag;
	}

	$post_type = sanitize_key( $post_type );

	$types = pac_post_types();
	if ( ! isset( $types[ $post_type ] ) ) {
		return new WP_Error(
			'pac_unregistered_post_type',
			sprintf(
				'Post type "%s" is not registered with pac. Register it via the pac_post_types filter.',
				$post_type
			)
		);
	}

	return $post_type;
}

/**
 * Get the capability required to push/pull a given post type.
 *
 * Resolution order:
 *   1. `capability` from the per-type config (set via pac_post_types filter)
 *   2. The post-type object's `cap->edit_posts` (e.g. `edit_pages` for page)
 *   3. `edit_posts` as a final fallback
 *
 * @param string $post_type Post-type slug.
 * @return string Capability slug.
 */
function pac_post_type_capability( $post_type ) {
	$config = pac_post_type_config( $post_type );

	if ( is_array( $config ) && ! empty( $config['capability'] ) ) {
		return (string) $config['capability'];
	}

	$object = get_post_type_object( $post_type );
	if ( $object && ! empty( $object->cap->edit_posts ) ) {
		return (string) $object->cap->edit_posts;
	}

	return 'edit_posts';
}

/**
 * On activation, create the pages directory and scaffold AI agent skills.
 *
 * If the `pac_pages_root` filter is registered (e.g. by a theme), the
 * scaffolding step is skipped — the consumer is expected to manage its
 * own CLAUDE.md and skills tree.
 */
function pac_activate() {
	$pages_root = pac_pages_root();

	if ( ! is_dir( $pages_root ) ) {
		wp_mkdir_p( $pages_root );
	}

	PAC_Preview::register_rewrite_rules();
	flush_rewrite_rules();

	// Skip scaffolding when a theme/plugin overrides the pages root.
	if ( has_filter( 'pac_pages_root' ) ) {
		return;
	}

	// Create .gitkeep so the directory can be committed to version control.
	$gitkeep = $pages_root . '/.gitkeep';
	if ( ! file_exists( $gitkeep ) ) {
		file_put_contents( $gitkeep, '' );
	}

	// Copy agent instructions to pages root (separate from plugin dev CLAUDE.md).
	// Skip if already present — deployed sites may have customized versions.
	$source = PAC_PLUGIN_DIR . 'assets/pages-CLAUDE.md';
	$dest   = $pages_root . '/CLAUDE.md';
	if ( file_exists( $source ) && ! file_exists( $dest ) ) {
		copy( $source, $dest );
	}

	// Copy .claude/skills/ tree to pages root for Claude Code skill discovery.
	// Skip if the skills directory already exists — avoid overwriting customizations.
	$skills_source = PAC_PLUGIN_DIR . '.claude/skills';
	$skills_dest   = $pages_root . '/.claude/skills';
	if ( is_dir( $skills_source ) && ! is_dir( $skills_dest ) ) {
		pac_copy_directory( $skills_source, $skills_dest );
	}
}

/**
 * Recursively copy a directory.
 *
 * @param string $source Source directory.
 * @param string $dest   Destination directory.
 */
function pac_copy_directory( $source, $dest ) {
	if ( ! is_dir( $dest ) ) {
		wp_mkdir_p( $dest );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$target = $dest . '/' . $iterator->getSubPathname();
		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) ) {
				wp_mkdir_p( $target );
			}
		} else {
			copy( $item->getPathname(), $target );
		}
	}
}
register_activation_hook( __FILE__, 'pac_activate' );

/**
 * Flush rewrite rules on deactivation to remove preview routes.
 */
function pac_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pac_deactivate' );

/**
 * Register WP-CLI command when running in CLI context.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once PAC_PLUGIN_DIR . 'includes/class-pac-cli.php';
	WP_CLI::add_command( 'pac', 'PAC_CLI' );
}
