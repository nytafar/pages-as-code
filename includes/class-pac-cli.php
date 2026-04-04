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
	 * Push a page file to WordPress.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : File path relative to the pages root (wp-content/pages/).
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
	 *     wp pac push landing/product-a.html
	 *     wp pac push about.html --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function push( $args, $assoc_args ) {
		// Capability check.
		if ( ! current_user_can( 'edit_pages' ) ) {
			WP_CLI::error( 'You do not have permission to edit pages.' );
		}

		$relative_path = $args[0];
		$format        = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'human' );

		// Parse the file.
		$file = PAC_File::parse( $relative_path );
		if ( is_wp_error( $file ) ) {
			$this->output_error( $file->get_error_message(), $relative_path, $format );
			return;
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
	 * Validate block markup in a page file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : File path relative to the pages root (wp-content/pages/).
	 *
	 * [--strict]
	 * : Treat warnings as fatal for exit code.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pac validate rite.html
	 *     wp pac validate landing/product-a.html --strict
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

		switch ( $result['status'] ) {
			case 'created':
				WP_CLI::success(
					sprintf(
						'Created page "%s" (ID %d, slug: %s).',
						$result['title'],
						$result['id'],
						$result['slug']
					)
				);
				break;

			case 'updated':
				WP_CLI::success(
					sprintf(
						'Updated page "%s" (ID %d, slug: %s).',
						$result['title'],
						$result['id'],
						$result['slug']
					)
				);
				break;

			case 'unchanged':
				WP_CLI::success(
					sprintf(
						'Page "%s" unchanged, skipping.',
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
	 * @param string $relative_path File path for JSON output.
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
