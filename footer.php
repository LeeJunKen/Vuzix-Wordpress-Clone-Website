<?php
/**
 * Footer: Trích xuất từ original-index.html từ sau thẻ đóng </main> đến hết.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$theme_uri  = get_template_directory_uri();
$index_path = get_theme_file_path( 'original-index.html' );

if ( file_exists( $index_path ) ) {
	$html = file_get_contents( $index_path );
	$parts = explode( '</main>', $html );
	$footer_html = isset( $parts[1] ) ? vuzix_strip_shopify_runtime_assets( $parts[1] ) : '';

	$footer_html = str_replace( '\\u0026', '&', $footer_html );
	$footer_html = vuzix_convert_internal_links( $footer_html );
	$footer_html = vuzix_replace_asset_paths( $footer_html, $theme_uri );
	$footer_html = vuzix_translate_static_ui( $footer_html );

	$body_parts = explode( '</body>', $footer_html );
	if ( count( $body_parts ) > 1 ) {
		echo $body_parts[0];
		wp_footer();
		echo '</body>';
		echo $body_parts[1];
	} else {
		echo $footer_html;
	}
} else {
	?>
	<?php wp_footer(); ?>
	</body>
	</html>
	<?php
}
