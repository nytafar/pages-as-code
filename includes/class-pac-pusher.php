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

		return array(
			'status' => 'created',
			'id'     => $post_id,
			'slug'   => $file->slug,
			'title'  => $file->title,
			'file'   => $file->relative_path,
		);
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
			return array(
				'status' => 'unchanged',
				'id'     => $existing->ID,
				'slug'   => $file->slug,
				'title'  => $file->title,
				'file'   => $file->relative_path,
			);
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

		return array(
			'status' => 'updated',
			'id'     => $existing->ID,
			'slug'   => $file->slug,
			'title'  => $file->title,
			'file'   => $file->relative_path,
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

		// Write user-defined meta from front matter.
		foreach ( $file->meta as $key => $value ) {
			update_post_meta( $post_id, sanitize_key( $key ), sanitize_text_field( $value ) );
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
