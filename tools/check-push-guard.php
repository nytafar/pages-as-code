<?php
/**
 * Runnable check: `wp pac push` never overwrites a post changed since its baseline (issue #7). Exits non-zero on failure.
 *
 *   gp wp <site> eval-file wp-content/plugins/pages-as-code/tools/check-push-guard.php
 *
 * Writes: two private test pages (`pac-guard-check-*`) and their files in a temp pages root; both are deleted at the
 * end, also on failure. Proves: push → editor edit → push is refused; pull → push is accepted; --force is accepted;
 * an unmanaged post is refused without expect_hash:, refused with a wrong one, accepted with the right one; a
 * pre-1.11 post (no _pac_content_hash) falls back to the revision time; a refusal writes nothing.
 */

PHP_SAPI === 'cli' || exit;

$fail = static function ( $msg ) {
	fwrite( STDERR, "FAIL: {$msg}\n" );
	exit( 1 );
};
$ok   = static function ( $msg ) {
	echo "ok  {$msg}\n";
};

// Setup: an administrator (as `--user=` would give), a throwaway pages root, cleanup on any exit.
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admins || $fail( 'test setup: no administrator' );
wp_set_current_user( (int) $admins[0] );

$root = sys_get_temp_dir() . '/pac-guard-check-' . wp_generate_password( 8, false, false );
wp_mkdir_p( $root ) || $fail( "test setup: cannot create {$root}" );
add_filter( 'pac_pages_root', static function () use ( $root ) {
	return $root;
}, 999 );

$suffix  = strtolower( wp_generate_password( 6, false, false ) );
$slug    = 'pac-guard-check-' . $suffix;
$slug_u  = 'pac-guard-check-unmanaged-' . $suffix;
$created = array();
register_shutdown_function( static function () use ( &$created, $root ) {
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( (array) glob( $root . '/*' ) as $f ) {
		is_file( $f ) && unlink( $f );
	}
	is_dir( $root ) && rmdir( $root );
} );

$para  = static function ( $text ) {
	return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
};
$write = static function ( $name, $slug, $body, $extra = '' ) use ( $root ) {
	file_put_contents( "{$root}/{$name}", "---\ntitle: PAC guard check\nslug: {$slug}\nstatus: private\n{$extra}---\n{$body}\n" );
};
$push  = static function ( $name, $force = false ) use ( $fail ) {
	$file = PAC_File::parse( $name );
	is_wp_error( $file ) && $fail( "parse {$name}: " . $file->get_error_message() );
	return PAC_Pusher::push( $file, array( 'force' => $force ) );
};
$expect_status = static function ( $result, $status, $what ) use ( $fail, $ok ) {
	if ( is_wp_error( $result ) ) {
		$fail( "{$what}: expected {$status}, got refusal " . $result->get_error_code() . ': ' . $result->get_error_message() );
	}
	$status === $result['status'] || $fail( "{$what}: expected {$status}, got {$result['status']}" );
	$ok( "{$what}: {$status}" );
	return $result;
};
// Everything a push could write: content, modified time, revision count, every meta row (straight from the db).
$state = static function ( $id ) {
	global $wpdb;
	clean_post_cache( $id );
	$p = get_post( $id );
	return array(
		'content'   => $p->post_content,
		'modified'  => $p->post_modified_gmt,
		'title'     => $p->post_title,
		'revisions' => count( wp_get_post_revisions( $id, array( 'fields' => 'ids' ) ) ),
		'meta'      => $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $id ), ARRAY_A ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	);
};
$expect_refusal = static function ( $name, $id, $code, $what ) use ( $push, $state, $fail, $ok ) {
	$before = $state( $id );
	$result = $push( $name );
	is_wp_error( $result ) || $fail( "{$what}: expected refusal {$code}, got {$result['status']}" );
	$code === $result->get_error_code() || $fail( "{$what}: expected {$code}, got " . $result->get_error_code() . ': ' . $result->get_error_message() );
	$before === $state( $id ) || $fail( "{$what}: the refusal changed the post (content, revisions or meta)" );
	$ok( "{$what}: refused ({$code}), nothing written" );
};
$editor_edit = static function ( $id, $text ) use ( $para ) {
	$r = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $para( $text ) ) ), true );
	is_wp_error( $r ) && exit( 'FAIL: editor edit: ' . $r->get_error_message() . "\n" );
};

// 1. A post pac pushed: push → push again → change the file → push.
$write( 'a.html', $slug, $para( 'One' ) );
$id        = $expect_status( $push( 'a.html' ), 'created', 'first push' )['id'];
$created[] = $id;
get_post_meta( $id, '_pac_content_hash', true ) === PAC_Pusher::content_hash( get_post( $id )->post_content ) || $fail( '_pac_content_hash does not hold the stored content hash' );
$expect_status( $push( 'a.html' ), 'unchanged', 'same file again' );
$write( 'a.html', $slug, $para( 'Two' ) );
$expect_status( $push( 'a.html' ), 'updated', 'changed file, post untouched since the push' );

