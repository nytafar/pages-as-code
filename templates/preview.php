<?php
/**
 * Default PAC preview template.
 *
 * Override by creating preview.php at the root of your PAC pages directory.
 *
 * Available variables:
 * - $pac_preview_title
 * - $pac_preview_relative_path
 * - $pac_preview_body
 * - $pac_preview_error
 *
 * @package Pages_as_Code
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $pac_preview_title ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'pac-preview' ); ?>>
<?php wp_body_open(); ?>
<main class="pac-preview__content">
	<?php if ( is_wp_error( $pac_preview_error ) ) : ?>
		<h1><?php echo esc_html( $pac_preview_title ); ?></h1>
		<p><?php echo esc_html( $pac_preview_error->get_error_message() ); ?></p>
		<?php if ( '' !== $pac_preview_relative_path ) : ?>
			<p><code><?php echo esc_html( $pac_preview_relative_path ); ?></code></p>
		<?php endif; ?>
	<?php else : ?>
		<?php echo $pac_preview_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
