<?php
/**
 * PAC_Asset_Path — Convert asset paths between absolute, wp-content-relative, and URL forms.
 *
 * Stored asset meta (`_pac_css`, `_pac_js`) is wp-content-relative so it survives
 * filesystem moves. All three converters tolerate either form on input, so legacy
 * absolute meta keeps rendering correctly without a migration step.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_Asset_Path {

	/**
	 * Convert any path (absolute or relative) to wp-content-relative.
	 *
	 * @param string $path Absolute filesystem path or wp-content-relative path.
	 * @return string Wp-content-relative path (no leading slash), or '' for empty input.
	 */
	public static function to_relative( $path ) {
		$path = (string) $path;
		if ( '' === $path ) {
			return '';
		}

		// Already relative.
		if ( '/' !== $path[0] ) {
			return ltrim( $path, '/' );
		}

		// Absolute under the current WP_CONTENT_DIR — prefer realpath for symlinks / `..`.
		$real = realpath( $path );
		$base = realpath( WP_CONTENT_DIR );
		if ( $real && $base && 0 === strpos( $real, $base . '/' ) ) {
			return substr( $real, strlen( $base ) + 1 );
		}

		// Literal strip for values that don't exist on disk.
		$prefix = WP_CONTENT_DIR . '/';
		if ( 0 === strpos( $path, $prefix ) ) {
			return substr( $path, strlen( $prefix ) );
		}

		// Cross-install legacy values: paths cloned from another environment
		// (e.g. /var/www/staging/.../wp-content/pages/foo.css on a dev clone).
		// Recover the tail after the last `/wp-content/` segment so the meta
		// self-heals against the current install's WP_CONTENT_DIR.
		$marker = strrpos( $path, '/wp-content/' );
		if ( false !== $marker ) {
			return substr( $path, $marker + strlen( '/wp-content/' ) );
		}

		return ltrim( $path, '/' );
	}

	/**
	 * Convert any path (relative or absolute) to absolute under the current install.
	 *
	 * Routes through `to_relative` first so cross-install legacy absolute values
	 * (paths whose original WP_CONTENT_DIR no longer exists) are remapped onto
	 * this install's WP_CONTENT_DIR.
	 *
	 * @param string $path Wp-content-relative path or absolute path.
	 * @return string Absolute filesystem path, or '' for empty input.
	 */
	public static function to_absolute( $path ) {
		$relative = self::to_relative( $path );
		if ( '' === $relative ) {
			return '';
		}
		return WP_CONTENT_DIR . '/' . $relative;
	}

	/**
	 * Convert any path (relative or absolute) to a URL.
	 *
	 * @param string $path Wp-content-relative path or absolute path.
	 * @return string URL, or '' if the path is empty or outside WP_CONTENT_DIR.
	 */
	public static function to_url( $path ) {
		$relative = self::to_relative( $path );
		if ( '' === $relative ) {
			return '';
		}
		// content_url() respects is_ssl() so https pages don't get mixed-content
		// blocked when WP_CONTENT_URL is configured as http.
		return content_url( '/' . $relative );
	}
}
