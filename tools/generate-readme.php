#!/usr/bin/env php
<?php
/**
 * Kaupang suite readme.txt generator. (kit v2.2, docs/kit/)
 *
 * readme.txt is a build artefact: never edit it. Every fact has one source —
 *   plugin header (<folder>.php) : name, version (Stable tag), Requires at least, Requires PHP, License, Description
 *   README.md                    : prose — ## Description, ## Installation, ## Usage, ## FAQ, ## Screenshots, ## Upgrade Notice
 *   CHANGELOG.md                 : history (Keep a Changelog; [Unreleased] is left out)
 *   readme.meta.json (optional)  : registry-only fields — contributors, tags, testedUpTo, shortDescription, donateLink
 * `Tested up to` defaults to the major.minor of the WordPress install the plugin sits in (the hook runs on staging),
 * else to the last committed readme.txt value.
 *
 * Usage: php tools/generate-readme.php [--index]   (--index reads staged content; the pre-commit hook uses it)
 * Exits 1 on missing required facts; prints warnings for wordpress.org limits.
 */

$root  = dirname( __DIR__ );
$index = in_array( '--index', $argv, true );
chdir( $root );
$mainf = trim( (string) shell_exec( 'git config kit.main 2>/dev/null' ) ) ?: basename( $root ) . '.php'; // same override as the hook

function src( string $file ): ?string {
	global $index;
	if ( $index ) {
		exec( 'git ls-files --error-unmatch -- ' . escapeshellarg( $file ) . ' 2>/dev/null', $o, $code );
		return 0 === $code ? (string) shell_exec( 'git show :' . escapeshellarg( $file ) ) : null;
	}
	return is_file( $file ) ? file_get_contents( $file ) : null;
}
function fail( string $msg ): void {
	fwrite( STDERR, "generate-readme: {$msg}\n" );
	exit( 1 );
}
function warn( string $msg ): void {
	fwrite( STDERR, "generate-readme: warning: {$msg}\n" );
}

