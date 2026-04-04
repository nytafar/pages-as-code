<?php
/**
 * PAC_Serializer — Convert page data into .html files with YAML front matter.
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PAC_Serializer {

	/**
	 * Serialize page data into a complete file string (front matter + body).
	 *
	 * @param array  $fields Associative array of front matter fields.
	 * @param string $body   Block markup body.
	 * @return string Complete file content with --- delimiters.
	 */
	public static function serialize( $fields, $body ) {
		$lines = self::yaml_lines( $fields );
		$yaml  = implode( "\n", $lines );
		$body  = ltrim( $body );

		return "---\n" . $yaml . "\n---\n" . $body . "\n";
	}

	/**
	 * Convert an associative array into YAML lines.
	 *
	 * Handles one level of nesting (for the meta map).
	 * Field order is preserved from the input array.
	 *
	 * @param array $fields Key-value pairs.
	 * @return array Lines of YAML text.
	 */
	private static function yaml_lines( $fields ) {
		$lines = array();

		foreach ( $fields as $key => $value ) {
			if ( is_array( $value ) ) {
				// Nested map (e.g. meta).
				$lines[] = $key . ':';
				foreach ( $value as $sub_key => $sub_value ) {
					$lines[] = '  ' . $sub_key . ': ' . self::format_value( $sub_value );
				}
			} else {
				$lines[] = $key . ': ' . self::format_value( $value );
			}
		}

		return $lines;
	}

	/**
	 * Format a scalar value for YAML output.
	 *
	 * Quotes strings that contain colons, quotes, or other special chars.
	 * Leaves integers and booleans bare.
	 *
	 * @param mixed $value Scalar value.
	 * @return string Formatted value.
	 */
	private static function format_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_int( $value ) ) {
			return (string) $value;
		}

		$value = (string) $value;

		// Quote if the value contains characters that could confuse the YAML parser.
		if ( preg_match( '/[:{}\[\]&*?|>!%@`#,]/', $value ) || '' === $value ) {
			return '"' . str_replace( '"', '\\"', $value ) . '"';
		}

		// Quote if it looks like a boolean or number but should stay a string.
		$lower = strtolower( $value );
		if ( in_array( $lower, array( 'true', 'false', 'null', 'yes', 'no' ), true ) ) {
			return '"' . $value . '"';
		}

		return $value;
	}
}
