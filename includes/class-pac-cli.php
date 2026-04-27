<?php
/**
 * PAC_CLI — WP-CLI command for Pages as Code.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_CLI {

	/**
	 * Push a file to WordPress.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : File path relative to the pages root.
	 *
	 * [--post-type=<slug>]
	 * : Override the post type. Wins over the front-matter `type:` field when
	 * both are present. Defaults to `page` when neither is set.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: human
	 * options:
	 *   - human
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pac push about.html
	 *     wp pac push products/cacao-200g.html
	 *     wp pac push some/random/path/cacao.html --post-type=product
	 *     wp pac push about.html --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function push( $args, $assoc_args ) {
		$relative_path = $args[0];
		$format        = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'human' );
		$flag_type     = WP_CLI\Utils\get_flag_value( $assoc_args, 'post-type', null );

		// Parse the file (this also validates any front-matter `type:`).
		$file = PAC_File::parse( $relative_path );
		if ( is_wp_error( $file ) ) {
			$this->output_error( $file->get_error_message(), $relative_path, $format );
			return;
		}

		// Resolve final post type: --post-type flag > front-matter type: > 'page'.
		$post_type = pac_resolve_post_type( $file, $flag_type );
		if ( is_wp_error( $post_type ) ) {
			$this->output_error( $post_type->get_error_message(), $relative_path, $format );
			return;
		}
		$file->post_type = $post_type;

		// Capability check, scoped to the resolved post type.
		$cap = pac_post_type_capability( $post_type );
		if ( ! current_user_can( $cap ) ) {
			WP_CLI::error( sprintf( 'You do not have permission (%s) to push %s.', $cap, $post_type ) );
		}

		// Push to WordPress.
		$result = PAC_Pusher::push( $file );
		if ( is_wp_error( $result ) ) {
			$this->output_error( $result->get_error_message(), $relative_path, $format );
			return;
		}

		$this->output_result( $result, $format );
	}

	/**
	 * Validate block markup in a file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : File path relative to the pages root.
	 *
	 * [--strict]
	 * : Treat warnings as fatal for exit code.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pac validate rite.html
	 *     wp pac validate products/cacao-200g.html --strict
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function validate( $args, $assoc_args ) {
		$relative_path = $args[0];
		$strict        = WP_CLI\Utils\get_flag_value( $assoc_args, 'strict', false );

		// Parse the file (reuses PAC_File for path resolution and front matter extraction).
		$file = PAC_File::parse( $relative_path );
		if ( is_wp_error( $file ) ) {
			WP_CLI::line(
				wp_json_encode( array(
					'ok'      => false,
					'source'  => $relative_path,
					'summary' => array(
						'blocks'  => 0,
						'fatal'   => 1,
						'warning' => 0,
						'info'    => 0,
					),
					'issues'  => array(
						array(
							'severity'        => 'fatal',
							'path'            => '',
							'blockName'       => null,
							'rule'            => 'file_error',
							'message'         => $file->get_error_message(),
							'expected'        => null,
							'actual'          => null,
							'autoFixable'     => false,
							'suggestedRepair' => null,
						),
					),
				), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			);
			WP_CLI::halt( 1 );
			return;
		}

		// Run validation on the block body.
		$report           = PAC_Validator::validate_document( $file->body );
		$report['source'] = $relative_path;

		WP_CLI::line( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		// Exit code: 1 if fatal issues, or if --strict and warnings exist.
		if ( $report['summary']['fatal'] > 0 ) {
			WP_CLI::halt( 1 );
		} elseif ( $strict && $report['summary']['warning'] > 0 ) {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Pull a WordPress post to a local file.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Post slug. Accepts `<post_type>/<slug>` shorthand, e.g. `product/cacao-200g`.
	 * A bare slug defaults to post type `page`.
	 *
	 * [--post-type=<slug>]
	 * : Explicit post type. Wins over the path-style shorthand.
	 *
	 * [--dir=<dir>]
	 * : Subdirectory under the pages root to write the file into. Overrides
	 * the per-type default dir from the pac_post_types filter.
	 *
	 * [--force]
	 * : Overwrite existing file without prompting.
	 *
	 * [--revision-suffix]
	 * : Append revision ID to filename (e.g. about.r123.html).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: human
	 * options:
	 *   - human
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp pac pull about
	 *     wp pac pull product/cacao-200g
	 *     wp pac pull cacao-200g --post-type=product
	 *     wp pac pull about --dir=drafts/
	 *     wp pac pull about --revision-suffix
	 *     wp pac pull about --force --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function pull( $args, $assoc_args ) {
		$raw_slug  = $args[0];
		$format    = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'human' );
		$flag_type = WP_CLI\Utils\get_flag_value( $assoc_args, 'post-type', null );

		// Path-style shorthand: `<type>/<slug>` — only when no explicit flag.
		$shorthand_type = null;
		$slug           = $raw_slug;
		if ( null === $flag_type && false !== strpos( $raw_slug, '/' ) ) {
			list( $shorthand_type, $slug ) = explode( '/', $raw_slug, 2 );
		}

		// Resolve final post type: flag > shorthand > 'page'.
		$post_type = pac_resolve_post_type( $shorthand_type, $flag_type );
		if ( is_wp_error( $post_type ) ) {
			$this->output_error( $post_type->get_error_message(), $raw_slug, $format );
			return;
		}

		// Capability check, scoped to the resolved post type.
		$cap = pac_post_type_capability( $post_type );
		if ( ! current_user_can( $cap ) ) {
			WP_CLI::error( sprintf( 'You do not have permission (%s) to pull %s.', $cap, $post_type ) );
		}

		$options = array(
			'dir'             => WP_CLI\Utils\get_flag_value( $assoc_args, 'dir', null ),
			'force'           => WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ),
			'revision_suffix' => WP_CLI\Utils\get_flag_value( $assoc_args, 'revision-suffix', false ),
		);

		$result = PAC_Puller::pull( $slug, $post_type, $options );
		if ( is_wp_error( $result ) ) {
			$this->output_error( $result->get_error_message(), $raw_slug, $format );
			return;
		}

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}

		WP_CLI::success(
			sprintf(
				'Pulled %s "%s" to %s (revision %s).',
				$result['post_type'],
				$result['title'],
				$result['file'],
				$result['revision']
			)
		);
	}

	/**
	 * Output a successful result.
	 *
	 * @param array  $result Push result.
	 * @param string $format Output format.
	 */
	private function output_result( $result, $format ) {
		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $result ) );
			return;
		}

		$post_type = isset( $result['post_type'] ) ? $result['post_type'] : 'page';

		switch ( $result['status'] ) {
			case 'created':
				WP_CLI::success(
					sprintf(
						'Created %s "%s" (ID %d, slug: %s).',
						$post_type,
						$result['title'],
						$result['id'],
						$result['slug']
					)
				);
				break;

			case 'updated':
				WP_CLI::success(
					sprintf(
						'Updated %s "%s" (ID %d, slug: %s).',
						$post_type,
						$result['title'],
						$result['id'],
						$result['slug']
					)
				);
				break;

			case 'unchanged':
				WP_CLI::success(
					sprintf(
						'%s "%s" unchanged, skipping.',
						ucfirst( $post_type ),
						$result['title']
					)
				);
				break;
		}

		// Report resolved assets.
		if ( ! empty( $result['css'] ) ) {
			WP_CLI::log( sprintf( '  CSS: %s', $result['css'] ) );
		}
		if ( ! empty( $result['js'] ) ) {
			WP_CLI::log( sprintf( '  JS:  %s', $result['js'] ) );
		}
	}

	/**
	 * Output an error.
	 *
	 * @param string $message       Error message.
	 * @param string $relative_path File path or slug for JSON output.
	 * @param string $format        Output format.
	 */
	private function output_error( $message, $relative_path, $format ) {
		if ( 'json' === $format ) {
			WP_CLI::line(
				wp_json_encode( array(
					'status'  => 'error',
					'message' => $message,
					'file'    => $relative_path,
				) )
			);
			WP_CLI::halt( 1 );
			return;
		}

		WP_CLI::error( $message );
	}
}
