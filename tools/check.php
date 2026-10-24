<?php
/**
 * Runnable check: loading precision (loading Tracks 3, 5, 6). Read-only; exits non-zero on failure.
 *
 *   gp wp <site> eval-file wp-content/plugins/pages-as-code/tools/check.php
 *
 * Proves: boot includes only the main file (+ PAC_CLI under WP-CLI); every class autoloads; page CSS/JS is enqueued
 * on managed posts only; editor CSS only in editors of registered post types; validate still runs.
 */

PHP_SAPI === 'cli' || exit;

$fail = static function ( $msg ) {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
};
$ok   = static function ( $msg ) {
	echo "ok  {$msg}\n";
};

// 1. Boot: nothing but the bootstrap (and the CLI command class WP-CLI reflects on registration).
$dir    = wp_normalize_path( PAC_PLUGIN_DIR );
$loaded = array();
foreach ( get_included_files() as $f ) {
	$f = wp_normalize_path( $f );
	if ( 0 === strpos( $f, $dir ) ) {
		$loaded[] = substr( $f, strlen( $dir ) );
	}
}
$extra = array_diff( $loaded, array( 'pages-as-code.php', 'includes/class-pac-cli.php', 'tools/check.php' ) );
array() === $extra || $fail( 'loaded at boot: ' . implode( ', ', $extra ) );
$ok( 'boot includes only the bootstrap (+ PAC_CLI under WP-CLI)' );

// 2. CLI command registered, all classes resolve through the autoloader.
WP_CLI::get_runner()->find_command_to_run( array( 'pac', 'validate' ) ) || $fail( 'wp pac validate not registered' );
foreach ( array( 'PAC_Asset_Path', 'PAC_File', 'PAC_Pusher', 'PAC_Validator', 'PAC_Serializer', 'PAC_Puller', 'PAC_CLI' ) as $c ) {
	class_exists( $c ) || $fail( "class {$c} does not autoload" );
}
$report = PAC_Validator::validate_document( "<!-- wp:paragraph -->\n<p>Hei</p>\n<!-- /wp:paragraph -->" );
! empty( $report['ok'] ) || $fail( 'validate_document rejected a plain paragraph' );
$ok( 'wp pac registered, classes autoload, validator runs' );

// 3. Frontend: run only pac's wp_enqueue_scripts callbacks against a managed and an unmanaged post.
$managed = get_posts( array( 'post_type' => array_keys( pac_post_types() ), 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_pac_css', 'meta_query' => array( array( 'key' => '_pac_managed', 'value' => '1' ) ), 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
$plain   = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'meta_query' => array( array( 'key' => '_pac_managed', 'compare' => 'NOT EXISTS' ) ), 'fields' => 'ids' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
( $managed && $plain ) || $fail( 'test setup: need a published managed post with CSS and an unmanaged page' );
$run_front = static function ( $post_id ) use ( $dir ) {
	$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $post_id, 'post_type' => 'any' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	foreach ( $GLOBALS['wp_filter']['wp_enqueue_scripts']->callbacks as $cbs ) {
		foreach ( $cbs as $cb ) {
			if ( $cb['function'] instanceof Closure && 0 === strpos( wp_normalize_path( ( new ReflectionFunction( $cb['function'] ) )->getFileName() ), $dir ) ) {
				call_user_func( $cb['function'] );
			}
		}
	}
};
$pac_styles = static function () {
	return array_filter( wp_styles()->queue, static function ( $h ) {
		return 0 === strpos( $h, 'pac-page-' );
	} );
};
$run_front( $plain[0] );
array() === $pac_styles() || $fail( 'page CSS queued on an unmanaged page' );
class_exists( 'PAC_Assets', false ) && $fail( 'PAC_Assets loaded on an unmanaged page' );
$run_front( $managed[0] );
in_array( 'pac-page-' . $managed[0], $pac_styles(), true ) || $fail( "page CSS missing on managed post {$managed[0]} (check is vacuous)" );
wp_dequeue_style( 'pac-page-' . $managed[0] );
wp_dequeue_script( 'pac-page-' . $managed[0] . '-js' );
$ok( "frontend: PAC_Assets + page CSS only on managed posts (#{$managed[0]} yes, #{$plain[0]} no)" );

// 4. Admin (Pattern E): no admin_enqueue_scripts callbacks; editor CSS only in editors of registered post types.
foreach ( $GLOBALS['wp_filter']['admin_enqueue_scripts']->callbacks ?? array() as $cbs ) {
	foreach ( $cbs as $cb ) {
		if ( is_array( $cb['function'] ) && is_string( $cb['function'][0] ) && 0 === strpos( $cb['function'][0], 'PAC_' ) ) {
			$fail( 'admin_enqueue_scripts callback registered' );
		}
	}
}
has_action( 'enqueue_block_editor_assets', array( 'PAC_Assets', 'enqueue_editor' ) ) || $fail( 'editor hook missing' );
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
$_GET['post'] = $managed[0];
foreach ( array( 'index', 'site-editor', 'post' ) as $foreign ) { // `post` type is not in pac_post_types on staging
	if ( isset( pac_post_types()[ $foreign ] ) ) {
		continue;
	}
	set_current_screen( $foreign );
	PAC_Assets::enqueue_editor();
	array() === $pac_styles() || $fail( "editor CSS queued on the {$foreign} screen" );
}
set_current_screen( get_post_type( $managed[0] ) );
PAC_Assets::enqueue_editor();
in_array( 'pac-page-' . $managed[0], $pac_styles(), true ) || $fail( 'editor CSS missing in the managed post editor (check is vacuous)' );
unset( $_GET['post'] );
$ok( 'editor CSS only in editors of registered post types, on managed posts' );
