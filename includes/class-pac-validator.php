<?php
/**
 * PAC_Validator — Block markup validation service.
 *
 * Stateless service that validates Gutenberg block markup using parse_blocks().
 * Returns structured JSON-friendly reports for agent tuning and diagnostics.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_Validator {

	/**
	 * Phase-1 supported blocks (content + layout).
	 *
	 * Blocks in this list pass the unknown_block check.
	 * Only a subset have per-block validators.
	 *
	 * @var array
	 */
	private static $supported_blocks = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/image',
		'core/buttons',
		'core/button',
		'core/group',
		'core/columns',
		'core/column',
		'core/cover',
		'core/quote',
		'core/html',
		'core/separator',
		'core/spacer',
		'core/embed',
		'core/shortcode',
		'core/pullquote',
		'core/table',
		'core/code',
		'core/preformatted',
		'core/audio',
		'core/file',
		'core/freeform',
		'core/search',
		'core/footnotes',
		'core/navigation',
		'core/navigation-link',
		'core/social-links',
		'core/social-link',
		'core/more',
		'core/nextpage',
		'core/latest-posts',
		'core/latest-comments',
		'core/archives',
		'core/rss',
		'core/table-of-contents',
		'core/site-title',
		'core/site-logo',
		'core/site-tagline',
		'core/post-content',
		'core/post-title',
		'core/post-date',
		'core/post-excerpt',
		'core/post-featured-image',
		'core/gallery',
		'core/comments',
		'core/query',
		'core/post-template',
		'core/query-pagination',
		'core/query-pagination-next',
		'core/query-pagination-previous',
	);

	/**
	 * Blocks that require a specific parent block.
	 *
	 * @var array
	 */
	private static $required_parents = array(
		'core/list-item' => array( 'core/list' ),
		'core/button'    => array( 'core/buttons' ),
		'core/column'    => array( 'core/columns' ),
	);

	/**
	 * Container blocks that require specific child block types.
	 *
	 * @var array
	 */
	private static $required_children = array(
		'core/list'    => 'core/list-item',
		'core/buttons' => 'core/button',
		'core/columns' => 'core/column',
	);

	/**
	 * Validate a block document.
	 *
	 * @param string $content Block markup string (the body, not front matter).
	 * @param array  $options Reserved for future use (e.g. strict mode).
	 * @return array Structured report with ok, summary, and issues.
	 */
	public static function validate_document( $content, $options = array() ) {
		$issues      = array();
		$block_count = 0;

		// Empty content check.
		if ( '' === trim( $content ) ) {
			$issues[] = self::make_issue(
				'fatal',
				'',
				null,
				'empty_document',
				'Document contains no block markup',
				'At least one block',
				'Empty content'
			);

			return self::build_report( $block_count, $issues );
		}

		$blocks = parse_blocks( $content );

		// Filter whitespace-only null blocks (parser artifacts).
		$real_blocks = array();
		foreach ( $blocks as $block ) {
			if ( null === $block['blockName'] && '' === trim( $block['innerHTML'] ) ) {
				continue;
			}
			$real_blocks[] = $block;
		}

		if ( empty( $real_blocks ) ) {
			$issues[] = self::make_issue(
				'fatal',
				'',
				null,
				'empty_document',
				'Document contains no block markup after whitespace filtering',
				'At least one block',
				'Only whitespace content'
			);

			return self::build_report( $block_count, $issues );
		}

		self::walk_blocks( $real_blocks, '', null, $issues, $block_count );

		return self::build_report( $block_count, $issues );
	}

	/**
	 * Recursively walk the block tree and validate each block.
	 *
	 * @param array    $blocks       Array of parsed blocks.
	 * @param string   $path_prefix  Parent path prefix (e.g. "3/1").
	 * @param string|null $parent_name Parent block name or null for top-level.
	 * @param array    &$issues      Accumulated issues array (by reference).
	 * @param int      &$block_count Running block count (by reference).
	 */
	private static function walk_blocks( $blocks, $path_prefix, $parent_name, &$issues, &$block_count ) {
		foreach ( $blocks as $index => $block ) {
			$path = '' === $path_prefix ? (string) $index : $path_prefix . '/' . $index;

			// Null block = bare HTML outside block comments.
			if ( null === $block['blockName'] ) {
				$block_count++;
				$snippet = trim( $block['innerHTML'] );
				if ( strlen( $snippet ) > 80 ) {
					$snippet = substr( $snippet, 0, 80 ) . '...';
				}
				$issues[] = self::make_issue(
					'fatal',
					$path,
					null,
					'null_block',
					'Bare HTML outside block comments — content will be silently lost',
					'All HTML wrapped in <!-- wp:blockname --> comments',
					$snippet
				);
				continue;
			}

			$block_count++;

			// Unknown block check.
			if ( ! in_array( $block['blockName'], self::$supported_blocks, true ) ) {
				$issues[] = self::make_issue(
					'warning',
					$path,
					$block['blockName'],
					'unknown_block',
					sprintf( 'Block "%s" is not in the supported block set', $block['blockName'] ),
					'A supported core/* block',
					$block['blockName']
				);
			}

			// Parent constraint check.
			if ( isset( self::$required_parents[ $block['blockName'] ] ) ) {
				$valid_parents = self::$required_parents[ $block['blockName'] ];
				if ( null === $parent_name || ! in_array( $parent_name, $valid_parents, true ) ) {
					$issues[] = self::make_issue(
						'fatal',
						$path,
						$block['blockName'],
						'invalid_parent',
						sprintf(
							'%s must be a child of %s',
							$block['blockName'],
							implode( ' or ', $valid_parents )
						),
						implode( ' or ', $valid_parents ),
						null === $parent_name ? 'top-level' : $parent_name
					);
				}
			}

			// Child constraint check.
			if ( isset( self::$required_children[ $block['blockName'] ] ) && ! empty( $block['innerBlocks'] ) ) {
				$expected_child = self::$required_children[ $block['blockName'] ];
				foreach ( $block['innerBlocks'] as $ci => $child ) {
					if ( null !== $child['blockName'] && $expected_child !== $child['blockName'] ) {
						$child_path = $path . '/' . $ci;
						$issues[]   = self::make_issue(
							'warning',
							$child_path,
							$child['blockName'],
							'invalid_children',
							sprintf(
								'%s expects %s children, found %s',
								$block['blockName'],
								$expected_child,
								$child['blockName']
							),
							$expected_child,
							$child['blockName']
						);
					}
				}
			}

			// Per-block validator dispatch.
			$method = 'validate_' . str_replace( '/', '_', $block['blockName'] );
			if ( method_exists( __CLASS__, $method ) ) {
				$block_issues = self::$method( $block, $path );
				foreach ( $block_issues as $issue ) {
					$issues[] = $issue;
				}
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk_blocks( $block['innerBlocks'], $path, $block['blockName'], $issues, $block_count );
			}
		}
	}

	/**
	 * Build the final report array.
	 *
	 * @param int   $block_count Total blocks validated.
	 * @param array $issues      All issues found.
	 * @return array
	 */
	private static function build_report( $block_count, $issues ) {
		$fatal   = 0;
		$warning = 0;
		$info    = 0;

		foreach ( $issues as $issue ) {
			switch ( $issue['severity'] ) {
				case 'fatal':
					$fatal++;
					break;
				case 'warning':
					$warning++;
					break;
				case 'info':
					$info++;
					break;
			}
		}

		return array(
			'ok'      => 0 === $fatal,
			'source'  => null,
			'summary' => array(
				'blocks'  => $block_count,
				'fatal'   => $fatal,
				'warning' => $warning,
				'info'    => $info,
			),
			'issues'  => $issues,
		);
	}

	/**
	 * Construct a single issue array.
	 *
	 * @param string      $severity       fatal|warning|info.
	 * @param string      $path           Tree position (e.g. "3/1").
	 * @param string|null $block_name     Parsed block name.
	 * @param string      $rule           Internal rule ID.
	 * @param string      $message        Human-readable explanation.
	 * @param string      $expected       What should be there.
	 * @param string      $actual         What is actually there.
	 * @param bool        $auto_fixable   Whether this can be auto-fixed.
	 * @param array|null  $suggested_repair Structured repair hint.
	 * @return array
	 */
	private static function make_issue( $severity, $path, $block_name, $rule, $message, $expected, $actual, $auto_fixable = false, $suggested_repair = null ) {
		return array(
			'severity'        => $severity,
			'path'            => $path,
			'blockName'       => $block_name,
			'rule'            => $rule,
			'message'         => $message,
			'expected'        => $expected,
			'actual'          => $actual,
			'autoFixable'     => $auto_fixable,
			'suggestedRepair' => $suggested_repair,
		);
	}

	// -------------------------------------------------------------------------
	// Shared helpers for per-block validators.
	// -------------------------------------------------------------------------

	/**
	 * Check that innerHTML contains a specific HTML element.
	 *
	 * @param string $html HTML string to check.
	 * @param string $tag  Tag name (e.g. 'p', 'img', 'figure').
	 * @return bool
	 */
	private static function check_contains_element( $html, $tag ) {
		return false !== stripos( $html, '<' . $tag );
	}

	/**
	 * Check that innerHTML contains a tag with a required CSS class.
	 *
	 * Returns an issue array if the class is missing, or null if ok.
	 *
	 * @param array  $block          Parsed block.
	 * @param string $path           Tree position.
	 * @param string $tag            HTML tag to look for.
	 * @param string $required_class Required CSS class on that tag.
	 * @return array|null Issue or null.
	 */
	private static function check_wrapper_class( $block, $path, $tag, $required_class ) {
		$html = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		// Look for <tag ... class="...required_class..." ...>.
		$pattern = '/<' . preg_quote( $tag, '/' ) . '\b[^>]*\bclass\s*=\s*"[^"]*\b' . preg_quote( $required_class, '/' ) . '\b[^"]*"[^>]*>/i';
		if ( preg_match( $pattern, $html ) ) {
			return null;
		}

		// Extract actual tag for the report.
		$actual = '';
		if ( preg_match( '/<' . preg_quote( $tag, '/' ) . '\b[^>]*>/i', $html, $m ) ) {
			$actual = $m[0];
			if ( strlen( $actual ) > 120 ) {
				$actual = substr( $actual, 0, 120 ) . '...';
			}
		} else {
			$actual = sprintf( 'No <%s> element found', $tag );
		}

		return self::make_issue(
			'fatal',
			$path,
			$block['blockName'],
			'missing_wrapper_class',
			sprintf( 'Missing %s class on <%s> wrapper', $required_class, $tag ),
			sprintf( '<%s class="%s ...">', $tag, $required_class ),
			$actual,
			true,
			array(
				'type'  => 'add-class',
				'class' => $required_class,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Per-block validators.
	// -------------------------------------------------------------------------

	/**
	 * Validate core/paragraph.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_paragraph( $block, $path ) {
		$issues = array();
		$html   = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		if ( ! self::check_contains_element( $html, 'p' ) ) {
			$issues[] = self::make_issue(
				'fatal',
				$path,
				'core/paragraph',
				'missing_wrapper_element',
				'core/paragraph must contain a <p> element',
				'<p>...</p>',
				self::snippet( $html )
			);
		}

		return $issues;
	}

	/**
	 * Validate core/heading.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_heading( $block, $path ) {
		$issues = array();
		$html   = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		// Check for any heading tag.
		if ( ! preg_match( '/<h([1-6])\b/i', $html, $m ) ) {
			$issues[] = self::make_issue(
				'fatal',
				$path,
				'core/heading',
				'missing_wrapper_element',
				'core/heading must contain an <h1>-<h6> element',
				'<h2>...</h2>',
				self::snippet( $html )
			);
			return $issues;
		}

		// Check level attr matches actual tag.
		$actual_level = (int) $m[1];
		$attr_level   = isset( $block['attrs']['level'] ) ? (int) $block['attrs']['level'] : 2;

		if ( $actual_level !== $attr_level ) {
			$issues[] = self::make_issue(
				'fatal',
				$path,
				'core/heading',
				'heading_level_mismatch',
				sprintf( 'Heading level attribute is %d but HTML tag is <h%d>', $attr_level, $actual_level ),
				sprintf( '<h%d>', $attr_level ),
				sprintf( '<h%d>', $actual_level ),
				true,
				array(
					'type'     => 'fix-heading-level',
					'expected' => $attr_level,
					'actual'   => $actual_level,
				)
			);
		}

		return $issues;
	}

	/**
	 * Validate core/list.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_list( $block, $path ) {
		$issues = array();
		$html   = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		if ( ! self::check_contains_element( $html, 'ul' ) && ! self::check_contains_element( $html, 'ol' ) ) {
			$issues[] = self::make_issue(
				'fatal',
				$path,
				'core/list',
				'missing_wrapper_element',
				'core/list must contain a <ul> or <ol> element',
				'<ul>...</ul> or <ol>...</ol>',
				self::snippet( $html )
			);
		}

		return $issues;
	}

	/**
	 * Validate core/list-item.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_list_item( $block, $path ) {
		$issues = array();
		$html   = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		if ( ! self::check_contains_element( $html, 'li' ) ) {
			$issues[] = self::make_issue(
				'fatal',
				$path,
				'core/list-item',
				'missing_wrapper_element',
				'core/list-item must contain an <li> element',
				'<li>...</li>',
				self::snippet( $html )
			);
		}

		return $issues;
	}

	/**
	 * Validate core/image.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_image( $block, $path ) {
		$issues = array();
		$html   = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		// Check for wp-block-image class on figure.
		$class_issue = self::check_wrapper_class( $block, $path, 'figure', 'wp-block-image' );
		if ( null !== $class_issue ) {
			$issues[] = $class_issue;
		}

		// Check for <img> element.
		if ( ! self::check_contains_element( $html, 'img' ) ) {
			$issues[] = self::make_issue(
				'fatal',
				$path,
				'core/image',
				'missing_wrapper_element',
				'core/image must contain an <img> element',
				'<figure class="wp-block-image"><img .../></figure>',
				self::snippet( $html )
			);
		}

		return $issues;
	}

	/**
	 * Validate core/buttons.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_buttons( $block, $path ) {
		$issues = array();

		$class_issue = self::check_wrapper_class( $block, $path, 'div', 'wp-block-buttons' );
		if ( null !== $class_issue ) {
			$issues[] = $class_issue;
		}

		return $issues;
	}

	/**
	 * Validate core/button.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_button( $block, $path ) {
		$issues = array();
		$html   = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';

		$class_issue = self::check_wrapper_class( $block, $path, 'div', 'wp-block-button' );
		if ( null !== $class_issue ) {
			$issues[] = $class_issue;
		}

		// Check for <a> element (warning — buttons can sometimes use other elements).
		if ( ! self::check_contains_element( $html, 'a' ) ) {
			$issues[] = self::make_issue(
				'warning',
				$path,
				'core/button',
				'missing_wrapper_element',
				'core/button typically contains an <a> element',
				'<a class="wp-block-button__link" href="...">',
				self::snippet( $html )
			);
		}

		return $issues;
	}

	/**
	 * Validate core/group.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_group( $block, $path ) {
		$issues = array();

		$class_issue = self::check_wrapper_class( $block, $path, 'div', 'wp-block-group' );
		if ( null !== $class_issue ) {
			$issues[] = $class_issue;
		}

		return $issues;
	}

	/**
	 * Validate core/columns.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_columns( $block, $path ) {
		$issues = array();

		$class_issue = self::check_wrapper_class( $block, $path, 'div', 'wp-block-columns' );
		if ( null !== $class_issue ) {
			$issues[] = $class_issue;
		}

		return $issues;
	}

	/**
	 * Validate core/column.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_column( $block, $path ) {
		$issues = array();

		$class_issue = self::check_wrapper_class( $block, $path, 'div', 'wp-block-column' );
		if ( null !== $class_issue ) {
			$issues[] = $class_issue;
		}

		return $issues;
	}

	/**
	 * Validate core/cover.
	 *
	 * @param array  $block Parsed block.
	 * @param string $path  Tree position.
	 * @return array Issues.
	 */
	private static function validate_core_cover( $block, $path ) {
		$issues = array();

		$class_issue = self::check_wrapper_class( $block, $path, 'div', 'wp-block-cover' );
		if ( null !== $class_issue ) {
			$issues[] = $class_issue;
		}

		return $issues;
	}

	/**
	 * Create a truncated snippet for issue reporting.
	 *
	 * @param string $html Raw HTML.
	 * @param int    $max  Maximum length.
	 * @return string
	 */
	private static function snippet( $html, $max = 80 ) {
		$text = trim( $html );
		if ( strlen( $text ) > $max ) {
			return substr( $text, 0, $max ) . '...';
		}
		return $text;
	}
}
