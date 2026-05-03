<?php
/**
 * PAC_Preview — Pretty-permalink preview rendering for PAC files.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_Preview {

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	/**
	 * Register preview rewrite rules.
	 */
	public static function register_rewrite_rules() {
		add_rewrite_tag( '%pac_preview%', '(.+)' );
		add_rewrite_rule( '^preview/(.+)/?$', 'index.php?pac_preview=$matches[1]', 'top' );
	}

	/**
	 * Register public query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'pac_preview';
		return $vars;
	}

	/**
	 * Intercept preview requests and render the requested PAC file.
	 */
	public static function maybe_render() {
		$request_path = get_query_var( 'pac_preview' );
		if ( empty( $request_path ) ) {
			return;
		}

		$relative_path = self::normalize_request_path( $request_path );
		if ( is_wp_error( $relative_path ) ) {
			self::render_error( $relative_path, '', 400 );
		}

		$file = PAC_File::parse( $relative_path );
		if ( is_wp_error( $file ) ) {
			self::render_error( $file, $relative_path, 404 );
		}

		self::enqueue_assets( $file );
		self::render(
			array(
				'title'         => $file->title,
				'relative_path' => $file->relative_path,
				'body'          => $file->body,
				'error'         => null,
			),
			200
		);
	}

	/**
	 * Normalize a preview request path to a PAC file path.
	 *
	 * @param string $request_path Requested path from the preview URL.
	 * @return string|WP_Error Relative PAC file path.
	 */
	private static function normalize_request_path( $request_path ) {
		$request_path = trim( rawurldecode( (string) $request_path ), '/' );
		$request_path = str_replace( '\\', '/', $request_path );

		if ( '' === $request_path ) {
			return new WP_Error( 'pac_empty_preview_path', 'Preview path is empty.' );
		}

		if ( ! preg_match( '/\.html$/i', $request_path ) ) {
			$request_path .= '.html';
		}

		return $request_path;
	}

	/**
	 * Enqueue CSS and JS resolved from the PAC file.
	 *
	 * @param PAC_File $file Parsed PAC file.
	 */
	private static function enqueue_assets( PAC_File $file ) {
		$hash = substr( hash( 'sha256', $file->relative_path ), 0, 12 );

		self::enqueue_asset( 'pac-preview-' . $hash, $file->css_path, 'style' );
		self::enqueue_asset( 'pac-preview-' . $hash . '-js', $file->js_path, 'script' );
	}

	/**
	 * Enqueue a single preview asset.
	 *
	 * @param string      $handle Asset handle.
	 * @param string|null $path   Absolute asset path.
	 * @param string      $type   Asset type: style or script.
	 */
	private static function enqueue_asset( $handle, $path, $type ) {
		if ( empty( $path ) ) {
			return;
		}

		$relative = PAC_Asset_Path::to_relative( $path );
		$absolute = PAC_Asset_Path::to_absolute( $relative );
		$url      = PAC_Asset_Path::to_url( $relative );

		if ( '' === $url || ! is_readable( $absolute ) ) {
			return;
		}

		$version = (string) filemtime( $absolute );

		if ( 'script' === $type ) {
			wp_enqueue_script( $handle, $url, array(), $version, true );
			return;
		}

		wp_enqueue_style( $handle, $url, array(), $version );
	}

	/**
	 * Render an error response with the preview template.
	 *
	 * @param WP_Error $error         Error to display.
	 * @param string   $relative_path Relative path, if known.
	 * @param int      $status_code   HTTP status code.
	 */
	private static function render_error( WP_Error $error, $relative_path, $status_code ) {
		self::render(
			array(
				'title'         => 'PAC Preview',
				'relative_path' => $relative_path,
				'body'          => '',
				'error'         => $error,
			),
			$status_code
		);
	}

	/**
	 * Render the preview template.
	 *
	 * @param array $context     Template context.
	 * @param int   $status_code HTTP status code.
	 */
	private static function render( $context, $status_code ) {
		status_header( $status_code );
		nocache_headers();

		$pac_preview_title         = $context['title'];
		$pac_preview_relative_path = $context['relative_path'];
		$pac_preview_body          = $context['body'];
		$pac_preview_error         = $context['error'];

		include self::locate_template();
		exit;
	}

	/**
	 * Locate the preview template.
	 *
	 * Users can override the plugin template by creating preview.php at the
	 * root of the PAC pages directory.
	 *
	 * @return string Absolute template path.
	 */
	private static function locate_template() {
		$override = pac_pages_root() . '/preview.php';
		if ( is_readable( $override ) ) {
			return $override;
		}

		return PAC_PLUGIN_DIR . 'templates/preview.php';
	}
}