// ── Header (same parsing rules as WordPress' get_file_data) ─────────────
$main = src( $mainf ) ?? fail( "{$mainf} not found (set `git config kit.main <file>.php` if the folder isn't named after the plugin)." );
$head = substr( $main, 0, 8192 );
$h    = array();
foreach ( array( 'Plugin Name', 'Description', 'Version', 'Requires at least', 'Requires PHP', 'License', 'License URI', 'Tested up to' ) as $f ) {
	$h[ $f ] = preg_match( '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote( $f, '/' ) . ':(.*)$/mi', $head, $m ) ? trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $m[1] ) ) : '';
}
foreach ( array( 'Plugin Name', 'Version', 'Description', 'License' ) as $f ) {
	'' === $h[ $f ] && fail( "header field '{$f}' is missing in {$mainf}." );
}

$meta = json_decode( src( 'readme.meta.json' ) ?? '{}', true ) ?: array();

$licenses = array(
	'GPL-2.0-or-later' => 'https://www.gnu.org/licenses/gpl-2.0.html',
	'GPL-2.0-only'     => 'https://www.gnu.org/licenses/gpl-2.0.html',
	'GPL-3.0-or-later' => 'https://www.gnu.org/licenses/gpl-3.0.html',
	'GPL-3.0-only'     => 'https://www.gnu.org/licenses/gpl-3.0.html',
	'MIT'              => 'https://opensource.org/licenses/MIT',
);
$license_uri = $h['License URI'] ?: ( $licenses[ $h['License'] ] ?? '' );

$tested = $meta['testedUpTo'] ?? $h['Tested up to'];
if ( '' === $tested && is_file( $root . '/../../../wp-includes/version.php' ) ) {
	preg_match( "/\\\$wp_version\s*=\s*'(\d+\.\d+)/", file_get_contents( $root . '/../../../wp-includes/version.php' ), $v );
	$tested = $v[1] ?? '';
}
if ( '' === $tested && preg_match( '/^Tested up to:[ \t]*(\S+)/mi', (string) shell_exec( 'git show HEAD:readme.txt 2>/dev/null' ), $v ) ) {
	$tested = $v[1]; // outside a WordPress install: keep the last committed value rather than dropping the field
}

$short = $meta['shortDescription'] ?? $h['Description'];
$tags  = $meta['tags'] ?? array();
if ( strlen( $short ) > 150 ) {
	warn( 'short description is ' . strlen( $short ) . ' chars; wordpress.org truncates at 150 (set readme.meta.json shortDescription).' );
}
if ( count( $tags ) > 5 ) {
	warn( 'wordpress.org shows only the first 5 tags.' );
}
'' === $tested && warn( "no 'Tested up to' (not in readme.meta.json, and no WordPress install found above the plugin)." );

// ── Markdown → readme.txt ───────────────────────────────────────────────
$md = src( 'README.md' ) ?? '';

function section( string $md, array $titles ): string {
	foreach ( $titles as $t ) {
		if ( preg_match( '/^##[ \t]+' . preg_quote( $t, '/' ) . '[ \t]*$\R(.*?)(?=^##[ \t]|\z)/msi', $md, $m ) ) {
			return trim( $m[1] );
		}
	}
	return '';
}
function to_readme( string $text ): string {
	$text = preg_replace( '/\[!\[[^\]]*\]\([^)]+\)\]\([^)]+\)/', '', $text );              // badges
	$text = preg_replace( '/!\[[^\]]*\]\([^)]+\)/', '', $text );                           // images
	$text = preg_replace( '/<details[\s\S]*?<\/details>/mi', '', $text );                  // <details>
	$text = preg_replace_callback( '/^```[^\n]*\n(.*?)^```[ \t]*$/ms', fn( $m ) => preg_replace( '/^(?=.)/m', '    ', $m[1] ), $text ); // fenced → indented
	$text = preg_replace( '/^#{3,}[ \t]+(.+?)[ \t]*$/m', '= $1 =', $text );                // ### h → = h =
	$text = preg_replace( '/^[ \t]*-{3,}[ \t]*$/m', '', $text );                          // hr
	return trim( preg_replace( '/\n{3,}/', "\n\n", $text ) );
}

$changelog = '';
if ( null !== ( $log = src( 'CHANGELOG.md' ) ) && preg_match_all( '/^##[ \t]+\[([^\]]+)\]([^\n]*)\R(.*?)(?=^##[ \t]+\[|\z)/ms', $log, $rel, PREG_SET_ORDER ) ) {
	foreach ( $rel as $r ) {
		if ( 0 === strcasecmp( $r[1], 'Unreleased' ) ) {
			continue;
		}
		$date  = trim( ltrim( trim( $r[2] ), '-– ' ) );
		$body  = preg_replace( '/^###[ \t]+(.+?)[ \t]*$/m', '**$1**', trim( $r[3] ) );
		$body  = preg_replace( '/^([ \t]*)[-*][ \t]+/m', '$1* ', $body );
		$changelog .= '= ' . $r[1] . ( '' !== $date ? " – {$date}" : '' ) . " =\n\n" . trim( $body ) . "\n\n";
	}
}

$out   = array( '=== ' . $h['Plugin Name'] . ' ===' );
$out[] = 'Contributors: ' . implode( ', ', $meta['contributors'] ?? array( 'lassejellum' ) );
isset( $meta['donateLink'] ) && $out[] = 'Donate link: ' . $meta['donateLink'];
$tags && $out[] = 'Tags: ' . implode( ', ', $tags );
'' !== $h['Requires at least'] && $out[] = 'Requires at least: ' . $h['Requires at least'];
'' !== $tested && $out[] = 'Tested up to: ' . $tested;
'' !== $h['Requires PHP'] && $out[] = 'Requires PHP: ' . $h['Requires PHP'];
$out[] = 'Stable tag: ' . $h['Version'];
$out[] = 'License: ' . $h['License'];
'' !== $license_uri && $out[] = 'License URI: ' . $license_uri;
$out[] = '';
$out[] = $short;

$sections = array(
	'Description'                => section( $md, array( 'Description' ) ) ?: $h['Description'],
	'Installation'               => section( $md, array( 'Installation' ) ),
	'Usage'                      => section( $md, array( 'Usage' ) ), // wordpress.org shows extra sections under "Other Notes"
	'Frequently Asked Questions' => section( $md, array( 'FAQ', 'Frequently Asked Questions' ) ),
	'Screenshots'                => section( $md, array( 'Screenshots' ) ),
	'Changelog'                  => trim( $changelog ),
	'Upgrade Notice'             => section( $md, array( 'Upgrade Notice' ) ),
);
foreach ( $sections as $title => $body ) {
	if ( '' !== $body ) {
		$out[] = '';
		$out[] = "== {$title} ==";
		$out[] = '';
		$out[] = 'Changelog' === $title ? $body : to_readme( $body );
	}
}

$result = implode( "\n", $out ) . "\n";
if ( ( is_file( 'readme.txt' ) ? file_get_contents( 'readme.txt' ) : null ) !== $result ) {
	file_put_contents( 'readme.txt', $result );
}
exit( 0 );
