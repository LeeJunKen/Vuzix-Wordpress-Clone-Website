<?php
/**
 * Header: Trích xuất từ original-index.html tới trước thẻ <main>.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$theme_uri  = get_template_directory_uri();
$index_path = get_theme_file_path( 'original-index.html' );

if ( file_exists( $index_path ) ) {
	$html = file_get_contents( $index_path );
	$parts = explode( '<main id="MainContent"', $html );
	$header_html = $parts[0];

	$header_html = str_replace( '\\u0026', '&', $header_html );
	$header_html = vuzix_convert_internal_links( $header_html );
	$header_html = vuzix_replace_asset_paths( $header_html, $theme_uri );

	$head_parts = explode( '</head>', $header_html );
	if ( count( $head_parts ) > 1 ) {
		echo $head_parts[0];
		wp_head();
		echo '</head>';

		$body_parts = explode( '<body class="gradient">', $head_parts[1] );
		if ( count( $body_parts ) > 1 ) {
			echo $body_parts[0];
			?>
			<body <?php body_class( 'gradient' ); ?>>
			<?php
			wp_body_open();
			echo $body_parts[1];
		} else {
			echo $head_parts[1];
		}
	} else {
		echo $header_html;
	}
} else {
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<?php
}
