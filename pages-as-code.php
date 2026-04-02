<?php
/**
 * Plugin Name: Pages as Code
 * Plugin URI:  https://github.com/nytafar/pages-as-code
 * Description: File-backed Gutenberg pages for WordPress. Author page content as .html files with front matter and block markup, push to WordPress via WP-CLI.
 * Version:     1.3.0
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

define( 'PAC_VERSION', '1.3.0' );
define( 'PAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAC_PAGES_ROOT', WP_CONTENT_DIR . '/pages' );

require_once PAC_PLUGIN_DIR . 'includes/class-pac-file.php';
require_once PAC_PLUGIN_DIR . 'includes/class-pac-pusher.php';

/**
 * On activation, create the pages directory and scaffold AI agent skills.
 */
function pac_activate() {
	if ( ! is_dir( PAC_PAGES_ROOT ) ) {
		wp_mkdir_p( PAC_PAGES_ROOT );
	}

	// Create .gitkeep so the directory can be committed to version control.
	$gitkeep = PAC_PAGES_ROOT . '/.gitkeep';
	if ( ! file_exists( $gitkeep ) ) {
		file_put_contents( $gitkeep, '' );
	}

	// Copy CLAUDE.md to pages root so AI agents pick it up automatically.
	$source = PAC_PLUGIN_DIR . 'CLAUDE.md';
	$dest   = PAC_PAGES_ROOT . '/CLAUDE.md';
	if ( file_exists( $source ) ) {
		copy( $source, $dest );
	}

	// Copy .claude/skills/ tree to pages root for Claude Code skill discovery.
	$skills_source = PAC_PLUGIN_DIR . '.claude/skills';
	$skills_dest   = PAC_PAGES_ROOT . '/.claude/skills';
	if ( is_dir( $skills_source ) ) {
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
 * Register WP-CLI command when running in CLI context.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once PAC_PLUGIN_DIR . 'includes/class-pac-cli.php';
	WP_CLI::add_command( 'pac', 'PAC_CLI' );
}