// 2. Edited in the editor after the push: the next push is refused.
$editor_edit( $id, 'Edited in wp-admin' );
$write( 'a.html', $slug, $para( 'Three' ) );
$expect_refusal( 'a.html', $id, 'pac_post_changed', 'push after an editor edit' );

// 3. Pull, then push: accepted, and the editor's text is what goes back.
$pulled = PAC_Puller::pull( $slug, 'page', array( 'force' => true ) );
is_wp_error( $pulled ) && $fail( 'pull: ' . $pulled->get_error_message() );
$raw = file_get_contents( "{$root}/{$pulled['file']}" );
false !== strpos( $raw, 'expect_hash: ' . PAC_Pusher::content_hash( get_post( $id )->post_content ) ) || $fail( 'pulled file carries no expect_hash: of the current content' );
file_put_contents( "{$root}/{$pulled['file']}", rtrim( $raw ) . "\n\n" . $para( 'Added after pull' ) . "\n" );
$expect_status( $push( $pulled['file'] ), 'updated', 'pull, then push' );
false !== strpos( get_post( $id )->post_content, 'Edited in wp-admin' ) || $fail( 'pull → push lost the editor edit' );

// 4. --force overwrites an edited post.
$editor_edit( $id, 'Edited again' );
$write( 'a.html', $slug, $para( 'Forced' ) );
$expect_refusal( 'a.html', $id, 'pac_post_changed', 'push after a second editor edit' );
$expect_status( $push( 'a.html', true ), 'updated', '--force after an editor edit' );
false !== strpos( get_post( $id )->post_content, 'Forced' ) || $fail( '--force did not write the file' );

// 5. Pre-1.11 post (no _pac_content_hash): the revision time decides.
delete_post_meta( $id, '_pac_content_hash' );
update_post_meta( $id, '_pac_last_push_gmt', gmdate( 'c', time() + 120 ) );
$write( 'a.html', $slug, $para( 'Legacy clean' ) );
$expect_status( $push( 'a.html' ), 'updated', 'pre-1.11 post, no revision after its last push' );
delete_post_meta( $id, '_pac_content_hash' );
update_post_meta( $id, '_pac_last_push_gmt', gmdate( 'c', time() - 3600 ) );
$editor_edit( $id, 'Legacy edit' );
$write( 'a.html', $slug, $para( 'Legacy push' ) );
$expect_refusal( 'a.html', $id, 'pac_post_changed', 'pre-1.11 post with a revision newer than its last push' );

// 6. A post pac never pushed (a live page the launch overwrites).
$uid = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'private', 'post_title' => 'Unmanaged', 'post_name' => $slug_u, 'post_content' => $para( 'Live text' ) ) ), true );
is_wp_error( $uid ) && $fail( 'test setup: ' . $uid->get_error_message() );
$created[] = $uid;
$right     = PAC_Pusher::content_hash( get_post( $uid )->post_content );
$wrong     = PAC_Pusher::content_hash( 'something else' );
$write( 'u.html', $slug_u, $para( 'New text' ) );
$expect_refusal( 'u.html', $uid, 'pac_unmanaged_post', 'unmanaged post, no expect_hash' );
$write( 'u.html', $slug_u, $para( 'New text' ), "expect_hash: {$wrong}\n" );
$expect_refusal( 'u.html', $uid, 'pac_expect_hash_mismatch', 'unmanaged post, wrong expect_hash' );
$write( 'u.html', $slug_u, $para( 'New text' ), "expect_hash: sha256:{$right}\n" );
$expect_status( $push( 'u.html' ), 'updated', 'unmanaged post, right expect_hash' );
'1' === get_post_meta( $uid, '_pac_managed', true ) || $fail( 'accepted push did not mark the post managed' );

// 7. A malformed expect_hash is a parse error, not a silent pass.
$write( 'bad.html', $slug_u, $para( 'x' ), "expect_hash: abc\n" );
is_wp_error( PAC_File::parse( 'bad.html' ) ) || $fail( 'malformed expect_hash parsed' );
$ok( 'malformed expect_hash: parse error' );

// 8. The CLI exposes --force on push and the hash command.
$cmd = WP_CLI::get_runner()->find_command_to_run( array( 'pac', 'push' ) );
( is_array( $cmd ) && false !== strpos( $cmd[0]->get_synopsis(), '--force' ) ) || $fail( 'wp pac push has no --force' );
WP_CLI::get_runner()->find_command_to_run( array( 'pac', 'hash' ) ) || $fail( 'wp pac hash not registered' );
$ok( 'wp pac push --force and wp pac hash registered' );

echo "OK\n";
