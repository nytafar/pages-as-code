<?php
/**
 * PAC_Assets — Enqueue page-specific CSS/JS on frontend and CSS in block editor.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_Assets {

	/**
	 * Register WordPress hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
	}

	/**
	 * Enqueue CSS and JS for a PAC-managed page on the frontend.
	 */
	public static function enqueue_frontend() {
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || '1' !== get_post_meta( $post_id, '_pac_managed', true ) ) {
			return;
		}

		self::enqueue_css( $post_id, 'frontend' );
		self::enqueue_js( $post_id );
	}

	/**
	 * Enqueue CSS for a PAC-managed page in the block editor.
	 *
	 * JS is intentionally excluded from the editor for MVP — frontend scripts
	 * often rely on DOM state that doesn't exist inside the editor iframe.
	 */
	public static function enqueue_editor() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}

		$post_id = self::get_editor_post_id();
		if ( ! $post_id || '1' !== get_post_meta( $post_id, '_pac_managed', true ) ) {
			return;
		}

		self::enqueue_css( $post_id, 'editor' );
	}

	/**
	 * Enqueue page-specific CSS.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $context 'frontend' or 'editor'.
	 */
	private static function enqueue_css( $post_id, $context ) {
		$css_path = get_post_meta( $post_id, '_pac_css', true );
		if ( empty( $css_path ) || ! is_readable( $css_path ) ) {
			return;
		}

		$url = self::path_to_url( $css_path );
		if ( ! $url ) {
			return;
		}

		$handle  = 'pac-page-' . $post_id;
		$version = file_exists( $css_path ) ? (string) filemtime( $css_path ) : PAC_VERSION;

		wp_enqueue_style( $handle, $url, array(), $version );
	}

	/**
	 * Enqueue page-specific JS (frontend only).
	 *
	 * @param int $post_id Post ID.
	 */
	private static function enqueue_js( $post_id ) {
		$js_path = get_post_meta( $post_id, '_pac_js', true );
		if ( empty( $js_path ) || ! is_readable( $js_path ) ) {
			return;
		}

		$url = self::path_to_url( $js_path );
		if ( ! $url ) {
			return;
		}

		$handle  = 'pac-page-' . $post_id . '-js';
		$version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : PAC_VERSION;

		wp_enqueue_script( $handle, $url, array(), $version, true );
	}

	/**
	 * Convert an absolute filesystem path to a URL.
	 *
	 * Supports custom WP_CONTENT_DIR / WP_CONTENT_URL setups.
	 *
	 * @param string $path Absolute file path.
	 * @return string|false URL or false if path is outside WP_CONTENT_DIR.
	 */
	private static function path_to_url( $path ) {
		$content_dir = realpath( WP_CONTENT_DIR );
		$real_path   = realpath( $path );

		if ( false === $content_dir || false === $real_path ) {
			return false;
		}

		if ( 0 !== strpos( $real_path, $content_dir ) ) {
			return false;
		}

		$relative = substr( $real_path, strlen( $content_dir ) );
		return WP_CONTENT_URL . $relative;
	}

	/**
	 * Get the post ID being edited in the block editor.
	 *
	 * @return int|false Post ID or false.
	 */
	private static function get_editor_post_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['post'] ) ) {
			return absint( $_GET['post'] );
		}
		return false;
	}
}
