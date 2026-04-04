<?php
/**
 * PAC_File — Parse page files with YAML front matter and Gutenberg block markup.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_File {

	/** @var string Resolved slug. */
	public $slug;

	/** @var string Page title. */
	public $title;

	/** @var string Post status. */
	public $status = 'draft';

	/** @var string Page template. */
	public $template = '';

	/** @var string Parent page slug. */
	public $parent = '';

	/** @var array Post meta from front matter. */
	public $meta = array();

	/** @var string Block markup body. */
	public $body = '';

	/** @var string SHA-256 hash of the entire file. */
	public $hash = '';

	/** @var string Relative file path. */
	public $relative_path = '';

	/** @var string|null Resolved CSS asset path (absolute). */
	public $css_path = null;

	/** @var string|null Resolved JS asset path (absolute). */
	public $js_path = null;

	/** @var string|null SHA-256 hash of CSS file. */
	public $css_hash = null;

	/** @var string|null SHA-256 hash of JS file. */
	public $js_hash = null;

	/**
	 * Parse a page file.
	 *
	 * @param string $relative_path File path relative to the pages root.
	 * @return PAC_File|WP_Error Parsed file object or error.
	 */
	public static function parse( $relative_path ) {
		// Resolve and validate path.
		$full_path = self::resolve_path( $relative_path );
		if ( is_wp_error( $full_path ) ) {
			return $full_path;
		}

		if ( ! file_exists( $full_path ) ) {
			return new WP_Error( 'pac_file_not_found', sprintf( 'File not found: %s', $relative_path ) );
		}

		$raw = file_get_contents( $full_path );
		if ( false === $raw ) {
			return new WP_Error( 'pac_file_read_error', sprintf( 'Cannot read file: %s', $relative_path ) );
		}

		// Split front matter and body.
		$parts = self::split_front_matter( $raw );
		if ( is_wp_error( $parts ) ) {
			return $parts;
		}

		$front_matter = $parts['front_matter'];
		$body         = $parts['body'];

		// Parse YAML front matter.
		$yaml = self::parse_yaml( $front_matter );
		if ( is_wp_error( $yaml ) ) {
			return new WP_Error(
				'pac_front_matter_error',
				sprintf( 'Front matter parse error in %s: %s', $relative_path, $yaml->get_error_message() )
			);
		}

		// Validate required fields.
		if ( empty( $yaml['title'] ) ) {
			return new WP_Error(
				'pac_missing_title',
				sprintf( 'Front matter parse error in %s: missing title field.', $relative_path )
			);
		}

		// Build PAC_File instance.
		$file                = new self();
		$file->relative_path = $relative_path;
		$file->title         = (string) $yaml['title'];
		$file->body          = $body;
		$file->hash          = hash( 'sha256', $raw );

		// Slug resolution: front matter > filename.
		if ( ! empty( $yaml['slug'] ) ) {
			$file->slug = sanitize_title( (string) $yaml['slug'] );
		} else {
			// Strip .rNNN revision suffix before sanitizing (e.g. about.r123.html → about).
			$filename   = pathinfo( $relative_path, PATHINFO_FILENAME );
			$filename   = preg_replace( '/\.r\d+$/', '', $filename );
			$file->slug = sanitize_title( $filename );
		}

		if ( empty( $file->slug ) ) {
			return new WP_Error( 'pac_invalid_slug', sprintf( 'Cannot derive a valid slug from %s.', $relative_path ) );
		}

		// Optional fields.
		if ( ! empty( $yaml['status'] ) ) {
			$file->status = sanitize_key( (string) $yaml['status'] );
		}
		if ( ! empty( $yaml['template'] ) ) {
			$file->template = (string) $yaml['template'];
		}
		if ( ! empty( $yaml['parent'] ) ) {
			$file->parent = sanitize_title( (string) $yaml['parent'] );
		}
		if ( ! empty( $yaml['meta'] ) && is_array( $yaml['meta'] ) ) {
			$file->meta = $yaml['meta'];
		}

		// Resolve sibling CSS/JS assets.
		$full_path = PAC_PAGES_ROOT . '/' . $relative_path;
		$css_front = isset( $yaml['css'] ) ? (string) $yaml['css'] : null;
		$js_front  = isset( $yaml['js'] ) ? (string) $yaml['js'] : null;

		$file->css_path = self::resolve_asset( $full_path, 'css', $css_front );
		$file->js_path  = self::resolve_asset( $full_path, 'js', $js_front );

		if ( null !== $file->css_path ) {
			$file->css_hash = hash_file( 'sha256', $file->css_path );
		}
		if ( null !== $file->js_path ) {
			$file->js_hash = hash_file( 'sha256', $file->js_path );
		}

		return $file;
	}

	/**
	 * Resolve an asset (CSS or JS) for a page file.
	 *
	 * Resolution order:
	 * 1. Front matter explicit path (relative to WP_CONTENT_DIR)
	 * 2. Sibling file with same basename: about.html -> about.css
	 * 3. Shared directory: pages/css/about.css or pages/js/about.js
	 *
	 * @param string      $page_path Full path to the .html page file.
	 * @param string      $ext       Asset extension: 'css' or 'js'.
	 * @param string|null $front     Front matter override path (relative to WP_CONTENT_DIR), or null.
	 * @return string|null Validated absolute path, or null if no asset found.
	 */
	public static function resolve_asset( $page_path, $ext, $front = null ) {
		// 1. Front matter explicit path.
		if ( null !== $front && '' !== $front ) {
			$candidate = WP_CONTENT_DIR . '/' . ltrim( $front, '/' );
			if ( self::validate_asset_path( $candidate ) ) {
				return $candidate;
			}
			return null;
		}

		$basename = pathinfo( $page_path, PATHINFO_FILENAME );
		$dir      = dirname( $page_path );

		// 2. Sibling file.
		$sibling = $dir . '/' . $basename . '.' . $ext;
		if ( self::validate_asset_path( $sibling ) ) {
			return $sibling;
		}

		// 3. Shared directory under pages root.
		$shared = PAC_PAGES_ROOT . '/' . $ext . '/' . $basename . '.' . $ext;
		if ( self::validate_asset_path( $shared ) ) {
			return $shared;
		}

		return null;
	}

	/**
	 * Validate that an asset path exists and is safely under WP_CONTENT_DIR.
	 *
	 * @param string $path Absolute path to validate.
	 * @return bool True if the file exists and is within WP_CONTENT_DIR.
	 */
	public static function validate_asset_path( $path ) {
		$real = realpath( $path );
		if ( false === $real ) {
			return false;
		}
		if ( ! is_file( $real ) || ! is_readable( $real ) ) {
			return false;
		}
		$content_real = realpath( WP_CONTENT_DIR );
		if ( false === $content_real ) {
			return false;
		}
		return 0 === strpos( $real . '/', $content_real . '/' ) || $real === $content_real;
	}

	/**
	 * Resolve a relative path inside the pages root and validate it.
	 *
	 * @param string $relative_path Relative file path.
	 * @return string|WP_Error Full path or error.
	 */
	private static function resolve_path( $relative_path ) {
		// Reject empty paths.
		if ( empty( $relative_path ) ) {
			return new WP_Error( 'pac_empty_path', 'File path is empty.' );
		}

		$full_path = PAC_PAGES_ROOT . '/' . $relative_path;
		$real_path = realpath( $full_path );

		// If file doesn't exist, realpath returns false. Check with dirname.
		if ( false === $real_path ) {
			$dir_real = realpath( dirname( $full_path ) );
			$root_real = realpath( PAC_PAGES_ROOT );
			if ( false === $root_real || false === $dir_real || 0 !== strpos( $dir_real . '/', $root_real . '/' ) ) {
				return new WP_Error(
					'pac_path_traversal',
					sprintf( 'Path outside managed root: %s', $relative_path )
				);
			}
			return $full_path;
		}

		$root_real = realpath( PAC_PAGES_ROOT );
		if ( false === $root_real || 0 !== strpos( $real_path . '/', $root_real . '/' ) ) {
			return new WP_Error(
				'pac_path_traversal',
				sprintf( 'Path outside managed root: %s', $relative_path )
			);
		}

		return $real_path;
	}

	/**
	 * Split file content into front matter and body.
	 *
	 * @param string $content Raw file content.
	 * @return array|WP_Error Array with 'front_matter' and 'body' keys, or error.
	 */
	private static function split_front_matter( $content ) {
		$pattern = '/\A---\r?\n(.*?)\r?\n---\r?\n(.*)\z/s';
		if ( ! preg_match( $pattern, $content, $matches ) ) {
			return new WP_Error( 'pac_no_front_matter', 'File does not contain valid front matter delimiters.' );
		}

		return array(
			'front_matter' => $matches[1],
			'body'         => ltrim( $matches[2] ),
		);
	}

	/**
	 * Minimal YAML parser for front matter.
	 *
	 * Supports:
	 * - Simple key: value pairs
	 * - One level of nested map (for meta)
	 * - Quoted and unquoted string values
	 *
	 * @param string $yaml YAML string.
	 * @return array|WP_Error Parsed data or error.
	 */
	private static function parse_yaml( $yaml ) {
		$result       = array();
		$current_map  = null;
		$lines        = preg_split( '/\r?\n/', $yaml );

		foreach ( $lines as $line ) {
			// Skip empty lines and comments.
			if ( '' === trim( $line ) || '#' === substr( trim( $line ), 0, 1 ) ) {
				continue;
			}

			// Detect indentation level.
			$stripped = ltrim( $line );
			$indent   = strlen( $line ) - strlen( $stripped );

			if ( $indent >= 2 && null !== $current_map ) {
				// Nested key-value inside a map.
				if ( preg_match( '/^\s+(\w[\w-]*):\s*(.+)$/', $line, $m ) ) {
					$result[ $current_map ][ $m[1] ] = self::cast_value( trim( $m[2] ) );
				}
				continue;
			}

			// Top-level key: value.
			$current_map = null;
			if ( preg_match( '/^(\w[\w-]*):\s*$/', $stripped, $m ) ) {
				// Key with no value — start of a nested map.
				$current_map           = $m[1];
				$result[ $current_map ] = array();
			} elseif ( preg_match( '/^(\w[\w-]*):\s+(.+)$/', $stripped, $m ) ) {
				$result[ $m[1] ] = self::cast_value( trim( $m[2] ) );
			} else {
				return new WP_Error( 'pac_yaml_parse_error', sprintf( 'Unparseable YAML line: %s', $line ) );
			}
		}

		return $result;
	}

	/**
	 * Cast a YAML scalar value to the appropriate PHP type.
	 *
	 * @param string $value Raw string value.
	 * @return mixed Cast value.
	 */
	private static function cast_value( $value ) {
		// Unquote.
		if ( ( '"' === $value[0] && '"' === substr( $value, -1 ) ) ||
			 ( "'" === $value[0] && "'" === substr( $value, -1 ) ) ) {
			return substr( $value, 1, -1 );
		}

		// Booleans.
		$lower = strtolower( $value );
		if ( 'true' === $lower ) {
			return true;
		}
		if ( 'false' === $lower ) {
			return false;
		}

		// Integers.
		if ( ctype_digit( $value ) || ( '-' === $value[0] && ctype_digit( substr( $value, 1 ) ) ) ) {
			return (int) $value;
		}

		return $value;
	}
}
