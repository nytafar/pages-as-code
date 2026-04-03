<?php
/**
 * PAC_Pusher — Create or update WordPress pages from parsed PAC_File objects.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_Pusher {

	/**
	 * Push a parsed file to WordPress.
	 *
	 * @param PAC_File $file Parsed page file.
	 * @return array|WP_Error Result array with keys: status, id, slug, title, file.
	 */
	public static function push( PAC_File $file ) {
		// Resolve parent if specified.
		$parent_id = 0;
		if ( ! empty( $file->parent ) ) {
			$parent_id = self::resolve_parent( $file->parent );
			if ( is_wp_error( $parent_id ) ) {
				return $parent_id;
			}
		}

		// Look up existing page by slug.
		$existing = self::find_page_by_slug( $file->slug );

		if ( $existing ) {
			return self::update_page( $existing, $file, $parent_id );
		}

		return self::create_page( $file, $parent_id );
	}

	/**
	 * Create a new page.
	 *
	 * @param PAC_File $file      Parsed file.
	 * @param int      $parent_id Parent page ID.
	 * @return array|WP_Error
	 */
	private static function create_page( PAC_File $file, $parent_id ) {
		$post_data = array(
			'post_type'    => 'page',
			'post_title'   => $file->title,
			'post_name'    => $file->slug,
			'post_content' => $file->body,
			'post_status'  => $file->status,
			'post_parent'  => $parent_id,
		);

		if ( ! empty( $file->template ) ) {
			$post_data['page_template'] = $file->template;
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::write_meta( $post_id, $file );

		return self::build_result( 'created', $post_id, $file );
	}

	/**
	 * Update an existing page or skip if unchanged.
	 *
	 * @param WP_Post  $existing  Existing page post object.
	 * @param PAC_File $file      Parsed file.
	 * @param int      $parent_id Parent page ID.
	 * @return array|WP_Error
	 */
	private static function update_page( $existing, PAC_File $file, $parent_id ) {
		$stored_hash = get_post_meta( $existing->ID, '_pac_hash', true );

		if ( $stored_hash === $file->hash ) {
			if ( self::has_asset_changes( $existing->ID, $file ) ) {
				self::write_meta( $existing->ID, $file );
				return self::build_result( 'updated', $existing->ID, $file );
			}

			return self::build_result( 'unchanged', $existing->ID, $file );
		}

		$post_data = array(
			'ID'           => $existing->ID,
			'post_title'   => $file->title,
			'post_name'    => $file->slug,
			'post_content' => $file->body,
			'post_status'  => $file->status,
			'post_parent'  => $parent_id,
		);

		if ( ! empty( $file->template ) ) {
			$post_data['page_template'] = $file->template;
		}

		$result = wp_update_post( $post_data, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::write_meta( $existing->ID, $file );

		return self::build_result( 'updated', $existing->ID, $file );
	}

	/**
	 * Write plugin tracking meta and user-defined meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param PAC_File $file    Parsed file.
	 */
	private static function write_meta( $post_id, PAC_File $file ) {
		update_post_meta( $post_id, '_pac_managed', '1' );
		update_post_meta( $post_id, '_pac_source', $file->relative_path );
		update_post_meta( $post_id, '_pac_hash', $file->hash );
		update_post_meta( $post_id, '_pac_last_push_gmt', gmdate( 'c' ) );

		// Asset meta: store path or clear if absent.
		self::write_asset_meta( $post_id, '_pac_css', '_pac_css_hash', $file->css_path, $file->css_hash );
		self::write_asset_meta( $post_id, '_pac_js', '_pac_js_hash', $file->js_path, $file->js_hash );

		// Write user-defined meta from front matter.
		foreach ( $file->meta as $key => $value ) {
			$meta_key = self::sanitize_front_matter_meta_key( $key );
			if ( ! $meta_key ) {
				continue;
			}

			update_post_meta( $post_id, $meta_key, sanitize_text_field( $value ) );
		}
	}

	/**
	 * Build a standardized push result payload.
	 *
	 * @param string   $status  Result status.
	 * @param int      $post_id Post ID.
	 * @param PAC_File $file    Parsed file.
	 * @return array
	 */
	private static function build_result( $status, $post_id, PAC_File $file ) {
		return array(
			'status' => $status,
			'id'     => $post_id,
			'slug'   => $file->slug,
			'title'  => $file->title,
			'file'   => $file->relative_path,
			'css'    => $file->css_path,
			'js'     => $file->js_path,
		);
	}

	/**
	 * Determine whether resolved asset state differs from stored post meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param PAC_File $file    Parsed file.
	 * @return bool True when CSS or JS path/hash metadata has changed.
	 */
	private static function has_asset_changes( $post_id, PAC_File $file ) {
		return self::asset_meta_has_changed( $post_id, '_pac_css', '_pac_css_hash', $file->css_path, $file->css_hash )
			|| self::asset_meta_has_changed( $post_id, '_pac_js', '_pac_js_hash', $file->js_path, $file->js_hash );
	}

	/**
	 * Determine whether a specific asset meta pair differs from stored values.
	 *
	 * @param int         $post_id  Post ID.
	 * @param string      $path_key Meta key for the path.
	 * @param string      $hash_key Meta key for the hash.
	 * @param string|null $path     Resolved asset path, or null.
	 * @param string|null $hash     Resolved asset hash, or null.
	 * @return bool True when the stored values differ.
	 */
	private static function asset_meta_has_changed( $post_id, $path_key, $hash_key, $path, $hash ) {
		$stored_path = get_post_meta( $post_id, $path_key, true );
		$stored_hash = get_post_meta( $post_id, $hash_key, true );

		if ( null === $path ) {
			return '' !== $stored_path || '' !== $stored_hash;
		}

		return $stored_path !== $path || $stored_hash !== $hash;
	}

	/**
	 * Validate and sanitize a front matter meta key before writing post meta.
	 *
	 * Protected meta keys are skipped to avoid overwriting WordPress or plugin internals.
	 *
	 * @param string $key Raw front matter meta key.
	 * @return string|false Sanitized meta key or false when the key is not allowed.
	 */
	private static function sanitize_front_matter_meta_key( $key ) {
		$meta_key = sanitize_key( $key );
		if ( empty( $meta_key ) || is_protected_meta( $meta_key, 'post' ) ) {
			return false;
		}

		return $meta_key;
	}

	/**
	 * Write or clear an asset path and hash in post meta.
	 *
	 * @param int         $post_id  Post ID.
	 * @param string      $path_key Meta key for the path.
	 * @param string      $hash_key Meta key for the hash.
	 * @param string|null $path     Resolved asset path, or null.
	 * @param string|null $hash     Asset hash, or null.
	 */
	private static function write_asset_meta( $post_id, $path_key, $hash_key, $path, $hash ) {
		if ( null !== $path ) {
			update_post_meta( $post_id, $path_key, $path );
			update_post_meta( $post_id, $hash_key, $hash );
		} else {
			delete_post_meta( $post_id, $path_key );
			delete_post_meta( $post_id, $hash_key );
		}
	}

	/**
	 * Find a page by its slug.
	 *
	 * @param string $slug Post name (slug).
	 * @return WP_Post|null
	 */
	private static function find_page_by_slug( $slug ) {
		$query = new WP_Query( array(
			'post_type'              => 'page',
			'name'                   => $slug,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return null;
	}

	/**
	 * Resolve a parent page slug to its ID.
	 *
	 * @param string $slug Parent page slug.
	 * @return int|WP_Error Parent ID or error.
	 */
	private static function resolve_parent( $slug ) {
		$parent = self::find_page_by_slug( $slug );
		if ( ! $parent ) {
			return new WP_Error(
				'pac_parent_not_found',
				sprintf( 'Parent page "%s" not found.', $slug )
			);
		}
		return $parent->ID;
	}
}
