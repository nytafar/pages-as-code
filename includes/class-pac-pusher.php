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
	 * An existing post is overwritten only when its content still equals its baseline (see check_baseline()), or
	 * with `force`. A refusal writes nothing.
	 *
	 * @param PAC_File $file Parsed file.
	 * @param array    $args {
	 *     Optional.
	 *
	 *     @type bool $force Overwrite even when the post changed since its baseline.
	 * }
	 * @return array|WP_Error Result array with keys: status, id, slug, title, file, post_type.
	 */
	public static function push( PAC_File $file, $args = array() ) {
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
			return self::update_post( $existing, $file, $parent_id, ! empty( $args['force'] ) );
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
	 * @param bool     $force     Skip the baseline check.
	 * @return array|WP_Error
	 */
	private static function update_post( $existing, PAC_File $file, $parent_id, $force = false ) {
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

		if ( ! $force ) {
			$baseline = self::check_baseline( $existing, $file );
			if ( is_wp_error( $baseline ) ) {
				return $baseline;
			}
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
	 * SHA-256 of a post's stored content: what `_pac_content_hash` holds and `expect_hash:` declares.
	 *
	 * @param string $content Raw post_content.
	 * @return string 64 hex characters.
	 */
	public static function content_hash( $content ) {
		return hash( 'sha256', (string) $content );
	}

	/**
	 * Decide whether an existing post may be overwritten: its content must still equal its baseline.
	 *
	 * Accepted when the post's current content hash equals
	 *  1. the file's `expect_hash:` (written by `wp pac pull`, or recorded by whoever saw the content), or
	 *  2. `_pac_content_hash`, the content pac itself last saved.
	 * A post pushed before 1.11.0 has no `_pac_content_hash`: it is accepted when no revision is newer than its
	 * `_pac_last_push_gmt`. A post pac never pushed, with no matching `expect_hash:`, is refused.
	 *
	 * @param WP_Post  $post Existing post.
	 * @param PAC_File $file Parsed file.
	 * @return true|WP_Error
	 */
	public static function check_baseline( $post, PAC_File $file ) {
		$current = self::content_hash( $post->post_content );

		if ( '' !== $file->expect_hash && hash_equals( $file->expect_hash, $current ) ) {
			return true;
		}

		$label  = sprintf( '%s "%s" (ID %d)', $post->post_type, $post->post_name, $post->ID );
		$remedy = sprintf(
			'Pull it first: `wp pac pull %1$s%2$s --revision-suffix` writes a copy beside your file; bring its edits and its expect_hash: line into your file, then push again. To discard the edits on purpose: --force.',
			'page' === $post->post_type ? '' : $post->post_type . '/',
			$post->post_name
		);
		$data   = array(
			'id'           => $post->ID,
			'current_hash' => $current,
		);

		$pushed = (string) get_post_meta( $post->ID, '_pac_content_hash', true );
		if ( '' !== $pushed ) {
			if ( hash_equals( $pushed, $current ) ) {
				return true;
			}
			return new WP_Error(
				'pac_post_changed',
				sprintf( 'Refused: %s was changed after pac last pushed it, and the file does not declare its current content. %s', $label, $remedy ),
				$data
			);
		}

		$last_push = (string) get_post_meta( $post->ID, '_pac_last_push_gmt', true );
		if ( '' !== $last_push ) {
			$changed = self::last_change_gmt( $post );
			if ( strtotime( $changed . ' UTC' ) <= strtotime( $last_push ) ) {
				return true;
			}
			return new WP_Error(
				'pac_post_changed',
				sprintf( 'Refused: %s has a revision of %s UTC, newer than pac\'s last push (%s). %s', $label, $changed, $last_push, $remedy ),
				$data
			);
		}

		if ( '' !== $file->expect_hash ) {
			return new WP_Error(
				'pac_expect_hash_mismatch',
				sprintf( 'Refused: %s does not hold the content the file expects (expect_hash: differs). It changed since that hash was recorded. %s', $label, $remedy ),
				$data
			);
		}

		return new WP_Error(
			'pac_unmanaged_post',
			sprintf( 'Refused: %s exists and pac never pushed it, and the file declares no expect_hash: for its current content. %s', $label, $remedy ),
			$data
		);
	}

	/**
	 * When a post's content last changed: its newest revision, else its own modified time (revisions off).
	 *
	 * @param WP_Post $post Post.
	 * @return string GMT datetime, `Y-m-d H:i:s`.
	 */
	private static function last_change_gmt( $post ) {
		$revisions = wp_get_post_revisions(
			$post->ID,
			array(
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$latest    = $revisions ? reset( $revisions ) : null;
		return $latest ? $latest->post_modified_gmt : $post->post_modified_gmt;
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
		// The content as WordPress stored it (kses or filters may differ from the file body): the next push's baseline.
		update_post_meta( $post_id, '_pac_content_hash', self::content_hash( get_post_field( 'post_content', $post_id, 'raw' ) ) );

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
	public static function find_post_by_slug( $slug, $post_type ) {
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
