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
	 * Enqueue CSS and JS for a PAC-managed post on the frontend.
	 */
	public static function enqueue_frontend() {
		$managed_types = array_keys( pac_post_types() );
		if ( ! is_singular( $managed_types ) ) {
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
	 * Enqueue CSS for a PAC-managed post in the block editor.
	 *
	 * JS is intentionally excluded from the editor for MVP — frontend scripts
	 * often rely on DOM state that doesn't exist inside the editor iframe.
	 */
	public static function enqueue_editor() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$managed_types = array_keys( pac_post_types() );
		if ( ! in_array( $screen->post_type, $managed_types, true ) ) {
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
		$stored = get_post_meta( $post_id, '_pac_css', true );
		if ( empty( $stored ) ) {
			return;
		}

		$absolute = PAC_Asset_Path::to_absolute( $stored );
		if ( ! is_readable( $absolute ) ) {
			return;
		}

		$url = PAC_Asset_Path::to_url( $stored );
		if ( '' === $url ) {
			return;
		}

		$handle  = 'pac-page-' . $post_id;
		$version = (string) filemtime( $absolute );

		wp_enqueue_style( $handle, $url, array(), $version );
	}

	/**
	 * Enqueue page-specific JS (frontend only).
	 *
	 * @param int $post_id Post ID.
	 */
	private static function enqueue_js( $post_id ) {
		$stored = get_post_meta( $post_id, '_pac_js', true );
		if ( empty( $stored ) ) {
			return;
		}

		$absolute = PAC_Asset_Path::to_absolute( $stored );
		if ( ! is_readable( $absolute ) ) {
			return;
		}

		$url = PAC_Asset_Path::to_url( $stored );
		if ( '' === $url ) {
			return;
		}

		$handle  = 'pac-page-' . $post_id . '-js';
		$version = (string) filemtime( $absolute );

		wp_enqueue_script( $handle, $url, array(), $version, true );
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
