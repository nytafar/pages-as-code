<?php
/**
 * PAC_Pusher — Create or update WordPress posts from parsed PAC_File objects.
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
	 * @param PAC_File $file Parsed file.
	 * @return array|WP_Error Result array with keys: status, id, slug, title, file, post_type.
	 */
	public static function push( PAC_File $file ) {
		// Resolve parent if specified (looked up within the same post type).
		$parent_id = 0;
		if ( ! empty( $file->parent ) ) {
			$parent_id = self::resolve_parent( $file->parent, $file->post_type );
			if ( is_wp_error( $parent_id ) ) {
				return $parent_id;
			}
		}

		// Look up existing post by slug within the file's post type.
		$existing = self::find_post_by_slug( $file->slug, $file->post_type );

		if ( $existing ) {
			return self::update_post( $existing, $file, $parent_id );
		}

		return self::create_post( $file, $parent_id );
	}

	/**
	 * Create a new post.
	 *
	 * @param PAC_File $file      Parsed file.
	 * @param int      $parent_id Parent post ID (page hierarchy only).
	 * @return array|WP_Error
	 */
	private static function create_post( PAC_File $file, $parent_id ) {
		$post_data = array(
			'post_type'    => $file->post_type,
			'post_title'   => $file->title,
			'post_name'    => $file->slug,
			'post_content' => $file->body,
			'post_status'  => $file->status,
		);

		// Hierarchy and templates are page-specific.
		if ( 'page' === $file->post_type ) {
			$post_data['post_parent'] = $parent_id;
			if ( ! empty( $file->template ) ) {
				$post_data['page_template'] = $file->template;
			}
		}

		// wp_insert_post() expects form-style escaped data and runs wp_unslash()
		// on every field. PAC reads its content straight from disk, so we slash
		// it first to compensate — otherwise sequences like < (in block-
		// comment JSON) get unslashed to a bare "u003c", breaking inline HTML.
		$post_id = wp_insert_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::write_meta( $post_id, $file );

		return array(
			'status'    => 'created',
			'id'        => $post_id,
			'slug'      => $file->slug,
			'title'     => $file->title,
			'post_type' => $file->post_type,
			'file'      => $file->relative_path,
			'css'       => $file->css_path,
			'js'        => $file->js_path,
		);
	}

	/**
	 * Update an existing post or skip if unchanged.
	 *
	 * @param WP_Post  $existing  Existing post object.
	 * @param PAC_File $file      Parsed file.
	 * @param int      $parent_id Parent post ID (page hierarchy only).
	 * @return array|WP_Error
	 */
	private static function update_post( $existing, PAC_File $file, $parent_id ) {
		$stored_hash = get_post_meta( $existing->ID, '_pac_hash', true );

		if ( $stored_hash === $file->hash ) {
			return array(
				'status'    => 'unchanged',
				'id'        => $existing->ID,
				'slug'      => $file->slug,
				'title'     => $file->title,
				'post_type' => $file->post_type,
				'file'      => $file->relative_path,
				'css'       => $file->css_path,
				'js'        => $file->js_path,
			);
		}

		$post_data = array(
			'ID'           => $existing->ID,
			'post_title'   => $file->title,
			'post_name'    => $file->slug,
			'post_content' => $file->body,
			'post_status'  => $file->status,
		);

		// Hierarchy and templates are page-specific.
		if ( 'page' === $file->post_type ) {
			$post_data['post_parent'] = $parent_id;
			if ( ! empty( $file->template ) ) {
				$post_data['page_template'] = $file->template;
			}
		}

		// wp_update_post() expects form-style escaped data and runs wp_unslash()
		// on every field. PAC reads its content straight from disk, so we slash
		// it first to compensate — otherwise sequences like < (in block-
		// comment JSON) get unslashed to a bare "u003c", breaking inline HTML.
		$result = wp_update_post( wp_slash( $post_data ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::write_meta( $existing->ID, $file );

		return array(
			'status'    => 'updated',
			'id'        => $existing->ID,
			'slug'      => $file->slug,
			'title'     => $file->title,
			'post_type' => $file->post_type,
			'file'      => $file->relative_path,
			'css'       => $file->css_path,
			'js'        => $file->js_path,
		);
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
			update_post_meta( $post_id, sanitize_key( $key ), sanitize_text_field( $value ) );
		}

		// Store meta key list for pull round-trip.
		if ( ! empty( $file->meta ) ) {
			$meta_keys = array_map( 'sanitize_key', array_keys( $file->meta ) );
			update_post_meta( $post_id, '_pac_meta_keys', $meta_keys );
		} else {
			delete_post_meta( $post_id, '_pac_meta_keys' );
		}
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
			update_post_meta( $post_id, $path_key, PAC_Asset_Path::to_relative( $path ) );
			update_post_meta( $post_id, $hash_key, $hash );
		} else {
			delete_post_meta( $post_id, $path_key );
			delete_post_meta( $post_id, $hash_key );
		}
	}

	/**
	 * Find a post by its slug within a given post type.
	 *
	 * @param string $slug      Post name (slug).
	 * @param string $post_type Post-type slug.
	 * @return WP_Post|null
	 */
	private static function find_post_by_slug( $slug, $post_type ) {
		$query = new WP_Query( array(
			'post_type'              => $post_type,
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
	 * Resolve a parent slug to its ID, scoped to a single post type.
	 *
	 * @param string $slug      Parent slug.
	 * @param string $post_type Post-type slug to look the parent up in.
	 * @return int|WP_Error Parent ID or error.
	 */
	private static function resolve_parent( $slug, $post_type ) {
		$parent = self::find_post_by_slug( $slug, $post_type );
		if ( ! $parent ) {
			return new WP_Error(
				'pac_parent_not_found',
				sprintf( 'Parent "%s" not found in post type "%s".', $slug, $post_type )
			);
		}
		return $parent->ID;
	}
}
