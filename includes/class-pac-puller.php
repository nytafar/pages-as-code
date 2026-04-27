<?php
/**
 * PAC_Puller — Extract WordPress posts into .html files with front matter.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_Puller {

	/**
	 * Pull a post by slug and write it to a file.
	 *
	 * @param string $slug      Post slug.
	 * @param string $post_type Post-type slug (defaults to 'page').
	 * @param array  $options   {
	 *     Optional. Pull options.
	 *
	 *     @type string $dir             Subdirectory under pages root. Overrides
	 *                                   the per-type default dir from
	 *                                   pac_post_type_config().
	 *     @type bool   $force           Overwrite existing file.
	 *     @type bool   $revision_suffix Append revision ID to filename.
	 * }
	 * @return array|WP_Error Result array or error.
	 */
	public static function pull( $slug, $post_type = 'page', $options = array() ) {
		$post = self::find_post_by_slug( $slug, $post_type );
		if ( ! $post ) {
			return new WP_Error(
				'pac_post_not_found',
				sprintf( 'No %s found with slug "%s".', $post_type, $slug )
			);
		}

		$revision_id = self::get_revision_id( $post->ID );
		$fields      = self::extract_fields( $post, $revision_id );
		$content     = PAC_Serializer::serialize( $fields, $post->post_content );

		// Resolve output path.
		$output = self::resolve_output_path( $post->post_name, $post_type, $options, $revision_id );
		if ( is_wp_error( $output ) ) {
			return $output;
		}

		// Ensure directory exists.
		$dir = dirname( $output['full_path'] );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Write file.
		$written = file_put_contents( $output['full_path'], $content );
		if ( false === $written ) {
			return new WP_Error(
				'pac_write_error',
				sprintf( 'Cannot write file: %s', $output['relative_path'] )
			);
		}

		// Write pull tracking meta.
		update_post_meta( $post->ID, '_pac_pulled_revision', $revision_id );
		update_post_meta( $post->ID, '_pac_pulled_gmt', gmdate( 'c' ) );

		return array(
			'status'    => 'pulled',
			'file'      => $output['relative_path'],
			'slug'      => $post->post_name,
			'title'     => $post->post_title,
			'id'        => $post->ID,
			'post_type' => $post->post_type,
			'revision'  => $revision_id,
		);
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
	 * Get the latest revision ID for a post.
	 *
	 * Falls back to post_modified_gmt as a string if revisions are disabled.
	 *
	 * @param int $post_id Post ID.
	 * @return int|string Revision post ID or post_modified_gmt.
	 */
	private static function get_revision_id( $post_id ) {
		$revisions = wp_get_post_revisions( $post_id, array(
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		if ( ! empty( $revisions ) ) {
			$latest = reset( $revisions );
			return $latest->ID;
		}

		// Revisions disabled or none exist — fall back to modified timestamp.
		$post = get_post( $post_id );
		return $post->post_modified_gmt;
	}

	/**
	 * Build front matter fields array from a WP_Post.
	 *
	 * @param WP_Post    $post        Post object.
	 * @param int|string $revision_id Revision ID or modified timestamp.
	 * @return array Ordered associative array of front matter fields.
	 */
	private static function extract_fields( $post, $revision_id ) {
		$fields = array();

		// Core fields (always present).
		$fields['title']  = $post->post_title;
		$fields['slug']   = $post->post_name;

		// Emit `type:` only when the post type isn't the default ('page').
		// Keeps existing pulled-page files visually unchanged.
		if ( 'page' !== $post->post_type ) {
			$fields['type'] = $post->post_type;
		}

		$fields['status'] = $post->post_status;

		// Template (page only, only if not default).
		if ( 'page' === $post->post_type ) {
			$template = get_post_meta( $post->ID, '_wp_page_template', true );
			if ( ! empty( $template ) && 'default' !== $template ) {
				$fields['template'] = $template;
			}
		}

		// Parent (page only — non-pages don't roundtrip hierarchy in this pass).
		if ( 'page' === $post->post_type && $post->post_parent > 0 ) {
			$parent_slug = self::resolve_parent_slug( $post->post_parent, $post->post_type );
			if ( null !== $parent_slug ) {
				$fields['parent'] = $parent_slug;
			}
		}

		// Asset references (only if files exist on disk).
		$css_path = get_post_meta( $post->ID, '_pac_css', true );
		if ( ! empty( $css_path ) && file_exists( $css_path ) ) {
			// Convert absolute path to relative (from wp-content/).
			$relative      = str_replace( WP_CONTENT_DIR . '/', '', $css_path );
			$fields['css'] = $relative;
		}

		$js_path = get_post_meta( $post->ID, '_pac_js', true );
		if ( ! empty( $js_path ) && file_exists( $js_path ) ) {
			$relative     = str_replace( WP_CONTENT_DIR . '/', '', $js_path );
			$fields['js'] = $relative;
		}

		// User-defined meta (round-trip from push).
		$meta_keys = get_post_meta( $post->ID, '_pac_meta_keys', true );
		if ( ! empty( $meta_keys ) && is_array( $meta_keys ) ) {
			$meta = array();
			foreach ( $meta_keys as $key ) {
				$value = get_post_meta( $post->ID, $key, true );
				if ( '' !== $value ) {
					$meta[ $key ] = $value;
				}
			}
			if ( ! empty( $meta ) ) {
				$fields['meta'] = $meta;
			}
		}

		// Pull tracking fields.
		$fields['pulled_revision'] = $revision_id;
		$fields['pulled_gmt']      = gmdate( 'c' );

		return $fields;
	}

	/**
	 * Resolve parent post ID to its slug, scoped to a post type.
	 *
	 * @param int    $parent_id Parent post ID.
	 * @param string $post_type Post-type slug the parent must match.
	 * @return string|null Parent slug or null if not found / wrong type.
	 */
	private static function resolve_parent_slug( $parent_id, $post_type ) {
		$parent = get_post( $parent_id );
		if ( ! $parent || $parent->post_type !== $post_type ) {
			return null;
		}
		return $parent->post_name;
	}

	/**
	 * Resolve the output file path with collision handling.
	 *
	 * @param string     $slug        Post slug.
	 * @param string     $post_type   Post-type slug.
	 * @param array      $options     Pull options.
	 * @param int|string $revision_id Revision ID for suffix mode.
	 * @return array|WP_Error Array with full_path and relative_path, or error.
	 */
	private static function resolve_output_path( $slug, $post_type, $options, $revision_id ) {
		// Per-type default dir; --dir flag overrides; bare 'page' lands at root.
		$dir = isset( $options['dir'] ) ? trim( (string) $options['dir'], '/' ) : null;
		if ( null === $dir ) {
			$config = pac_post_type_config( $post_type );
			if ( is_array( $config ) && ! empty( $config['dir'] ) ) {
				$dir = trim( (string) $config['dir'], '/' );
			} elseif ( 'page' === $post_type ) {
				$dir = '';
			} else {
				$dir = $post_type;
			}
		}

		$force           = ! empty( $options['force'] );
		$revision_suffix = ! empty( $options['revision_suffix'] );

		// Build filename.
		if ( $revision_suffix ) {
			$filename = $slug . '.r' . $revision_id . '.html';
		} else {
			$filename = $slug . '.html';
		}

		// Build paths.
		$pages_root    = pac_pages_root();
		$relative_path = '' !== $dir ? $dir . '/' . $filename : $filename;
		$full_path     = $pages_root . '/' . $relative_path;
		$root_real     = realpath( $pages_root );
		$target_dir    = dirname( $full_path );
		$target_real   = realpath( $target_dir );

		// Reject directory traversal in output path.
		if ( false === $root_real ) {
			return new WP_Error( 'pac_path_error', 'Managed pages root is not available.' );
		}
		if ( false !== $target_real && $target_real !== $root_real && 0 !== strpos( $target_real . '/', $root_real . '/' ) ) {
			return new WP_Error(
				'pac_path_traversal',
				sprintf( 'Output path outside managed root: %s', $relative_path )
			);
		}
		if ( false === $target_real && $root_real !== $target_dir && 0 !== strpos( $target_dir, $root_real . '/' ) ) {
			return new WP_Error(
				'pac_path_traversal',
				sprintf( 'Output path outside managed root: %s', $relative_path )
			);
		}

		// Collision check.
		if ( file_exists( $full_path ) && ! $force ) {
			return new WP_Error(
				'pac_file_exists',
				sprintf(
					'File already exists: %s. Use --force to overwrite or --revision-suffix for a versioned copy.',
					$relative_path
				)
			);
		}

		return array(
			'full_path'     => $full_path,
			'relative_path' => $relative_path,
		);
	}
}
